<?php

class Chat
{
	private $dbh            = null;
	private $pid            = null;
	private $curtime        = 0;
	private $client_message = array();

	private $config = array(
			'host' => 'suffer.mysql',
			'user' => 'suffer_mysql',
			'password' => '/YwUCf+F'
		);

	private $nick          = '';
	private $sess_id       = null;
	private $last_checking = null;
	private $wishname      = '';
	private $session_restore = false;


	function __construct()
	{
		session_start();
		error_reporting(0);
		$this->dbh            = new PDO("mysql:dbname=suffer_db;charset=utf8;host={$this->config['host']}",			
			$this->config['user'],
			$this->config['password']
			);

		$this->pid            = session_id();
		$this->client_message = $_POST;
		$this->wishname = $this->sanitize_name($this->client_message['wishname']);

		// получаем текущую сессию
		$res = $this->dbh->query("SELECT id, last_checking, username, session_born
									FROM suff40_chat_sessions
									WHERE psid = '{$this->pid}'");
		$res = $res->fetch(PDO::FETCH_ASSOC);

		if (empty($res))
		{
			return;
		}
		else
		{
			$this->session_born_time = $res['session_born'];
		}

		// если с запросом на вход пришло имя, даже в существующей сессии надо его обновить.
		$update_name = strlen($this->wishname) > 1 ? true : false;
		
		if ($update_name && $this->is_unique_name($this->wishname))
		{
			$this->dbh->query("UPDATE suff40_chat_sessions
								SET username = '{$this->wishname}'
								WHERE psid = '{$this->pid}'");
		}

		$this->sess_id       = $res['id'];
		$this->last_checking = $res['last_checking'];
		$this->nick          = $update_name ? $this->wishname :
			($res['username'] != 'Guest' && $res['username'] != ''
				? $res['username']
				: 'Guest' . $res['id']);

		$time_bound = time() - 60; // двадцать секунд простоя без посыла пинга - это по-видимому означает что клиент умер.
		$this->dbh->query("DELETE FROM suff40_chat_sessions
							WHERE last_checking < '{$time_bound}'");
		
		//echo "UPDATE suff40_chat_sessions SET last_checking = '{$now_time}' WHERE psid = {$this->pid}";
	}

	public function is_unique_name($name)
	{
		$res = $this->dbh->query("SELECT * FROM suff40_chat_sessions
									WHERE username='{$name}'");
		$res = $res->fetch(PDO::FETCH_ASSOC);
		if (empty($res))
		{
			return true;
		}

		return false;
	}

	
	public function run($com)
	{
		$err = '';

		if (strstr(' ', $com) === false)
		{			
			switch ($com)
			{
				case 'sessionstart':
					$this->sessionstart();
					if (!empty($this->nick) && !empty($this->sess_id))
					{	
						$this->response(array(
									'got'        => true, 
									'userlist'   => $this->getuserlist(),
									'you'        => $this->nick,
									'messages'   => $this->getmessages()
								     ));
					}
					else
					{
						$this->response(array(
									'got' => false
								));
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
					$this->response(array(
						'deleted' => empty($this->nick) && empty($this->sess_id)
					));

					break;

				case 'newmessages':					
					$this->putmessage($this->client_message['messages']);
					if (!$this->checksession())
					{
						$err = 'session_not_found';
					}
					else
					{
						$this->response(array(
							'userlist'          => $this->getuserlist(),
							'messages'          => $this->getmessages(),
							'you'               => $this->nick
						));
					}				

					break;

				case 'refresh':
					if (!$this->checksession())
					{
						$err = 'session_not_found';
					}
					else
					{
						$this->response(array(
							//'have_session'      => !empty($this->nick) && !empty($this->sess_id) && !empty($this->last_checking),
							'userlist'          => $this->getuserlist(),
							'messages'          => $this->getmessages(),
							'you'               => $this->nick
						));
					}
			}
		}
		else
		{
			$err = 'illegal command format';
		}

		if ($err != '')
		{
			$this->response(array(
				'error' => $err
			));
		}

		$now_time = microtime(true);
		$this->dbh->query("UPDATE suff40_chat_sessions
							SET last_checking = '{$now_time}'
							WHERE psid = '{$this->pid}'");
	}



	private function sanitize_name($name) {
		$utfname = $name;
		if ($utfname === '')
		{
			$res = '';
		}

		if (mb_strlen($utfname) > 0 && mb_strlen($utfname) < 15 && preg_match('/^[-_A-Za-zА-Яа-я0-9^]+$/i', $utfname))
		{
			$res = $utfname;
		}
		else
		{
		//	$res = preg_replace('/[^-_A-Za-zА-Яа-я0-9^]/', '', $utfname);
	//		echo "Check name: {$res}";
		}
		
		return $utfname;
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
		$res = $this->dbh->query("SELECT username, id
									FROM suff40_chat_sessions");

		$res = $res->fetchAll(PDO::FETCH_ASSOC);
		
		return $res;
	}



	private function getmessages()
	{
		$sql = '';
		if ($this->session_restore)
		{
			$sql = "SELECT mess.message_text, mess.date, mess.put_date, sess.id, sess.username, sess.last_checking
					FROM suff40_chat_messages as mess
					LEFT JOIN suff40_chat_sessions as sess ON mess.session_id = sess.id
					WHERE mess.put_date > {$this->session_born_time}";

		}
		else {
			$sql = "SELECT mess.message_text, mess.date, mess.put_date, sess.id, sess.username, sess.last_checking
					FROM suff40_chat_messages as mess
					LEFT JOIN suff40_chat_sessions as sess ON mess.session_id = sess.id
					WHERE mess.put_date > {$this->last_checking}
						AND mess.session_id != '{$this->sess_id}'";

		}

		$res = $this->dbh->query($sql);		

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
		
		if (!is_array($decoded_messages))
		{			
			return;
		}

		//echo "SELECT id FROM suff40_chat_sessions WHERE psid = '{$this->pid}'";

		//var_dump($decoded_messages);		
		$now_time = microtime(true);

		if (count($decoded_messages) == 1)
		{
			$values_string = "('{$decoded_messages[0]['text']}', '{$decoded_messages[0]['date']}', '{$this->sess_id}', {$now_time})";
		}
		else
		{
			$values_string = '';

			for ($i = 0, $n = count($decoded_messages); $i < $n; ++$i)
			{
				$part = ",('{$decoded_messages[$i]['text']}', '{$decoded_messages[$i]['date']}', '{$this->sess_id}', {$now_time})";
				$values_string .= $part;
			}

			$values_string = substr($values_string, 1);
		}

		// echo "INSERT INTO suff40_chat_messages (message_text, date, session_id) 	VALUES {$values_string}";

		$this->dbh->query("INSERT INTO suff40_chat_messages (message_text, date, session_id, put_date)
							VALUES {$values_string}");		
	}



	private function sessiondelete()
	{
		if (!empty($this->pid))
		{
			$this->dbh->query("DELETE FROM suff40_chat_sessions
								WHERE psid = '{$this->pid}'");
		}

		$this->nick          = '';
		$this->sess_id       = null;
		$this->last_checking = null;

		return;
	}



	private function get_color()
	{
		return '#' . dechex(rand(0,255)) . dechex(rand(0,255)) . dechex(rand(0,255));
	}



	private function sessionstart()
	{
		//echo $sid;

		$res = $this->dbh->query("SELECT * FROM suff40_chat_sessions
									WHERE psid = '{$this->pid}'");
		$res = $res->fetch(PDO::FETCH_ASSOC);
		
		if (empty($res))
		{
			//echo "Перед добавлением сессии вишнейм: " . $this->wishname;
			if ($this->wishname === '')
			{
				return false;
			}

			$individual_color = $this->get_color();

			if (!$this->is_unique_name($this->wishname)) {
				$this->wishname = '';
			}
			
			$name = $this->wishname ? $this->wishname : 'Guest';
			$now_time = microtime(true);
			
			$this->dbh->query("INSERT INTO suff40_chat_sessions (psid, username, individual_color, session_born)
								VALUES ('{$this->pid}', '{$name}', '{$individual_color}', {$now_time})");

			$this->nick          = $name != 'Guest' ? $name : 'Guest' . $this->dbh->lastInsertId();
			$this->sess_id       = $this->dbh->lastInsertId();
			$this->last_checking = null;

		} else {
			$this->session_restore = (int)$this->session_born_time > 0 ? true : false;
			$this->nick            = $res['username'] != 'Guest' ? $res['username'] : 'Guest' . $res['id'];
			$this->sess_id         = $res['id'];
			$this->last_checking   = $res['last_checking'];
		}

		return;
	}

}
