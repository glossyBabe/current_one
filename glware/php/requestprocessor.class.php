<?php
	class glwRequestForm {
		private $glo;
		private $form_object;
		private $form;

		public function __construct($glo, $hash) {
			$this->glo = $glo;

			if (!class_exists("FormIt")) {
				$glo->modx->getService("formit", "FormIt", $glo->modx->getOption("formit.core_path", null, $glo->modx->getOption("core_path") . "components/formit/") . "model/formit/", array());
			}

			$this->form_object = $glo->modx->getObject("FormItForm", array("hash" => $hash));
		}

	
		private function extract_values() {
			if (!empty($this->form)) {
				return;
			}

			$json = $this->form_object->get("values");

			if (strstr($json, "+")) {
				$json = $this->deplusify($json);
			}

			try {
				$this->form = $this->glo->modx->fromJSON($json);
			} catch (Error $e) {
				$this->form = array();
			}
		}


		private function deplusify($json) {
			$form_array = array();
			$parts = explode("+", $json);	

			foreach ($parts as $part) {
				$form_array[] = urldecode($part);
			}

			return implode("+", $form_array);
		}


		public function get() {
			if (!isset($this->form) || empty($this->form)) {
				$this->extract_values();
			}

			return $this->form;
		}


		public function get_as_array() {
			$result = array();

			if ($form = $this->get()) {
				foreach ($form as $k => $v) {
					$result[$k] = $v;
				}
			}
	
			return $result;
		}


		public function rename_form_value($key, $value) {
			$this->extract_values();

			if (!array_key_exists($key, $this->form)) {
				return;
			}

			if (is_array($this->form[$key])) {

				if (is_array($value)) {
					$this->form[$key] = $value;
				} else {

					// search and replace

				}
			} else {
				$this->form[$key] = $value;
			}

			$new_json = $this->glo->modx->toJSON($this->form);
			$this->form_object->set("values", $new_json);
			$this->form_object->save();

			// values expired
			$this->form = false;
		}
	}


	class glwRequestProcessor {
		private $modx;
		private $glo;
		private $yah;
		private $me;
		private $current_hash;
		private $previous_hash;
		private $form;
		private $yadisk_links_cache;
	

		public function __construct($god_object) {
			$this->glo = $god_object;
			$this->modx = $this->glo->modx;
			$this->yah = $this->glo->yahelper;
			$this->me = $this->modx->getUser();
			$this->current_hash = $this->glo->params["hash"];
//			$this->my_form = array();
			$this->request_nominations_pivot_table = array();

//			if ($this->current_hash) {
//				$this->my_form = $this->get_formdata_by_hash($this->current_hash);
//			}

			$this->previous_hash = false;
			
		}


		public function set_previous_hash($hash) {
			$this->previous_hash = $hash;
		}


		public function get_previous_request_hash() {
			return $this->previous_hash;
		}


		public function get_formdata_by_hash($hash, $as_array = false) {
			if (!$this->forms[$hash]) {
				$formObject = new glwRequestForm($this->glo, $hash);
//				$this->glo->log("hash: " . print_r($formObject->get_as_array(),true));
				$this->forms[$hash] = $formObject;

				if ($hash == $this->current_hash) {
					$this->form = $formObject;
				}
			}
			
			return $as_array ? $this->forms[$hash]->get_as_array() : $this->forms[$hash]->get();
		}

		
		public function get_saved_hash() {
			$hash = '';
			$tp = $this->glo->table_prefix;
			$user_id = $this->glo->current_user["id"];
			
			if (is_numeric($user_id) && $this->glo->is_member($user_id, "Contestants")) {
				//$req_id = $this->get_user_last_requests($user_id);
				$sql = "SELECT formit_hash FROM {$tp}user_formit_request WHERE user_id = {$user_id} ORDER BY id DESC";
				$result = $this->modx->query($sql);

				if (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
					$hash = $row["formit_hash"];
				}
			}

			return $hash;
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
			$fu = $this->glo->fileuploader;

			$files_array = $this->yah->get_previews_list($hash, "");
			$files_array["gallery"] = $this->yah->get_previews_list($hash, "gallery");
			//$this->glo->log("file_previes array: " . print_r($files_array, true));

			// create all files with fileuploader and get resource handlers
			$file_handlers = $fu->create_files($files_array);

			foreach ($file_handlers as $fname => $fh) {
				if (!is_resource($fh)) {
					continue;
				}
	
				$this->yah->download($files_array[$fname], $fh);
				$filetype = $fu->get_filetype($fname);
				$filenames_by_type[$filetype] = $fname;

				$this->glo->log("Fname: " . $fname . "; filehandler: " . $fh);
				if (strstr($fname, "s_") !== false) {
					$fname = substr($fname, 2);
				}
				$this->glo->download_queue->done("files", $fname);
				// remove file element from queue stack
			}

			if (!empty($file_handlers["gallery"])) {
				$filenames_by_type["gallery"] = array();

				foreach ($file_handlers["gallery"] as $fname => $fh) {
					if (!is_resource($fh)) {
						continue;
					}

					$this->yah->download($files_array["gallery"][$fname], $fh);
//					if (!filesize($output)) {
//						$this->glo->log("Error occured while loading " . $output);
//					}
					$filenames_by_type["gallery"][] = $fname;

					if (strstr($fname, "s_") !== false) {
						$fname = substr($fname, 2);
					}

					$this->glo->download_queue->done("gallery", $fname);

				}
			}

			$this->glo->download_queue->finish();

			return $filenames_by_type;
		}

		
		public function get_user_file_link($user_id, $type = false) {
			$tp = $this->glo->table_prefix;
			$file_links = array();

			$last_request_id = intval($this->get_user_last_requests($user_id));

			$result = $this->modx->query("SELECT * FROM {$tp}yalinks_cache WHERE request_id = " . $last_request_id);

			while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
				$file_links[$row["resource_type"]] = $row["cached_link"];
			}
/*
			$links = array("presentation" => false, "dir_photo" => false, "logo" => false, "press_release" => false, "gallery" => false);
			$iterator = $type ? array($type) : array("presentation", "dir_photo", "logo", "press_release", "gallery");
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
*/
			return $type ? $file_links[$type] : $file_links;
		}


		private function load_selected_nominations($request_id) {
			$tp = $this->glo->table_prefix;

			if (empty($this->request_nominations_pivot_table[$request_id])) {
				$sql = "SELECT anl.*, nl.* FROM {$tp}attendee_nomination_links AS anl LEFT JOIN {$tp}nomination_list AS nl ON anl.nomination_id = nl.id";	
				$result = $this->glo->modx->query($sql);

				while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
					//$this->glo->log("Processing another row: nomination is " . $row["code"]);
					if (!isset($this->request_nominations_pivot_table[$row["request_id"]])) {
						$this->request_nominations_pivot_table[$row["request_id"]] = array();
					}

					$this->request_nominations_pivot_table[$row["request_id"]][] = $row["code"];
				}
			}

			return $this->request_nominations_pivot_table[$request_id];
		}

	
		public function clean_all_requests() {
			$tp = $this->glo->table_prefix;
			$this->modx->query("DELETE FROM {$tp}attendee_nomination_links");
			$this->modx->query("DELETE FROM {$tp}yalinks_cache");
			$this->modx->query("DELETE FROM {$tp}user_formit_request");
		}

		
		public function get_all_requests_table_data() {
			$requests = $table_rows = array();

			$tp = $this->glo->table_prefix;
			$in = $this->get_user_last_requests();

			if (!count($in)) {															// ???
				return false;
			}

			$where = count($in) > 1
				? "fr.id IN (" . implode(',', $in) . ")"
				: "fr.id = " . array_pop($in);	

			$sql = "SELECT fr.*, yc.* FROM {$tp}user_formit_request as fr
					LEFT JOIN {$tp}yalinks_cache as yc ON yc.request_id = fr.id
					WHERE {$where} ORDER BY request_id";
			$result = $this->modx->query($sql);

			// first loop for filling requests array
			while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
				if (!isset($table_rows[$row["request_id"]])) {
					$table_rows[$row["request_id"]] = array();
				}

				$table_rows[$row["request_id"]][] = $row;
			}

			// second loop that prepares table rows
			foreach ($table_rows as $rows) {
				$output[] = $this->prepare_one_request_table_row($rows);
			}

			$this->glo->log("hash: " .$rows[0]['formit_hash'] . "; " . print_r($output,true));
			//$this->glo->log("Get admin output: " . print_r($output, true));

			return $output;
		}


		private function prepare_one_request_table_row($request_rows) {
			$result_row = array();

			$current_id = intval($request_rows[0]['request_id']);
			$user_id = $request_rows[0]['user_id'];
			$user = $this->glo->get_user($user_id);

			if ($user) {
				$form = $this->get_formdata_by_hash($request_rows[0]['formit_hash'], true);
				for ($i = 0, $n = count($request_rows); $i < $n; ++$i) {
					$result_row[$request_rows[$i]['resource_type']] = $request_rows[$i]["cached_link"];
					unset($form[$request_rows[$i]['resource_type']]);
				}

				$result_row["user_id"] = $user_id;
				$result_row["name"] = $user->get("username");
				$result_row["anket"] = $form;
				$result_row["exists"] = $form->org_name;

				$selected_nominations = $this->load_selected_nominations($current_id);
				$required = count($selected_nominations);
				$loaded = 0;

				for ($i = 0; $i < $required; ++$i) {
					if (array_key_exists("presentation_" . $selected_nominations[$i], $result_row)) {
						$loaded++;
					}
				}

				$result_row["presentations_proportion"] = $loaded . "/" . $required;
				if ($loaded > 0 && $loaded == $required) {
					$result_row["all_presentations"] = true;
				}
			}

			return $result_row;
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

					$this->glo->log("Created the requst. Found following correct nominations in request: ".
					implode(',', $nom));
					$this->glo->log("SQL: " . $sql . implode(',' , $values));

					$success = $this->modx->query($sql . implode(',', $values));
				}
			}
				
			if ($success) {
				$this->glo->log("Successfuly created all nomination links. Preparng file routines");
			} else {
				// roll-back of transaction... kind of
				$this->modx->query("DELETE FROM {$tp}user_formit_request WHERE formit_hash = {$this->currnet_hash}");
			}

			return $success ? $request_id : 0;
		}


		public function file_exists_on_disk($fname) {
			$exists = false;
			$tmp_name = $fname;
			$hash_chunk = "";

			if (strpos($fname, "s_") === 0) {
				$tmp_name = substr($fname, 2);
			}
			
			if ($this->previous_hash) {
				$hash_chunk = strtolower(substr($this->previous_hash, 0, 6));
				$exists_on_disk_sign = $this->me->get("id") . $hash_chunk;
				$exists = strpos($tmp_name, $exists_on_disk_sign) === 0;
			}

			$this->glo->log("Checking existence of disk: " . print_r(array(
				'name' => $fname,
				's_ found' => strpos($fname, "s_") === 0,
				'sign' => $exists_on_disk_sign,
				'exists on disk' => strpos($tmp_name, $exists_on_disk_sign)
			), true));

			return $exists;
		}


		public function prepare_uploading($request_id) {
			$dir_created = false;

			if (!$this->current_hash) {
				return;
			}
			
//			$this->glo->log("Got form object: " . print_r($this->my_form, true));

			if ($this->yah->create_yadirectory("/" . $this->current_hash)) {
				if ($this->yah->create_yadirectory("/" . $this->current_hash . "/gallery")) {
					$dir_created = true;
				}
			}
	
			if (!$dir_created) {
				$this->glo->log("Creating directory " . $this->current_hash . " caused error");
			}

//			$this->glo->log("Extracted gallery list from form data: " . is_array($this->my_form['pic']) ? implode("-", $this->my_form["pic"]) : $this->my_form["pic"]);
		}

		
		public function upload_single_userfile($type, $request_id) {
			$fu = $this->glo->fileuploader;
			$input_p = "{$fu->config["buffer"]}/";
			$output_p = "/{$this->current_hash}/";
			$my_form = $this->get_formdata_by_hash($this->current_hash);

			if ($my_form[$type] == "") {
				$this->glo->log("Not found " . $type . " in form, aborting loading");
				return false;
			}

			$item = $my_form[$type];
			$new_name = $fu->generate_name($type, $item, false);

			if ($this->file_exists_on_disk($item)) {
				$this->glo->log("trying to move from older request " . $item . " -> " . $new_name);
				$this->yah->move_from_older_request($item, $new_name, false);
			} else {
				$this->glo->log("trying to add and publish " . $item . " -> " . $new_name);
				$this->yah->load_apilevel($item, $new_name, $input_p, $output_p);
			}

			$this->form->rename_form_value($type, $new_name);
			$this->yah->publish($type, $output_p . $new_name, $request_id);
		}

	
		public function upload_collection($request_id) {
			$file_group = array();
			$fu = $this->glo->fileuploader;
			$input_p = "{$fu->config["buffer"]}/gallery/";
			$output_p = "/{$this->current_hash}/gallery/";
			$my_form = $this->get_formdata_by_hash($this->current_hash);

			if (!empty($my_form["pic"])) {
				$counter = 0;
				$file_group = $my_form["pic"];
				$gallery_new_fnames = array();
			}

			foreach ($file_group as $item) {
				$new_name = $fu->generate_name("gallery", $item, $counter);
				$gallery_new_fnames[] = $new_name;
				
				if ($this->file_exists_on_disk($item)) {
					$this->glo->log("trying to move from older request " . $item . " -> " . $new_name);
					$this->yah->move_from_older_request($item, $new_name, true);
				} else {
					$this->glo->log("trying to add and publish " . $item . " -> " . $new_name);
					$this->yah->load_apilevel($item, $new_name, $input_p, $output_p);
				}

				$counter++;
			}

			$this->form->rename_form_value("pic", $gallery_new_fnames);
			$this->yah->publish("gallery", $output_p, $request_id);
		}


		public function upload_userfiles($type, $request_id) {
			if ($type == "gallery") {
				$this->upload_collection($request_id);
			} else {
				$this->upload_single_userfile($type, $request_id);
			}

		}

