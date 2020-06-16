<?php
	class glwYaHelper {
	
		private $god_object;
		private $modx;
		private $token;
		private $form;
		public $hash;

		public function __construct($glware) {
			$this->god_object = $glware;
			$this->modx = $glware->modx;

			$setting = $this->modx->getObject("modSystemSetting", "glware_yadisk_token");
			$this->token = $setting->get("value");
			$this->secret = $secret = $this->modx->getOption("glware_yadisk_api_secret", null, '');
			$this->client_id = $this->modx->getOption("glware_yadisk_client_id", null, '');

			$this->client = $this->modx->getService('rest', 'rest.modRest');
		}


		public function check_token() {
			$token_request = array();
			$token_valid = false;

			if ($this->token) {
				$headers = array("Authorization" => "OAuth {$this->token}", "Content-Type" => "application/json");
				$result = $this->client->get('https://cloud-api.yandex.net:443/v1/disk', array(), $headers);
				$result = $result->process();
			//	$this->god_object->log("EXISTING OAUTH TOKEN CHECK: " . print_r(array('result' => $result->process(), 'token' => $token), true));
				$token_valid = isset($result['max_file_size']) && isset($result['used_space']);
			}
			
			if (!$token_valid) {
				$state_token = 'glware_token_request:';
				$token_request = array(
					'id' => $this->id,
					'secret' => $this->secret,
					'state_token' => md5($state_token . $this->god_object->current_user['sessionid'])
				); //компоненты для построения ссылки, взятые из settings или пустой массив
			}

			return $token_request;
		}


		public function receive_token() {
			$result = array();
			$hash = md5('glware_token_request:' . $this->god_object->current_user['sessionid']);
			if ($_GET['state'] == $hash) {
				$modx = $this->god_object->modx;
				$headers = array("Content-type" => "application/x-www-form-urlencoded");
				$response = $this->client->post('https://oauth.yandex.ru/token', array(
					'client_id' => $this->id,
					'client_secret' => $this->secret,
					'grant_type' => 'authorization_code',
					'code' => $_GET['code']
				), array('headers' => $headers));

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
			$headers = array("Authorization" => "OAuth " . $this->token, "Content-Type" => "application/json");
			$realname = str_replace('^', '.', $oldname);

			if ($newname == '') {
				continue;
			}

			$response = $this->client->get('https://cloud-api.yandex.net/v1/disk/resources/upload', array(
				'path' => $output_path . $newname
			), $headers);
//				$this->god_object->log("Start loading " . $realname . " to yadisk:" . $output_path . "; from " . $input_path);

			if (property_exists($response->responseInfo, 'scalar') && $response->responseInfo->scalar == '200') {
				$response = $response->process();
						
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
			}
	
			return $success;
		}


		private function publish($resource_type, $ya_path, $request_id) {
			$res = false;
			$request_id = intval($request_id);
			$headers = array("Authorization" => "OAuth " . $this->token, "Content-Type" => "application/json");
			$publish_href = "https://cloud-api.yandex.net/v1/disk/resources/publish?path=" . urlencode($ya_path);

			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => $publish_href,
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
					$response = $this->client->get($response->href, array(), $headers);

					if (property_exists($response->responseInfo, 'scalar') && $response->responseInfo->scalar == '200') {
						$response = $response->process();	
						$link = $response['public_url'];
						$this->god_object->log("FILE FOUND; " . print_r($response, true));

						$tp = $this->god_object->table_prefix;
						$sql = "INSERT INTO {$tp}yalinks_cache (request_id, resource_type, cached_link) VALUES
						('{$request_id}', '{$resource_type}', '{$link}')";
						$res = $this->god_object->modx->query($sql);

						$this->god_object->log("Result of link caching (". $resource_type . ")" . print_r(array(
							'sql' => $sql,
							'result' => $res	
						), true));
					}
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


		public function upload($request_id) {
			$dir_created = false;
			$hash = $this->god_object->params['hash'];
			
			if ($hash) {
				$this->hash = $hash;
				$this->form = $this->get_request_by_hash($hash);

				if ($this->create_yadirectory("/" . $hash)) {
					if ($this->create_yadirectory("/" . $hash . "/gallery")) {
						$dir_created = true;
					}
				}

				if ($dir_created) {
					$fu = $this->god_object->fileuploader;
					
					if (!empty($this->form->pic)) {
						$input_path = $fu->config['buffer'] . "/gallery/";
						$output_path = "/{$hash}/gallery/";

						foreach ($this->form->pic as $pic) {
							$this->load_apilevel($pic, "gallery" . $counter, $input_path, $output_path);
						}

						$this->publish('gallery', $output_path, $request_id);
					}

					$input_path = $fu->config['buffer'] . "/";
					$output_path = "/{$hash}/";

					if ($this->form->presentation != ''
						&& $this->load_apilevel($this->form->presentation, "presentation", $input_path, $output_path)) {

						$this->publish("presentation", "{$output_path}presentation", $request_id);
					}

					if ($this->form->dir_photo != ''
						&& $this->load_apilevel($this->form->dir_photo, "dir_photo", $input_path, $output_path)) {
						
						$this->publish("dir_photo", "{$output_path}dir_photo", $request_id);
					}

					if ($this->form->logo != ''
						&& $this->load_apilevel($this->form->logo, "logo", $input_path, $output_path)) {
				
						$this->publish("logo", "{$output_path}logo", $request_id);
					}

					if ($this->form->press_release != ''
						&& $this->load_apilevel($this->form->press_release, "press_release", $input_path, $output_path)) {
			
						$this->publish("press_release", "{$output_path}press_release", $request_id);
					}
		
	/*	
					if (!empty($this->form->pic)) {
						$pic = $this->rename_files($this->form->pic, $hash, 'gallery');
						$this->load_apilevel($pic, "{$fu->config['buffer']}/gallery/", "/{$hash}/gallery/");
					}

					$other_files = $this->rename_files(array(
						'presentation' => $this->form->presentation,
						'logo' => $this->form->logo,
						'press_release' => $this->form->press_release,
						'dir_photo' => $this->form->dir_photo
					), $hash, false);

					$this->load_apilevel($other_files, "{$fu->config['buffer']}/", "/{$hash}/");
		*/

				}
			}
		}


		private function get_request_by_hash($hash) {
			$form = array();
			$form = $this->god_object->modx->getObject('FormItForm', array('hash' => $hash));

			if ($form) {
				$form = urldecode($form->get('values'));
				try {
					$form = json_decode($form);
				} catch (Error $e) {
					$form = array();
				}
			}
	
			return $form;
		}
	}
