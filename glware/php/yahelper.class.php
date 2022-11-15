<?php
	class glwYaHelper {
	
		private $god_object;
		private $modx;
		private $token;
		private $previous_hash;
		private $yalinks;

		public $hash;
		public $headers;
	
		public function __construct($glware) {
			$this->god_object = $glware;
			$this->modx = $glware->modx;
			$this->previous_hash = false;
			
			$setting = $this->modx->getObject("modSystemSetting", "glware_yadisk_token");
			$this->token = $setting->get("value");
			$this->secret = $secret = $this->modx->getOption("glware_yadisk_api_secret", null, '');
			$this->app_id = $this->modx->getOption("glware_yadisk_client_id", null, '');
			$this->client_id = $this->modx->getOption("glware_yadisk_client_id", null, '');

			$this->client = $this->modx->getService('rest', 'rest.modRest');
			$this->standart_headers = array(
				"Authorization" => "OAuth " . $this->token, "Content-Type" => "application/json"
			);
			$this->post_headers = array(
				"Authorization" => "OAuth " . $this->token, "Content-Type" => "application/x-www-form-urlencoded"
			);
			$this->yalinks = array(
				"check_t" => "https://cloud-api.yandex.net:443/v1/disk",
				"receive_t" => "https://oauth.yandex.ru/token",
				"resources" => "https://cloud-api.yandex.net/v1/disk/resources",
				"pubresources" => "https://cloud-api.yandex.net/v1/disk/public/resources",
				"resources_flat" => "https://cloud-api.yandex.net/v1/disk/resources/files",
				"upload" => "https://cloud-api.yandex.net/v1/disk/resources/upload",
				"download" => "https://cloud-api.yandex.net/v1/disk/resources/download",
				"pubdownload" => "https://cloud-api.yandex.net/v1/disk/public/resources/download",
				"publish" => "https://cloud-api.yandex.net/v1/disk/resources/publish?path=",
				"move" => "https://cloud-api.yandex.net/v1/disk/resources/move"
			);

		}
		
		private function _get_request($yalink_name, $parameters = array(), $need_response = true) {
			$response = true;
			$result = false;

			$href = isset($this->yalinks[$yalink_name]) ? $this->yalinks[$yalink_name] : $yalink_name;
			$result = $this->client->get($href, $parameters, $this->standart_headers);

			if (property_exists($result->responseInfo, 'scalar') && $result->responseInfo->scalar == '200') {
				if ($need_response) {
					$response = $result->process();
				}

				$result = true;
			} else {
				$this->god_object->log("Failed while sending simple get request. Parameters is " . print_r(array(
					"yalink" => $href,
					$yalink_name . " in yalinks:" => in_array($yalink_name, $this->yalinks),
					"parameters" => $parameters
				), true) . " Error code is: " . $result->responseInfo->scalar);
			}

			return $need_response ? $response : $result;
		}


		public function remove_directory($path) {
			$success = false;
			$acceptable_codes = array("202", "204");
			$result = $this->client->delete($this->yalinks["resources"] . "?path={$path}&permanently=true}", array(), $this->standart_headers);

			if (property_exists($result->responseInfo, 'scalar') && in_array($result->responseInfo->scalar, $acceptable_codes)) {
				$success = true;
			} else {
				$this->god_object->log("Failed when trying to delete resource with path " . $path . ": error code is: " . print_r($result->response, true) . " other information is: " . print_r(array(
					"yalink" => $this->yalinks["resources"],
					"path" => $path
				), true));
			}

			return $success;
		}


		public function check_token() {
			$token_request = array();
			$token_valid = false;

			if ($this->token) {
				$result = $this->_get_request("check_t");
			//	$this->god_object->log("EXISTING OAUTH TOKEN CHECK: " . print_r(array('result' => $result->process(), 'token' => $token), true));
				$token_valid = isset($result['max_file_size']) && isset($result['used_space']);
			}
			
			if (!$token_valid) {
				$state_token = 'glware_token_request:';
				$token_request = array(
					'id' => $this->app_id,
					'secret' => $this->secret,
					'state_token' => md5($state_token . $this->god_object->current_user['sessionid'])
				); //компоненты для построения ссылки, взятые из settings или пустой массив
			}

			return $token_request;
		}


		public function receive_token() {
			$result = array();
			$modx = $this->god_object->modx;
			$request_properties = array(
				"client_id" => $this->app_id,
				"client_secret" => $this->secret,
				"grant_type" => "authorization_code",
				"code" => $_GET["code"]
			);

			$hash = md5('glware_token_request:' . $this->god_object->current_user['sessionid']);

			if ($_GET['state'] == $hash) {
				$response = $this->client->post($this->yalinks["receive_t"], $request_properties, array('headers' => array("Content-Type" => "application/x-www-form-urlencoded")));
				$result = $response->process();

				if ($result['token_type'] == 'bearer' && $result['access_token'] != '') {
					$token = $this->god_object->modx->getObject('modSystemSetting', 'glware_yadisk_token');
					$token->set('value', $result['access_token']);	
					$token->save();
					$result = array('redirect' => true, 'url' => $modx->makeUrl(197, '', '', 'http'));
				}
			}
			
			return $result;
		}



		public function load_apilevel($oldname, $newname, $input_path, $output_path) {
			$loaded = false;
			$realname = $this->remove_accent($oldname);

			if ($newname == '') {
				return;
			}

			$response = $this->_get_request("upload", array('path' => $output_path . $newname));
					
			if ($response['href'] != '') {
				$ch = curl_init();
				$fh = fopen($input_path . $realname, 'r');
				$size = filesize($input_path . $realname);
				curl_setopt_array($ch, array(
					CURLOPT_URL => $response['href'],
					CURLOPT_RETURNTRANSFER => true,
				//	CURLOPT_POST => true,
					CURLOPT_PUT => true,
					CURLOPT_INFILE => $fh,
					CURLOPT_INFILESIZE => $size,
				//	CURLOPT_POSTFIELDS => array('file' => '@' . $input_path . $filename),
					CURLOPT_HEADER => true
				));

				$second_response = curl_exec($ch);
				$status = curl_getinfo($ch);

				if ($status['http_code'] = '201' || $status['http_code'] == 201) {
					$success = true;
					$this->god_object->log("trying to load " . $oldname . "; status is " . print_r(array(
						"status" => "ok",
						"output" => $output_path . $newname,
						"input" => $input_path . $realname
					), true));

					if (file_exists($input_path . $realname)) {
						unlink($input_path . $realname);
					}

					if (file_exists($input_path . "s_" . $realname)) {
						unlink($input_path . "s_" . $realname);
					}
				} else {
					$this->god_object->log("trying to load " . $oldname . "; status is " . $status["http_code"]);
				}

				$debug = array(
					'status' => $status,	
					'file_data' => array(
						'is_file' => file_exists($input_path . $realname),
						'path' => $input_path . $realname,
						'contents' => fread($fh, $size),
					),
					'error' => curl_error($ch),
					'resp' => $second_response
				);
				//$this->god_object->log("Unfortunately file loading ended with error. Try again with another options :( Error: " . print_r($debug, true));

				if (!isset($errors[$realname])) {
					$errors[$realname] = 'ERROR';
				}

				curl_close($ch);
			}
	
			return $success;
		}


		public function publish($resource_type, $ya_path, $request_id) {
			$res = false;
			$request_id = intval($request_id);
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => $this->yalinks["publish"] . urlencode($ya_path),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_PUT => true,
				CURLOPT_HTTPHEADER => array(
					"Authorization: OAuth " . $this->token
				)
			));

			$response = curl_exec($ch);
			$status = curl_getinfo($ch);

