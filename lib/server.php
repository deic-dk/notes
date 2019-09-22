<?php

require_once 'chooser/lib/server.php';
require_once 'notes/lib/libnotes.php';

class OC_Connector_Sabre_Server_notes extends OC_Connector_Sabre_Server_chooser {
	
	private static function getFilePath($uri){
		if(strpos($uri, '/')===false && substr($uri, -3) == ".md"){
			$id = basename($uri, ".md");
			$dir = dirname("/".trim($uri, "/"));
			$dataDir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
			$notesDir = $dataDir.'/'.trim(\OC\Files\Filesystem::getRoot(), '/').'/'.\OCA\Notes\Lib::getNotesFolder();
			$fullPath = $notesDir.ltrim($dir, '/');
			$check = shell_exec("grep -r '^id: ".$id."$' '".$fullPath.
					"' | sed -E 's|:id: ".$id."$||'");
			if(preg_match('|^'.$notesDir.'|', trim($check))){
				$uri = preg_replace('|^'.$notesDir.'|', '', trim($check));
			}
			else{
				$uri = '';
			}
			\OCP\Util::writeLog('Notes', 'Path of file: '.$uri.':'.$id, \OCP\Util::WARN);
		}
		return $uri;
	}
	
	private static function getTagName($tagId){
		$dataDir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
		$homedir = $dataDir.'/'.trim(\OC\Files\Filesystem::getRoot(), '/');
		$notesDir = $homedir .'/' . \OCA\Notes\Lib::getNotesFolder();
		$check = shell_exec("grep -r '^id: ".$tagId."$' '".$notesDir."' | awk -F : '{print $1}'");
		if(preg_match('|^'.$notesDir.'|', trim($check))){
			$path = preg_replace('|^'.$homedir.'|', '', trim($check));
			$note = \OCA\Notes\Lib::getNoteMeta($path);
			return $note['title'];
		}
		return '';
	}
	
	private static function addDbTag($joplinTagId, $joplinNoteId){
		// First get the tag name
		$fileTagName = self::getTagName($joplinTagId);
		if(empty($fileTagName)){
			\OCP\Util::writeLog('Notes', 'ERROR: could not get tag name of  '.serialize($fileMeta), \OCP\Util::ERROR);
		}
		// Then get the tagged file
		$noteUri = $joplinNoteId.'.md';
		$notePath = self::getFilePath($noteUri);
		$fileInfo = \OC\Files\Filesystem::getFileInfo(\OCA\Notes\Lib::getNotesFolder().$notePath);
		// Then get the db tags of the tagged file
		$filesTags = \OCA\Meta_data\Tags::dbGetFileTags([$fileInfo['fileid']]);
		$filetags = empty($filesTags[$fileInfo['fileid']])?[]:$filesTags[$fileInfo['fileid']];
		$fullTags = \OCA\meta_data\Tags::getTags($filetags);
		$tagNames = array_column($fullTags, 'name');
		
		\OCP\Util::writeLog('Notes', 'Checking tag  '.$fileTagName.'-->'.serialize($tagNames), \OCP\Util::WARN);
		if(!in_array($fileTagName, $tagNames)){
			$userid = \OCP\User::getUser();
			$tagId = \OCA\Meta_data\Tags::getTagID($fileTagName, $userid);
			if(empty($tagId)){
				\OCP\Util::writeLog('Notes', 'ERROR: could not get tag ID of  '.$fileTagName, \OCP\Util::ERROR);
			}
			else{
				\OCA\Meta_data\Tags::updateFileTag($tagId, $userid, $fileInfo['fileid']);
			}
		}
	}
	
	private static function removeDbTag($joplinTagId, $joplinNoteId){
		// First get the tag name
		$fileTagName = self::getTagName($joplinTagId);
		if(empty($fileTagName)){
			\OCP\Util::writeLog('Notes', 'ERROR: could not get tag name of  '.serialize($fileMeta), \OCP\Util::ERROR);
		}
		// Then get the tagged file
		$noteUri = $joplinNoteId.'.md';
		$notePath = self::getFilePath($noteUri);
		$fileInfo = \OC\Files\Filesystem::getFileInfo(\OCA\Notes\Lib::getNotesFolder().$notePath);
		// Then get the db tags of the tagged file
		$filesTags = \OCA\Meta_data\Tags::dbGetFileTags([$fileInfo['fileid']]);
		$filetags = empty($filesTags[$fileInfo['fileid']])?[]:$filesTags[$fileInfo['fileid']];
		$fullTags = \OCA\meta_data\Tags::getTags($filetags);
		$tagNames = array_column($fullTags, 'name');
		
		\OCP\Util::writeLog('Notes', 'Checking tag  '.$fileTagName.'-->'.serialize($tagNames), \OCP\Util::WARN);
		if(!in_array($fileTagName, $tagNames)){
			$userid = \OCP\User::getUser();
			$tagId = \OCA\Meta_data\Tags::getTagID($fileTagName, $userid);
			if(empty($tagId)){
				\OCP\Util::writeLog('Notes', 'ERROR: could not get tag ID of  '.$fileTagName, \OCP\Util::ERROR);
			}
			else{
				\OCA\Meta_data\Tags::removeFileTag($tagId, $fileInfo['fileid']);
			}
		}
	}
	
