<?php
	class glwGodObject {
	
		private $action = '';
		public $params = array();

		public $work_dir = '';

		public $current_user = false;
		public $fileuploader = false;
		public $fileanalyzer = false;
		public $chatbackend = false;
		public $yahelper = false;
		public $adminviewer = false;

		public $table_prefix = '';
		private $log_level = 3;
		private $database = '';
		private $users_cache;

		private $log_file_path = '';
		private $log_events = array();

		public $cant_create_table = 1005;
		private $nominations = array(
			'national_sell' => array('public_name' => "Национальная торговая компания", "public" => true),
			'national_production' => array('public_name' => "Национальная производственная компания", "public" => true),
			'salon' => array('public_name' => "Салон года", "public" => true),
			'network' => array('public_name' => "Сеть года", "public" => true),
			'debut' => array('public_name' => "Дебют года", "public" => true),
			'innovation' => array('public_name' => "Инновация года", "public" => true),
			'marketnetwork' => array('public_name' => "Маркетинговый проект года", "public" => true),
			'advertising' => array('public_name' => "Рекламный проект года", "public" => true),
			'privatelabel' => array('public_name' => "Частная торговая марка (Коллекция)", "public" => true),
			'service' => array('public_name' => "Образование и обучение", "public" => true),
			'jury_selected' => array('public_name' => "Выбран жюри", 'public' => false),
			'optometrist' => array('public_name' => "Оптометрист года", 'public' => false)
		);

		private $components_info = array(
			"yahelper" => array(
				"filename" => "yahelper.class.php",
				"classname" => "glwYaHelper",
				"propertyname" => "yahelper"
			),
			"loadassistant" => array(
				"filename" => "maincontroller.class.php",
				"classname" => "loadAssistant",
				"propertyname" => "loadassistant"
			),
			"requestprocessor" => array(
				"filename" => "requestprocessor.class.php",
				"classname" => "glwRequestProcessor",
				"propertyname" => "requestprocessor"
			),
			"voteprocessor" => array(
				"filename" => "voteprocessor.class.php",
				"classname" => "glwVoteProcessor",
				"propertyname" => "voteprocessor"
			),
			"fileuploader" => array(
				"filename" => "fileuploader.class.php",
				"classname" => "glwFileUploader",
				"propertyname" => "fileuploader"

			),
			"fileanalyzer" => array(
				"filename" => "fileanalyzer.class.php",
				"classname" => "glwFileAnalyzer",
				"propertyname" => "fileanalyzer"
			)
		);
		
		private $required_classes = array(
			'fileuploader', 'fileanalyzer', 'chatbackend', 'requestprocessor', 'voteprocessor', 'adminviewer'
		);

		public function __construct($modx, $config) {
			$this->modx = $modx;
			setlocale(LC_ALL, "C");

			$user = $modx->getUser();
			$profile = $modx->getObject('modUserProfile', array('internalkey' => $user->get('id')));
			$this->current_user = array_merge($user->toArray(), $profile->toArray());

			$this->work_dir = $config['work_dir'] != '' ? ($config['work_dir'] . '/php') : dirname(__FILE__);
			$this->log_file_path = $config['log_file_path'] != '' ? $config['log_level_path'] : (dirname(dirname($this->work_dir)) . '/log_file');
			$this->table_prefix = $modx->config[xPDO::OPT_TABLE_PREFIX] . "glw_";

			if (!empty($config['params'])) {
				$this->params = $config['params'];
			}

			$dsn_parts = explode(';', $this->modx->config[xPDO::OPT_CONNECTIONS][0]['dsn']);
			foreach ($dsn_parts as $part) {
				if (strpos($part, 'dbname') !== FALSE) {
					$left_right = explode('=', $part);
					$this->database = $left_right[1];
				}	
			}
					
			$this->action = array_key_exists('action', $config) ? $config['action'] : '';
			$this->log("Action registered - " . $this->action);
		}


		public function log_flush() {
			if (empty($this->log_events)) {
				return;
			}

			$opening_mode = filesize($this->log_file_path) > 500000 ? 'w' : 'a';
			$fh = fopen($this->log_file_path, $opening_mode);
			
			fwrite($fh, "========================\n");
			fwrite($fh, "glware session init\n");
			fwrite($fh, "========================\n");

			foreach ($this->log_events as $event) {
				$string = '<' . $event['time'] . '>; ('
					. $event['level'] . ')  ' . $event['message'] . "\n";

				fwrite($fh, $string);
			}
			fclose($fh);
		}

		public function log($message, $level = 3) {
			$this->log_events[] = array(
				'time' => date('H:m:i'),
				'level' => (integer)$level,
				'message' => $message
			);
		}


		public function table_exists($table_name) {
			$res = $this->modx->query("select * from information_schema.tables where "
			. "table_schema = '" . $this->database . "' and table_name = '"
			. $this->table_prefix . "{$table_name}'");
			
			$res = is_object($res) ? $res->fetch(PDO::FETCH_ASSOC) : false;
			
			return (boolean)$res;
		}


		public function init() {
			$class_path = $class_name = '';
			$installed = false;

			if ($this->table_exists('nomination_list')) {
				$result = $this->modx->query("SELECT * FROM " . $this->table_prefix . "nomination_list");

				if ($result->fetch(PDO::FETCH_ASSOC)) {
					$installed = true;
				}
			}

			if (!$installed) {
				$this->install();
			}

			if (!$this->action) {
				return array('message' => 'no action found');
			}

			if (!($this->modx instanceof modX)) {
				return array('message' => 'can\'t connect to the main system API');
			}

			foreach ($this->required_classes as $class) {
				$class_path = $this->work_dir . '/' . strtolower($class) . '.class.php';
				$class_name = 'glw' . $class;
				if (!file_exists($class_path)) {
					return array('message' => 'Required classes error: ' .  $class_path);

					break;
				}
			}

			$this->user = $this->modx->user;
			$this->user_type = '';
		}


		private function install() {
			$this->log("Goldlornet Ware is not installed yet. Trying to setup tables;");
			$tp = $this->table_prefix;

			$create_nominations = "CREATE table IF NOT EXISTS {$tp}nomination_list (
				id INT AUTO_INCREMENT,
				code VARCHAR(60) NOT NULL,
				public_name VARCHAR(150) NULL,
				PRIMARY KEY (id)
			)";
			$create_request_table = "CREATE table IF NOT EXISTS {$tp}user_formit_request (
				id INT AUTO_INCREMENT,
				formit_hash VARCHAR(255) NOT NULL,
				user_id INT NOT NULL,
				date INT NOT NULL,
				PRIMARY KEY (id)
			)";
			$create_request_nominations_table = "CREATE table IF NOT EXISTS {$tp}attendee_nomination_links (
				id INT AUTO_INCREMENT,
				request_id INT NOT NULL,
				nomination_id INT NOT NULL,
				PRIMARY KEY (id)
			)";
			$create_voting_table = "CREATE table IF NOT EXISTS {$tp}voting_results (
				id INT AUTO_INCREMENT,
				process_id INT NOT NULL,
				request_id INT NOT NULL,
				nomination_id INT NOT NULL,
				PRIMARY KEY (id)
			)";

			$create_voting2_table = "CREATE table IF NOT EXISTS {$tp}voting_process (
				id INT AUTO_INCREMENT,
				judge_id INT NOT NULL,
				year VARCHAR(4) NOT NULL,
				person_of_year VARCHAR (255) NULL,
				PRIMARY KEY (id)
			)";

			$create_yalinks_cache = "CREATE table IF NOT EXISTS {$tp}yalinks_cache (
				id INT AUTO_INCREMENT,
				request_id INT NOT NULL,
				resource_type VARCHAR (30) NOT NULL,
				cached_link VARCHAR (255) NOT NULL,
				PRIMARY KEY (id)
			)";

			$this->modx->query($create_nominations);
			$err = $this->modx->errorInfo();

			if ($err[1] == $this->cant_create_table) {
				$this->log("Unfortunately, not allowed to create tables on this server programmatically. Instead you should fulfill this preparations manually");
			} else {
				$insert_nominations = "INSERT into {$tp}nomination_list (code, public_name) VALUES ";
				$values = array();
				foreach ($this->nominations as $code => $nomination) {
					$values[] = "('{$code}', '{$nomination['public_name']}')";
				}

				$this->modx->query($insert_nominations . implode(',', $values));
		
				$this->modx->query($create_request_table);
				$this->modx->query($create_request_nominations_table);
				$this->modx->query($create_voting_table);
				$this->modx->query($create_voting2_table);
				$this->modx->query($create_yalinks_cache);
			}
		}

		public function load_component($name, $argum = array()) {
			$classname = $this->components_info[$name]['classname'];
			$path = $this->components_info[$name]['filename'];
			$propertyname = $this->components_info[$name]['propertyname'];
			$result = false;

			if (!($this->$propertyname instanceof $classname)) {

				if (!class_exists($classname) && file_exists($this->work_dir . "/" . $path)) {
					include_once $this->work_dir . "/" . $path;	
				}

				if (class_exists($classname)) {
					if (!empty($argum)) {
						$this->$propertyname = new $classname($this, $argum);
					} else {
						$this->$propertyname = new $classname($this);
					}

					$result = true;
				}
			} else {
				$result = true;	
			}

			if (!$result) {
				$this->log("Component loader: can not find " . $name . "; Given data for search: " . print_r(array_merge(array(
					'type of class' => $propertyname,
				), $this->components_info[$name]), true));
			}
		}


		public function get_user($id, $filter = false) {
			$user = false;

			if (isset($this->users_cache[$id])) {
				$user = $this->users_cache[$id];
			} else {
				$user = $this->modx->getObject('modUser', $id);
				$this->users_cache[$id] = $user;
			}

			if ($filter) {
				$user = $this->is_member($user, $filter) ? $user : false;
			}

			return $user;
		}


		public function is_member($user, $group) {
			$result = false;

			if (!($user instanceof xPDO) && is_numeric($user)) {
				$user = $this->modx->getObject('modUser', intval($user));
			}

			$result = $user instanceof modUser ? $user->isMember($group) : false;

			return $result;
		}


		public function get_members($group_name) {
			$group = $this->modx->getObject('modUserGroup', array('name' => $group_name));
			$members = $this->modx->getCollection('modUserGroupMember', array('user_group' => $group->get('id')));

			$result = array();
		
			foreach ($members as $member) {
				$user = $this->modx->getObject('modUser', array('id' => $member->get('member')));
				$result[$member->get('member')] = $user;
			}

			return $result;
		}


		public function print_yadirectory($path) {
			$f_list = array();
			$hashes = array();

			$f_array = $this->yahelper->get_directory($path);

			$sql = "SELECT * FROM {$this->table_prefix}user_formit_request";
			$result = $this->modx->query($sql);

			while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
				$hashes[$row["formit_hash"]] = $row;
			}

			$html = "<h1>{$path}</h1>";
			$html .= "<ul>";

			foreach ($f_array as $f) {
				if (substr($path, -1, 1) != "/") {
					$path .= "/";
				}

				$user_id = "";
				if ($path == "/") {
					$user_id = isset($hashes[$f["name"]]) ? "(" . $hashes[$f["name"]]["user_id"] . ")" : "(UNKNOWN)";
				}

				$link = "/third_party/glware/ajhandler.php?glw_action={$this->action}&path=" . $path . $f["name"];
				$f_list[] = $f["type"] == "dir"
					? "<li><a href='{$link}'>{$f["name"]} {$user_id}</a></li>"
					: "<li>{$f["name"]}</li>";
			}

			return $html . implode($f_list) . "</ul>";
		}

	
		public function handle() {
			$output = array();

			$this->load_component("yahelper");
			$this->load_component("requestprocessor");
			$this->load_component("voteprocessor");
			$this->load_component("fileuploader");
			$this->load_component("fileanalyzer");


			switch ($this->action) {
				case 'ya_init':
					$output = $this->yahelper->check_token();	
					break;

				case 'ya_receive':
					$output = $this->yahelper->receive_token();
					break;

				case 'request':
					//$this->load_component("loadassistant");

					$files = array();

					$this->requestprocessor->set_previous_hash($this->requestprocessor->get_saved_hash());
					/*$la->put(array(
						"create_request" => false,
						"create_dir" => false,
						"files" => array($files),
						"delete_previous" => false
					));*/

					$request_id = $this->requestprocessor->create_request();
				//	$la->fihish("CREATE_REQUEST");

					if ($request_id != 0) {
						$this->requestprocessor->prepare_uploading($request_id);
				//		$la->fihish("CREATE_DIR");

						foreach ($this->fileuploader->config["id_set"] as $k => $t) {
							$output = $this->requestprocessor->upload_userfiles($k, $request_id);
				//			$la->finish($k, "files");
						}
						$this->requestprocessor->delete_previous_requests();
					}

					break;

				case 'files':
					$output = $this->fileuploader->upload();
					break;

				case 'init_upload':
/*
					// session instead formit hash
					$this->load_component("loadassistant", array("code" => $resp["hash"]));
					$this->loadassistant->put("Как тебе такое, баран!");
					$output = array(
							'success' => true,
							'loadid' => $this->loadassistant->get_filename()
					$output["success"] = false;
*/

					break;

				case 'init_download':
					$full_queue = $files_queue = array();
		
					if ($hash = $this->requestprocessor->get_saved_hash()) {
						$this->download_queue = new LoadQueue($this, $hash);

//						$this->load_component("loadassistant", array("code" => $hash));
//						$this->loadassistant->init_session();

						$form_data = $this->requestprocessor->get_formdata_by_hash($hash);
						//$this->log("Form data: " . print_r($form_data,true) . ", list of filetypes: " . print_r($this->fileuploader->config["id_set"],true));

						foreach ($this->fileuploader->config["id_set"] as $key => $v) {
							if (!empty($form_data[$key])) {
								$files_queue[] = $form_data[$key];
							}
						}

						$gallery_array = isset($form_data["pic"]) ? $form_data["pic"] : array();

						$this->download_queue->add_task(false, "form_loading");
						$this->download_queue->add_task("files", $files_queue);
						$this->download_queue->add_task("gallery", $gallery_array);
						$this->download_queue->save();
					
						$output = array(
							'success' => true,
							'operation_type' => "DOWNLOAD_PROFILE",
							"queue" => $this->download_queue->get_queue(),
							'loadid' => $this->download_queue->get_filename()
						);
					} else {
						$output["success"] = false;
					}

					unset($this->download_queue);

					break;

				case 'get_request_json':
					$rp = $this->requestprocessor;
					$hash = $rp->get_saved_hash();

					$this->download_queue = new LoadQueue($this, $hash);
					$this->download_queue->restore();
					$user_id = $this->current_user["id"];

					if (is_numeric($user_id) && $this->is_member($user_id, "Contestants") && !empty($hash)) {
						$output = array(
							'success' => true,
							'id' => $user_id,
							'hash' => $hash,
							'form' => $rp->get_formdata_by_hash($hash),
							'links' => $rp->get_user_file_link($user_id),
							'previews' => $rp->download_user_files($hash)
						);
					} else {
						$output = array("success" => false, "message" => "Unsuitable user type. Only contestants can receive their request data.");
					}

					unset($this->download_queue);

					break;

				case 'print_yadisk_directory':
					$path = (isset($_GET["path"]) && $_GET["path"] != "") ? $_GET["path"] : "/";
					$output = $this->print_yadirectory($path);
					break;

				case 'get_gallery':
					/* DEPRICATED */
					$this->fileuploader->prepare_buffer();
					$output = array();
					break;

				case 'request_table':
					$output = $this->requestprocessor->get_all_requests_table_data();
					break;

				case 'judge_voting':
					$output = $this->voteprocessor->get_judge_voting_table();
					break;

				case 'judges_activity':
					$output = $this->voteprocessor->get_judges_activity_table();
					break;

				case 'voting_summary':
					$output = $this->voteprocessor->get_voting_summary_table();
					break;				

				case 'vote':
					$output = $this->voteprocessor->vote();
					break;

				case 'clean_all_data':
					$user = $this->modx->getUser();
					if ($this->is_member($user, "Administrator")) {

						include_once $this->work_dir . "/chatbackend.class.php";
						$this->chat = new glwChatBackend($this);

						$this->chat->clean_store();
						$this->requestprocessor->clean_all_requests();
						$this->voteprocessor->clean_all_data();

						$output = array("success" => true);
					}
					break;

				case 'chat':
					include_once $this->work_dir . "/chatbackend.class.php";
					$this->chat = new glwChatBackend($this);

					$output = $this->chat->run();
					break;
				default:
					$output = array("success" => false, "message" => "Invalid request");
			}
			
			$this->log_flush();
			return $output;
		}
	}