//			$this->god_object->log("FILE FOUND; " . $response);
			if ($status['http_code'] == '200' || $status['http_code'] == 200) {
				try {
					$response = json_decode($response);
				} catch (Error $e) {
					$this->god_object->log("Error has occured while building link to file");
				}

				if ($response->href != '') {
					$response = $this->_get_request($response->href);

					$link = $response['public_url'];
					//$this->god_object->log("FILE FOUND; " . print_r($response, true));

					$tp = $this->god_object->table_prefix;
					$sql = "INSERT INTO {$tp}yalinks_cache (request_id, resource_type, cached_link) VALUES
					('{$request_id}', '{$resource_type}', '{$link}')";
					$res = $this->god_object->modx->query($sql);

					/*$this->god_object->log("Result of link caching (". $resource_type . ")" . print_r(array(
						'sql' => $sql,
						'result' => $res	
					), true));*/
				}

			} else {
				$this->god_object->log("FILE NOT FOUND on address: " . $ya_path . "; and type is " . $resource_type);
			}
		}

	
		public function create_yadirectory($path) {
			$href = "https://cloud-api.yandex.net/v1/disk/resources?path=" . urlencode($path);
			$ch = curl_init();

			curl_setopt_array($ch, array(
				CURLOPT_URL => $href,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => false,
				CURLOPT_HTTPHEADER => array(
					"Authorization: OAuth " . $this->token
				),
				CURLOPT_PUT => true 
			));

			$result = true;
			$second_response = curl_exec($ch);
			$status = curl_getinfo($ch);

			if ($status['http_code'] != 201 && $status['http_code'] != '201') {
				$debug = array(
					'status' => $status,	
					'error' => curl_error($ch),
					'resp' => $second_response,
				);
	
				$result = false;
			}

			$this->god_object->log("Creating directory ({$path}); reponse: " . print_r($debug, true));

			curl_close($ch);
			return $result;
		}


		public function remove_accent($filename) {
			$new_filename = "";

			if (strstr($filename, "^") !== FALSE) {
				$new_filename = str_replace("^", ".", $filename);
			}

			return $new_filename;
		}



		public function get_directory($path) {
			$files = array();
			$response = $this->_get_request("resources", array("path" => $path, "limit" => 400, "sort" => "-created"));
			if (isset($response["_embedded"]) && isset($response["_embedded"]["items"])) {
				foreach ($response["_embedded"]["items"] as $item) {
					if (isset($item["type"]) && isset($item["name"])) {
						$files[$item["name"]] = $item;
					}
				}
			}

			return $files;
		}


		public function get_previews_list($hash, $dir) {
			$preview_collection = array();
			$not_images = array();
			$current_path = "/" . $hash . ($dir ? "/" . $dir : "");

			// get previews
			//$this->god_object->log("got path for preview of file: " . $current_path); 
			$response = $this->_get_request("resources", array("path" => $current_path));
			if (isset($response["_embedded"]) && isset($response["_embedded"]["items"])) {
				foreach ($response["_embedded"]["items"] as $item) {
//					$this->god_object->log("current not image is: " . print_r($item, true));
/*					$this->god_object->log("current not image is: " . print_r(array(
						'name' => $item["name"],
						'preview_exists' => isset($item["preview"]),
						'public' => isset($item["public_key"]),
						'media_type is image' => $item["media_type"] == "image"
					), true));*/
					if (isset($item["preview"]) && $item["media_type"] == "image") {
						$preview_collection["s_" . $item["name"]] = $item["preview"];
					} else {
						$not_images[] = $item["name"];
					}
				}
			}

			foreach ($not_images as $name) {
				$response = $this->_get_request("download", array("path" => $current_path . "/" . $name));
				if (isset($response["href"])) {
					$preview_collection[$name] = $response["href"];
				}
			}

			return $preview_collection;
		}


		public function download($file_path, $output) {
			//$result = $this->client->get($this->yalinks["download"], array("path" => $file_path), $this->standart_headers);
			//if (property_exists($result->responseinfo, "scalar") && $result->responseinfo->scalar == "200") {
			//	$response = $result->process();
				
			//	if (isset($response["href"])) {
			$ch = curl_init();

			curl_setopt_array($ch, array(
				CURLOPT_HTTPHEADER => array(
					"Authorization: OAuth " . $this->token
				),
				CURLOPT_URL => $file_path, 
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 20,
				CURLOPT_FILE => $output
			));

			$second_response = curl_exec($ch);
			$status = curl_getinfo($ch);
			curl_close($ch);
	
			fclose($output);

/*
			$this->god_object->log("Status of operation: " . print_r(
				array(
					"code" => $status["http_code"],
					"downloaded" => $status["size_download"]
				), true));
			//	}
			//}*/
		}


		public function move_from_older_request($filename, $new_name, $is_gallery) {
			$parts = explode("^", $filename);
			$ext = array_pop($parts);
			$old_name = implode("^", $parts) . "." . strtolower($ext);
			$prev_hash = $this->god_object->requestprocessor->get_previous_request_hash();

			$basic_old_path = "/" . $prev_hash . "/";
			$basic_old_path .= $is_gallery ? "gallery/" . $old_name : $old_name;
			$old_path = $basic_old_path;

			$basic_new_path = "/" . $this->god_object->params["hash"] . "/";
			$basic_new_path .= $is_gallery ? "gallery/" . $new_name : $new_name;
			$new_path = $basic_new_path;

			$response = $this->client->post($this->yalinks["move"] . "?from={$old_path}&path={$new_path}", array(), $this->post_headers);
			//$this->god_object->log("trying to move " . $old_path . "; status is " . $response->response);
		}
	}
