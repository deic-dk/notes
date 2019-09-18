<?php

require_once 'notes/lib/notes_directory.php';

class Notes_ObjectTree extends \OC\Connector\Sabre\ObjectTree {
	
	private $notesDir;
	
	public function init(\Sabre\DAV\ICollection $rootNode, \OC\Files\View $view, \OC\Files\Mount\Manager $mountManager) {
		$this->$notesDir = \OCA\Notes\Lib::getNotesFolder();
		//$this->rootNode = $rootNode;
		$this->rootNode = new \OC_Connector_Sabre_Notes_Directory(rtrim($this->$notesDir, '/'));
		$this->fileView = $view;
		$this->mountManager = $mountManager;
		if(empty($this->auth_user)){
			$this->auth_user = \OC_User::getUser();
			if(!empty($_SERVER['PHP_AUTH_USER'])){
				$this->auth_user = $_SERVER['PHP_AUTH_USER'];
			}
		}
	}
	
	
	// We need .resource, .templates, .sync besides the root dir.
	public function getNodeForPath($path) {
		\OC_Log::write('notes','Getting node for '.$path, \OC_Log::WARN);
	
		$path = trim($path, '/');

		// Is it the root node?
		if(!strlen($path)){
			\OC_Log::write('notes','Got node for '.$path, \OC_Log::WARN);
			return $this->rootNode;
		}
		// Otherwise, if it's not in .resource, .templates, .sync, it's an md file in the root
		// We change the path to a folder matching the notebook parent
		$filepath = rtrim($this->$notesDir, '/').'/'.$path;
		
		if(isset($this->cache[$path])) {
			\OC_Log::write('notes','Got node for '.$path, \OC_Log::WARN);
			return $this->cache[$path];
		}
		
		if(pathinfo($path, PATHINFO_EXTENSION) === 'part'){
			// read from storage
			$absPath = $this->fileView->getAbsolutePath($filepath);
			list($storage, $internalPath) = Filesystem::resolvePath('/' . $absPath);
			if ($storage) {
				$scanner = $storage->getScanner($internalPath);
				// get data directly
				$info = $scanner->getData($internalPath);
			}
		}
		else {
			// read from cache
			$info = $this->fileView->getFileInfo($filepath);
		}

		if (!$info) {
			throw new \Sabre\DAV\Exception\NotFound('File with name ' . $filepath . ' could not be located');
		}
		
		OC_Log::write('chooser','Returning Sabre '.$info->getType().': '.$info->getPath(), OC_Log::INFO);
		
		if ($info->getType() === 'dir') {
			$node = new \OC_Connector_Sabre_Directory($this->fileView, $info);
		} else {
			$node = new \OC_Connector_Sabre_File($this->fileView, $info);
		}
		
		$this->cache[$path] = $node;
		return $node;

	}
	
	public function fixPath(&$path){
	}

}