<?php

namespace OCA\Notes;

require_once __DIR__ . '/../../../lib/base.php';
require_once('apps/chooser/appinfo/apache_note_user.php');
require_once('apps/files_picocms/3rdparty/symfony/component/yaml/Parser.php');
require_once('apps/files_picocms/3rdparty/symfony/component/yaml/Inline.php');
require_once('apps/files_picocms/3rdparty/symfony/component/yaml/Yaml.php');
require_once('apps/files_picocms/3rdparty/symfony/component/yaml/Exception/ExceptionInterface.php');
require_once('apps/files_picocms/3rdparty/symfony/component/yaml/Exception/RuntimeException.php');
require_once('apps/files_picocms/3rdparty/symfony/component/yaml/Exception/ParseException.php');

class Lib {
	
	public static $NOTES_DIR = "Notes/";
	public static $TEMPLATES_DIR = "Notes/.templates/";
	public static $RESOURCE_DIRECTORIES = ["Notes/.resource", "Notes/.sync", "Notes/.templates"];
		
	public static function getDirList($dir, $depth=-1, $excludeDir=null){
		if($depth == 0){
			return array();
		}
		$ret = array();
		foreach(\OC\Files\Filesystem::getDirectoryContent($dir) as $i){
			$path = rtrim($dir, '/').'/'.$i['name'];
			if($i['type']=='dir'){
				\OCP\Util::writeLog('Notes', 'Adding dir '.$path.':'.$excludeDir.':'.$i['type'], \OCP\Util::WARN);
				if((empty($i['permissions']) || $i['permissions']&\OCP\PERMISSION_UPDATE!=0) &&
						(empty($excludeDir) || strpos($path, $excludeDir)===false)){
					$ret[] = $path;
				}
				$ret = array_merge($ret, self::getDirList($path, $depth-1, $excludeDir));
			}
		}
		return $ret;
	}
	
	public static function getNotebookInfo($name, $user){
		$dir = $name;
		$info = \OC\Files\Filesystem::getFileInfo($dir);
		$data = empty($info)?[]:\OCA\Files\Helper::formatFileInfo($info);
		return $data;
	}
	
	public static function createNotebook($name, $user){
		$dir = $name;
		$info = \OC\Files\Filesystem::mkdir($dir);
		return $info;
	}
	
	public static function deleteNotebook($name, $user){
		$dir = $name;
		if(!empty($dir) && trim($dir, "/")!=trim(self::$NOTES_DIR, "/")){
			$info = \OC\Files\Filesystem::unlink($dir);
		}
		return $info;
	}
	
	public static function getNoteInfo($name){
		$path = self::$NOTES_DIR.$name;
		return self::getNoteMeta($path);
	}
	
	public static function getNoteMeta($path, $query=''){
		if(empty($path) || substr($path, -3)!='.md' || !\OC\Files\Filesystem::file_exists($path)){
			\OCP\Util::writeLog('Notes', 'No file '.$path, \OCP\Util::WARN);
			return null;
		}
		$fileContent = \OC\Files\Filesystem::file_get_contents($path);
		//\OCP\Util::writeLog('Notes', 'CONTENT: '.$fileContent, \OCP\Util::WARN);
		$filename = basename($path);
		if(!empty($query) &&
				!preg_match('|.*'.$query.'.*|i', $filename) &&
				!preg_match('|.*'.$query.'.*|i', $fileContent)){
					\OCP\Util::writeLog('Notes', 'Skipping note dir '.$filename.' : '.$query, \OCP\Util::WARN);
					return null;
		}
		//$picoMeta = self::parsePicoFileMeta($fileContent);
		$picoMeta = [];
		$joplinMeta = self::parseJoplinFileMeta($fileContent);
		$meta = array_merge($picoMeta, $joplinMeta);
		$meta['path'] = $path;
		$meta['content'] = self::getFileContent($fileContent);
		\OCP\Util::writeLog('Notes', 'Got note '.serialize($meta), \OCP\Util::WARN);
		return $meta;
	}
	
	public static function createNote($name, $user, $template, $position){
		$path = self::$NOTES_DIR.$name;
		$filename = basename($name);
		if(strlen($filename)<3 || strpos($filename, '.')===false || substr($filename, -3)!='.md'){
			$path = $path.'.md';
		}
		$id = md5(uniqid(mt_rand(), true));
		$lines = [$filename];
		$lines[] = '';
		if(!empty($template)){
			$templateString = \OC\Files\Filesystem::file_get_contents($template);
			$templateLines = preg_split("/\r\n|\r|\n/", $templateString);
			array_shift($templateLines);
			$lines = array_merge($lines, $templateLines);
			$lines[] = '';
		}
		$lines[] = "id: ".$id;
		$lines[] = 'latitude: '.$position['coords']['latitude'];
		$lines[] = 'longitude: '.$position['coords']['longitude'];
		$lines[] = 'altitude: '.$position['coords']['altitude'];
		$place = self::getPlace($position);
		if(!empty($template)){
			// Parse tags in template file
			$tags = self::parseTags($lines);
			// Parse special variables if present in template: %date%, %me%
			$lines = self::replaceSpecialVars($lines, $user, $place);
		}
		\OC\Files\Filesystem::file_put_contents($path, implode("\n", $lines));
		$info = \OC\Files\Filesystem::getFileInfo($path);
		\OCP\Util::writeLog('Notes', 'Tagging '.$info['fileid'].' with '.serialize($tags), \OCP\Util::WARN);
		if(!empty($template) && !empty($info['fileid'])){
			// Tag note in DB if template has tags
			foreach($tags as $tag){
				$tagid = \OCA\meta_data\Tags::getTagID($tag, $user);
				\OCA\meta_data\Tags::updateFileTag($tagid, $user, $info['fileid']);
			}
			// Insert place in note db metadata if template metadata has tag with key 'place'
			self::setPlace($tags, $info['fileid'], $user, $place);
		}
		return $info;
	}
	
