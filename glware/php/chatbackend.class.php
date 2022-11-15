<?php
	class glwChatBackend {

		private $modx;
		private $god_object;
		private $command;
		private $messages;
		private $tp;
		private $pid            = null;
		private $client_message = array();
		private $nick          = '';
		private $sess_id       = null;
		private $last_checking = null;
		private $session_restore = false;
		private $blocked = false;


		public function __construct(&$god_object) {
			$this->god_object = $god_object;
			$this->modx = $this->god_object->modx;
			$this->tp = $this->god_object->table_prefix;

			if (!empty($_POST['command'])) {
				$this->command = preg_replace('![^a-z_]*!', '', $_POST['command']);
			}

			if (!$this->god_object->table_exists('chat_messages')) {

				$create_sess_table = "CREATE table IF NOT EXISTS {$this->tp}chat_sessions (
					id INT AUTO_INCREMENT,
					psid VARCHAR(250),
					last_checking DOUBLE,
					session_born INT,
					username VARCHAR(100),
					role TINYINT,
					PRIMARY KEY (id)
				)";
				$create_msg_table = "CREATE table IF NOT EXISTS {$this->tp}chat_messages (
					id INT AUTO_INCREMENT,
					username VARCHAR(100),
					date VARCHAR(15),
					put_date DOUBLE,
					message_text TEXT,
					session_id INT,
					PRIMARY KEY (id)
				)";

				$this->modx->query($create_sess_table);
				$err = $this->modx->errorInfo();

				if ($err[1] == $this->god_object->cant_create_table) {
					$this->log("Unfortunately, not allowed to create tables on this server programmatically. Instead you should fulfill these preparations manually");
				} else {
					$this->modx->query($create_msg_table);
				}
			}
	
			$this->client_message = $_POST;

			$this->userObj = $this->modx->getUser();

			if ($this->userObj->isAuthenticated('web')) {
				$profile = $this->modx->getObject('modUserProfile', array('internalkey' => $this->userObj->get('id')));
				$this->user = array_merge($this->userObj->toArray(), $profile->toArray());

				if (!$this->userObj->isMember(array('Judges', 'Manager', 'Administrator'))) {
					$this->blocked = true;	
				}

				$this->nick = $this->user['username'];
				$this->pid = $this->user['sessionid'];
		
				if (empty($this->load_session())) { 
					$this->session_not_exists = true;
				}

				$this->clean_old_sessions();

			} else {
				$this->blocked = true;
			}
		}

	
		private function clean_old_sessions() {
			// двадцать секунд простоя без посыла пинга - это по-видимому означает что клиент умер.
			$time_bound = time() - 60;
			$this->modx->query("DELETE FROM {$this->tp}chat_sessions WHERE last_checking < '{$time_bound}'");
		}


		private function load_session() {
			$sess = array();

			$res = $this->modx->query("SELECT * FROM {$this->tp}chat_sessions
										WHERE psid = '{$this->pid}'");
	
			if (is_object($res) && $sess = $res->fetch(PDO::FETCH_ASSOC)) {
				$this->session_born_time = $sess['session_born'];
				$this->sess_id = $sess['id'];
				$this->last_checking = $sess['last_checking'];
			}

			return $sess;
		}


		private function sessionstart() {
			$this->session_restore = (int)$this->session_born_time > 0 ? true : false;
			//echo "Перед добавлением сессии вишнейм: " . $this->wishname;
	
	/*		$individual_color = $this->get_color(); */
			$now_time = microtime(true);
			
			$this->modx->query("INSERT INTO {$this->tp}chat_sessions (psid, username, session_born, role)
								VALUES ('{$this->pid}', '{$this->nick}', {$now_time}, "
							. ($this->userObj->isMember('Administrator', 'Manager') ? 1 : 0) . ")");

			$this->god_object->log("Error occured while session creation: " . print_r(array(
				'error' => $this->modx->errorInfo(), 
				'sql' => "INSERT INTO {$this->tp}chat_sessions (psid, username, session_born, role)
								VALUES ('{$this->pid}', '{$this->nick}', {$now_time}, "
							. ($this->userObj->isMember('Administrator', 'Manager') ? 1 : 0) . ")"
			), true));

			$this->sess_id = $this->modx->lastInsertId();
			$this->last_checking = 0;
		}


		private function sessiondelete()
		{
			if (!empty($this->pid))
			{
				$this->modx->query("DELETE FROM {$tp}chat_sessions
									WHERE psid = '{$this->pid}'");
			}

			$this->nick          = '';
			$this->sess_id       = 0;
			$this->last_checking = 0;

			return;
		}

		
		public function run()
		{
			$err = '';
			$this->god_object->log("chat reseived action: " . $this->command);

			if ($this->blocked) {
				$err = 'You have no access to this page';
			} elseif (strstr(' ', $this->command) === false) {

				if (!$this->checksession())
				{
					if (!$this->sessionstart()) {
						$session_err = 'session_not_found';
					}
				}

				switch ($this->command)
				{
					case 'sessionstart':
						$this->sessionstart();
						if (!empty($this->nick) && !empty($this->sess_id))
						{	
							$response = array(
										'got'        => true, 
										'userlist'   => $this->getuserlist(),
										'you'        => $this->nick,
										'messages'   => $this->getmessages()
										 );
						}
						else
						{
							$response = array(
										'got' => false,
										'error_status' => array(
											'blocked' => $this->blocked,
											'nick' => $this->nick,
											'session_id' => $this->sess_id
										)
									);
						}
						break;

					case 'sessiondelete':
						$this->sessiondelete();
						$response = array(
							'deleted' => empty($this->nick) && empty($this->sess_id)
						);

						break;

					case 'oldmessages':
						if (!$session_err)
						{
							$later = $this->client_message['request'];
							if (!is_float($later)) {
								$later = floatval($later);
							}

							$response = array(
								'userlist'          => $this->getuserlist(),
								'messages'          => $this->getmessages($later),
								'you'               => $this->nick
							);
						}		
						break;

					case 'newmessages':					
						$this->putmessage($this->client_message['messages']);

						if (!$session_err)
						{
							$response = array(
								'userlist'          => $this->getuserlist(),
								'messages'          => $this->getmessages(),
								'you'               => $this->nick
							);
						}				

						break;

					case 'refresh':
						if (!$session_err)
						{
							$response = array(
								//'have_session'      => !empty($this->nick) && !empty($this->sess_id) && !empty($this->last_checking),
								'userlist'          => $this->getuserlist(),
								'messages'          => $this->getmessages(),
								'you'               => $this->nick
							);
						}

				}
			}
			else
			{
				$err = 'illegal command format';
			}

			if ($err != '')
			{
				$response = array(
					'error' => $err
				);
			}

			$now_time = microtime(true);
//			$this->god_object->log("Checking finished. By session " . $this->pid . "; microtime is " . sprintf("%f", $now_time));
			$this->modx->query("UPDATE {$this->tp}chat_sessions
								SET last_checking = {$now_time}
								WHERE psid = '{$this->pid}'");

			return $response;
		}


		private function sanitize_msg($msg) {

			//return preg_replace('/([\/"\'])+/', '', $name);
		}



		private function checksession()
		{
			return !empty($this->nick) && !empty($this->sess_id) && !empty($this->last_checking);
		}



		private function response($array)
		{
			if (is_array($array))
			{
				echo json_encode($array);
			}
		}



		private function getuserlist()
		{
			$res = $this->modx->query("SELECT username, id
										FROM {$this->tp}chat_sessions");

			$res = $res->fetchAll(PDO::FETCH_ASSOC);
			
			return $res;
		}


		public function clean_store() {
			$this->modx->query("DELETE FROM {$this->tp}chat_messages");
			$this->modx->query("DELETE FROM {$this->tp}chat_sessions");
		}


		private function getmessages($timestamp = false)
		{
			$sql = '';
			$where_later_then = $timestamp ? "mess.put_date < {$timestamp}" : "";
	
			$select_join = "SELECT mess.message_text, mess.date, mess.put_date, sess.id, mess.username, sess.last_checking, sess.role admin
							FROM {$this->tp}chat_messages as mess
							LEFT JOIN {$this->tp}chat_sessions as sess ON mess.session_id = sess.id ";

			if ($this->session_restore) {
				$sql_ending = "ORDER BY mess.id DESC LIMIT 20";
			} else if ($timestamp) {
				$sql_ending = "WHERE {$where_later_then} ORDER BY mess.id DESC";
			} else {
//				$sql_ending = "WHERE mess.put_date > {$this->last_checking} AND mess.session_id != '{$this->sess_id}' ORDER BY mess.id DESC";
				$sql_ending = "WHERE mess.put_date > {$this->last_checking} ORDER BY mess.id DESC";
			}
			
			$sql = $select_join . $sql_ending;
			$res = $this->modx->query($sql);

			if ($timestamp) {
				$this->god_object->log("getting messages SQL: " . print_r($this->modx->errorInfo(), true));
			}

			if ($res)
			{
				$recent_messages = $res->fetchAll(PDO::FETCH_ASSOC);
				//echo "SELECT mess.message_text, mess.date, sess.last_checking FROM suff40_chat_messages as mess
				//LEFT JOIN suff40_chat_sessions as sess ON mess.session_id = sess.id
				//WHERE mess.date > {$this->last_checking} AND mess.session_id != {$this->sess_id}";
				return $recent_messages;
			}
		}


		private function purge($string, $encode = true) {
			if ($encode) {
				$new_string = htmlentities($string, ENT_QUOTES | ENT_HTML5, "UTF-8");
			} else {
				$new_string = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, "UTF-8");
			}
		
			return $new_string;
		}


		private function putmessage($messages)
		{
			$decoded_messages = json_decode($messages, true);
			$values_string = array();
			
			if (!is_array($decoded_messages))
			{			
				return;
			}

			$now_time = microtime(true);

			for ($i = 0, $n = count($decoded_messages); $i < $n; ++$i) {
				$values_string[] = "('" . $this->purge($decoded_messages[$i]['text']) . "', '{$this->nick}', '{$decoded_messages[$i]['date']}', '{$this->sess_id}', {$now_time})";
			}

			$this->modx->query("INSERT INTO {$this->tp}chat_messages (message_text, username, date, session_id, put_date)
								VALUES " . implode(',', $values_string));		
		}
	}
