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

OCP\Util::addStyle('files', 'files');
OCP\Util::addStyle('chooser','jqueryFileTree');
OCP\Util::addScript('notes','script');
OCP\Util::addStyle('notes','notes');

$tpl = new OCP\Template("notes", "main", "user");

$notebooks = OCA\Notes\Lib::getDirList("/Notes/");
$notes = OCA\Notes\Lib::getFileList("/Notes/");
$templateFiles = OCA\Notes\Lib::getFileList("/Notes/.template/");
$templates = [['name'=>'Blank']];
foreach($templateFiles as $templateFile){
	$templates[] = ['name'=>basename($templateFile), 'path'=>$templateFile];
}

$tpl->assign('notebooks', $notebooks);
$tpl->assign('notes', $notes);
$tpl->assign('templates', $templates);
$tpl->printPage();


