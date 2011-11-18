<?php
/*
Plugin Name: Twitter Tools
Plugin URI: http://crowdfavorite.com/wordpress/plugins/twitter-tools/
Description: A complete integration between your WordPress blog and Twitter. Bring your tweets into your blog and pass your blog posts to Twitter. Show your tweets in your sidebar. Relies on <a href="http://wordpress.org/extend/plugins/social/">Social</a>.
Version: 3.0dev
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

// Copyright (c) 2007-2011 Crowd Favorite, Ltd. All rights reserved.
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

require_once('classes/aktt.php');
require_once('classes/aktt_account.php');
require_once('classes/aktt_tweet.php');
require_once('widget.php');

add_action('init', array('AKTT', 'init'), 0);

// TODO - shortcode

if (!function_exists('trim_add_elipsis')) {
	function trim_add_elipsis($string, $limit = 100) {
		if (strlen($string) > $limit) {
			$string = substr($string, 0, $limit)."...";
		}
		return $string;
	}
}

/**
 * You must flush the rewrite rules to activate this action.
 */
// function aktt_add_tweet_rewrites() {
// 	global $wp_rewrite;
// 
// 	$rules = $wp_rewrite->generate_rewrite_rules('/tweets/%post_id%', EP_PERMALINK);
// 	
// 	foreach ($rules as &$rule) {
// 		$rule = str_replace('index.php?', 'index.php?post_type=aktt_tweet&', $rule);
// 	}
// 
// 	// All, paginated
// 	$rules['tweets/page/([0-9]+)/?$'] = 'index.php?post_type=aktt_tweet&paged=$matches[1]';
// 	// all
// 	$rules['tweets/?$'] = 'index.php?post_type=aktt_tweet';
// 
// 	$wp_rewrite->rules = $rules + $wp_rewrite->rules;
// }
// add_action('generate_rewrite_rules', 'aktt_add_tweet_rewrites');
