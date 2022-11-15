<?php
	class glwFileAnalyzer {
		var $god_object;
		var $config = array();
		var $error;

		public function __construct(&$system) {
			$this->god_object = $system;
		}

		public function validate($config, $files) {
			$this->config = $config;
			$validated_files = array();

			if (empty($this->config)) {
				$this->god_object->log("Analyzer was not configured yet");
				return $results;
			}

			foreach ($files as $file) {
				if (isset($this->config['size_constraint']) && filesize($file['tmp_name']) > intval($this->config['size_constraint'])) {
					$this->put_error("Загружаемый файл большего размера чем указано в требованиях.");	
				} else {
					foreach ($this->config['types'] as $type) {
						$method_name = 'is_' . $type;
						$res = method_exists($this, $method_name)
							? call_user_func(array($this, $method_name), $file)
							: false;

						if ($res) {
							$this->god_object->log("File " . $file['name'] . " is checked. Type is " . $type);
							$file['real_type'] = $type;
							$validated_files[] = $file;
						} else {
							$this->put_error("Неверный формат, допустимые форматы указаны в описании.");
						}
					}
				}
			}
			
			return $validated_files;	
		}

		private function is_pdf($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'pdf' ? 'pdf' : false ;
		}

		private function put_error($message) {
			$this->error = $message;
		}

		public function get_errors() {
			return $this->error;
		}

		private function is_jpeg($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'jpg' || strtolower($last_part) == 'jpeg' ? 'jpg' : false;
		}

		private function is_gif($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'gif' ? 'gif' : false ;
		}

		private function is_png($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'png' ? 'png' : false;
		}

		private function is_ai($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'ai' ? 'ai' : false;
		}

		private function is_pptx($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'pptx' ? 'pptx' : false;
		}

		private function is_ppt($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'ppt' ? 'ppt' : false;
		}

		private function is_doc($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'doc' ? 'doc' : false;
		}

		private function is_docx($file) {
			$last_part = array_pop(explode('.', $file['name']));
			return strtolower($last_part) == 'docx' ? 'docx' : false;
		}
	}
