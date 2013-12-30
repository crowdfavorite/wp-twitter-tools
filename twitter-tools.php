<?php
/*
Plugin Name: Twitter Tools
Plugin URI: http://crowdfavorite.com/wordpress/plugins/twitter-tools/
Description: An integration between your WordPress site and Twitter. Create posts from your tweets. Show your tweets in your sidebar. Relies on <a href="http://wordpress.org/extend/plugins/social/">Social</a>.
Version: 3.1
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

// Copyright (c) 2007-2013 Crowd Favorite, Ltd. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for WordPress
// http://wordpress.org/
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

$aktt_file = __FILE__;
if (isset($plugin)) {
	$aktt_file = $plugin;
}
else if (isset($mu_plugin)) {
	$aktt_file = $mu_plugin;
}
else if (isset($network_plugin)) {
	$aktt_file = $network_plugin;
}
@define('AKTT_FILE', $aktt_file);
@define('AKTT_PATH', WP_PLUGIN_DIR.'/'.basename(dirname($aktt_file)).'/');

require_once(AKTT_PATH.'/classes/aktt.php');
require_once(AKTT_PATH.'/classes/aktt_account.php');
require_once(AKTT_PATH.'/classes/aktt_tweet.php');
require_once(AKTT_PATH.'/widget.php');

add_action('after_setup_theme', array('AKTT', 'after_setup_theme'), 9999);
add_action('init', array('AKTT', 'init'), 0);

/* Shortcode syntax
 *	[aktt_tweets 
 *		account="alexkingorg"
 *		count="5" 
 *		offset="0"
 *		include_rts="0"
 *		include_replies="0"
 *		mentions="crowdfavorite,twittertools"
 *		hashtags="wordpress,plugin,twittertools"
 *	]
 */
function aktt_shortcode_tweets($args) {
	if (!AKTT::$enabled) {
		return '';
	}
	if ($account = AKTT::default_account()) {
		$username = $account->social_acct->name();
	}
	else { // no accounts, get out
		return '';
	}
	$args = shortcode_atts(array(
		'account' => $username,
		'include_rts' => 0,
		'include_replies' => 0,
		'count' => 5,
		'mentions' => '',
		'hashtags' => '',
	), $args);
	$tweets = AKTT::get_tweets($args);
	ob_start();
	include(AKTT_PATH.'/views/tweet-list.php');
	return ob_get_clean();
}

/* Shortcode syntax
 *	[aktt_tweet account="alexkingorg"]
 *	[aktt_tweet id="138741523272577028"]
 */
function aktt_shortcode_tweet($args) {
	if (!AKTT::$enabled) {
		return '';
	}
	if ($account = AKTT::default_account()) {
		$username = $account->social_acct->name();
	}
	else { // no accounts, get out
		return '';
	}
	$args = shortcode_atts(array(
		'account' => $username,
		'id' => null
	), $args);
// if we have an ID, only search by that
	if (!empty($args['id'])) {
		unset($args['account']);
	}
	$args['count'] = 1;
	$tweets = AKTT::get_tweets($args);
	if (count($tweets) != 1) {
		return '';
	}
	$tweet = $tweets[0];
	ob_start();
	include(AKTT_PATH.'/views/tweet.php');
	return ob_get_clean();
}

// included for compatibility only
function aktt_sidebar_tweets($count = 5, $form = null) {
	_deprecated_function(__FUNCTION__, '3.0', 'aktt_shortcode_tweets()');
	echo do_shortcode('[aktt_tweets count="'.intval($count).'"]');
}

function aktt_sideload_image($file, $post_id, $desc = null) {
	if (!function_exists('wp_sideload_image') && !function_exists('download_url')) {
		include(ABSPATH.'wp-admin/includes/file.php');
	}
	// Can be replaced with `wp_sideload_image` once WP 3.5 is released
	if (function_exists('wp_sideload_image')) {
		return wp_sideload_image($file, $post_id, $desc);
	}
	if ( empty( $file ) ) {
		return new WP_Error( '', 'File URL cannot be empty.' );
	}

	// Download file to temp location
	$tmp = download_url( $file );

	// Set variables for storage
	// fix file filename for query strings
	preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
	$file_array['name'] = basename($matches[0]);
	$file_array['tmp_name'] = $tmp;

	// If error storing temporarily, unlink
	if ( is_wp_error( $tmp ) ) {
		@unlink($file_array['tmp_name']);
		$file_array['tmp_name'] = '';
	}

	if (!function_exists('media_handle_sideload')) {
		include(ABSPATH.'wp-admin/includes/media.php');
	}
	if (!function_exists('wp_read_image_metadata')) {
		include(ABSPATH.'wp-admin/includes/image.php');
	}
	return media_handle_sideload( $file_array, $post_id, $desc );
}

function aktt_latest_tweet() {
	_deprecated_function(__FUNCTION__, '3.0', 'aktt_shortcode_tweets()');
	echo do_shortcode('[aktt_tweets count="1"]');
}