	private static function getPlace($position){
		// http://www.geonames.org/export/web-services.html#findNearbyPlaceName
		// http://api.geonames.org/findNearbyPlaceNameJSON?lat=47.3&lng=9&username=sciencedata
		//{"geonames":[{"adminCode1":"SG","lng":"9.01488","distance":"1.1379","geonameId":7910950,
		//"toponymName":"Chrüzegg","countryId":"2658434","fcl":"P","population":0,"countryCode":"CH",
		//"name":"Chrüzegg","fclName":"city, village,...","adminCodes1":{"ISO3166_2":"SG"},
		//"countryName":"Switzerland","fcodeName":"section of populated place","adminName1":"Saint Gallen",
		//"lat":"47.2985","fcode":"PPLX"}]}
		
		$url = "http://api.geonames.org/findNearbyPlaceNameJSON?username=sciencedata&".
						"lat=".$position['coords']['latitude']."&lng=".$position['coords']['longitude'];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$ret = curl_exec($ch);
		curl_close($ch);
		$res = json_decode($ret, true);
		return $res['geonames'][0]['name'];
	}
	
	private static function setPlace($tags, $fileid, $user, $place){
		foreach($tags as $tag){
			$tagid = \OCA\meta_data\Tags::getTagID($tag, $user);
			$keyid = \OCA\meta_data\Tags::getKeyID($tagid, 'place', $user);
			if(!empty($keyid)){
				\OCA\meta_data\Tags::updateFileKeyVal($fileid, $tagid, $keyid, $place);
			}
		}
	}
	
	private static function parseTags(&$lines){
		foreach($lines as $index=>$line){
			if(preg_match('/^tags: (.*)$/', $line, $matches)){
				$tags = array_map('trim', explode(',', $matches[1]));
				unset($lines[$index]);
				return $tags;
			}
		}
		return [];
	}
	
	private static function replaceSpecialVars($lines, $user, $place){
		$date = \OC_Util::formatDate(time());
		$displayName = \OCP\User::getDisplayName($user);
		$place = empty($place)?'':$place;
		$vars = ['%date%'=>$date, '%myname%'=>$displayName, '%place%'=>$place];
		foreach($lines as &$line){
			foreach($vars as $key=>$value){
				$line = str_replace($key, $value, $line);
			}
		}
		return $lines;
	}
	
	public static function deleteNote($name, $user){
		$path = $name;
		\OCP\Util::writeLog('Notes', 'Deleting note '.$path, \OCP\Util::WARN);
		if(!empty($name)){
			$info = \OC\Files\Filesystem::unlink($path);
		}
		return $info;
	}
	
	public static function rename($name, $target){
		$path = $name;
		$targetPath = $target;
		\OCP\Util::writeLog('Notes', 'Moving '.$path.' into '.$targetPath, \OCP\Util::WARN);
		$info = \OC\Files\Filesystem::rename($path, $targetPath);
		return $info;
	}
	
