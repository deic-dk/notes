<?php

OCP\App::checkAppEnabled('notes');

require_once('apps/notes/lib/libnotes.php');
require_once('apps/notes/lib/notes_objecttree.php');
require_once('apps/notes/lib/server.php');

if(preg_match("|^".OC::$WEBROOT."/notes/.*|", $_SERVER['REQUEST_URI'])){
	$_SERVER['BASE_URI'] = OC::$WEBROOT."/notes";
}
elseif(preg_match("|^".OC::$WEBROOT."/remote.php/notes/.*|", $_SERVER['REQUEST_URI'])){
	$_SERVER['BASE_URI'] = OC::$WEBROOT."/remote.php/notes";
}

//$user = \OC_User::getUser();
//$_SERVER['BASE_DIR'] = $user.'/files/'.OCA\Notes\Lib::getNotesFolder();

$_SERVER['OBJECT_TREE'] = 'Notes_ObjectTree';
$_SERVER['DAV_SERVER'] = 'OC_Connector_Sabre_Server_notes';

include('chooser/appinfo/remote.php');