class LoadQueue {
	private $id;
	private $fh;
	private $glo;
	private $path;
	private $queue;
	private $modified;
	private $filename;

	public function __construct($god_object, $id) {
		$this->glo = $god_object;
		$this->path = dirname($this->glo->work_dir);
		$this->queue = array("loading_status" => "OK");

		if (!empty($id)) {
			$this->filename = "LOADERDATA_" . $id;
		}

	}


	public function get_filename() {
		return $this->filename;
	}


	public function get_queue() {
		return $this->queue;
	}


	public function modified() {
		$this->modified = true;
	}


	public function add_task($category, $fileName) {
		if ($category) {
			if (!array_key_exists($category, $this->queue)) {
				$this->queue[$category] = array();
			}
	
			if (is_array($fileName)) {
				$this->queue[$category] = $fileName;
			} else {
				$this->queue[$category][] = $fileName;
			}
		} else {
			$this->queue[$fileName] = false;
		}
	}


	public function done($category, $fname) {
		if (!$this->queue) {
			return;
		}

		$this->glo->log("Done command for filename: " . $category . "/" . $fname);

		if ($category && $fname) {
			$key = array_search($fname, $this->queue[$category]);
			$this->glo->log("Finished position: " . $key . "; " . $fname . " was searched");

			if ($key !== false) {
				array_splice($this->queue[$category], $key, 1);
				$this->modified();
			}	
		} else if ($category && !$fname) {
			if (array_key_exists($category, $this->queue)) {
				$this->queue[$category] = array();
				$this->modified();
			}
		} else if (!$category && $fname) {
			$this->glo->log("Finished position: " . $key . "; " . $fname . " was searched");
			if (array_key_exists($fname, $this->queue)) {
				unset($this->queue[$fname]);	
				$this->modified();
			}
		}

		if ($this->modified) {
			$this->save();
			$this->glo->log("Status (old): filesize after \"opening\" is " . filesize($this->filename));
			$this->modified = false;
		}
	}
/*
	function done($ftype, $name = "", $subarray = "files") {
		if (!$name && $subarray == "files") {
			if (array_key_exists($ftype, $this->queue)) {
				$this->queue[$ftype] = array();
			}
		} else {
			$parts = explode("-", $name);
			$transformed_name = trim(array_pop($parts));
			$parts = explode(".", $transformed_name);
			$ext = array_pop($parts);
			$name_before_ext = implode(".", $parts);
			$transformed_name = $name_before_ext . "^" . strtolower($ext);
			
			$this->glo->log("Finished " . $subarray . " -> " . $transformed_name . " and type: " . $ftype . ": " . print_r($this->queue[$subarray], true));

			if ($ftype) {
				if (array_key_exists($ftype, $this->queue[$subarray])) {
					unset($this->queue[$subarray][$ftype]);
				}
			} else {
				if (strstr($name, "s_")) {
					$transformed_name = substr($name, 2);
				}
				$pos = array_search($transformed_name, $this->queue[$subarray]);
				$this->glo->log("Finished position: " . $pos . "; " . $transformed_name . " was searched");
				unset($this->queue[$subarray][$pos]);
			}
		}

		$this->save();
		$this->glo->log("Status (old): filesize after \"opening\" is " . filesize($this->filename));
	}
*/