	protected function httpPropfind($uri) {
		if(substr($uri, -3)=='.md'){
			$uri = self::getFilePath($uri);
			if(empty($uri)){
				header("HTTP/1.1 404 Not Found");
				exit;
			}
		}
		return parent::httpPropfind($uri);
	}
	
	protected function httpGet($uri) {
		$uri = self::getFilePath($uri);
		if(empty($uri)){
			header("HTTP/1.1 404 Not Found");
			exit;
		}
		return parent::httpGet($uri);
	}
	
	protected function httpDelete($uri) {
		$uri = self::getFilePath($uri);
		if(empty($uri)){
			header("HTTP/1.1 404 Not Found");
			exit;
		}
		$ret = null;
		// If this is a notebook md file, remove the containing folder
		if(preg_match('|^\..+\.md$|', basename($uri))){
			$notesDir = \OCA\Notes\Lib::getNotesFolder();
			$fileMeta = \OCA\Notes\Lib::getNoteMeta($notesDir.$uri);
			if($fileMeta['type_']=='2'){
				$dir = dirname($uri);
				if(!empty($dir) && $dir!='/'){
					\OCP\Util::writeLog('Notes', 'Unlinking '.$dir.':'.$uri, \OCP\Util::WARN);
					$ret = \OC\Files\Filesystem::unlink($notesDir.$dir);
				}
			}
			if($fileMeta['type_']=='5'){
				// Ignore - keep tag in DB - it might be used on files outside of Notes
			}
			elseif($fileMeta['type_']=='6'){
				self::removeDbTag($fileMeta['tag_id'], $fileMeta['note_id']);
			}
		}
		if(empty($ret)){
			$ret = parent::httpDelete($uri);
		}
		apc_store(\OCA\Notes\Lib::$cacheDirtyKey, true, (int)\OCA\Notes\Lib::$filesystemCacheTimeout);
		return $ret;
	}
	
