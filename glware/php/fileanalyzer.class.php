<?php
	class glwFileAnalyzer {
		var $god_object;
		var $config = array();

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
				if (isset($this->config['size_constraint']) && filesize($file['tmp_name']) < intval($this->config['size_constraint'])) {
					foreach ($this->config['types'] as $type) {
						$method_name = 'is_' . $type;
						$res = method_exists($this, $method_name)
							? call_user_func(array($this, $method_name), $file)
							: false;

						if ($res) {
							$this->god_object->log("File " . $file['name'] . " is checked. Type is " . $type);
							$file['real_type'] = $type;
							$validated_files[] = $file;
						}
					}
				}
			}
			
			return $validated_files;	
		}

		public function get_errors() {
			return $this->errors;	
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
			return false;
		}

		private function is_ptx($file) {
			return false;
		}

		private function is_pptx($file) {
			return false;
		}

		private function is_doc() {
			return false;
		}

		private function is_docx() {
			return false;
		}
	}
