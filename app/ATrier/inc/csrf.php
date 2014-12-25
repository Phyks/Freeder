<?php
/** Freeder
 *  -------
 *  @file
 *  @copyright Copyright (c) 2014 Freeder, MIT License, See the LICENSE file for copying permissions.
 *  @brief Provide anti-CSRF functions.
 */


/**
 * Generate a token to protect against CSRF.
 * The token is stored in a session.
 *
 * @param	$name	(optionnal) A unique name for the token. Defaults to an empty string (conflicts risks !).
 * @return The generated token.
 */
function generate_token($name = '') {
	if(session_id() == '')
		session_start();

	$token = uniqid(rand(), true);

	$_SESSION[$name.'_token'] = $token;
	$_SESSION[$name.'_token_time'] = time();

	return $token;
}

/**
 * Check that the anti-CSRF token (provided in `$_GET` or `$_POST` superglobal) is correct.
 *
 * @param	$time	Time validity for this token.
 * @param	$name	(optionnal) Token unique name. Defaults to an empty string (conflicts risks !).
 *
 * @return `true` or `false` whether the token was correct or not.
 */
function check_token($time, $name = '') {
	if(session_id() == '')
		session_start();

	if(isset($_SESSION[$name.'_token']) && isset($_SESSION[$name.'_token_time']) && (isset($_POST['token']) || isset($_GET['token']))) {
		if(!empty($_POST['token']))
			$token = $_POST['token'];
		else
			$token = $_GET['token'];

		if($_SESSION[$name.'_token'] == $token) {
			if($_SESSION[$name.'_token_time'] >= (time() - (int) $time))
				return true;
		}
	}
	return false;
}