	public static function getFileList($dir, $depth=-1, $excludeDir=null, $tags=[], $query='',
			$includeResources=false){
		if($depth == 0){
			return array();
		}
		$dirContent = \OC\Files\Filesystem::getDirectoryContent($dir);
		$taggedFilesData = [];
		if(!empty($tags)){
			foreach($tags as $tag){
				$tagFiles = [];
				$taggedFiles = \OCA\meta_data\Tags::dbGetTaggedFiles($tag, \OCP\User::getUser());
				foreach($taggedFiles as $fileInfo){
					$tagFiles[] = $fileInfo;
				}
				$taggedFilesData[$tag] = $tagFiles;
			}
			$dirContent = array_filter($dirContent, function($var) use ($taggedFilesData){
				$ok = true;
				$dirPath = $var->getData()['path'];
				foreach($taggedFilesData as $tag=>$tagFiles){
					$taggedPaths = array_column($tagFiles, 'internalPath');
					$match = false;
					foreach($taggedPaths as $tagPath){
						$len = strlen($dirPath);
						if($len<=strlen($tagPath) && substr($tagPath, 0, $len)===$dirPath){
							$match = true;
							break;
						}
					}
					$ok = $ok && $match;
					if(!$ok){
						break;
					}
				}
				\OCP\Util::writeLog('Notes', 'Checked '.$dirPath.'-->'.serialize($taggedPaths).'-->'.$ok, \OCP\Util::WARN);
				return $ok;
			});
		}
		$ret = array();
		// If not requesting specific tags, get all tags of requested files
		if(empty($tags)){
			$files = array_filter($dirContent, function($file){
				\OCP\Util::writeLog('Notes', 'FILTERING '.$file['type'].'-->'.serialize($file->getData()), \OCP\Util::INFO);
				return $file['type']=='file';
			});
				$fileIds = array_column(array_map(function($arg){return $arg->getData();},$files), 'fileid');
			$filesTags = \OCA\meta_data\Tags::dbGetFileTags($fileIds);
		}
		foreach($dirContent as $i){
			$path = rtrim($dir, '/').'/'.$i['name'];
			if($i['type']=='dir'){
				// Ignore .resource, .sync and .templates
				if(!$includeResources && in_array($path, self::$RESOURCE_DIRECTORIES)){
					continue;
				}
				\OCP\Util::writeLog('Notes', 'Listing dir '.$path.':'.$excludeDir, \OCP\Util::WARN);
				if(!empty($i['permissions']) && $i['permissions']&\OCP\PERMISSION_UPDATE!=0 &&
						(empty($excludeDir)||strpos($path, $excludeDir)===false)){
					$ret = array_merge($ret, self::getFileList($path, $depth-1, $excludeDir, $tags, $query));
				}
			}
			else{
				\OCP\Util::writeLog('Notes', 'Listing file '.$path, \OCP\Util::WARN);
				if(substr($path, -3)!='.md' || !empty($excludeDir) && strpos($path, $excludeDir)!==false){
					continue;
				}
				$noteMeta = self::getNoteMeta($path, $query);
				if($noteMeta===null){
					\OCP\Util::writeLog('Notes', 'No meta for '.$path, \OCP\Util::WARN);
					continue;
				}
				$dbMeta = [];
				$dbKeys = [];
				// If requesting specific tags, no need to worry about other tags
				if(!empty($tags)){
					$fullTags = \OCA\meta_data\Tags::getTags($tags);
				}
				else{
					$filetags = empty($filesTags[$i['fileid']])?[]:$filesTags[$i['fileid']];
					$fullTags = \OCA\meta_data\Tags::getTags($filetags);
					\OCP\Util::writeLog('Notes', 'Got TAGS for : '.$i['fileid'].'-->'.serialize($fullTags), \OCP\Util::WARN);
				}
				$tagNames = array_column($fullTags, 'name');
				if(in_array('todo', $tagNames)){
					$noteMeta['is_todo'] = 1;
				}
				$checkTags = array_column($fullTags, 'id');
				foreach($checkTags as $tagid){
					$keyInfo = \OCA\meta_data\Tags::searchKey($tagid, '%');
					\OCP\Util::writeLog('Notes', 'TAG info: '.serialize($keyInfo), \OCP\Util::WARN);
					//$tagKeys = array_column($keyInfo, 'name');
					foreach($keyInfo as $tagI){
						/*if(!empty($tagI['allowed_values'])){
							$dbKeys[$tagI['name']] = $tagI['allowed_values'];
						}
						else{
							$dbKeys[$tagI['name']] = [];
						}*/
						$dbKeys[$tagI['name']] = $tagI;
					}
					//$dbKeys = array_merge($dbKeys, array_column($keyInfo, 'name'));
					$dbMeta = self::getMeta($i['fileid'], $tagid);
				}
					
				$meta = array_merge($noteMeta, $dbMeta);
				$meta['date'] = empty($meta['date'])?date("Y-m-d", substr($i->getData()['mtime'], 0, 10)):$meta['date'];
				\OCP\Util::writeLog('Notes', 'Adding file '.$path.':'.(empty($meta['title'])?'':$meta['title']).
						':'.(empty($meta['id'])?"":$meta['id']), \OCP\Util::WARN);
				if(!empty($meta['id']) || $includeResources){
					$ret[] = ['metadata'=>$meta, 'notemetadata'=>$noteMeta, 'path'=>$path,
							'dbmetadata'=>$dbMeta, 'dbkeys'=>$dbKeys, 'tags'=>$fullTags, 'fileinfo'=>$i->getData()];
				}
			}
		}
		return $ret;
	}
	
	private static function getMeta($fileid, $tagid){
		$keys = \OCA\meta_data\Tags::dbLoadFileKeys($fileid, $tagid);
		\OCP\Util::writeLog('Notes', 'Got  KEYS for: '.$fileid.':'.$tagid.'-->'.serialize($keys), \OCP\Util::WARN);
		$dbMeta = [];
		foreach($keys as $key){
			$keyRows = \OCA\meta_data\Tags::searchKeysByIDs([$key['keyid']]);
			\OCP\Util::writeLog('Notes', 'KEY info: '.$key['keyid'].'-->'.serialize($keyRows), \OCP\Util::WARN);
			if(empty($keyRows)){
				continue;
			}
			$keyName = $keyRows[$key['keyid']]['name'];
			$dbMeta[$keyName] = $key['value'];
		}
		return $dbMeta;
	}
	
	// This is called by getChildren() for each file.
	// The list of missing notebooks (passed by reference) is initially just the list of directories.
	// Those directories containing a notebook md file are flagged as such.
	// The list of notes to fix is initially empty. The fixing will consist in adding a parent
	// and adding metadata from the db. It cannot be done until we're sure all notes and notebooks
	// have a parent.
	public static function checkJoplinFileMeta($note, &$noteBooks, &$tags, &$fileTags){
		\OCP\Util::writeLog('Notes', 'Checking NOTE '.serialize($note['notemetadata']), \OCP\Util::WARN);
		if(empty($note['notemetadata']['type_'])){
			$note['notemetadata']['type_'] = 1;
		}
		if((int)$note['notemetadata']['type_']==2){
			// Notebook md file for this directory
			\OCP\Util::writeLog('Notes', 'Adding notebook: '.serialize($note['notemetadata']), \OCP\Util::WARN);
			if(!empty($note['notemetadata']['id'])){
				$path = preg_replace('|^files/|', '', $note['fileinfo']['path']);
				$dir = rtrim(dirname($path), '/');
				if(!empty($dir)){
					$noteBooks[$dir] = $note['notemetadata'];
				}
			}
		}
		elseif((int)$note['notemetadata']['type_']==5){
			\OCP\Util::writeLog('Notes', 'Adding tag: '.$note['notemetadata']['id'].'-->'.$note['notemetadata']['title'], \OCP\Util::WARN);
			// Tag md file
			$tags[$note['notemetadata']['id']] = $note['notemetadata']['title'];
		}
		elseif((int)$note['notemetadata']['type_']==6){
			\OCP\Util::writeLog('Notes', 'Adding mapping: '.$note['notemetadata']['note_id'].'-->'.$note['notemetadata']['tag_id'], \OCP\Util::WARN);
			// note_id/tag_id mapping md file
			$fileTags[$note['notemetadata']['note_id']][] = $note['notemetadata']['tag_id'];
		}
	}
	
