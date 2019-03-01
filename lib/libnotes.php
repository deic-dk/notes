<?php

/**
	Requires: symfony
**/

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

	public static function getDirList($dir, $depth=-1, $excludeDir=null){
		if($depth == 0){
			return array();
		}
		$ret = array();
		foreach(\OC\Files\Filesystem::getDirectoryContent($dir) as $i){
			if($i['name'][0]=='.'){
				continue;
			}
			elseif($i['type']=='dir'){
				$path = rtrim($dir, '/').'/'.$i['name'];
				\OCP\Util::writeLog('Notes', 'Adding dir '.$path.':'.$excludeDir, \OCP\Util::WARN);
				if(!empty($i['permissions']) && $i['permissions']&\OCP\PERMISSION_UPDATE!=0 &&
						!empty($excludeDir)&&strpos(trim($path, '/'), trim($excludeDir, '/'))!==0){
					$ret[] = $path;
				}
				$ret = array_merge($ret, self::getDirList($path, $depth-1, $excludeDir));
			}
		}
		return $ret;
	}
	
		public static function getFileList($dir, $depth=-1, $excludeDir=null){
		if($depth == 0){
			return array();
		}
		$ret = array();
		foreach(\OC\Files\Filesystem::getDirectoryContent($dir) as $i){
			$path = rtrim($dir, '/').'/'.$i['name'];
			if($i['name'][0]=='.'){
				continue;
			}
			elseif($i['type']=='dir'){
				\OCP\Util::writeLog('Notes', 'Listing dir '.$path.':'.$excludeDir, \OCP\Util::WARN);
				if(!empty($i['permissions']) && $i['permissions']&\OCP\PERMISSION_UPDATE!=0 &&
						(empty($excludeDir)||strpos(trim($path, '/'), trim($excludeDir, '/'))!==0)){
					$ret = array_merge($ret, self::getFileList($path, $depth-1, $excludeDir));
				}
			}
			else{
				$fileContent = \OC\Files\Filesystem::file_get_contents($path);
				$meta = self::parseFileMeta($fileContent);
				\OCP\Util::writeLog('Notes', 'Adding file '.$path.':'.$excludeDir.'-->'.serialize($meta), \OCP\Util::WARN);
				if(!empty($meta['title'])){
					$ret[] = $meta;
				}
			}
		}
		return $ret;
	}
	
	public static function parseJoplinFileMeta($rawContent){
		$meta = array();
		$pattern = "|([^\n]+)\n.*\n((\n.*: .*)*?)$|s";
		if(preg_match($pattern, $rawContent, $rawMetaMatches) && isset($rawMetaMatches[2])){
			\OCP\Util::writeLog('Notes', 'PARSING '.$rawMetaMatches[1].'-->'.serialize($rawMetaMatches[2]), \OCP\Util::WARN);
			$yamlParser = new \Symfony\Component\Yaml\Parser();
			$meta = $yamlParser->parse($rawMetaMatches[2]);
			$meta['title'] = $rawMetaMatches[1];
		}
		return $meta;
	}
	
public function parsePicoFileMeta($rawContent, array $headers=[]){
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

								$rawDateFormat = (date('H:i:s', $meta['time']) === '00:00:00') ? 'Y-m-d' : 'Y-m-d H:i:s';
								$meta['date'] = date($rawDateFormat, $meta['time']);
						} else {
								$meta['time'] = strtotime($meta['date']);
						}
						$meta['date_formatted'] = utf8_encode(strftime($this->getConfig('date_format'), $meta['time']));
				} else {
						$meta['time'] = $meta['date_formatted'] = '';
				}
		} else {
				// guarantee array key existance
				$meta = array_fill_keys(array_keys($headers), '');
				$meta['time'] = $meta['date_formatted'] = '';
		}
		// NC change
		if(!empty($meta['author'])){
			$meta['displayname'] = \OCP\User::getDisplayName($meta['author']);
		}
		if(!empty($this->indexInferred)){
			$meta['indexinferred'] = 'yes';
		}

		return $meta;
	}

}
