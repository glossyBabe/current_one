<?php
	class glwFileUploader {
		
		private $god_object;
		private $analyzer;

		public $config;
		private $files;
		private $requested_type;
		private $response;

		public function __construct(&$god_object) {
			$this->god_object = $god_object;
			$this->god_object->load_component("fileanalyzer");
			$this->analyzer = $this->god_object->fileanalyzer;

			$this->config = array(
				'prev_width' => 50,
				'prev_height' => 50,
				'id_set' => array(
					'gallery' => 'new_picture',
					'presentation' => 'presentation',
					'photo' => 'photo',
					'press_release' => 'pressrelease',
					'logo' => 'logo'
				),
				'buffer' => dirname($this->god_object->work_dir) . '/images_buffer'
			);

			$this->distribute_files();
		}


		private function not_empty($key) {
			if (!isset($_FILES[$key])) {
				return false;
			}

			$this->multi = is_array($_FILES[$key]['name']) && is_array($_FILES[$key]['type']);
			return $this->multi ? $_FILES[$key]['name'][0] != '' : $_FILES[$key]['name'] != '';
		}

	
		private function distribute_files() {
			$not_empty_key = '';
			foreach ($this->config['id_set'] as $type => $key) {
				if (array_key_exists(preg_replace('/[^a-z_]*/i', '', $key), $_FILES) && $this->not_empty($key)) {
					$this->requested_type = $type;
					$not_empty_key = $key;
					break;
				}
			}

			$files = $_FILES[$not_empty_key];

			if ($this->multi) {
				for ($i = 0, $n = count($files['error']); $i < $n; ++$i) {
					$this->files[] = array(
						'name' => $files['name'][$i],
						'tmp_name' => $files['tmp_name'][$i],
						'type' => $this->requested_type,
						'error' => $files['error'][$i],
						'size' => $files['size'][$i],
						'buffer_path' => $this->config['buffer'],
						'w' => GetImageSize($files['tmp_name'][$i])[0],
						'h' => GetImageSize($files['tmp_name'][$i])[1]
					);
				}
			} else {
				$this->files[] = array(
					'name' => $files['name'],
					'tmp_name' => $files['tmp_name'],
					'type' => $this->requested_type,
					'error' => $files['error'],
					'size' => $files['size'],
					'buffer_path' => $this->config['buffer'],
					'w' => GetImageSize($files['tmp_name'])[0],
					'h' => GetImageSize($files['tmp_name'])[1]
				);
			}
		}


		public function upload() {
			$valid = $make_preview = false;
			$loaded_files = $deleted_files = array();
			$key = $this->config['id_set'][$this->requested_type];
			$type = $this->requested_type;

			$deleted_files = $this->check_removing();
			
			if ($type) {
				$this->god_object->log("Requested type is " . $this->requested_type . ', ' . $key);

				if ($type == 'gallery') {
					if (!empty($deleted_files)) {
					}
					$type_set = array('jpeg', 'gif', 'png');
					$size = 10000000;
					$make_preview = true;

				} else if ($type == 'logo' || $type == 'photo') {
					$type_set = array('jpeg', 'gif', 'ai', 'png');
					$size = 10000000;
					$make_preview = true;

				} else if ($type == 'presentation') {
					$type_set = array('pptx', 'ppt');
					$size = 10000000;

				} else if ($type == 'press_release') {
					$type_set = array('odt', 'doc', 'docx');
					$size = 10000000;
				}

				$this->god_object->log("Files array filled with " . count($this->files) . " files");
				$this->files = $this->analyzer->validate(array(
					'size_constraint' => $size,	
					'types' => $type_set
				), $this->files);

				if (empty($this->files)) {
					$this->god_object->log('Loading files interrupted because target file format is not matches. Try ' . implode(',', $type_set));
					$this->response['errors'][] = "Не верный формат, допустимые форматы указаны в описании.";
				} else if (is_array($this->files)) {
					$this->_upload($type, $make_preview);
				}

			} else if (!empty($deleted_files)) {
				$this->response['deleted'] = $deleted_files;
			} else if (empty($this->files)) {
				$this->god_object->log("Operation type is not defined or files are not received. No action.");
			}

			return $this->response;
		}


		private function check_removing() {
			$result = array();
	
			foreach ($_POST as $k => $val) {
				if ($val == 'delete') {
					$result[] = $k;
					$real_picname = str_replace('^', '.', $k);
					$full_path = $this->config['buffer'] . '/' . $real_picname;
					$full_path_prev = $this->config['buffer'] . '/s_' . $real_picname;
					
					if (file_exists($full_path) && $_SESSION[$k] == 'exists') {
						unlink($full_path);
	
						if (file_exists($full_path_prev)) {
							unlink($full_path_prev);
						}

						unset($_SESSION[$k]);
					}
				}
			}

			return $result;
		}


		function prepare_buffer() {
			$buf = $this->config['buffer'];
			if (is_dir($buf)) {
				if (is_dir($buf . '/gallery')) {
					$content = scandir($buf . '/gallery');

					foreach ($content as $filename) {
						$name = $buf . '/gallery/' . $filename;

						if ($filename != '.' && $filename != '..') {
							if (is_dir($name)) {
								rmdir($name);
							} else {
								unlink($name);
							}
						}
					}
				}

				$content = scandir($buf);

				foreach ($content as $filename) {
					$name = $buf . '/' . $filename;

					if ($filename != '.' && $filename != '..') {
						if (is_dir($name)) {
							rmdir($name);
						} else {
							unlink($name);
						}
					}	
				}

				if (is_writable($this->config['buffer'])) {
					mkdir($buf . '/gallery');
				} else {
					$this->god_object->log("some directory permissions requried for uploader working", 1);
				}
			}
		}


		private function _upload($type, $preview = false) {
			$path = $type == 'gallery' ? $this->config['buffer'] . '/gallery' : $this->config['buffer'];

			/* todo: all security procedures before file loading */
			
			foreach ($this->files as $file) {
				if (is_uploaded_file($file['tmp_name'])) {
					$success = move_uploaded_file($file['tmp_name'], $path . '/' . $file['name']);
				}

				if ($success) {
					$this->response['loaded'][] = str_replace('.', '^', $file['name']);

					$file['buffer_path'] = $path . '/' . $file['name'];
					if ($preview) {
						if (!isset($file['preview_path'])) {
							$file['preview_path'] = $path . '/s_' . $file['name'];
						}

						$preview_success = $this->make_preview($file);

						if ($preview_success !== true) {
							$this->response['errors'][] = "Unrecognized error occured while making preview; Filename: " . $file['name'];
						}
					}

					$this->god_object->log("File successfuly loaded. Name: " . $file['name'] . '; Type: ' . $type, 3);
				} else {
					$this->response['errors'][] = "File " . $file['name'] . " loading failed";
				}
			}
		}

	
		private function compute_size($file, $width, $height, $dim) {
			$ration = 1;
			$w_new = $width;
			$h_new = $height;

			if ($file['w'] && $file['h'] && $file['w'] >= $file['h']) {
				
				if ($file['w'] > $w_new) {
					$w_new = round($file['w'] / ($file['w'] / $w_new));
					$h_new = round($file['h'] / ($file['w'] / $w_new));
				} else {
					$w_new = $file['w'];
					$h_new = $file['h'];
				}
			} else if ($file['w'] && $file['h'] && $file['w'] < $file['h']) {
				
				if ($file['w'] > $h_new) {
					$w_new = round($file['w'] / ($file['h'] / $h_new));
					$h_new = round($file['h'] / ($file['h'] / $h_new));
				} else {
					$w_new = $file['w'];
					$h_new = $file['h'];
				}
			}
		
			return $dim == 'width' ? $w_new : $h_new;
		}


		private function make_preview($file) {
			$result = false;
			
			$prev_w = $this->compute_size($file, $this->config['prev_width'], $this->config['prev_height'], 'width');
			$prev_h = $this->compute_size($file, $this->config['prev_width'], $this->config['prev_height'], 'height');
			
			$corresponding_funcs = array(
				'jpg' => array('imagecreatefromjpeg', 'imagejpeg'),
				'jpeg' => array('imagecreatefromjpeg', 'imagejpeg'),
				'gif' => array('imagecreatefromgif', 'imagegif'),
				'png' => array('imagecreatefrompng', 'imagepng')
			);

			$img_create = $corresponding_funcs[$file['real_type']][0];
			$img_save = $corresponding_funcs[$file['real_type']][1];

			if (is_callable($img_create) && is_callable($img_save)) {
				$preview_handl = imagecreatetruecolor($prev_w, $prev_h);
				$source_handl = call_user_func($img_create, $file['buffer_path']);
				imagecopyresampled($preview_handl, $source_handl, 0, 0, 0, 0, $prev_w, $prev_h, $file['w'], $file['h']);
				$result = call_user_func($img_save, $preview_handl, $file['preview_path']);
			
				imagedestroy($preview_handl);
				imagedestroy($source_handl);
			}

			if (!$result) {
				$this->god_object->log("Error occured when attempting to resize this image; filename: " . $file['name'], 1);
			}

			return (boolean)$result;
		}
}