	public function finish() {
		$this->glo->log("LAST STATE OF QUEUE: " . print_r($this->queue, true));
		foreach ($this->queue as $key => $item) {
			if (is_array($item)) {
				if (!empty($item)) {
					$this->queue[$key] = array();
					$this->modified();
				}
			}
		}

		if ($this->modified) {
			$this->queue["loading_status"] = "NOT_FOUND";
			$this->save();
			$this->modified = false;
		}
	}


	public function abort() {

	}


	public function get_error() {

	}


	public function get_queue_as_json() {
		return json_encode($this->queue);
	}


	public function restore() {
		$this->fh = fopen($this->filename, "c+");
		$this->glo->log("Status (old): " . $old . "; before init filesize of " . $this->filename . " is: " . filesize($this->filename));
		$temp = fgets($this->fh);
		$this->glo->log("Status (old): " . $old . "; after init queue is: " . $temp);
		$this->queue = json_decode(trim($temp, "\0"), true);
	}


	public function save() {
		if (!$this->fh) {
			$this->fh = fopen($this->filename, "w");
		}

		if (is_writable(dirname($this->path))) {
			$this->glo->log("File handler is: " . $this->fh);
			ftruncate($this->fh, 0);
			rewind($this->fh);
			fwrite($this->fh, $this->get_queue_as_json());
			fflush($this->fh);
		} else {
			$this->glo->log("Not writable loadmanager store");
		}
	}


	function __destruct() {
		$res = fclose($this->fh);
	}
}