	public static function fixJoplinParents(&$noteBooks){
		\OCP\Util::writeLog('Notes', 'Fixing notebooks: '.serialize($noteBooks), \OCP\Util::INFO);
		// Make sure we start from the root of the tree
		uksort($noteBooks, function($dir1, $dir2){
			if($dir1==$dir2){
				return 0;
			}
			return strpos($dir1, $dir2)===0?1:-1;
		});
		$createdNotes = [];
		foreach($noteBooks as $dir=>$note){
			if(empty($note)){
				\OCP\Util::writeLog('Notes', 'Creating directory md file: '.$dir.':'.serialize($note), \OCP\Util::WARN);
				$noteBooks[$dir] = self::createNotebookMDFile($dir, $noteBooks);
				$createdNotes[] = $noteBooks[$dir];
			}
		}
		return $createdNotes;
	}
	
	private static function createNoteBookBaseMeta($noteBooks=[], $dir=""){
		$id = md5(uniqid(mt_rand(), true));
		$parentid = "";
		$parentDir = dirname($dir);
		if(!empty($dir) && rtrim($dir, '/')!=rtrim(self::$NOTES_DIR, '/') && $dir!="/" &&
				!empty($parentDir) && rtrim($parentDir, '/')!=rtrim(self::$NOTES_DIR, '/') && $parentDir!="/"){
					$parentNotebookMeta = $noteBooks[$parentDir];
					\OCP\Util::writeLog('Notes', 'Getting parent id for: '.$parentDir.':'.serialize($parentNotebookMeta), \OCP\Util::WARN);
					$parentid = $parentNotebookMeta['id'];
		}
		$date = \DateTime::createFromFormat("U.u", microtime(true));
		$micros = $date->format("u");
		$millis = round($micros/1000);
		$nowDate = $date->format("Y-m-d\TH:i:s.$millis\Z");
		$ret = ['id'=>$id, 'date'=>$nowDate];
		if(!empty($parentid)){
			$ret['parent_id'] = $parentid;
		}
		return $ret;
	}
	
	private static function createNotebookMDFile($dir, $noteBooks){
		$meta = self::createNoteBookBaseMeta($noteBooks, $dir);
		$id = $meta['id'];
		$nowDate = $meta['date'];
		$path = $dir."/.".$id.".md";
		$name = basename($dir);
		$content = "$name

id: $id
created_time: $nowDate
updated_time: $nowDate
user_created_time: $nowDate
user_updated_time: $nowDate
encryption_cipher_text: 
encryption_applied: 0
type_: 2".(empty($meta['parent_id'])?"":"
parent_id: ".$meta['parent_id']);
		\OC\Files\Filesystem::file_put_contents($path, $content);
		return self::getNoteMeta($path);
	}
	
	private static function createTagMDFile($noteBooks, $tagName){
		$meta = self::createNoteBookBaseMeta($noteBooks);
		$id = $meta['id'];
		$nowDate = $meta['date'];
		$path = self::$NOTES_DIR.".".$id.".md";
		$content = "$tagName

id: $id
created_time: $nowDate
updated_time: $nowDate
user_created_time: $nowDate
user_updated_time: $nowDate
encryption_cipher_text:
encryption_applied: 0
type_: 5";
		\OC\Files\Filesystem::file_put_contents($path, $content);
		$fileInfo = \OC\Files\Filesystem::getFileInfo($path);
		$fileInfo['noteid'] = $id;
		return $fileInfo;
	}
	
	private static function createTagMappingMDFile($noteId, $noteBooks, $tagId){
		$meta = self::createNoteBookBaseMeta($noteBooks);
		$id = $meta['id'];
		$nowDate = $meta['date'];
		$path = self::$NOTES_DIR.".".$id.".md";
		$content = "id: $id
note_id: $noteId
tag_id: $tagId
created_time: $nowDate
updated_time: $nowDate
user_created_time: $nowDate
user_updated_time: $nowDate
encryption_cipher_text:
encryption_applied: 0
type_: 6";
		\OC\Files\Filesystem::file_put_contents($path, $content);
		$fileInfo = \OC\Files\Filesystem::getFileInfo($path);
		$fileInfo['noteid'] = $id;
		return $fileInfo;
	}
	
	private static function createImageMDFile($imageId, $extension){
		$meta = self::createNoteBookBaseMeta();
		$id = $meta['id'];
		$nowDate = $meta['date'];
		$path = self::$NOTES_DIR.".resource/.".$imageId.".md";
		$content = "$id.$extension

id: $imageId
mime: image/$extension
created_time: $nowDate
updated_time: $nowDate
user_created_time: $nowDate
user_updated_time: $nowDate
encryption_cipher_text:
encryption_applied: 0
encryption_blob_encrypted: 0
size: 
type_: 4";
		\OC\Files\Filesystem::file_put_contents($path, $content);
		return $id;
	}
	
	public static function deleteNoteId($path){
		$note = self::getNoteMeta($path);
		unset($note['id']);
		self::writeNote($note);
	}
	
