<?php

	// this handler also may be included in snippet or plugin
	$mode = 'full';
	if (!isset($modx) || !($modx instanceof $modx)) {
		define('MODX_API_MODE', true);
		require_once  $_SERVER['DOCUMENT_ROOT'] . "/index.php";

		$modx->getService("error", "error.modError");
		$modx->setLogLevel(modX::LOG_LEVEL_FATAL);
		$modx->setLogTarget(XPDO_CLI_MODE ? "ECHO" : "HTML");
	} else {
		$mode = 'slave';
	}

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
		if ($glw_action == "print_yadisk_directory") {
			echo $response;	
		} else if ($response['redirect'] && $response['url'] != '') {
			$_GET = array();

			header("Location: " . $response['url']);
			exit;
		} else {
			echo json_encode($response);
			//print_r($response);
		}
	}
