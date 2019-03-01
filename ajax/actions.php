<?php

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('notes');
OCP\JSON::callCheck();

$name = $_POST['name'];
$action = $_POST['action'];
$user = \OC_User::getUser();

doAction($name, $action);

function doAction($name, $action){
	
	switch($action){
		case "addnotebook":
			// Check for existence
			$notebookinfo = OCA\Notes\Lib::getNotebookInfo($name, $user);
			if(empty($notebookinfo)){
				$result = OCA\Notes\Lib::createNotebook($name, $user);
			}
			break;
			case "getnotebookinfo":
				$notebookinfo = OCA\Notes\Lib::getNotebookInfo($name, $user);
				OCP\JSON::success(array('data' => $notebookinfo));
				break;
			default:
				OCP\JSON::success();
		}
	}

}


