<?php
	class glwGodObject {
	
		private $action = '';
		public $params = array();

		public $work_dir = '';

		public $fileuploader = false;
		public $fileanalyzer = false;
		public $chatbackend = false;
		public $formprocessor = false;
		public $adminviewer = false;

		private $log_level = 3;
		public $table_prefix = '';
		private $database = '';

		private $log_file_path = '';
		private $log_events = array();

		private $cant_create_table = 1005;
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
			'service' => array('public_name' => "Частная торговая марка (Коллекция)", "public" => true),
			'jury_selected' => array('public_name' => "Выбран жюри", 'public' => false)
		);
		
		private $required_classes = array(
			'fileuploader', 'fileanalyzer', 'chatbackend', 'formprocessor', 'adminviewer'
		);

		public function __construct($modx, $config) {
			$this->modx = $modx;

			$this->work_dir = $config['work_dir'] != '' ? ($config['work_dir'] . '/php') : dirname(__FILE__);
			$this->log_file_path = $config['log_file_path'] != '' ? $config['log_level_path'] : (dirname(dirname($this->work_dir)) . '/log_file');
			$this->table_prefix = $modx->config[xPDO::OPT_TABLE_PREFIX];

			if (!empty($config['params'])) {
				$this->params = $config['params'];
			}

			$dsn_parts = explode(';', $this->modx->config[xPDO::OPT_CONNECTIONS][0]['dsn']);
			foreach ($dsn_parts as $part) {
				if (strpos('dbname', $part) !== FALSE) {
					$left_right = explode('=', $part);
					$this->database = $left_right[1];
				}	
			}
					
			$this->action = array_key_exists('action', $config) ? $config['action'] : '';
		}


		public function log_flush() {
			if (empty($this->log_events)) {
				return;
			}

			$fh = fopen($this->log_file_path, 'a');
			
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


		public function init() {
			$class_path = $class_name = '';
			$installed = false;

			$result = $this->modx->query("SELECT * FROM information_schema.tables WHERE "
			. "TABLE_SCHEMA = '" . $this->database . "' AND TABLE_NAME = '"
			. $this->table_prefix . "nomination_list'");
	
			if (is_object($result)) {
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
				judge_id INT NOT NULL,
				request_id INT NOT NULL,
				nomination_id INT NOT NULL,
				score TINYINT NULL,
				year VARCHAR(4) NOT NULL,
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
			}
		}


		public function handle() {
			$output = array();

			switch ($this->action) {
				case 'request':

					include_once $this->work_dir . "/fileuploader.class.php";
					include_once $this->work_dir . "/formprocessor.class.php";

					$this->formprocessor = new glwFormProcessor($this);
					$this->fileuploader = new glwFileUploader($this);

					$output = $this->formprocessor->create_request();

					break;
				case 'load':
	
					include_once $this->work_dir . "/fileuploader.class.php";
					include_once $this->work_dir . "/fileanalyzer.class.php";

					$this->fileanalyzer = new glwFileAnalyzer($this);
					$this->fileuploader = new glwFileUploader($this);

					$output = $this->fileuploader->upload();

					break;

				case 'vote':

					include_once $this->work_dir . "/formprocessor.class.php";

					$this->formprocessor = new glwFormProcessor($this);
					$output = $this->formprocessor->vote();
	
			}
			
			$this->log_flush();
			return $output;
		}
	}
