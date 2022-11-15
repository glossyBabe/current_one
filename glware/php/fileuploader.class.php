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
				'prev_width' => 150,
				'prev_height' => 150,
				'id_set' => array(
					'gallery' => '_pic',
					'photo' => '_photo',
					'pressrelease' => '_pressrelease',
					'logo' => '_logo'
				),
				'type_codes' => array(
					'pressrelease' => 2,
					'photo' => 3,
					'logo' => 4,
					'gallery' => 5
				),
				'buffer' => dirname($this->god_object->work_dir) . '/images_buffer'
			);

			// file flags depended on nominations list
			$config_update = $this->build_presentation_file_flags();
			$this->config["id_set"] = array_merge($config_update["id_set"], $this->config["id_set"]);
			$this->config["type_codes"] = array_merge($config_update["type_codes"], $this->config["type_codes"]);
			$this->config["code_types"] = $config_update["code_types"];
			
			$this->config["code_types"][2] = "pressrelease";
			$this->config["code_types"][3] = "photo";
			$this->config["code_types"][4] = "logo";
			$this->config["code_types"][5] = "gallery";

			//$this->god_object->log("Updated arrays for file keys: " . print_r($this->config, true));

			$this->identify_file_keys();
		}


		private function build_presentation_file_flags() {
			$update = array();
			$tp = $this->god_object->table_prefix;
			$sql = "SELECT * FROM {$tp}nomination_list WHERE code != 'jury_selected'";
			$res = $this->god_object->modx->query($sql);
			$counter = 10;

			if (is_object($res)) {
				while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
					$update["id_set"]["presentation_" . $row["code"]] = "_presentation_" . $row["code"];
					$update["type_codes"]["presentation_" . $row["code"]] = $counter;
					$update["code_types"][$counter] = "presentation_" . $row["code"];
					$counter++;
				}
			}

			return $update;
		}


		public function get_filetype($file_name) {
			$filetype = 6; // type unknown
			$namebuffer = $file_name;
			$hash_chunk_length = 6;
			$user_id = $this->god_object->current_user["id"];
			$name_parts = explode("_", $namebuffer);

			$namebuffer = strpos($namebuffer, "s_") === 0 ? $name_parts[1] : array_shift($name_parts);

			// delete userid + hash chunk
			$namebuffer = substr($namebuffer, strlen(strval($user_id)) + $hash_chunk_length);
		
			if (strlen($namebuffer) == 1 && array_key_exists($namebuffer, $this->config["code_types"])) {
				$filetype = $this->config["code_types"][$namebuffer];
			} else if (strlen($namebuffer) == 2 && $namebuffer[0] == 1) {
				$filetype = $this->config["code_types"][$namebuffer];
			} else if (strlen($namebuffer) == 2 && $namebuffer[0] == 5) {
				$filetype = "gallery";
			}

			$this->god_object->log("Trying to parse filename " . $file_name . ": as result it is " . $namebuffer . ", " . $this->config["code_types"][$namebuffer]);

			return $filetype;
		}


		public function generate_name($file_type, $old_name, $number = 0) {
			if (!isset($this->config["type_codes"][$file_type])) {
				$this->god_object->log("Detected unknown filetype. It is: " . $file_type);
				return "ERROR-FILETYPE";	
			}

			$name = $old_name;
			$username = $this->god_object->current_user["username"];
			$user_id = $this->god_object->current_user["id"];
			$file_code = $this->config['type_codes'][$file_type];
			$type_counter = "";
	
			if ($file_type == "gallery") {
				$type_counter = $number;
			}

			$rp = $this->god_object->requestprocessor;
			$prev_hash = $rp->get_previous_request_hash();

			if ($prev_hash) {
				$sign = $user_id . strtolower(substr($prev_hash, 0, 6));
				if (strpos($name, $sign) === 0) {
					$this->god_object->log("Name before generating name: " . $name);
					$parts = explode("_", $name);
					$file_type_part = str_replace($sign, "", $parts[0]);
					$name = trim(str_replace($sign . $file_type_part . "_" . $username . " -", "", $name));
				} else {

				}
			}

			$this->god_object->log("Name after generating name: " . $name);

			$new_name = "";
			$name_parts = explode("^", $name);
			$ext = array_pop($name_parts);
			$oldname = implode("^", $name_parts);

			$hash_chunk = strtolower(substr($this->god_object->params["hash"], 0, 6));
			$new_name = $user_id . $hash_chunk . $file_code . $type_counter . "_" . $username . " - " . $oldname . "." . strtolower($ext);
			
			return $new_name;
		}


		private function not_empty($key) {
			if (!isset($_FILES[$key])) {
				return false;
			}

			$this->multi = is_array($_FILES[$key]['name']) && is_array($_FILES[$key]['type']);
			return $this->multi ? $_FILES[$key]['name'][0] != '' : $_FILES[$key]['name'] != '';
		}

	
		private function identify_file_keys() {
			if (empty($_FILES)) {
				return false;
			}

			$not_empty_key = '';
			foreach ($this->config['id_set'] as $type => $key) {
				if (array_key_exists(preg_replace('/[^a-z_]*/i', '', $key), $_FILES) && $this->not_empty($key)) {
					$this->requested_type = strpos($type, "presentation") === 0 ? "presentation" : $type;
					$not_empty_key = $key;
					break;
				}
			}

			$files = $_FILES[$not_empty_key];

			if (!$not_empty_key) {
				$this->god_object->log("Not known file type.");
			}

			if ($this->multi) {
				for ($i = 0, $n = count($files['error']); $i < $n; ++$i) {
					$this->files[] = array(
						'name' => $files['name'][$i],
						//'name' => $this->generate_name($files["name"][$i], $i),
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
					//'name' => $this->generate_name($files["name"]),
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
					$size = 1024 * 2000;
					$make_preview = true;

				} else if ($type == 'logo' || $type == 'photo') {
					$type_set = array('jpeg', 'gif', 'ai', 'png');
					$size = 1024 * 2000;
					$make_preview = true;

				} else if ($type == 'presentation') {
					$type_set = array('pptx', 'ppt', 'pdf');
					$size = 1024 * 8000;

				} else if ($type == 'pressrelease') {
					$type_set = array('odt', 'doc', 'docx');
					$size = 1024 * 2000;
				}

				$this->god_object->log("Files array filled with " . count($this->files) . " files");
				$this->files = $this->analyzer->validate(array(
					'size_constraint' => $size,	
					'types' => $type_set
				), $this->files);

				if (empty($this->files)) {
					$this->god_object->log('Loading files interrupted because of ' . $this->analyzer->get_errors() . ' Try ' . implode(',', $type_set));
					$this->response['errors'][] = $this->analyzer->get_errors();//"Не верный формат, допустимые форматы указаны в описании.";
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

	
		/* remove all files belonged to this user (from older sessions) before loading current collection from ya.disk */
		private function clean_buffer() {
			$buf = $this->config["buffer"];	
			$gal = $this->config["buffer"] . "/gallery/";
			$user_id = $this->god_object->current_user["id"];

			if (is_dir($buf)) {
				$content = scandir($buf);							
				foreach ($content as $filename) {
					$path = "{$buf}/{$filename}";

					if ($filename != "." && $filename != ".." && strpos($filename, $user_id) === 0) {
						unlink($path);
					}
				}

				// now cleaning gallery
				if (is_dir($gal)) {
					$content = scandir($gal . $filename);
					
					foreach ($content as $filename) {
						$path = "{$gal}/{$filename}";			
	
						if ($filename != "." && $filename != ".." && strpos($filename, $user_id) === 0) {
							unlink($path);
						}
					}
				}

			}
	
		}


		/* DEPRICATED */
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


		public function create_files($file_names) {
			$file_handlers = array("gallery" => array());

			foreach ($file_names as $fname => $link) {
				if (!$link) {
					continue;
				}
				$file_handlers[$fname] = fopen($this->config["buffer"] . "/" . $fname, "w+");
			}

			if (!empty($file_names["gallery"])) {
				foreach ($file_names["gallery"] as $fname => $link) {
					$file_handlers["gallery"][$fname] = fopen($this->config["buffer"] . "/gallery/" . $fname, "w+");
				}
			}

			return $file_handlers;
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
