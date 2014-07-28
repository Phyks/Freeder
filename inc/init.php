<?php
/*	Copyright (c) 2014 Freeder
 *	Released under a MIT License.
 *	See the file LICENSE at the root of this repo for copying permission.
 */

/**
 * This include file defines the following variables that you can reuse in
 * your code after including it:
 *  $config Configuration object
 *  $tpl Rain TPL handler
 *  $dbh Database handler
 *
 * This file automatically includes `functions.php` which is required by
 * template generation of almost every page.
 */

session_start();

// Load current directory's `path.php` to retrieve root path.
// If there is no such file, use the current directory as root.
if (is_file('path.php')) {
	require('path.php');
} else {
	define('ROOTPATH', dirname(dirname(__FILE__)));
}

define(INCPATH, ROOTPATH . '/inc');

// Load constant config
require_once(INCPATH . '/constants.php');


// Check database installation
if(!is_file(DATA_DIR.DB_FILE)) {
	require_once(INCPATH . '/install.php');

	install();
}


// Initialize database handler
$dbh = new PDO('sqlite:'.DATA_DIR.DB_FILE);
$dbh->query('PRAGMA foreign_keys = ON');

$query = $dbh->query('SELECT COUNT(*) AS nb_admins FROM users WHERE is_admin=1');
$admins = $query->fetch();
if($admins['nb_admins'] == 0) {
	require_once(INCPATH . '/install.php');

	install();
}


// Load config from database
require_once(INCPATH . '/config.class.php');
$config = new Config();
date_default_timezone_set($config->timezone);


// Test wether an update should be done
if($config->version !== Config::$versions[count(Config::$versions) - 1]) {
	require_once(INCPATH . '/update.php');
	update($config->version, Config::$versions[count(Config::$versions) - 1]);
	header('location: index.php');
	exit();
}


// Load Rain TPL
require_once(INCPATH . '/rain.tpl.class.php');
RainTPL::$tpl_dir = TPL_DIR.$config->template;
$tpl = new RainTPL;
$tpl->assign('start_generation_time', microtime(true));

// `functions.php` must be included in each page for templates.
require_once(INCPATH . '/functions.php');

// Manage users
require_once(INCPATH . '/users.php');
log_user_in();
$tpl->assign('user', isset($_SESSION['user']) ? $_SESSION['user'] : false);
check_anonymous_view();


