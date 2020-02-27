<?php
	class glwFileUploader {
		
		private $god_object;
		private $analyzer;

		private $config;
		private $files;
		private $requested_type;
		private $response;

		public function __construct($god_object) {
			$this->god_object = $god_object;
			if ($this->god_object->fileanalyzer instanceof glwFileAnalyzer) {
				$this->analyzer = $this->god_object->fileanalyzer;
			}

			$this->config = array(
				'id_set' => array(
					'gallery' => 'new_picture',
					'presentation' => 'presentation',
					'photo' => 'photo',
					'press_release' => 'press_release',
					'logo' => 'logo'
				),
				'buffer' => dirname($this->god_object->work_dir) . '/images_buffer'
			);

			foreach ($this->config['id_set'] as $type => $key) {
				if (array_key_exists(preg_replace('/[^a-z_]*/i', '', $key), $_FILES)) {
					$this->requested_type = $type;
				}
			}

			$this->god_object->log("Requested action upload; Assumed file type is " . $this->requested_type, 3);
		}

		public function upload() {
			$valid = false;
			$loaded_files = $deleted_files = array();
			$key = $this->config['id_set'][$this->requested_type];

			$files = $_FILES[$key];
			$multi = is_array($files['name']) && is_array($files['type']) && is_array($files['size']) ? true : false;

			if ($multi) {
				for ($i = 0, $n = count($files['error']); $i < $n; ++$i) {
					$this->files[] = array(
						'name' => $files['name'][$i],
						'tmp_name' => $files['tmp_name'][$i],
						'type' => $files['type'][$i],
						'error' => $files['error'][$i],
						'size' => $files['size'][$i],
						'buffer_path' => $this->config['buffer'],
						'real_type' => false
					);
				}
			} else {
				$this->files[] = array(
					'name' => $files['name'],
					'tmp_name' => $files['tmp_name'],
					'type' => $files['type'],
					'error' => $files['error'],
					'size' => $files['size'],
					'buffer_path' => $this->config['buffer'],
					'real_type' => false
				);
			}

			$this->god_object->log("Files array filled with " . count($this->files) . " files");
			$type = $this->requested_type;

			if ($type == 'gallery') {
				if (!empty($this->check_removing())) {
					$this->response['deleted'] = $deleted_files;
				}
				$type_set = array('jpg', 'gif', 'png');

			} else if ($type == 'logo' || $type == 'photo') {
				$type_set = array('jpg', 'gif', 'ai', 'png');

			} else if ($type == 'presentation') {
				$type_set = array('pptx', 'ppt');

			} else if ($type == 'press_release') {
				$type_set = array('odt', 'doc', 'docx');
			}

			if ($this->analyzer->validate($type_set, $this->files)) {
				$this->_upload($type, true);
			} else {
				$this->god_object->log("Analyzer aborted file loading because requested type was not approved");
				$this->response['errors'] = $this->analyzer->get_errors();
			}
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


		private function _upload($type, $make_preview = false) {
			$path = $type == 'gallery' ? $this->config['buffer'] . '/gallery' : $this->config['buffer'];

			/* todo: all security procedures before file loading */
			
			foreach ($this->files as $file) {
				if (is_uploaded_file($file['tmp_name'])) {
					$success = move_uploaded_file($file['tmp_name'], $path . '/' . $file['name']);
				}

				if ($success) {
					$this->response['loaded'][] = $file['name'];

					$file['buffer_path'] = $path . '/' . $file['name'];
					if (!isset($file['preview_path'])) {
						$file['preview_path'] = $path . '/s_' . $file['name'];
					}

					if (!$this->make_preview($file)) {
						$this->response['errors'][] = "Error occured while making preview; Filename: " . $file['name'];
					}

					unlink($file['tmp_name']);
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
					$w_new = intval($file['w'] / ($file['w'] / $w_new));
					$h_new = intval($file['h'] / ($file['w'] / $w_new));
				} else {
					$w_new = $file['w'];
					$h_new = $file['h'];
				}
			} else if ($file['w'] && $file['h'] && $file['w'] < $file['h']) {
				
				if ($file['w'] > $h_new) {
					$w_new = intval($file['w'] / ($file['h'] / $h_new));
					$h_new = intval($file['h'] / ($file['h'] / $h_new));
				} else {
					$w_new = $file['w'];
					$h_new = $file['h'];
				}
			}
		
			return $dim == 'width' ? $w_new : $h_new;
		}


		private function make_preview($file, $w, $h) {
			$result = false;
			$prev_w = $this->compute_size($file, $this->config['prev_width'], $this->config['prev_height'], 'width');
			$prev_h = $this->compute_size($file, $this->config['prev_width'], $this->config['prev_height'], 'height');

			
			$corresponding_funcs = array(
				'jpg' => array('imagecreatefromjpeg', 'imagejpeg'),
				'jpeg' => array('imagecreatefromjpeg', 'imagejpeg'),
				'gif' => array('imagecreatefromgif', 'imagegif'),
				'png' => array('imagecreatefrompng', 'imagepng')
			);

			if (is_callable($corresponding_funcs[$file['type']][0]) && is_callable($corresponding_funcs[$file['type']][1])) {
				$image = imagecreatetruecolor($prew_w, $prev_h);
				$im_old = call_user_func($corresponding_funcs[$file['type']][0], $file['buffer_path']);;
				imagecopyresampled($image, $memory_image, 0, 0, 0, 0, $prev_w, $prev_h, $file['w'], $file['h']);
				$result = call_user_func($corresponding_funcs[$file['type']][1], $image, $file['preview_path']);
			
				imagedestroy($image);
				imagedestroy($im_old);

			} else {
				$result = true;
				$this->god_object->log("Preview making omitted, because file type is not supposed to be resized; filename: " . $file['name'], 3);
			}

			if (!$result) {
				$this->god_object->log("Error occured when attempting to resize this image; filename: " . $file['name'], 1);	
			}

			return (boolean)$result;
		}


		private function ya_init() {

		}


		private function _ya_upload() {
			$this->ya_init();

			
		}
	}
