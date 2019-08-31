<?php

require_once 'chooser/lib/oc_remote_view.php';
require_once 'notes/lib/libnotes.php';

class OC_Connector_Sabre_Notes_Directory extends OC_Connector_Sabre_Directory
	implements \Sabre\DAV\ICollection, \Sabre\DAV\IQuota {
	
		private $noteBooks = [];
		private $tags = [];
		private $fileTags = [];
		
		public function __construct($path='/') {
			//$this->path = rtrim(\OCA\Notes\Lib::$NOTES_DIR, '/').'/'.ltrim($path, '/');
			$this->path = $path;
			//$user = \OC_User::getUser();
			//$this->info = new \OC\Files\FileInfo('/', -1, '/', array('mtime'=>0));
			//$storage = \OC\Files\Filesystem::getStorage('/'.$user.'/');
			//$this->info = \OC\Files\Filesystem::getFileInfo(\OCA\Notes\Lib::$NOTES_DIR);
			$this->info = \OC\Files\Filesystem::getFileInfo($path);
			/*$this->info = new \OC\Files\FileInfo('/', $storage, '/',
					array('fileid'=>-1, 'mimetype'=>'httpd/unix-directory', 'mtime'=>0, 'storage'=>$storage,
							'permissions'=>\OCP\PERMISSION_READ));*/
			$user = \OC_User::getUser();
			\OC_Util::setupFS($user);
			\OC\Files\Filesystem::initMountPoints($user);
			$this->fileView = new \OC\Files\View('/' . $user . '/files');
		}
			
	/**
	 * Creates a new file in the directory
	 *
	 */
		// Not needed as it is only called when a new file is created.
		// When the file already exists, OC_Connector_Sabre_File::put() is called.
	/*public function createFile($name, $data = null) {
		$fileContent = stream_get_contents($data);
		// Check for changed metadata
		$joplinMeta = \OCA\Notes\Lib::parseJoplinFileMeta($fileContent);
		$joplinMeta['path'] = $this->path.$name;
		$joplinMeta['content'] = \OCA\Notes\Lib::getFileContent($fileContent);
		
		\OCP\Util::writeLog('Notes', 'Creating file '.$name.':'.$this->path.':'.
				$fileContent, \OCP\Util::WARN);
		
		return \OC\Files\Filesystem::file_put_contents($joplinMeta['path'], $joplinMeta['content']);
	}*/

	/**
	 * Creates a new subdirectory
	 *
	 * @param string $name
	 * @throws \Sabre\DAV\Exception\Forbidden
	 * @return void
	 */
	/*public function createDirectory($name) {
		throw new \Sabre\DAV\Exception\Forbidden('Cannot write to virtual directory!');
	}*/

	/**
	 * Returns a specific child node, referenced by its name
	 *
	 * @param string $name
	 * @param \OCP\Files\FileInfo $info
	 * @throws \Sabre\DAV\Exception\FileNotFound
	 * @return \Sabre\DAV\INode
	 */
	public function getChild($name, $info=null) {
		if(empty($info)){
			return parent::getChild($name, $info);
		}
		if(is_null($info) || !$info){
			throw new \Sabre\DAV\Exception\NotFound('Need more info!');
		}
		if ($info['mimetype'] == 'httpd/unix-directory') {
			$this->fileView = new OC_Remote_View($info['path']);
			\OC_Log::write('notes','Returning OC_Connector_Sabre_Directory '.$name.':'.$info['fileid'], \OC_Log::WARN);
			$node = new OC_Connector_Sabre_Directory($this->fileView, $info);
		} else {
			$user = \OC_User::getUser();
			/*\OC_Util::setupFS($user);
			\OC\Files\Filesystem::initMountPoints($user);*/
			$this->fileView = new \OC\Files\View('/' . $user . '/files');
			$storage = \OC\Files\Filesystem::getStorage('/'.$user.'/');
			$path = preg_replace('|^files/|', '', $info['path']);
			if(!empty($info['noteid'])){
				$path = preg_replace('|/[^/]+\.md$|', '/'.$info['noteid'].'.md', $path);
				$info['name'] = '.'.$info['noteid'];
			}
			$fileinfo = new \OC\Files\FileInfo($path, $storage, /*internalPath*/$info['path'], $info);
			\OC_Log::write('notes','Returning OC_Connector_Sabre_File '.$user.':'.$name, \OC_Log::WARN);
			$node = new \OC_Connector_Sabre_File($this->fileView, $fileinfo);
		}
		return $node;
	}

	/**
	 * Returns an array with all the child nodes
	 *
	 * @return \Sabre\DAV\INode[]
	 */
	public function getChildren(){
		// We will only be called on either /, /.resource, /.sync or /.templates
		if(strpos($this->path, rtrim(\OCA\Notes\Lib::$NOTES_DIR, '/'))!==0){
			\OC_Log::write('notes','Will not list outside of '.\OCA\Notes\Lib::$NOTES_DIR.'-->'.$this->path, \OC_Log::WARN);
			return null;
		}
		elseif($this->path!=rtrim(\OCA\Notes\Lib::$NOTES_DIR, '/')){
			\OC_Log::write('notes','Falling through for '.$this->path, \OC_Log::WARN);
			return parent::getChildren();
		}
		require_once 'apps/notes/lib/libnotes.php';
		$user = \OC_User::getUser();
		$nodes = array();
		$notes = \OCA\Notes\Lib::getFileList($this->path, -1 , null, [], '', true);
		$dirs = \OCA\Notes\Lib::getDirList($this->path);
		foreach($dirs as $dir){
			// Ignore .resource etc.
			if(!in_array($dir, \OCA\Notes\Lib::$RESOURCE_DIRECTORIES)){
				\OCP\Util::writeLog('Notes', 'Notebooks now: '.serialize($this->noteBooks), \OCP\Util::INFO);
				$this->noteBooks[$dir] = [];
			}
		}
		\OC_Log::write('notes','Getting children...'.$user.':'.$this->path.'-->'.count($dirs).'-->'.
				count($notes), \OC_Log::WARN);
		foreach($notes as $note){
			// Check note metadata
			\OCA\Notes\Lib::checkJoplinFileMeta($note, $this->noteBooks, $this->tags, $this->fileTags);
			$info = $note['fileinfo'];
			$info['noteid'] = empty($note['notemetadata']['id'])?"":$note['notemetadata']['id'];
			\OC_Log::write('notes','Got info, '.'-->'.serialize($info), \OC_Log::WARN);
			$node = $this->getChild($info['name'], $info);
			$nodes[$info['fileid']] = ['node'=>$node, 'noteid'=>$info['noteid']];
		}
		// Fix up parents and note metadata before serving
		\OCP\Util::writeLog('Notes', 'Notebooks NOW: '.serialize($this->noteBooks), \OCP\Util::INFO);
		$createdNotes = \OCA\Notes\Lib::fixJoplinParents($this->noteBooks);
		$notesToDelete = [];
		foreach($notes as $note){
			$changes = \OCA\Notes\Lib::fixJoplinFileMeta($note, $this->noteBooks, $this->tags, $this->fileTags);
			if(empty($nodes[$note['fileinfo']['fileid']]['noteid'])){
				$noteMeta = \OCA\Notes\Lib::getNoteMeta($note['path']);
				$nodes[$note['fileinfo']['fileid']]['noteid'] = $noteMeta['id'];
				$nodes[$note['fileinfo']['fileid']]['node'] = $this->getChild($note['fileinfo']['name'],
						$note['fileinfo']);
			}
			if(!empty($changes['createdFiles'])){
				foreach($changes['createdFiles'] as $createdFile){
					$node = $this->getChild($createdFile['name'], $createdFile);
					$nodes[$createdFile['fileid']] = ['node'=>$node, 'noteid'=>$createdFile['noteid']];
				}
			}
			if(!empty($changes['deletedNotes'])){
				$notesToDelete = array_merge($notesToDelete, $changes['deletedNotes']);
			}
		}
		foreach($notesToDelete as $toDeleteNote){
			unset($nodes[$toDeleteNote['fileinfo']['fileid']]);
		}
		$allNodes = array_column(array_values($nodes), 'node');
		foreach($dirs as $dir){
			// Ignore other directories than .resource etc.
			if(in_array($dir, \OCA\Notes\Lib::$RESOURCE_DIRECTORIES)){
				\OC_Log::write('notes','Adding DIR, '.$dir, \OC_Log::WARN);
				$info = \OC\Files\Filesystem::getFileInfo($dir);
				//$data = empty($info)?[]:\OCA\Files\Helper::formatFileInfo($info);
				$node = $this->getChild($info['name'], $info);
				$allNodes[] = $node;
			}
		}
		foreach($createdNotes as $noteMeta){
			$fileInfo = \OC\Files\Filesystem::getFileInfo($noteMeta['path']);
			$fileInfo['noteid'] = $noteMeta['id'];
			$createdNode = $this->getChild($fileInfo['name'], $fileInfo);
			$allNodes[] = $createdNode;
		}
		return $allNodes;
	}
	
	public function getNoteBooks(){
		if(empty($this->noteBooks)){
			$this->getChildren();
		}
		return $this->noteBooks;
	}

	/**
	 * Checks if a child exists.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function childExists($name) {

		$user = \OC_User::getUser();
		//\OC_Util::setupFS($user);
		//\OC\Files\Filesystem::initMountPoints($user);
		$this->fileView = new \OC\Files\View('/' . $user . '/files');
		$path = $this->path . '/' . $name;
		$ret = $this->fileView->file_exists($path);
		\OCP\Util::writeLog('core', 'Checking '.$path.':'.$ret, \OCP\Util::WARN);
		return $ret;
	}

	/**
	 * Deletes all files in this directory, and then itself
	 *
	 * @return void
	 * @throws \Sabre\DAV\Exception\Forbidden
	 */
	/*public function delete() {

		throw new \Sabre\DAV\Exception\Forbidden('Cannot delete Noted directory.');

	}*/

	/**
	 * Returns available diskspace information
	 *
	 * @return array
	 */
	/*public function getQuotaInfo() {
			return array(0, 0);
	}*/

	/*public function getProperties($properties) {
		return array();
	}*/

}
