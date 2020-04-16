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

			$this->user = $this->modx->getUser();

			if ($this->user->isAuthenticated('web')) {
				$this->userGroup = $this->modx->getObject('modUserGroup', $this->user->get('primary_group'));
				$this->userPrimaryGroup = $this->userGroup->get('name');
				$profile = $this->modx->getObject('modUserProfile', array('internalkey' => $this->user->get('id')));
				$this->user = array_merge($this->user->toArray(), $profile->toArray());

				if (!in_array($this->userPrimaryGroup, array('Judges', 'Manager', 'Administrator'))) {
					//$this->blocked = true;	
				}

				$this->nick = $this->user['username'];
				$this->pid = $this->user['sessionid'];
		
				$this->god_object->log("This user's primary group: " . $this->userGroup->get('name'));

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
			if ($this->session_not_exists) {
				$this->session_restore = (int)$this->session_born_time > 0 ? true : false;
				//echo "Перед добавлением сессии вишнейм: " . $this->wishname;
		
		/*		$individual_color = $this->get_color(); */
				$now_time = microtime(true);
				
				$this->modx->query("INSERT INTO {$this->tp}chat_sessions (psid, username, session_born, role)
									VALUES ('{$this->pid}', '{$this->nick}', {$now_time}, "
								. (($this->userPrimaryGroup == 'Administrator' || $this->userPrimaryGroup == 'Manager') ? 1 : 0) . ")");

				$this->sess_id = $this->modx->lastInsertId();
				$this->last_checking = 0;
			}
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
										'got' => false
									);
						}
					/*	print_r(array(
							'you' => $this->nick,
							'messages' => $this->getmessages(),
							'userlist' => $this->getuserlist()
						));
	*/
						break;

					case 'sessiondelete':
						$this->sessiondelete();
						$response = array(
							'deleted' => empty($this->nick) && empty($this->sess_id)
						);

						break;

					case 'newmessages':					
						$this->putmessage($this->client_message['messages']);
						if (!$this->checksession())
						{
							if (!$this->sessionstart()) {
								$err = 'session_not_found';
							}
						}

						if (!$err)
						{
							$response = array(
								'userlist'          => $this->getuserlist(),
								'messages'          => $this->getmessages(),
								'you'               => $this->nick
							);
						}				

						break;

					case 'refresh':
						if (!$this->checksession())
						{
							if (!$this->sessionstart()) {
								$err = 'session_not_found';
							}
						}

						if (!$err)
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



		private function getmessages()
		{
			$sql = '';
			$where = $this->session_restore
				? "mess.put_date > {$this->session_born_time}"
				: "mess.put_date > {$this->last_checking} AND mess.session_id != '{$this->sess_id}'";

			$sql = "SELECT mess.message_text, mess.date, mess.put_date, sess.id, sess.username, sess.last_checking, sess.role admin
					FROM {$this->tp}chat_messages as mess
					LEFT JOIN {$this->tp}chat_sessions as sess ON mess.session_id = sess.id
					WHERE {$where}";

			$res = $this->modx->query($sql);		

			if ($res)
			{
				$recent_messages = $res->fetchAll(PDO::FETCH_ASSOC);
				//echo "SELECT mess.message_text, mess.date, sess.last_checking FROM suff40_chat_messages as mess
				//LEFT JOIN suff40_chat_sessions as sess ON mess.session_id = sess.id
				//WHERE mess.date > {$this->last_checking} AND mess.session_id != {$this->sess_id}";
				return $recent_messages;
			}
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
				$values_string[] = "('{$decoded_messages[$i]['text']}', '{$decoded_messages[$i]['date']}', '{$this->sess_id}', {$now_time})";
			}

			$this->modx->query("INSERT INTO {$this->tp}chat_messages (message_text, date, session_id, put_date)
								VALUES " . implode(',', $values_string));		
		}
	}