	private static function getNotebookNote($dir){
		$dataDir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
		$homeDir = $dataDir.'/'.trim(\OC\Files\Filesystem::getRoot(), '/');
		$notesDir = $homeDir.'/'.\OCA\Notes\Lib::$NOTES_DIR;
		$fullPath = $notesDir.trim($dir, '/');
		$check = shell_exec("grep '^type_: 2$' '".$fullPath."/\.*.md' | awk -F : '{print $1}'");
		if(preg_match('|^'.$notesDir.'|', trim($check))){
			$path = preg_replace('|^'.$homeDir.'|', '', trim($check));
		}
		return $path;
	}
	
	public static function deleteNotebookId($dir){
		$path = getNotebookNote($dir);
		self::deleteNoteId($path);
	}
	
	/**
	 * Add id, parentid, type_
	 * if not present.
	 * Update is_todo: 0, todo_due: 0, todo_completed: 0, additional metadata fields
	 * from DB.
	 * Notice that the DB will be updated on writes/PUT and so is the authoritative source.
	 * PUT is blocking (?).
	 * Update user_created_time, user_updated_time, created_time, updated_time
	 * from OC info in DB.

	 * @param array $note
	 */
	public static function fixJoplinFileMeta($checknote, $notebooks, $tags, $fileTags){
		$createdTagFiles = [];
		$deletedTagNotes = [];
		// Only update files on PROPFIND, not on PUT
		if($_SERVER['REQUEST_METHOD']!='PROPFIND' ||
				strpos(basename($checknote['fileinfo']['path']), '.')===0){
			return;
		}
		$note = $checknote;
		// If no id present, the file was created in the web interface - we add one before serving.
		if(empty($note['notemetadata']['id'])){
			$id = md5(uniqid(mt_rand(), true));
			$note['notemetadata']['id'] = $id;
		}
		if(empty($note['notemetadata']['type_'])){
			$note['notemetadata']['type_'] = 1;
		}
		if(!empty($note['notemetadata']['type_']) &&
				$note['notemetadata']['type_']==1 &&
				empty($note['notemetadata']['parent_id'])){
			$path = preg_replace('|^files/|', '', $note['fileinfo']['path']);
			$dir = dirname($path);
			if(!empty($notebooks[$dir])){
				$note['notemetadata']['parent_id'] = $notebooks[$dir]['id'];
			}
		}
		//$noteDate = date("Y-m-d\TH:i:s.000\Z", $note['fileinfo']['mtime']);
		$noteDate = $note['fileinfo']['mtime'];
		$note['notemetadata']['updated_time'] = $noteDate;
		$note['notemetadata']['user_updated_time'] = $note['notemetadata']['updated_time'];
		if(empty($note['notemetadata']['user_created_time'])){
			$note['notemetadata']['user_created_time'] = $note['notemetadata']['updated_time'];
		}
		foreach($note['dbmetadata'] as $key=>$val){
			if($key=='due'){
				if(empty($val)){
					$note['notemetadata']['todo_due'] = 0;
				}
				else{
					\OCP\Util::writeLog('Notes', 'Formatting date '.$val, \OCP\Util::WARN);
					$duedate = \DateTime::createFromFormat('Y-m-d', $val);
					//$note['notemetadata']['todo_due'] = strtotime($duedate->format('Y-m-d\TH:i:sP'));
					$note['notemetadata']['todo_due'] = $duedate->getTimestamp().'000';
				}
				$note['notemetadata']['is_todo'] = 1;
			}
			elseif($key=='status'){
				$note['notemetadata']['todo_completed'] = ($val=='done'?1:0);
			}
			else{
				// This will not be used by the client for now...
				//$note['notemetadata'][$key] = $val;
			}
		}
		// Delete tag or mapping file if it's not backed by DB entry.
		// Notice: The DB is updated on each PUT.
		if($note['notemetadata']['type_']==6){
			$tagNameId = getDbTagIdFromJoplinTagId($note['notemetadata']['tag_id']);
			$tagid = array_values($tagNameId)[0];
			$filesTags = \OCA\meta_data\Tags::dbGetFileTags($note['fileinfo']['fileid']);
			$fileTags = $filesTags[$note['fileinfo']['fileid']];
			if(!in_array($tagid, $fileTags)){
				if(!empty($note['path']) && trim($note['path'], '/')!=trim(self::$NOTES_DIR, '/')){
					\OCP\Util::writeLog('Notes', 'Deleting tag/file mapping '.$note['path'], \OCP\Util::WARN);
					\OC\Files\Filesystem::unlink($note['path']);
				}
			}
		}
		// Delete tag file if it's not backed by DB entry.
		// Notice: The DB is updated on each PUT.
		if($note['notemetadata']['type_']==5 && array_key_exists($note['notemetadata']['title'], $tags)){
			$user = \OC_User::getUser();
			$tagid = \OCA\meta_data\Tags::getTagID($note['notemetadata']['title'], $user);
			if(empty($tagid) && trim($note['path'], '/')!=trim(self::$NOTES_DIR, '/')){
				$tag_id = array_search($note['notemetadata']['title'], $tags);
				if(!empty($tag_id)){
					\OCP\Util::writeLog('Notes', 'Deleting tag/file mapping '.$note['path'], \OCP\Util::WARN);
					$deletedTagNotes[] = $note;
					\OC\Files\Filesystem::unlink($note['path']);
				}
			}
		}

		// Update tag files from DB
		foreach($note['tags'] as $tag){
			if(!in_array($tag['name'], array_values($tags))){
				// Write tag file, update $tags
				\OCP\Util::writeLog('Notes', 'Writing tag file '.$tag['name'].'-->'.serialize($tags), \OCP\Util::WARN);
				$tagNoteInfo = self::createTagMDFile($notebooks, $tag['name']);
				$tag_id = $tagNoteInfo['noteid'];
				$createdTagFiles[] = $tagNoteInfo;
				$tags[$tag_id] = $tag['name'];
			}
			$fileTagNames = empty($fileTags[$note['notemetadata']['id']])?[]:
				array_map(function($i) use ($tags) {
					return $tags[$i];
				}, $fileTags[$note['notemetadata']['id']]);
			if(!in_array($tag['name'], $fileTagNames)){
				// Write tag mapping, update $fileTags
				$tag_id = array_search($tag['name'], $tags);
				$mappingNoteInfo = self::createTagMappingMDFile($note['notemetadata']['id'], $notebooks, $tag_id);
				$createdTagFiles[] = $mappingNoteInfo;
				$fileTags[$note['notemetadata']['id']][] = $tag_id;
			}
		}
		
		// Fix any inserted local image URLs
		if(preg_match_all('|!\[([^\[\]]+)\]\(([^\(\)\:]+)\)|s', $note['notemetadata']['content'], $matches)){
			$i = 0;
			foreach($matches[0] as $match){
				$imageId = md5(uniqid(mt_rand(), true));
				$extension = pathinfo($matches[1][$i], PATHINFO_EXTENSION);
				\OC\Files\Filesystem::copy($matches[1][$i], self::$NOTES_DIR.".resource/".$imageId);
				$retID = self::createImageMDFile($imageId, $extension);
				$fixed = "![".$retID.".".$extension."](:/".$imageId.")";
				$note['notemetadata']['content'] = str_replace($match, $fixed, $note['notemetadata']['content']);
				++$i;
			}
		}
		
		// Check if note has been changed
		$diffMeta = self::noteMetaChanges($checknote['notemetadata'], $note['notemetadata']);
		// Check for todo date that has been set to current time
		if(!empty($diffMeta['todo_due'])){
			$checkdate = date('Y-m-d', $checknote['notemetadata']['todo_due']/1000);
			$notedate = date('Y-m-d', $note['notemetadata']['todo_due']/1000);
			if($checkdate==$notedate){
				unset($diffMeta['todo_due']);
			}
		}
		if(!empty($diffMeta)){
			\OCP\Util::writeLog('Notes', 'Note has changed '.$checknote['notemetadata']['title'].':'.
					$checknote['notemetadata']['id'].'-->'.
					serialize($diffMeta).'-->'.serialize($note['dbmetadata']), \OCP\Util::WARN);
			// Now write the updated metadata to file
			self::writeNote($note);
			// And touch back the file, to make the fs timestamp match
			$path = preg_replace('|^files/|', '', $note['fileinfo']['path']);
			\OC\Files\Filesystem::touch($path, $note['fileinfo']['mtime']);
		}
		
		\OCP\Util::writeLog('Notes', 'Created tag files: '.count($createdTagFiles), \OCP\Util::WARN);
		return ['createdFiles'=>$createdTagFiles, 'deletedNotes'=>$deletedTagNotes];
	}
	
