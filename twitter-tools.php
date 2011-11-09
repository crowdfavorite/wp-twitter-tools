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

require_once('class-aktt_account.php');
require_once('class-aktt_tweet.php');

class AKTT {
	static $ver = '3.0dev';
	static $enabled = false;
	static $prefix = 'aktt_';
	static $post_type = 'aktt_tweet';
	static $text_domain = 'twitter-tools';
	static $menu_page_slug = 'twitter-tools';
	static $plugin_options_key = 'aktt_v3_settings';
	static $plugin_settings_section_slug = 'aktt_plugin_settings_group';
	static $account_settings_section_slug = 'aktt_account_settings';
	static $cap_options = 'manage_options';
	static $cap_download = 'publish_posts';
	static $admin_notices = array();
	static $default_settings = array();
	static $accounts = array();
	static $debug = false;
	
	/**
	 * Sets whether or not the plugin should be enabled.  Also initialize the plugin's settings.
	 *
	 * @return void
	 */
	static function init() {
		self::$enabled = class_exists('Social');
		if (!self::$enabled) {
			self::add_admin_notice('error', sprintf(__('Twitter Tools relies on the <a href="%s">Social plugin</a>, please install this plugin.', 'twitter-tools'), 'http://wordpress.org/extend/plugins/social/'));
			return;
		}
		
		self::register_post_type();
		self::register_taxonomies();

		// General Hooks
		add_filter('post_type_link', array('AKTT', 'get_tweet_permalink'), 10, 2);
		add_action('social_account_disconnected', array('AKTT', 'social_account_disconnected'), 10, 2);
		add_action('social_broadcast_response', array('AKTT', 'social_broadcast_response'), 10, 3);
		
		// Admin Hooks
		add_action('admin_init', array('AKTT', 'init_settings'));
		add_action('admin_init', array('AKTT', 'admin_request_handler'));
		add_action('admin_notices', array('AKTT', 'admin_notices'));
		add_action('admin_menu', array('AKTT', 'admin_menu'));
		add_filter('plugin_action_links', array('AKTT', 'plugin_action_links'), 10, 2);
		add_action('admin_enqueue_scripts', array('AKTT', 'admin_enqueue_scripts'));
		
		// Cron Hooks
		add_action('social_cron_15', array('AKTT', 'import_tweets'));
		
		/* Set our default settings.  We need to do this at init() so 
		that any text domains (i18n) are registered prior to us setting 
		the labels. */
		self::set_default_settings();
		
		// Set logging to what's in the plugin settings
		self::$debug = self::get_option('debug');
	}
	
	
	/**
	 * Sets the default settings for the plugin
	 *
	 * @return void
	 */
	static function set_default_settings() {
		// Set default settings
		self::$default_settings = array(
			'post_type_admin_ui' => array(
				'value' => false,
				'label' => __('Enable admin UI for tweets', 'twitter-tools'),
				'label_first' => false,
				'type' => 'int',
			),
			'post_type_visibility' => array(
				'value' => false,
				'label' => __('Make imported tweets public', 'twitter-tools'),
				'label_first' => false,
				'type' => 'int',
			),
			'taxonomy_admin_ui' => array(
				'value' => false,
				'label' => __('Enable admin UI for tweet taxonomies', 'twitter-tools'),
				'label_first' => false,
				'type' => 'int',
			),
			'taxonomy_visibility' => array(
				'value' => false,
				'label' => __('Make tweet taxonomies public', 'twitter-tools'),
				'label_first' => false,
				'type' => 'int',
			),
			'debug' => array(
				'value' => 0,
				'label' => __('Enable debug mode', 'twitter-tools'),
				'label_first' => false,
				'type' => 'int',
			),
		);
	}
	
	
	/**
	 * Append a message of a certain type to the admin notices.
	 *
	 * @param string $type 
	 * @param string $msg 
	 * @return void
	 */
	static function add_admin_notice($type, $msg) {
		self::$admin_notices[] = array(
			'type' => $type == 'error' ? $type : 'updated', // If it's not an error, set it to updated
			'msg' => $msg
		);
	}
	
	
	/**
	 * Displays admin notices 
	 *
	 * @return void
	 */
	static function admin_notices() {
		if (is_array(self::$admin_notices)) {
			foreach (self::$admin_notices as $notice) {
				extract($notice);
				?>
				<div class="<?php echo esc_attr($type); ?>">
					<p><?php echo $msg; ?></p>
				</div><!-- /<?php echo esc_html($type); ?> -->
				<?php
			}
		}
	}
	
	
	/**
	 * Registers the aktt_tweet post type
	 *
	 * @return void
	 */
	static function register_post_type() {
		register_post_type(self::$post_type, array(
			'labels' => array(
				'name' => __('Tweets', 'twitter-tools'),
				'singular_name' => __('Tweet', 'twitter-tools')
			),
			'supports' => array(
				'editor',
			),
			'public' => (bool) self::get_option('post_type_visibility'),
			'show_ui' => (bool) self::get_option('post_type_admin_ui'),
			'rewrite' => array(
				'slug' => 'tweets',
				'with_front' => false
			),
		));
		add_permastruct(self::$post_type, '/tweets/%post_id%', false, EP_PERMALINK);
	}
	
	
	/**
	 * Register our taxonomies.
	 * 
	 * @return void
	 */
	static function register_taxonomies() {
		$defaults = array(
			'public' => (bool) self::get_option('taxonomy_visibility'),
			'show_ui' => (bool) self::get_option('taxonomy_admin_ui'),
		);
		$taxonomies = array(
			'aktt_account' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Account', 'twitter-tools'),
					'singular_name' => __('Account', 'twitter-tools')
				),
			)),
			'aktt_mentions' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Mentions', 'twitter-tools'),
					'singular_name' => __('Mention', 'twitter-tools')
				),
			)),
			'aktt_hashtags' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Hashtags', 'twitter-tools'),
					'singular_name' => __('Hashtag', 'twitter-tools')
				),
			)),
			'aktt_types' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Types', 'twitter-tools'),
					'singular_name' => __('Type', 'twitter-tools')
				),
			)),
		);
		foreach ($taxonomies as $tax => $args) {
			register_taxonomy($tax, self::$post_type, $args);
		}
	}
	
	
	/**
	 * Get default setting's value
	 *
	 * @param string $setting 
	 * @return mixed
	 */
	static function get_default_setting($setting) {
		return isset(self::$default_settings[$setting]) ? self::$default_settings[$setting]['value'] : null;
	}
	
	
	/**
	 * Get an option from the DB, and fall back to the default setting
	 *
	 * @param string $setting 
	 * @return mixed
	 */
	static function get_option($key) {
		// Do we have an option?
		$option = get_option(self::$plugin_options_key);
		if (!empty($option) && is_array($option) && isset($option[$key])) {
			$val = $option[$key];
		}
		else { // Get a default
			$val = self::get_default_setting($key);
		}
		return apply_filters(self::$prefix.'get_option', $val, $key);
	}
	
	
	/**
	 * Updates a setting, if === null is passed as the value, it 
	 * picks up the default setting
	 *
	 * @param string $setting 
	 * @param mixed $value 
	 * @return void
	 */
	static function save_setting($key, $value = null) {
		// If it's null, get the default value
		$val = is_null($value) ? self::get_default_setting($key) : $value;
		return update_option(self::$prefix.$key, $val);
	}


	/**
	 * Updates a setting, with a user capability check
	 *
	 * @param string $setting 
	 * @param mixed $value 
	 * @uses current_user_can()
	 * @return void
	 */
	static function form_save_setting($key, $value = null) {
		if (current_user_can(self::$cap_options)) {
			return self::save_setting($key, $value);
		}
	}
	
	
	/**
	 * Change the permalink for the tweets
	 *
	 * @param string $post_link 
	 * @param object $post 
	 * @return string
	 */
	static function get_tweet_permalink($post_link, $post) {
		if ($post->post_type == self::$post_type) {
			$rewritecode = array(
				'%post_id%',
			);
			$rewritereplace = array(
				$post->ID,
			);
			$post_link = str_replace($rewritecode, $rewritereplace, $post_link);
		}
		return $post_link;
	}
	
	
	/**
	 * Prepends a "settings" link for our plugin on the plugins.php page
	 *
	 * @param array $links 
	 * @param string $file -- filename of plugin 
	 * @return array
	 */
	function plugin_action_links($links, $file) {
		if (basename($file) == basename(__FILE__)) {
			$settings_link = '<a href="options-general.php?page='.self::$menu_page_slug.'">'.__('Settings', 'twitter-tools').'</a>';
			array_unshift($links, $settings_link);
		}
		return $links;
	}
	
	
	/**
	 * Adds a link to the "Settings" menu in WP-Admin.
	 */
	public function admin_menu() {
		add_options_page(
			__('Twitter Tools Options', 'twitter-tools'),
			__('Twitter Tools', 'twitter-tools'),
			self::$cap_options,
			self::$menu_page_slug,
			array('AKTT', 'output_settings_page')
		);
	}
	
	static function maybe_create_db_index($col, $key_name = null, $table_name = null) {
		global $wpdb;
		if (empty($key_name)) {
			$key_name = $col;
		}
		if (empty($table_name)) {
			$table_name = $wpdb->posts;
		}
		// Add a GUID index if none exists
		$results = $wpdb->get_results($wpdb->prepare("
			SHOW INDEX
			FROM $table_name
			WHERE KEY_NAME = '%s'
		", $key_name));
		if (!count($results)) {
			$wpdb->query("
				ALTER TABLE $table_name
				ADD INDEX ($col)
			"); // can's use $wpdb->prepare here
		}
	}
	
	/**
	 * Initializes the plugin settings in WP admin, using the Settings API
	 *
	 * @return void
	 */
	static function init_settings() {
		
		// Register our parent setting (it contains an array of all our plugin-wide settings)
		register_setting(
			self::$menu_page_slug, // Page it belongs to
			self::$plugin_options_key, // option name
			array('AKTT', 'sanitize_plugin_settings') // Sanitize callback
		);

		// Register our parent setting (it contains an array of all our plugin-wide settings)
		register_setting(
			self::$menu_page_slug, // Page it belongs to
			AKTT_Account::$settings_option_name, // option name
			array('AKTT', 'sanitize_account_settings') // Sanitize callback
		);


		// Add a section of settings to Twitter Tools' settings page
		add_settings_section(
			self::$plugin_settings_section_slug, // group id
			__('General Plugin Settings', 'twitter-tools'), // title
			array('AKTT', 'output_settings_section_text'), // callback for text
			self::$menu_page_slug // Page Handle
		);
		
		// Add a section for Account setting
		add_settings_section(
			self::$account_settings_section_slug, // group id
			__('Accounts', 'twitter-tools'), // title
			array('AKTT', 'output_account_settings_section'), // callback for text
			self::$menu_page_slug // Page Handle
		);

		// Register our settings' fields with WP
		foreach (self::$default_settings as $setting => $details) {
			// Add the settings to the proper group
			add_settings_field(
				$setting, // unique ID for the field...not necessarily the option_name
				$details['label'],
				array('AKTT', 'output_settings_field'), // Callback to output the actual HTML field
				self::$menu_page_slug, // Page Handle
				self::$plugin_settings_section_slug, // Settings Group
				array(
					'setting' => $setting,
				)
			);
		}
		
	}
	
	
	/**
	 * Sanitization of values
	 *
	 * @param mixed $value 
	 * @return int
	 */
	static function sanitize_plugin_settings($value) {
		self::maybe_create_db_index('guid');
		if (is_array($value)) {
			foreach ($value as $k => $v) {
				$value[$k] = self::sanitize_plugin_setting($k, $v);
			}
		}
		return $value;
	}
	
	/**
	 * Sanitizes the ACCOUNT settings from the Twitter Tools' admin page.
	 * 
	 *	** Option Storage Format **
	 *	
	 *	$option_value = array(
	 *		$this->id => array(
	 *			'settings' => array(
	 *				'post_author' => 0,
	 *				'post_cats' => array(),
	 *				'post_tags' => array(),
	 *				'hashtag' => '',
	 *			),
	 *		),
	 *	);
	 *
	 * @param array $value 
	 * @return array
	 */
	static function sanitize_account_settings($value) {
		if (is_array($value)) {
			foreach ($value as $id => &$acct) {
				// If we don't have a settings array, get rid of it
				if (!isset($acct['settings'])) {
					unset($value[$id]);
					continue;
				}
				
				// Loop over each setting and sanitize
				foreach (array_keys(AKTT_Account::$config) as $setting) {
					$acct['settings'][$setting] = self::sanitize_account_setting($setting, $acct['settings'][$setting]);
				}
			}
		}
		else {
			$value = null;
		}
		return $value;
	}
	
	
	static function sanitize_plugin_setting($setting, $value) {
		return self::sanitize_setting($setting, $value, self::$default_settings[$setting]['type']);
	}
	
	static function sanitize_account_setting($setting, $value) {
		return self::sanitize_setting($setting, $value, AKTT_Account::$config[$setting]['type']);
	}
	
	
	/**
	 * Sanitizes a setting, based on a big switch statement 
	 * that has each setting, and how to clean it.
	 *
	 * @param string $setting 
	 * @param mixed $value 
	 * @param string $type - type of setting (int, etc.)
	 * @return mixed - Clean value **If it matched a switch case**
	 */
	static function sanitize_setting($setting, $value, $type) {
		switch ($type) {
			case 'int':
				$value = is_array($value) ? array_map('intval', $value) : intval($value);
				break;
			case 'no_html':
				$value = is_array($value) ? array_map('wp_filter_nohtml_kses', $value) : wp_filter_nohtml_kses($value);
				break;
			case 'is_tag':
				$term = get_term_by('name', $value, 'post_tag');
				$value = (!$term) ? '' : $term->term_id;
				break;
			case 'is_cat':
				$term = get_term_by('id', $value, 'category');
				$value = (!$term) ? 0 : $term->term_id;
				break;
		}
		return $value;
	}
	
	
	/**
	 * Outputs the plugin's settings form.  Utilizes the "settings" API in WP
	 *
	 * @return void
	 */
	static function output_settings_page() {
?>
		<div class="wrap" id="<?php echo self::$prefix.'options_page'; ?>">
			<?php screen_icon(); ?>
			<h2><?php _e('Twitter Tools', 'twitter-tools'); ?></h2>

<?php
		if (self::$enabled) {
?>
			<a href="<?php echo esc_url(self::get_manual_update_url()); ?>" class="aktt-manual-update button-secondary"><?php _e('Update Tweets Manually', 'twitter-tools'); ?></a>
			
			<form method="post" action="options.php">
			
<?php 
			// Output the nonces, and hidden fields for the page
			settings_fields(self::$menu_page_slug);
			
			// Output the visible settings fields
			do_settings_sections(self::$menu_page_slug);
?>
				
				<input type="submit" class="button-primary" value="<?php _e('Save Settings', 'twitter-tools'); ?>" />
			</form>
<?php
		}
?>
		</div><!-- /wrap -->
<?php
	}
	
	
	/**
	 * Introductory text to the settings section.
	 *
	 * @return void
	 */
	static function output_settings_section_text() {
		echo '<p>'.__('Settings applied to plugin.', 'twitter-tools').'</p>';
	}
	
	
	/**
	 * Outputs a checkbox settings field
	 *
	 * @param array $args 
	 * @return void
	 */
	static function output_settings_field($args) {
		extract($args); // $setting name is passed here
		echo '<input type="checkbox" name="'.esc_attr(self::$plugin_options_key.'['.$setting.']').'" value="1" '.checked('1', self::get_option($setting), false).' />'."\n";
	}
	
	
	/**
	 * Outputs the HTML for the ACCOUNT portion of the settings page.
	 *
	 * @return void
	 */
	static function output_account_settings_section() {
		// Get all the available accounts from Social
		self::get_social_accounts();
?>
		<div id="<?php echo self::$prefix; ?>accounts">
<?php
		if (empty(self::$accounts)) {
?>
			<p class="aktt-none">
				<?php _e('No Accounts.', 'twitter-tools'); ?>
			</p>
<?php
		}
		else {
?>
			<ul id="<?php echo self::$prefix.'account_list'; ?>">
<?php 
			foreach (self::$accounts as $acct) {
				$acct->output_account_config();
			}
?>
			</ul><!-- /<?php echo self::$prefix.'account_list'; ?> -->
<?php
		}
?>
			<p><?php printf(__('Manage Twitter accounts on your <a href="%s">Social settings page</a>.', 'twitter-tools'), admin_url('options-general.php?page=social.php')); ?></p>
		</div><!-- <?php echo self::$prefix.'accounts'; ?> -->
<?php
	}
	
	
	/**
	 * Returns the nonce'd URL for manually kicking off updates
	 *
	 * @return string
	 */
	static function get_manual_update_url() {
		$url = add_query_arg(array('aktt_action' => 'manual_tweet_download'), admin_url('index.php'));
		return wp_nonce_url($url, 'manual_tweet_download');
	}
	
	
	/**
	 * Loads the social twitter accounts into a static variable
	 *
	 * @return void
	 */
	static function get_social_accounts() {
		$social_twitter = Social::instance()->service('twitter');
		
		// If we don't have a Social_Twitter object, get out
		if (is_null($social_twitter)) {
			return;
		}
		
		// If we don't have any Social Twitter accounts, get out
		$social_accounts = $social_twitter->accounts();
		if (empty($social_accounts)) {
			return;
		}
		
		/* Loop over our social twitter accounts and create AKTT_Account objects 
		that will store the various configuration options for the twitter accounts. */
		foreach ($social_accounts as $obj_id => $acct_obj) {
			// If this account has already been assigned, continue on
			if (isset(self::$accounts[$obj_id]) || !$acct_obj->universal()) {
				continue;
			}
			
			/* Call a static method to load the object, so we 
			can ensure it was instantiated properly */
			$o = AKTT_Account::load(&$acct_obj);
			
			// Assign the object, only if we were successfully created
			if (is_a($o, 'AKTT_Account')) {
				self::$accounts[$obj_id] = $o;
			}
		}
	}
	
	/**
	 * Remove an account when it is removed from Social
	 *
	 * @return void
	 */
	static function social_account_disconnected($service, $id) {
		if ($service == 'twitter') {
			$accounts = get_option(AKTT_Account::$settings_option_name);
			if (is_array($accounts) && count($accounts) && isset($accounts[$id])) {
				unset($accounts[$id]);
				update_option(AKTT_Account::$settings_option_name, $accounts);
			}
		}
	}
	
	/**
	 * Iterates over all the twitter accounts in social and downloads and imports the tweets.
	 *
	 * @return void
	 */
	function import_tweets() {
		// load our accounts
		self::get_social_accounts();
		
		// See if we have any accounts to loop over
		if (!is_array(self::$accounts) || empty(self::$accounts)) {
			return;
		}
		
		// iterate over each account and download the tweets
		foreach (self::$accounts as $id => $acct) {
			// Download the tweets for that acct
			if ($acct->get_option('enabled') == 1 && $tweets = $acct->download_tweets()) {
				$acct->save_tweets($tweets);
			}
		}
	}
	
	/**
	 * Create tweet when Social does a broadcast
	 *
	 * @param Social_Response $response 
	 * @param string $key
	 * @param stdClass $post
	 * @return void
	 */
	static function social_broadcast_response($response, $key, $post) {
// get tweet
		$data = $response->body();
		$tweet = $data->response;
// check if it's one of our enabled accounts
		self::get_social_accounts();
		foreach (self::$accounts as $account) {
			if ($account->get_option('enabled') && $account->social_acct->id() == $tweet->user->id) {
// populate AKTT_Tweet object, save
				$t = new AKTT_Tweet($tweet);
				$t->add();
				break;
			}
		}
	}
	
	
	/**
	 * Load JS resources necessary for admin... only on the twitter tools' settings page
	 *
	 * @param string $hook_suffix 
	 * @return void
	 */
	function admin_enqueue_scripts($hook_suffix) {
		add_action('admin_footer', array('AKTT', 'admin_js'));
		if ($hook_suffix == 'settings_page_twitter-tools') {
			wp_enqueue_script('suggest');
			add_action('admin_footer', array('AKTT', 'admin_js_suggest'));
		}
	}
	
	
	/**
	 * Request handler for admin
	 *
	 * @return void
	 */
	function admin_request_handler(){
		if (isset($_GET['aktt_action'])) {
			switch ($_GET['aktt_action']) {
				case 'manual_tweet_download':
					// Permission & nonce checking
					if (!check_admin_referer('manual_tweet_download') || !current_user_can(self::$cap_download)) { 
						wp_die(__('Sorry, try again.', 'twitter-tools'));
					}
					
					self::import_tweets();
					wp_redirect(add_query_arg(array(
						'page' => self::$menu_page_slug,
						'tweets_updated' => '1'),
						admin_url('options-general.php')
					));
					break;
			}
		}
	}
	
	/**
	 * Output the admin-side JavaScript
	 *
	 * @return void
	 */
	static function admin_js() {
?>
<script type="text/javascript">
jQuery(function($) {
	$('a[href="post-new.php?post_type=aktt_tweet"]').hide().parent('li').hide();
});
</script>
<?php
	}

	/**
	 * Output the admin-side JavaScript for auto-suggest
	 *
	 * @return void
	 */
	static function admin_js_suggest() {
?>
<script type="text/javascript">
jQuery(function($) {
	$('.type-ahead').each(function() {
		var tax = $(this).data('tax');
		$(this).suggest(
			ajaxurl + '?action=ajax-tag-search&tax=' + tax,
			{ 
				delay: 500, 
				minchars: 2, 
				multiple: false 
			}
		);
	});
});
</script>
<?php
	}
	
	static function admin_css() {
?>
<style type="text/css">
</style>
<?php
	}
	
	function log($msg) {
		if (self::$debug) {
			error_log($msg);
		}
	}
}
add_action('init', array('AKTT', 'init'), 0);

/********************
*  Helper Functions *
********************/
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