	protected function httpPut($uri) {
		
		if(strpos($uri, '/')!==false || substr($uri, -3)!='.md'){
			return parent::httpPut($uri);
		}
		
		$rootNode = $this->tree->getNodeForPath('/');
		$fileContent = stream_get_contents($this->httpRequest->getBody());
		
		if(!$this->checkPreconditions()){
			return;
		}
		
		// Get rid of weird unicode space from Evernote - dont' - changes size and breaks Joplin sync
		//$fileContent = str_replace(' ', ' ', $fileContent);
		
		$fileMeta = \OCA\Notes\Lib::parseJoplinFileMeta($fileContent);
		$path = \OCA\Notes\Lib::getPath($fileMeta, $rootNode);
		$oldFilePath = self::getFilePath($uri);
		
		$notesDir = rtrim(\OCA\Notes\Lib::getNotesFolder(), '/').'/';
		
		\OCP\Util::writeLog('Notes', 'Checking existing file '.$uri.':'.$oldFilePath, \OCP\Util::WARN);
		if(!empty(trim($oldFilePath)) && trim($oldFilePath, '/') != trim($path, '/')){
			$oldFileMeta = \OCA\Notes\Lib::getNoteMeta($notesDir.$oldFilePath);
			if(!empty($oldFileMeta) && 
					(empty($fileMeta['parent_id']) && !empty($oldFileMeta['parent_id']) ||
							!empty($fileMeta['parent_id']) && empty($oldFileMeta['parent_id']) ||
							$fileMeta['parent_id']!=$oldFileMeta['parent_id'] ||
					$fileMeta['title']!=$oldFileMeta['title'])){
						if($fileMeta['type_']=='1'){
					// This is a note that's being moved (to another parent notebook).
					// We move the note.
							$result = OCA\Notes\Lib::rename($notesDir.$oldFilePath,
									$notesDir.$path);
				}
				elseif($fileMeta['type_']=='2'){
					// This is a notebook that's being moved (to another parent notebook).
					// We move the whole containing folder
					$dirPath = dirname($path);
					$oldDirPath = dirname($oldFilePath);
					$result = OCA\Notes\Lib::rename($notesDir.$oldDirPath,
							$notesDir.$dirPath);
				}
				if(empty($result)){
					OCP\JSON::error(array('data'=>array('title'=>'Error', 'message'=>'Could not move note, '.
							$oldFilePath.'-->'.$path
					)));
					exit;
				}
			}
		}
		
		// If creating a new notebook, i.e. putting a notebook md file, create containing folder
		if($fileMeta['type_']=='2'){
			$dir = dirname($path);
			if(!\OC\Files\Filesystem::file_exists($notesDir.$dir)){
				\OCP\Util::writeLog('Notes', 'Creating folder '.$notesDir.$dir.':'.$path.':'.$uri, \OCP\Util::WARN);
				\OC\Files\Filesystem::mkdir($notesDir.$dir);
				// Check for notes with this parent in root (happens when syncing new notebook)
				$datadir = OC_Config::getValue('datadirectory').'/'.\OC\Files\Filesystem::getRoot();
				\OCP\Util::writeLog('Notes', 'Fixing up stray files '.
						rtrim($datadir, '/').'/'.ltrim($notesDir, '/').'*.md', \OCP\Util::WARN);
				$strayFiles = glob($datadir.'/'.$notesDir.'*.md');
				foreach($strayFiles as $file){
					$strayMeta = \OCA\Notes\Lib::parseJoplinFileMeta(file_get_contents($file));
					\OCP\Util::writeLog('Notes', 'Fixing up stray file '.
							$strayMeta['parent_id'].'=='.basename($uri, '.md'), \OCP\Util::WARN);
					if(!empty($strayMeta['parent_id']) && $strayMeta['parent_id']==basename($uri, '.md')){
						$strayName = basename($file);
						$strayMtime = \OC\Files\Filesystem::filemtime($notesDir.$strayName);
						\OC\Files\Filesystem::rename($notesDir.$strayName,
								$notesDir.$dir.'/'.$strayName);
						OC\Files\Filesystem::touch($notesDir.$dir.'/'.$strayName, $strayMtime);
					}
				}
			}
		}
		// If creating a new tag, update db
		elseif($fileMeta['type_']=='5'){
			$userid = \OCP\User::getUser();
			$tagID = \OCA\Meta_data\Tags::getTagID($fileMeta['title'], $userid);
			if(empty($tagID)){
					\OCA\Meta_data\Tags::newTag($fileMeta['title'], $userid, 0, "color-".rand(1, 6), 0);
			}
		}
		// If tagging a file, update db
		elseif($fileMeta['type_']=='6'){
			self::addDbTag($fileMeta['tag_id'], $fileMeta['note_id']); 
		}
		
		if($this->tree->nodeExists($path)){
			\OCP\Util::writeLog('Notes', 'Overwriting existing file '.$uri.':'.$path, \OCP\Util::WARN);
			$node = $this->tree->getNodeForPath($path);
			$fileId = (int) substr($node->getFileId(), 0, 8);
			//$fileResource = $node->get();
			//$fileContent = stream_get_contents($fileResource);
			//fclose($fileResource);
			// If the node is a collection, we'll deny it
			if(!($node instanceof IFile) && !($node instanceof OC_Connector_Sabre_File)){
				throw new Exception('PUT is not allowed on non-files. '.get_class($node));
			}
			if(!$this->broadcastEvent('beforeWriteContent',array($path, $node, &$fileContent))){
				return false;
			}
			$etag = $node->put($fileContent);
			$this->broadcastEvent('afterWriteContent',array($path, $node));
			\OCP\Util::writeLog('Notes', 'Updating METADATA for '.$fileId.':'.$uri.':'.$path.':'.$node->getName(), \OCP\Util::WARN);
			\OCA\Notes\Lib::updateMeta($fileId, $fileMeta, $rootNode);
			apc_store(\OCA\Notes\Lib::$cacheDirtyKey, true, (int)\OCA\Notes\Lib::$filesystemCacheTimeout);
			$this->httpResponse->setHeader('Content-Length','0');
			if($etag){
				$this->httpResponse->setHeader('ETag', $etag);
			}
			$this->httpResponse->sendStatus(204);
		}
		else{
			\OCP\Util::writeLog('Notes', 'Creating new file '.$path, \OCP\Util::WARN);
			$etag = null;
			// If we got here, the resource didn't exist yet.
			if(!$this->createFile($path, $fileContent, $etag)){
				// For one reason or another the file was not created.
				return;
			}
			$node = $this->tree->getNodeForPath($path);
			$fileId = (int) substr($node->getFileId(), 0, 8);
			\OCP\Util::writeLog('Notes', 'Writing METADATA for '.$fileId.':'.$uri.':'.$path.':'.$node->getName(), \OCP\Util::WARN);
			\OCA\Notes\Lib::updateMeta($fileId, $fileMeta, $rootNode);
			apc_store(\OCA\Notes\Lib::$cacheDirtyKey, true, (int)\OCA\Notes\Lib::$filesystemCacheTimeout);
			$this->httpResponse->setHeader('Content-Length','0');
			if($etag){
				$this->httpResponse->setHeader('ETag', $etag);
			}
			$this->httpResponse->sendStatus(201);
		}
	}
	
}
