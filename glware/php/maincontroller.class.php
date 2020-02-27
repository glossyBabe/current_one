<?php
	class glwGodObject {
	
		private $action = '';
		public $work_dir = '';

		public $fileuploader = false;
		public $fileanalyzer = false;
		public $chatbackend = false;
		public $formprocessor = false;
		public $adminviewer = false;

		private $log_level = 3;
		private $log_file_path = '';
		private $log_events = array();

		private $required_classes = array(
			'FileUploader', 'FileAnalyzer', 'ChatBackend', 'FormProcessor', 'AdminViewer'
		);

		public function __construct($modx, $config) {
			$this->modx = $modx;
			$this->work_dir = $config['work_dir'] != '' ? ($config['work_dir'] . '/php') : dirname(__FILE__);
			$this->log_file_path = $config['log_file_path'] != '' ? $config['log_level_path'] : (dirname(dirname($this->work_dir)) . '/log_file');

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

		public function handle() {
			$output = array();

			switch ($this->action) {
				case 'attach_to_user':
					if ($this->config['mode'] != 'slave') {
						return array('errors' => 'Error occured; wrong query use');
					}

					break;
				case 'load':
	
					include_once $this->work_dir . "/fileuploader.class.php";
					include_once $this->work_dir . "/fileanalyzer.class.php";

					$this->fileanalyzer = new glwFileAnalyzer($this);
					$this->fileuploader = new glwFileUploader($this);
		
					$output = $this->fileuploader->upload();

					break;

				case 'vote':

	
			}
			
			$this->log_flush();
			return $output;
		}
	}
