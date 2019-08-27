<?php

require_once('apps/notes/lib/libnotes.php');

\OCP\User::checkLoggedIn();
\OCP\App::checkAppEnabled('chooser');
\OCP\App::checkAppEnabled('notes');
\OCP\App::checkAppEnabled('files_picocms');

\OCP\App::setActiveNavigationEntry('notes');

OCP\Util::addScript('chooser','jquery.easing.1.3');
OCP\Util::addScript('chooser','jqueryFileTree');
OCP\Util::addScript('notes','chooser');
OCP\Util::addScript('notes','script');
OCP\Util::addScript('files', 'app');
OCP\Util::addScript('files', 'file-upload');
OCP\Util::addScript('files', 'files');
OCP\Util::addScript('files', 'filelist');
OCP\Util::addScript('files', 'navigation');
OCP\Util::addScript('files', 'fileactions');
OCP\Util::addScript('files', 'filesummary');
OCP\Util::addScript('files', 'breadcrumb');
OCP\Util::addScript('files', 'keyboardshortcuts');
OCP\Util::addScript('notes', 'excel-bootstrap-table-filter-bundle');

OCP\Util::addStyle('files', 'files');
OCP\Util::addStyle('chooser','jqueryFileTree');
OCP\Util::addStyle('notes','notes');
OCP\Util::addStyle('notes','excel-bootstrap-table-filter-style');

$tpl = new OCP\Template("notes", "main", "user");

$notebooks = OCA\Notes\Lib::getDirList(OCA\Notes\Lib::$NOTES_DIR, -1, '/.');
$notes = OCA\Notes\Lib::getFileList(OCA\Notes\Lib::$NOTES_DIR, -1, '/.');
foreach($notes as &$res){
	$res['fileinfo']['fullpath'] = $res['fileinfo']['path'];
	$res['fileinfo']['path'] = substr(trim($res['fileinfo']['fullpath'], "/"), strlen(trim(OCA\Notes\Lib::$NOTES_DIR, "/"))+1);
}
$templateFiles = OCA\Notes\Lib::getFileList(OCA\Notes\Lib::$TEMPLATES_DIR, -1, null, [], '', true);

$templates = [];
foreach($templateFiles as $templateFile){
	$templates[] = ['title'=>$templateFile['metadata']['title'], 'path'=>$templateFile['fileinfo']['path']];
}

\OCP\Util::writeLog('Notes', 'Got templates '.serialize($templates), \OCP\Util::WARN);

$tpl->assign('notesdir', OCA\Notes\Lib::$NOTES_DIR);
$tpl->assign('notebooks', $notebooks);
$tpl->assign('notes', $notes);
$tpl->assign('templates', $templates);
$tpl->printPage();