	private static function myempty($meta, $key){
		return !isset($meta[$key]) ||
			$meta[$key]===null || $meta[$key]==='';
	}
	
	private static function noteMetaChanges($oldmeta, $newmeta){
		$diff = [];
		$keys = array_merge(array_keys($oldmeta), array_keys($newmeta));
		foreach($keys as $key){
			if($key=="content" || $key=="title" || $key=="path"){
				continue;
			}
			if(self::myempty($oldmeta, $key) && !self::myempty($newmeta, $key)){
				$diff[$key] = $newmeta[$key];
			}
			elseif(self::myempty($newmeta, $key) && !self::myempty($oldmeta, $key)){
				$diff[$key] = "";
			}
			elseif(!self::myempty($oldmeta, $key) && !self::myempty($newmeta, $key) && $oldmeta[$key]!=$newmeta[$key]){
				\OCP\Util::writeLog('Notes', 'DIFF: '.$key.': '.$oldmeta[$key].'!='.$newmeta[$key], \OCP\Util::WARN);
				$diff[$key] = $newmeta[$key];
			}
		}
		return $diff;
	}
	
	public static function writeNote($note){
		$path = preg_replace('|^files/|', '', $note['fileinfo']['path']);
		\OCP\Util::writeLog('Notes', 'Writing note '.$path.':'.$note['notemetadata']['title'].
				':'.$note['notemetadata']['content'], \OCP\Util::WARN);
		$content = $note['notemetadata']['content'];
		$title = $note['notemetadata']['title'];
		// YAML parsing has converted dates to timestamps. Convert back before writing to disk.
		if(!empty($note['notemetadata']['updated_time'])){
			$note['notemetadata']['updated_time'] = date("Y-m-d\TH:i:s.000\Z", $note['notemetadata']['updated_time']);
		}
		if(!empty($note['notemetadata']['user_updated_time'])){
		 	$note['notemetadata']['user_updated_time'] = date("Y-m-d\TH:i:s.000\Z", $note['notemetadata']['user_updated_time']);
		}
		if(!empty($note['notemetadata']['created_time'])){
		 	$note['notemetadata']['created_time'] = date("Y-m-d\TH:i:s.000\Z", $note['notemetadata']['created_time']);
		}
		if(!empty($note['notemetadata']['user_created_time'])){
		 	$note['notemetadata']['user_created_time'] = date("Y-m-d\TH:i:s.000\Z", $note['notemetadata']['user_created_time']);
		}
		unset($note['notemetadata']['content']);
		unset($note['notemetadata']['title']);
		unset($note['notemetadata']['path']);
		$metaText = implode("\n", array_map(function($key) use ($note) {
			return $key.": ".$note['notemetadata'][$key];
			}, array_keys($note['notemetadata'])));
		$info = \OC\Files\Filesystem::file_put_contents($path, $title.
				(empty($content)?"":"\n\n".$content)."\n\n".$metaText);
		return $info;
	}
	
