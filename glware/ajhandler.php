<?php

	// this handler also may be included in snippet or plugin
	if (isset($modx) && $modx instanceof $modx) {
		$output_mode = 'slave';
	}

	// glw - GoldLornetWare action
	$root_path = dirname(dirname(dirname(__FILE__)));
	$work_dir = dirname(__FILE__);
	define('MODX_API_MODE', true);
	require_once $root_path . "/index.php";

	$modx->getService("error", "error.modError");
	$modx->setLogLevel(modX::LOG_LEVEL_INFO);
	$modx->setLogTarget(XPDO_CLI_MODE ? "ECHO" : "HTML");
	
	include_once $work_dir . "/php/maincontroller.class.php";

	if (!isset($glw_action)) {
		$REQ = array_merge($_POST, $_GET);
		$glw_action =  array_key_exists('glw_action', $REQ) ? $REQ['glw_action'] : false;
	}

	if ($glw_action) {
		$glw_action = preg_replace('/[^a-z_]*/i', '', strtolower($glw_action));

		$controller = new glwGodObject($modx, array(
			'work_dir' => $work_dir,
			'action' => $glw_action
		));

		if (!($casuality = $controller->init())) {
			$response = $controller->handle();
		} else {
			$response = array(
				'success' => false,
				'info' => $casuality['message']
			);
		}
	} else {
		$response = array('success' => false, 'info' => 'unknown request');
	}


	if ($output_mode == 'slave') {
		$o = json_encode($response);
	} else {
		echo json_encode($response);
	}
