<?php

require_once('apps/notes/lib/libnotes.php');

OCP\JSON::checkAppEnabled('notes');
OCP\User::checkLoggedIn();

OCP\Util::addscript('notes', 'personalsettings');

$tmpl = new OCP\Template('notes', 'personalsettings');

$tmpl->assign('notes_folder', OCA\Notes\Lib::getNotesFolder());
$tmpl->assign('default_tags', OCA\Notes\Lib::getDefaultTags());

return $tmpl->fetchPage();