	//private static $joplinPattern = "|([^\n]+)\n.*\n((\n.*: .*)*?)$|s";
	private static $joplinPattern = "|^([^\n]+)(\n\n)?(.*)\n((\n.+: .+)*)$|s";
	private static $joplinMappingPattern = "|^(.+: .+(\n.+: .+)*)$|s";
	private static $picoPattern = "|^\n*---\n*((\n[^\n]+: [^\n]*)+)\n\n*---\n*|s";
	
	public static function getFileContent($rawContent){
		$content = preg_replace(self::$joplinPattern, "$3", $rawContent);
		$content = preg_replace(self::$picoPattern, "", $content);
		return $content;
	}
	
	public static function parseJoplinFileMeta($rawContent){
		$rawContent = ltrim($rawContent);
		$meta = array();
		if(empty($rawContent)){
			return $meta;
		}
		$rawContent = preg_replace(self::$picoPattern, "", $rawContent);
		if(preg_match(self::$joplinPattern, $rawContent, $rawMetaMatches) && isset($rawMetaMatches[4])){
			\OCP\Util::writeLog('Notes', 'PARSING '.$rawContent.'-->'.$rawMetaMatches[1].'-->'.serialize($rawMetaMatches[4]), \OCP\Util::INFO);
			$yamlParser = new \Symfony\Component\Yaml\Parser();
			$meta = $yamlParser->parse($rawMetaMatches[4]);
			$meta['title'] = $rawMetaMatches[1];
		}
		elseif(preg_match(self::$joplinMappingPattern, $rawContent, $rawMetaMatches) &&
				isset($rawMetaMatches[1])){
					$yamlParser = new \Symfony\Component\Yaml\Parser();
					$meta = $yamlParser->parse($rawMetaMatches[1]);
		}
		else{
			\OCP\Util::writeLog('Notes', 'NOT PARSING '.$rawContent, \OCP\Util::WARN);
		}
		return $meta;
	}
	
	public static function parsePicoFileMeta($rawContent, array $headers=[]){
		$rawContent = ltrim($rawContent);
		$meta = array();
		$pattern = "/^(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
				. "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
		if (preg_match($pattern, $rawContent, $rawMetaMatches) && isset($rawMetaMatches[3])) {
				$yamlParser = new \Symfony\Component\Yaml\Parser();
				$meta = $yamlParser->parse($rawMetaMatches[3]);

				if ($meta !== null) {
						// the parser may return a string for non-YAML 1-liners
						// assume that this string is the page title
						$meta = is_array($meta) ? array_change_key_case($meta, CASE_LOWER) : array('title' => $meta);
				} else {
						$meta = array();
				}

				foreach ($headers as $fieldId => $fieldName) {
						$fieldName = strtolower($fieldName);
						if (isset($meta[$fieldName])) {
								// rename field (e.g. remove whitespaces)
								if ($fieldId != $fieldName) {
										$meta[$fieldId] = $meta[$fieldName];
										unset($meta[$fieldName]);
								}
						} elseif (!isset($meta[$fieldId])) {
								// guarantee array key existance
								$meta[$fieldId] = '';
						}
				}

				if (!empty($meta['date'])) {
						// workaround for issue #336
						// Symfony YAML interprets ISO-8601 datetime strings and returns timestamps instead of the string
						// this behavior conforms to the YAML standard, i.e. this is no bug of Symfony YAML
						if (is_int($meta['date'])) {
								$meta['time'] = $meta['date'];

								$rawDateFormat = (date('H:i:s', $meta['time']) === '00:00:00') ? 'Y-m-d' : 'Y-m-d\TH:i:s';
								$meta['date'] = date($rawDateFormat, $meta['time']);
						} else {
								$meta['time'] = strtotime($meta['date']);
						}
						$meta['date_formatted'] = utf8_encode(strftime($this->getConfig('date_format'), $meta['time']));
				} else {
						$meta['time'] = $meta['date_formatted'] = '';
				}
		} else {
				// guarantee array key existence
				$meta = array_fill_keys(array_keys($headers), '');
				$meta['time'] = $meta['date_formatted'] = '';
		}
		// NC change
		if(!empty($meta['author'])){
			$meta['displayname'] = \OCP\User::getDisplayName($meta['author']);
		}
		return $meta;
	}
	
