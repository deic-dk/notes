<?php

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('notes');
OCP\JSON::callCheck();

$action = $_REQUEST['action'];
$name = empty($_REQUEST['name'])?'':$_REQUEST['name'];
$target = empty($_POST['target'])?'':$_POST['target'];
$tags = empty($_POST['tags'])?[]:$_POST['tags'];
$folders = empty($_POST['folders'])?[]:$_POST['folders'];
$template = empty($_POST['template'])?'':$_POST['template'];
$position = empty($_POST['position'])?'':$_POST['position'];

require_once('apps/notes/lib/libnotes.php');


doAction($name, $action, $tags, $template, $target, $folders, $position);

function doAction($name, $action, $tags, $template, $target, $folders, $position){
	$user = \OC_User::getUser();
	$result = [];
	$notesDir = \OCA\Notes\Lib::getNotesFolder();
	switch($action){
		case "setnotesfolder":
			$noteinfo = OCA\Notes\Lib::setNotesFolder($user, $name);
			break;
		case "getresource":
			$dir = $notesDir.'.resource/';
			$datadir = OC_Config::getValue( 'datadirectory' );
			$imageFile = $datadir.'/'.$user.'/files/'.$dir.$name;
			$mime = mime_content_type($imageFile);
			if(substr($mime, 0, 10)=="text/x-tex" || substr($mime, 0, 10)=="text/plain" || $mime=="application/octet-stream"){
				$mime = "image/svg+xml";
			}
			header('Content-Type: '.$mime);
			\OC\Files\Filesystem::readfile($dir.$name);
			exit;
			break;
		case "addnote":
			// Check for existence
			$noteinfo = OCA\Notes\Lib::getNoteMeta($name);
			if(empty($noteinfo)){
				$result = OCA\Notes\Lib::createNote($name, $user, $template, $position);
				if(empty($result)){
					OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Could not create note')));
					exit;
				}
			}
			else{
				OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'A note with the path/name '.
						$name.' already exists: '.serialize($noteinfo))));
				exit;
			}
			break;
		case "addnotebook":
			// Check for existence
			$notebookinfo = OCA\Notes\Lib::getNotebookInfo($name, $user);
			if(empty($notebookinfo)){
				$result = OCA\Notes\Lib::createNotebook($name, $user);
				if(empty($result)){
					OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Could not create notebook')));
					exit;
				}
			}
			else{
				OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'A notebook with the path/name '.
						$name.' already exists: '.serialize($notebookinfo))));
			}
			break;
		case "deletenote":
			$failures = [];
			foreach($name as $note){
				// Check for existence
				$noteinfo = OCA\Notes\Lib::getNoteMeta($note);
				if(empty($noteinfo)){
					OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Note, '.$note.', does not exist')));
					exit();
				}
				else{
					$result = OCA\Notes\Lib::deleteNote($note, $user);
					if(empty($result)){
						$failures[] = $note;
					}
				}
			}
			if(!empty($failures)){
				$failures[] = $note;
				OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Could not delete notes '.implode(':', $failures))));
				exit;
			}
			break;
		case "movenote":
			// Check for existence
			$noteinfo = OCA\Notes\Lib::getNoteInfo($target);
			if(!empty($noteinfo)){
				OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Will not overwrite '.serialize($target))));
				exit;
			}
			else{
				$result = OCA\Notes\Lib::rename($name, $target);
				if(empty($result)){
					OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Could not move note')));
					exit;
				}
				// Delete parent_id of note's metadata.
				// It will be updated on next propfind
				OCA\Notes\Lib::deleteNoteId($target);
			}
			break;
		case "movenotebook":
			// Check for existence
			$notebookinfo = OCA\Notes\Lib::getNotebookInfo($target, $user);
			if(!empty($notebookinfo)){
				OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Will not overwrite '.$target.' --> '.serialize($notebookinfo))));
				exit;
			}
			else{
				$result = OCA\Notes\Lib::rename($name, $target);
				if(empty($result)){
					OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Could not move notebook')));
					exit;
				}
				// Delete parent_id of note's metadata.
				// It will be updated on next propfind
				OCA\Notes\Lib::deleteNotebookId($target);
			}
			break;
		case "deletenotebook":
			// Check for existence
			$notebookinfo = OCA\Notes\Lib::getNotebookInfo($name, $user);
			if(!empty($notebookinfo)){
				$result = OCA\Notes\Lib::deleteNotebook($name, $user);
				if(empty($result)){
					OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Could not delete notebook')));
					exit;
				}
			}
			break;
		case "getnotebookinfo":
			$result = OCA\Notes\Lib::getNotebookInfo($name, $user);
			break;
		case "listnotes":
			if(empty($name)){
				$name = ["/"];
			}
			$result = [];
			foreach($name as $nam){
				try{
					$result = array_merge($result,
							OCA\Notes\Lib::getFileList(ltrim($nam, "/"), -1, '/.', $tags));
				}
				catch(\Exception $e){
					$l = new OC_L10N('notes');
					$msg = $l->t('Please define a notes folder in your preferences.').' '.
							$e->getMessage();
					OCP\JSON::error(array('data'=>array('title'=>'No notes folder defined',
							'message'=>$msg)));
				}
			}
			foreach($result as &$res){
				$res['fileinfo']['fullpath'] = $res['fileinfo']['path'];
				$res['fileinfo']['path'] = substr(trim($res['fileinfo']['fullpath'], "/"), strlen(trim($notesDir, "/"))+1);
			}
			break;
		case "searchnotes":
			$result = [];
			$ret = [];
			if(empty($folders)){
				$folders[] = ltrim($notesDir, "/");
			}
			foreach($folders as $folder){
				$ret = array_merge($ret,
						\OCA\Notes\Lib::getFileList($folder, -1, '/.', $tags, $name));
			}
			foreach($ret as $res){
				if(!empty($tags) && (empty($res['tags']) || empty(array_intersect(array_column($res['tags'], 'id'), $tags)))){
					continue;
				}
				$res['fileinfo']['fullpath'] = $res['fileinfo']['path'];
				$res['fileinfo']['path'] = substr(trim($res['fileinfo']['fullpath'], "/"), strlen(trim($notesDir, "/"))+1);
				$result[] = $res;
			}
			break;
		default:
			//
	}
	OCP\JSON::success(array('data'=>$result));
	
}