/*
		public function upload_collection($type, $request_id) {
			$file_group = array();
			$fu = $this->glo->fileuploader;
			$input_p = "{$fu->config["buffer"]}/";
			$output_p = "/{$this->current_hash}/";
			$is_gallery = $type == "gallery";
			$my_form = $this->get_formdata_by_hash($this->current_hash);

			if (!$is_gallery && $my_form[$type] == "") {
				$this->glo->log("Not found " . $type . " in form, aborting loading");
				return false;
			}

			if ($is_gallery && !empty($my_form["pic"])) {
				$input_p .= "gallery/";
				$output_p .= "gallery/";
				$new_name = "";
				$counter = 0;
				$file_group = $my_form["pic"];
				$gallery_new_fnames = array();
			} else {
				$file_group[$type] = $my_form[$type];
			}

			foreach ($file_group as $t => $item) {
				$type = $is_gallery ? "gallery" : $t;
				$new_name = $fu->generate_name($type, $item, ($is_gallery ? $counter : false));
	
				if ($is_gallery) {
					$gallery_new_fnames[] = $new_name;	
				} else {
					$this->form->rename_form_value($t, $new_name);
				}
				
				if ($this->file_exists_on_disk($item)) {
					$this->glo->log("trying to move from older request " . $item . " -> " . $new_name);
					$this->yah->move_from_older_request($item, $new_name, ($is_gallery ? true : false));
				} else {
					$this->glo->log("trying to add and publish " . $item . " -> " . $new_name);
					$this->yah->load_apilevel($item, $new_name, $input_p, $output_p);
				}

				// Not publish separate files from gallery, we need only whole gallery public link
				if (!$is_gallery) {
					$this->yah->publish($type, $output_p . $new_name, $request_id);
				}

				if ($is_gallery) {
					$this->form->rename_form_value("pic", $gallery_new_fnames);
				}

				$counter++;
			}

			if ($is_gallery) {
				$this->yah->publish("gallery", $output_p, $request_id);
			}
		}
*/


		function sync_yadisk_directories() {
			$disk_requests = $db_requests = $for_delete_hashes = array();

			$tp = $this->glo->table_prefix;
			$sql = "SELECT * FROM {$tp}user_formit_request";
			$result = $this->modx->query($sql);

			while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
				$db_requests[] = $row["formit_hash"];
			}

			$disk_requests = $this->yah->get_directory("/");

			foreach ($disk_requests as $hash => $file) {
				if ($file["type"] == "dir" && strlen($hash) > 25 && !in_array($hash, $db_requests)) {
					if ($this->yah->remove_directory("/" . $file["name"])) {
						$deleted[] = $file["name"];
					}
				}
			}

			if (!empty($deleted)) {
				$this->glo->log("Successufly deleted " . count($deleted) . " files; these are: " . print_r($deleted, true));
			}
		}


		function delete_previous_requests() {
			if (!$this->previous_hash) {
				return;	
			}

			$this->sync_yadisk_directories();

			$deleted = $for_delete_ids = array();
			$tp = $this->glo->table_prefix;
			$sql = "SELECT * FROM {$tp}user_formit_request WHERE user_id = " . $this->me->get("id") . " ORDER BY id DESC LIMIT 3, 400";
			$result = $this->modx->query($sql);
			
			while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
				$for_delete_ids[] = $row["id"];
				if ($this->yah->remove_directory("/" . $row["formit_hash"])) {
					$deleted[] = $row["formit_hash"];
				}
			}

			if (!empty($deleted)) {
				$this->glo->log("Successfuly deleted " . count($deleted) . " files; these are: " . print_r($deleted, true));
			}

			// final db clean
			if (!empty($for_delete_ids)) {
				$sql = "DELETE FROM {$tp}user_formit_request WHERE id IN (" . implode(",", $for_delete_ids) . ")";
				$this->modx->query($sql);
			}
		}
	}