	// Called by httpPut()
	public static function updateMeta($fileId, $fileMeta){
		$user = \OC_User::getUser();
		//$tagLinkFileContent = \OC\Files\Filesystem::file_get_contents(self::$NOTES_DIR.$path);
		\OCP\Util::writeLog('Notes', 'Updating metadata for '.serialize($fileMeta), \OCP\Util::WARN);
		//$joplinMeta['path'] = self::$NOTES_DIR.$path;
		//$joplinMeta['content'] = self::getFileContent($fileContent);
		if(empty($fileMeta['type_']) || empty($fileMeta['id'])){
			return null;
		}
		// Take care of todo info.
		if($fileMeta['type_']==1 && !empty($fileMeta['is_todo']) && $fileMeta['is_todo']==1){
			$tagid = \OCA\meta_data\Tags::getTagID('todo', $user);
			\OCA\meta_data\Tags::updateFileTag($tagid, $user, $fileId);
			$dbMeta = self::getMeta($fileId, $tagid);
			if(!empty($fileMeta['todo_due'])){
				$millis = round(((int)$fileMeta['todo_due'])/1000);
				$fileDueDate = new \DateTime("@".$millis);
				$fileDue = $fileDueDate->format('Y-m-d');
				if($fileDue!=$dbMeta['due']){
					$keyId = \OCA\meta_data\Tags::getKeyID($tagid, 'due', $user);
					\OCA\meta_data\Tags::updateFileKeyVal($fileId, $tagid, $keyId, $fileDue);
				}
			}
			if(isset($fileMeta['todo_completed'])){
				$status = empty($fileMeta['todo_completed'])?'open':'done';
				if($status!=$dbMeta['status']){
					$keyId = \OCA\meta_data\Tags::getKeyID($tagid, 'status', $user);
					\OCA\meta_data\Tags::updateFileKeyVal($fileId, $tagid, $keyId, $status);
				}
			}
		}
		// This is a note<->tag link
		elseif($fileMeta['type_']==6){
			// Get the tag ID
			$tagNameId = self::getDbTagIdFromJoplinTagId($fileMeta['tag_id']);
			$tagid = array_values($tagNameId)[0];
			$tagName = array_keys($tagNameId)[0];
			// Get the tagged file
			$filePath = self::$NOTES_DIR.'.'.$fileMeta['note_id'].".md";
			$fileContent = \OC\Files\Filesystem::file_get_contents($filePath);
			$joplinMeta = self::parseJoplinFileMeta($fileContent);
			$fileInfo = \OC\Files\Filesystem::getFileInfo($filePath);
			if(!empty($tagid)){
				// Check metadata related to tag. This is a specific concept of the meta_data app,
				// but we carry it over here. The explicit link tag->attributes is just lost,
				// i.e. different tags cannot have attributes of the same name.
				// Notice that we assume the tag exists in the DB because previously
				// a corresponding type 5 md file will have been PUT.
				// See OC_Connector_Sabre_Server_notes::httpPut().
				$dbMeta = self::getMeta($fileInfo['fileid'], $tagid);
				$dbKeys = \OCA\meta_data\Tags::searchKey($tagid, '%', $user);
				foreach($joplinMeta as $key=>$value){
					if(in_array($key, $dbKeys) && value!=$dbMeta[$key] &&
							($tagName!='todo' || $key=='priority')){
								\OCP\Util::writeLog('Notes', 'Updating metadata for file '.$fileId.':'.
										$key.':'.$value, \OCP\Util::WARN);
								\OCA\meta_data\Tags::updateFileKeyVal($fileId, $tagid, $key, $value);
					}
				}
			}
		}
	}
	
	private static function getDbTagIdFromJoplinTagId($tag_id){
		$tagFileContent = \OC\Files\Filesystem::file_get_contents(self::$NOTES_DIR.
				$tag_id.".md");
		$tagFileMeta = self::parseJoplinFileMeta($tagFileContent);
		$tagName = $tagFileMeta['title'];
		$user = \OC_User::getUser();
		$tagId = \OCA\meta_data\Tags::getTagID($tagName, $user);
		return [$tagName=>$tagId];
	}
	
	public static function getPath(&$fileMeta, $rootNode){
		// List all notebooks to create tree and find path
		$folder = '';
		if(!empty($fileMeta['parent_id'])){
			foreach($rootNode->getNoteBooks() as $dir=>$note){
				if($note['id']==$fileMeta['parent_id']){
					$folder = $dir;
					$folder = preg_replace('|^'.\OCA\Notes\Lib::$NOTES_DIR.'|', '', $folder);
					break;
				}
			}
		}
		$cleanTitle = str_replace('/', '::', $fileMeta['title']);
		if($fileMeta['type_']=='2'){
			$fileMeta['path'] = trim($folder, '/').'/'.$cleanTitle.'/.'.$fileMeta['id'].'.md';
		}
		elseif($fileMeta['type_']=='4'){
			$fileMeta['path'] = '.resource/.'.$fileMeta['id'].'.md';
		}
		elseif($fileMeta['type_']=='5' || $fileMeta['type_']=='6'){
			$fileMeta['path'] = '.'.$fileMeta['id'].'.md';
		}
		else{
			$fileMeta['path'] = trim($folder, '/').'/'.$cleanTitle.'.md';
		}
		return $fileMeta['path'];
	}
	
	public static function mkTagIcons($tags){
		$tagwidth = 0;
		$overflow = 0;
		$html = '<div class="filetags-wrap col-xs-4">';
		if(!empty($tags)){
			foreach($tags as $key=>$value) {
				$color = self::colorTranslate($value['color']);
				if($tagwidth + strlen($value['name']) <= 10){
					$html = $html . '
					<span data-tag="'.$value['id'].'" class="label outline label-'.$color.'">
					<span class="deletetag" style="display:none">
					<i class="icon-cancel-circled"></i>
					</span>
					<i class="icon-tag"></i>
					<span class="tagtext">'.$value['name'].'</span>
					</span>
				';
				}
				else{
					$overflow += 1;
				}
				$tagwidth += strlen($value['name']);
			};
		}
		if($overflow > 0){
			$html = $html .
			'<span class="label outline label-default more-tags" title="Show more tags"><span class="tagtext">+'.
			$overflow.' more</span></span>';
		}
		$html = $html . '</div>';
		return $html;
	}
	
	private static function colorTranslate($color){
		if(empty($color)) return "default";
		if(strpos($color, 'color-1') !== false)  return "default";
		if(strpos($color, 'color-2') !== false)  return "primary";
		if(strpos($color, 'color-3') !== false)  return "success";
		if(strpos($color, 'color-4') !== false)  return "info";
		if(strpos($color, 'color-5') !== false)  return "warning";
		if(strpos($color, 'color-6') !== false)  return "danger";
		return "default";
	}

}
