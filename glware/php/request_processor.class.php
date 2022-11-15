<?php
	class glwRequestProcessor {
		private $modx;
		private $glo;
		private $yah;
		private $me;
		private $current_hash;
		private $previous_hash;
		private $form;
		private $yadisk_links_cache;
	

		public __construct($god_object) {
			$this->glo = $god_object;
			$this->modx = $this->glo->modx;
			$this->me = $this->modx->getUser();
			$this->hash = $this->glo->params["hash"];
			$this->my_form = array();

			if ($this->hash) {
				$this->my_form = $this->get_formdata_by_hash($this->hash);
			}

			$this->previous_hash = false;
			
		}


		public function set_previous_request_hash($hash) {
			$this->previous_hash = $hash;
		}


		public function get_previous_request_hash() {
			return $this->previous_hash;
		}


		public function get_formdata_by_hash($hash) {
			$form = array();
			$modx =& $this->modx;
			if (!class_exists("FormIt")) {
				$formit = $modx->getService(
					'formit', 'FormIt',
					$modx->getOption('formit.core_path', null, $modx->getOption("core_path") . "components/formit/") . "model/formit/", array()
				);
			}

			$form = $modx->getObject('FormItForm', array('hash' => $hash));

			if ($form) {
				$form = urldecode($form->get('values'));
				try {
					$form = $this->modx->fromJSON($form);
				} catch (Error $e) {
					$form = array();
				}
			}
	
			return $form;
		}

	
		public function get_current_request() {
			$output = array();
			$tp = $this->glo->table_prefix;
			$user_id = $this->glo->current_user["id"];
			
			if (is_numeric($user_id) && $this->glo->is_member($user_id, "Contestants")) {
				$form = array();
				$output = array(
					"hash" => '',
					"form" => array(),
					"files" => array(
						"presentation" => '',
						"logo" => '',
						"dir_photo" => '',
						"press_release" => ''
					),
					"previews" => array(),
					"gallery" => array()
				);

				$req_id = $this->get_user_last_requests($user_id);
				$sql = "SELECT * FROM {$tp}user_formit_request WHERE user_id = {$user_id} ORDER BY id DESC";
				$result = $this->modx->query($sql);

				if (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
					$hash = $row["formit_hash"];

					$output["hash"] = $hash;
					$output["form"] = $this->get_formdata_by_hash($hash);
					$output["id"] = $user_id;
					$files_and_gal = $this->get_user_file_link($user_id);
					$output["files"] = array_merge($output["files"], $files_and_gal);
					$output["gallery"] = $files_and_gal["gallery"];
					$output["previews"] = $this->download_user_files($hash);
					$output["success"] = true;
				}
			} else {
				$output = array("success" => false, "message" => "Unsuitable user type. Only contestants can receive their request data.");
			}
			
			return $output;
		}


		public function get_user_last_requests($user_id = 0) {
			$result = false;
			$rows = array();

			$tp = $this->glo->table_prefix;
			$sql = "SELECT max(id) as last_id FROM {$tp}user_formit_request ";
			$sql .= $user_id > 0
				? "WHERE user_id = " . intval($user_id)
				: "GROUP BY user_id";
			$result = $this->modx->query($sql);

			while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
				$rows[] = $row['last_id'];
			}
			
			return $user_id ? array_shift($rows) : $rows;
		}


		public function download_user_files($hash) {
			$files_array = array("gallery" => array());
			$files_array = array_merge($files_array, $this->yah->get_previews_list($hash, ""));
			$files_array["gallery"] = $this->get_previews_list($hash, "gallery");

			// create all files with fileuploader and get resource handlers
			$fu = $this->glo->fileuploader;
			$file_handlers = $fu->create_files($files_array);

			foreach ($file_handlers as $fname => $fh) {
				if (!is_resource($fh)) {
					continue;
				}
	
				$this->yah->download($file_array[$fname], $fh);
			}

			if (!empty($file_handlers["gallery"])) {
				foreach ($file_handlers["gallery"] as $fname => $fh) {
					if (!is_resource($fh)) {
						continue;
					}

					$this->yah->download($files_array["gallery"][$fname], $fh);
				}
			}

			return $files_array;
		}

		
		public function get_user_file_link($user_id, $type = false) {
			$tp = $this->glo->table_prefix;
			$links = array("presentation" => false, "dir_photo" => false, "logo" => false, "press_release" => false, "gallery" => false);
			$iterator = $type ? array($type) : array("presentation", "dir_photo", "logo", "press_release", "gallery");
			$last_request_id = intval($this->get_user_last_requests($user_id));

			foreach ($iterator as $current_type) {
				$cur_link = "";

				if (isset($this->yadisk_links_cache[$user_id]) && isset($this->yadisk_links_cache[$user_id][$current_type])) {
					$cur_link = $this->yadisk_links_cache[$user_id][$current_type];
				} else {
					$result = $this->modx->query("SELECT * FROM {$tp}yalinks_cache WHERE request_id = " . $last_request_id . " AND resource_type = '" . $current_type . "'");
					
					while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
						$cur_link = $row["cached_link"];
					}

					if (!isset($this->yadisk_links_cache[$user_id])) {
						$this->yadisk_links_cache[$user_id] = array();
					}

					if (strlen($cur_link)) {
						$this->yadisk_links_cache[$user_id][$current_type] = $cur_link;
						$links[$current_type] = $cur_link;
					}
				}
			}

			return $type ? $links[$type] : $links;
		}

		
		public function get_all_requests_table_data() {
			$current_request = array();
			$users_cache = array();

			$tp = $this->glo->table_prefix;
			$prev_req_id = 0;
			$in = $this->get_user_last_requests();

			if (count($in)) {
				$where = count($in) > 1
					? "fr.id IN (" . implode(',', $in) . ")"
					: "fr.id = " . array_pop($in);	

				$sql = "SELECT fr.*, yc.* FROM {$tp}user_formit_request as fr
						LEFT JOIN {$tp}yalinks_cache as yc ON yc.request_id = fr.id
						WHERE {$where} ORDER BY request_id";
				$result = $this->modx->query($sql);

				//$this->god_object->log("Current sql for file info: " . $sql);

				while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
					//$this->god_object->log("Current row for file info: " . print_r($row, true));
					$current_id = intval($row['request_id']);
					$type = $row['resource_type'];
					$user_id = $row['user_id'];
					$current_hash = $row['formit_hash'];

					if (!isset($users_cache[$row['user_id']])) {
						$users_cache[$user_id] = $this->modx->getObject('modUser', $row['user_id']);
					}

					if (!$this->glo->is_member($users_cache[$user_id], 'Contestants') || !$type) {
						$prev_req_id = $current_id;
						continue;
					}

					if ($prev_req_id && $current_id != $prev_req_id) {
						$output[] = $current_request;
						$current_request = array();
					}

					if (empty($current_request)) {
						$form = $this->get_request_by_hash($current_hash);
						$exclude_fields = array('pic', 'dir_photo', 'logo', 'presentation', 'press_release');

						foreach ($form as $k => $v) {
							if (!in_array($k, $exclude_fields)) {
								$anket[$k] = $form[$k];
							}
						}

						$current_request = array(
							'userid' => $user_id,
							'name' => $users_cache[$user_id]->get('username'),
							'anket' => $anket,
							'exists' => $form->org_name,
							'presentation' => false,
							'press' => false,
							'manager_photo' => false,	
							'gallery' => false,	
							'logo' => false
						);
					}

					if ($type == 'presentation') {
						$current_request['presentation'] = $row['cached_link'];
					} else if ($type == 'logo') {
						$current_request['logo'] = $row['cached_link'];
					} else if ($type == 'dir_photo') {
						$current_request['manager_photo'] = $row['cached_link'];
					} else if ($type == 'press_release') {
						$current_request['press'] = $row['cached_link'];
					} else if ($type == 'gallery') {
						$current_request['gallery'] = $row['cached_link'];
					}

					$prev_req_id = $current_id;
				}

				if (!empty($current_request)) {
					$output[] = $current_request;
				}
				$this->glo->log("Get admin output: " . print_r($output, true));
			}

			return $output;
		}

	
		public function create_request() {
			$success = false;
			$id = $this->me->get('id');
			$tp = $this->glo->table_prefix;

			$nom = array_map(function($el) {
				return "'" . $el . "'";
			}, $this->glo->params['nominations']);

			$this->glo->log("nominations: " . print_r($nom, true));
			$res = $this->modx->query("INSERT INTO {$tp}user_formit_request (formit_hash, user_id, date) VALUES (
				'{$this->current_hash}', {$id}, " . time() . ")");
	
			if ($res) {
				$request_id = $this->modx->lastInsertId();
				$res = $this->modx->query("SELECT * FROM {$tp}nomination_list WHERE code IN (" . implode(',', $nom) . ")");
				$nom = array();

				if (is_object($res)) {
					while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
						$values[] = "({$request_id}, {$row['id']})";
						$nom[] = $row['code'];
					}

					$sql = "INSERT INTO {$tp}attendee_nomination_links (request_id, nomination_id) VALUES ";

					$this->god_object->log("Created the requst. Found following correct nominations in request: ".
					implode(',', $nom));
					$this->god_object->log("SQL: " . $sql . implode(',' , $values));

					$success = $this->modx->query($sql . implode(',', $values));
				}
			}
				
			if ($success) {
				$this->god_object->log("Successfuly created all nomination links. Preparng file routines");
			} else {
				// roll-back of transaction... kind of
				$this->modx->query("DELETE FROM {$tp}user_formit_request WHERE formit_hash = {$this->currnet_hash}");
			}

			return $success ? $request_id : 0;
		}


		public function upload_userfiles($request_id) {
			$dir_created = false;

			if (!$this->current_hash) {
				return;
			}
			
			$this->glo->log("Got form object: " . print_r($this->my_form, true));

			if ($this->yah->create_yadirectory("/" . $this->current_hash)) {
				if ($this->yah->create_yadirectory("/" . $this->current_hash . "/gallery")) {
					$dir_created = true;
				}
			}

			if (!$dir_created) {
				$this->glo->log("Creating directory " . $this->current_hash . " caused error");
			}

			$fu = $this->glo->fileuploader;
			$this->glo->log("Extracted gallery list from form data: " . is_array($this->my_form['pic']) ? implode("-", $this->my_form["pic"]) : $this->my_form["pic"]);

			if (!empty($this->my_form['pic'])) {
				$hash_chunk = "";
				$input_path = $fu->config['buffer'] . "/gallery/";
				$output_path = "/{$this->current_hash}/gallery/";
				$counter = 0;

				if ($this->previous_hash) {
					$hash_chunk = strtolower(substr($this->previous_hash, 0, 6));
					$exists_on_disk_sign = $this->me->get("id") . $hash_chunk;
				}

				foreach ($this->my_form["pic"] as $pic) {
					$item_name = $fu->generate_name("gallery", $pic, $counter);

					if (strpos($pic, $exists_on_disk_sign) === 0) {
						$this->yah->move_from_older_request($pic, $item_name);
					} else {
						$this->glo->log("Trying to add and publish "  . $pic . " -> " . $item_name);
						$this->yah->load_apilevel($pic, $item_name, $input_path, $output_path);
						$this->yah->publish($pic, "{$output_path}" . $item_name, $request_id);
					}

					$counter++;
				}

				$this->yah->publish('gallery', $output_path, $request_id);
			}

			$input_path = $fu->config['buffer'] . "/";
			$output_path = "/{$this->current_hash}/";

			// generating unique names based on ID and formIt hash
			$presentation_new_name = $fu->generate_name("presentation", $this->my_form["presentation"]);
			$press_release_new_name = $fu->generate_name("press_release", $this->my_form["press_release"]);
			$logo_new_name = $fu->generate_name("logo", $this->my_form["logo"]);
			$dir_photo_new_name = $fu->generate_name("photo", $this->my_form["dir_photo"]);

			if ($this->my_form['presentation'] != '' && $this->yah->load_apilevel($this->my_form['presentation'], $presentation_new_name, $input_path, $output_path)) {
				$this->yah->publish("presentation", "{$output_path}" . $presentation_new_name, $request_id);
			}

			if ($this->my_form['dir_photo'] != '' && $this->yah->load_apilevel($this->my_form['dir_photo'], $dir_photo_new_name, $input_path, $output_path)) {
				$this->yah->publish("dir_photo", "{$output_path}" . $dir_photo_new_name, $request_id);
			}

			if ($this->my_form['logo'] != '' && $this->yah->load_apilevel($this->my_form['logo'], $logo_new_name, $input_path, $output_path)) {
				$this->yah->publish("logo", "{$output_path}" . $logo_new_name, $request_id);
			}

			if ($this->my_form['press_release'] != '' && $this->yah->load_apilevel($this->my_form['press_release'], $press_release_new_name, $input_path, $output_path)) {
				$this->yah->publish("press_release", "{$output_path}" . $press_release_new_name, $request_id);
			}
		}

	
		function delete_previous_request() {
			if (!$this->previous_hash) {
				return;	
			}


	
		}
	}
