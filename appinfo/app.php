<?php

//require_once('apps/notes/lib/lib_notes.php');

//OCP\App::registerPersonal('notes', 'personalsettings');

OCP\App::checkAppEnabled('meta_data');

\OCP\App::addNavigationEntry(array(

	// the string under which your app will be referenced in owncloud
	'id' => 'notes',

	// sorting weight for the navigation. The higher the number, the higher
	// will it be listed in the navigation
	'order' => 10,

	// the route that will be shown on startup
	'href' => OCP\Util::linkTo('notes' , 'index.php' ),

	// the icon that will be shown in the navigation
	// this file needs to exist in img/example.png
	'icon' => \OCP\Util::imagePath('notes', 'notes.png'),

	// the title of your application. This will be used in the
	// navigation or on the settings page of your app
	'name' => \OC_L10N::get('notes')->t('Notes')
));

OCP\Util::addScript('notes','app');


