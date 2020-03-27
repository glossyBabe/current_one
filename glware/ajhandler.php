<?php

	// this handler also may be included in snippet or plugin
	$mode = 'full';
	if (!isset($modx) || !($modx instanceof $modx)) {
		define('MODX_API_MODE', true);
		require_once  $_SERVER['DOCUMENT_ROOT'] . "/index.php";

		$modx->getService("error", "error.modError");
		$modx->setLogLevel(modX::LOG_LEVEL_INFO);
		$modx->setLogTarget(XPDO_CLI_MODE ? "ECHO" : "HTML");
	} else {
		$mode = 'slave';
	}

	// since code being loaded anytime when onbeforeclientscript fired we need
	// method for preventing full initialization, but we steel need our scripts
	if ($modx->event->name == 'OnBeforeRegisterClientScripts') {
		$r_id = $modx->resource->get('id');

		$js_path = '/third_party/glware/js/';
		$frontend_scripts = array(
			'controller' => $js_path . 'lib.controller.js',
			'server' => $js_path . 'lib.server.js',
			'selectable' => $js_path . 'lib.selectable.js',
			'store' => $js_path . 'lib.imagestore.js',
			'formevents' => $js_path . 'formevents.js',
			'formtest' => $js_path . 'formtester.js'
		);

		$resources_js = array(
			194 => 'controller;server;selectable;store;formevents;formtest'
	//		194 => 'controller;server;selectable;store;formevents'
		);

		if (in_array($r_id, array_keys($resources_js))) {
			$client_scripts = explode(';', $resources_js[$r_id]);
	
			foreach ($client_scripts as $name) {
				$modx->regClientScript($frontend_scripts[$name]);
			}

			$modx->log(modx_log_level_error, "glw: scripts included: " . $resources_js[$r_id]);
		}
	} else {
		$root_path = MODX_BASE_PATH;
		$work_dir = $root_path . 'third_party/glware';
		
		include_once $work_dir . "/php/maincontroller.class.php";

		if (!isset($glw_action)) {
			$REQ = array_merge($_POST, $_GET);
			$glw_action =  array_key_exists('glw_action', $REQ) ? $REQ['glw_action'] : false;
		}

		if ($glw_action) {
			$glw_action = preg_replace('/[^a-z_]*/i', '', strtolower($glw_action));
			
			$ops = array(	
				'work_dir' => $work_dir,
				'action' => $glw_action,
				'mode' => $output_mode,
				'params' => array()
			);
	
			if (!empty($glw_nominations)) {
				$ops['params']['nominations'] = $glw_nominations;
			}

			if (!empty($glw_hash)) {
				$ops['params']['hash'] = $glw_hash;
			}
				
			$controller = new glwGodObject($modx, $ops);

			if (!($casuality = $controller->init())) {
				$response = $controller->handle();
			}
		} else {
			$response = array('success' => false, 'info' => 'unknown request');
		}

		if ($mode == 'slave') {
			$_resp = $response;
		} else {
			echo json_encode($response);
		}
	}
