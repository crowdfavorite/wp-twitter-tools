<?php
/*
Plugin Name: Twitter Tools
Plugin URI: http://crowdfavorite.com/wordpress/plugins/twitter-tools/
Description: A complete integration between your WordPress blog and Twitter. Bring your tweets into your blog and pass your blog posts to Twitter. Show your tweets in your sidebar.
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

/*

- @DONE register post type
- @DONE register aktt_mentions taxonomy
- @DONE register aktt_hashtags taxonomy
- @DONE register aktt_status_types taxonomy (retweet, reply)

## Config (per Twitter account)

- check for Social to be installed, show "get this" form if not
- enable tweet admin
- enable public tweet access
- blog post author
- blog post cats
- blog post tags
- tweet hashtags
- include @replies (starts with @)
- include RTs


## Downloading

- use WP_Http
- schedule with WP-CRON
	- option to trigger solely via GET param (for real CRON)
- pull tweets on new GET request (zero timeout, a-la WP-CRON)
- store as post_type = tweet
- set post format = status (if theme supports it)
- ability to create blog post
	- store tweet ID as post meta, store post ID as well?
- support RTs?
- Manual update tweets button


## Sidebar Widget

- # of recent items to show
- show RTs
- show @replies
- AJAX pagination of tweets
- shortcode for recent items list (accept # of items as arg)


## Upgrade

- convert data from Twitter Tools to custom posts
- compatibility with existing TT plugins?



- check for social to be installed @done


*/

// Make sure we have our AKTT_Account definition around
// Bring in requisite classes
require_once('class-aktt_account.php');
require_once('class-aktt_tweet.php');

class AKTT {
	static $ver = '3.0dev';
	static $enabled = false;
	static $prefix = 'aktt_';
	static $post_type = 'aktt_tweet';
	static $text_domain = 'twitter-tools';
	static $menu_page_slug = 'twitter-tools';
	static $plugin_options_key = 'aktt_plugin_settings';
	static $plugin_settings_section_slug = 'aktt_plugin_settings_group';
	static $account_settings_section_slug = 'aktt_account_settings';
	static $cap_options = 'manage_options';
	static $cap_download = 'publish_posts';
	static $admin_notices = array();
	static $default_settings = array();
	static $accounts = array();
	static $debug = false;
	
	static function add_actions() {
		// General Hooks
		add_action('init', array('AKTT', 'init'), 0);
		add_action('init', array('AKTT', 'register_post_type'));
		add_action('init', array('AKTT', 'register_taxonomies'));
		add_filter('post_type_link', array('AKTT', 'get_tweet_permalink'), 10, 2);
		
		// Admin Hooks
		add_action('admin_init', array('AKTT', 'init_settings'));
		add_action('admin_init', array('AKTT', 'admin_request_handler'));
		add_action('admin_notices', array('AKTT', 'admin_notices'));
		add_action('admin_menu', array('AKTT', 'admin_menu'));
		add_filter('plugin_action_links', array('AKTT', 'plugin_action_links'), 10, 2);
		add_action('admin_enqueue_scripts', array('AKTT', 'admin_enqueue_scripts'));
		
		// Cron Hooks
		add_action('social_cron_15', array('AKTT', 'import_tweets'));
	}
	
	
	/**
	 * Sets whether or not the plugin should be enabled.  Also initialize the plugin's settings.
	 *
	 * @return void
	 */
	static function init() {
		AKTT::$enabled = class_exists('Social');
		if (!AKTT::$enabled) {
			AKTT::add_admin_notice('error', sprintf(__('Twitter Tools relies on the <a href="%s">Social plugin</a>, please install this plugin.', 'twitter-tools'), 'http://wordpress.org/extend/plugins/social/'));
			return;
		}
		
		/* Set our default settings.  We need to do this at init() so 
		that any text domains (i18n) are registered prior to us setting 
		the labels. */
		AKTT::set_default_settings();
		
		// Set logging to what's in the plugin settings
		AKTT::$debug = AKTT::get_option('debug');
	}
	
	
	/**
	 * Sets the default settings for the plugin
	 *
	 * @return void
	 */
	static function set_default_settings() {
		// Set default settings
		AKTT::$default_settings = array(
			'post_type_admin_ui' => array(
				'value' => false,
				'label' => __('Enable admin UI for post type?', 'twitter-tools'),
				'type'	=> 'int',
			),
			'post_type_visibility' => array(
				'value' => false,
				'label' => __('Should Twitter Tools post type be public?', 'twitter-tools'),
				'type'	=> 'int',
			),
			'taxonomy_admin_ui' => array(
				'value' => false,
				'label' => __('Enable admin UI for custom taxonomies?', 'twitter-tools'),
				'type'	=> 'int',
			),
			'taxonomy_visibility' => array(
				'value' => false,
				'label' => __('Should Twitter Tools custom taxonomies be public?', 'twitter-tools'),
				'type'	=> 'int',
			),
			'debug' => array(
				'value' => 0,
				'label' => __('Enable debug mode?', 'twitter-tools'),
				'type' 	=> 'int',
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
		AKTT::$admin_notices[] = array(
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
		if (is_array(AKTT::$admin_notices)) {
			foreach (AKTT::$admin_notices as $notice) {
				extract($notice);
				?>
				<div class="<?php echo esc_attr($type); ?>">
					<p><?php echo $msg; ?></p>
				</div><!-- /<?php echo esc_attr($type); ?> -->
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
		register_post_type(AKTT::$post_type, array(
			'labels' => array(
				'name' => __('Tweets', 'twitter-tools'),
				'singular_name' => __('Tweet', 'twitter-tools')
			),
			'supports' => array(
				'editor',
			),
			'public' => (bool) AKTT::get_option('post_type_visibility'),
			'show_ui' => (bool) AKTT::get_option('post_type_admin_ui'),
			'rewrite' => array(
				'slug' => 'tweets',
				'with_front' => false
			),
		));
		add_permastruct(AKTT::$post_type, '/tweets/%post_id%', false, EP_PERMALINK);
	}
	
	
	/**
	 * Register our taxonomies.
	 * 
	 * @return void
	 */
	static function register_taxonomies() {
		$defaults = array(
			'public' => (bool) AKTT::get_option('taxonomy_visibility'),
			'show_ui' => (bool) AKTT::get_option('taxonomy_admin_ui'),
		);
		$taxonomies = array(
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
			'aktt_status_types' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Types', 'twitter-tools'),
					'singular_name' => __('Type', 'twitter-tools')
				),
			)),
		);
		foreach ($taxonomies as $tax => $args) {
			register_taxonomy($tax, AKTT::$post_type, $args);
		}
	}
	
	
	/**
	 * Get default setting's value
	 *
	 * @param string $setting 
	 * @return mixed
	 */
	static function get_default_setting($setting) {
		return isset(AKTT::$default_settings[$setting]) ? AKTT::$default_settings[$setting]['value'] : null;
	}
	
	
	/**
	 * Get an option from the DB, and fall back to the default setting
	 *
	 * @param string $setting 
	 * @return mixed
	 */
	static function get_option($key) {
		// Do we have an option?
		$option = get_option(AKTT::$plugin_options_key);
		if (!empty($option) && is_array($option) && isset($option[$key])) {
			$val = $option[$key];
		}
		else { // Get a default
			$val = AKTT::get_default_setting($key);
		}
		return apply_filters(AKTT::$prefix.'get_option', $val, $key);
	}
	
	
	// /** @DEPRECATED for using one large option instead.  
	//  * Retrieves the actual WordPress option name for a passed setting.
	//  * For the time being, it just appends the "aktt_" prefix.
	//  *
	//  * @param string $setting 
	//  * @return string
	//  */
	// static function get_option_name_for_setting($setting) {
	// 	return AKTT::$prefix.$setting;
	// }
	
	
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
		$val = is_null($value) ? AKTT::get_default_setting($key) : $value;
		return update_option(AKTT::$prefix.$key, $val);
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
		if (current_user_can(AKTT::$cap_options)) {
			return AKTT::save_setting($key, $value);
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
		if ($post->post_type == AKTT::$post_type) {
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
			$settings_link = '<a href="options-general.php?page='.AKTT::$menu_page_slug.'">'.__('Settings', 'twitter-tools').'</a>';
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
			AKTT::$cap_options,
			AKTT::$menu_page_slug,
			array('AKTT', 'output_settings_page')
		);
	}
	
	
	/**
	 * Initializes the plugin settings in WP admin, using the Settings API
	 *
	 * @return void
	 */
	static function init_settings() {
		
		
		// Register our parent setting (it contains an array of all our plugin-wide settings)
		register_setting(
			AKTT::$menu_page_slug, // Page it belongs to
			AKTT::$plugin_options_key, // option name
			array('AKTT', 'sanitize_plugin_settings') // Sanitize callback
		);

		// Register our parent setting (it contains an array of all our plugin-wide settings)
		register_setting(
			AKTT::$menu_page_slug, // Page it belongs to
			AKTT_Account::$settings_option_name, // option name
			array('AKTT', 'sanitize_account_settings') // Sanitize callback
		);


		// Add a section of settings to Twitter Tools' settings page
		add_settings_section(
			AKTT::$plugin_settings_section_slug, // group id
			__('General Plugin Settings', 'twitter-tools'), // title
			array('AKTT', 'output_settings_section_text'), // callback for text
			AKTT::$menu_page_slug // Page Handle
		);
		
		// Add a section for Account setting
		add_settings_section(
			AKTT::$account_settings_section_slug, // group id
			__('Accounts', 'twitter-tools'), // title
			array('AKTT', 'output_account_settings_section'), // callback for text
			AKTT::$menu_page_slug // Page Handle
		);

		// Register our settings' fields with WP
		foreach (AKTT::$default_settings as $setting => $details) {
			// Add the settings to the proper group
			add_settings_field(
				$setting, // unique ID for the field...not necessarily the option_name
				$details['label'],
				array('AKTT', 'output_settings_field'), // Callback to output the actual HTML field
				AKTT::$menu_page_slug, // Page Handle
				AKTT::$plugin_settings_section_slug, // Settings Group
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
		foreach ($value as $k => $v) {
			$value[$k] = AKTT::sanitize_plugin_setting($k, $v);
		}
		return $value;
	}
	
	/**
	 * Sanitizes the ACCOUNT settings from the Twitter Tools' admin page.
	 * 
	 * 	** Option Storage Format **
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
		foreach ($value as $id => &$acct) {
			// If we don't have a settings array, get rid of it
			if (!isset($acct['settings'])) {
				unset($value[$id]);
				continue;
			}
			
			// Loop over each setting and sanitize
			foreach (array_keys(AKTT_Account::$config) as $setting) {
				$acct['settings'][$setting] = AKTT::sanitize_account_setting($setting, $acct['settings'][$setting]);
			}
		}
		
		return $value;
	}
	
	
	static function sanitize_plugin_setting($setting, $value) {
		return AKTT::sanitize_setting($setting, $value, AKTT::$default_settings[$setting]['type']);
	}
	
	static function sanitize_account_setting($setting, $value) {
		return AKTT::sanitize_setting($setting, $value, AKTT_Account::$config[$setting]['type']);
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
		<div class="wrap" id="<?php echo AKTT::$prefix.'options_page'; ?>">
			<?php screen_icon(); ?>
			<h2><?php _e('Twitter Tools', 'twitter-tools'); ?></h2>
			
			<a href="<?php echo esc_url(AKTT::get_manual_update_url()); ?>" class="aktt-manual-update button-secondary"><?php _e('Update Tweets Manually', 'twitter-tools'); ?></a>
			
			<form method="post" action="options.php">
			
<?php 
/* Output the nonces, and hidden fields for the page*/
settings_fields(AKTT::$menu_page_slug);

/* Output the visible settings fields */
do_settings_sections(AKTT::$menu_page_slug);
?>
				
				<input type="submit" class="button-primary" label="<?php _e('Save Settings', 'twitter-tools'); ?>" />
			</form>
			
		</div><!-- /wrap -->
		<?php
	}
	
	
	/**
	 * Introductory text to the settings section.
	 *
	 * @return void
	 */
	static function output_settings_section_text() {
		?>
		<p>
			<?php _e('Settings applied to plugin.', 'twitter-tools'); ?>
		</p>
		<?php 
	}
	
	
	/**
	 * Outputs a checkbox settings field
	 *
	 * @param array $args 
	 * @return void
	 */
	static function output_settings_field($args) {
		extract($args); // $setting name is passed here
		?>
		<input type="checkbox" name="<?php echo esc_attr(AKTT::$plugin_options_key.'['.$setting.']'); ?>" value="1"<?php checked('1', AKTT::get_option($setting)); ?> />
		<?php
	}
	
	
	/**
	 * Outputs the HTML for the ACCOUNT portion of the settings page.
	 *
	 * @return void
	 */
	static function output_account_settings_section() {
		// Get all the available accounts from Social
		AKTT::get_social_accounts();

		?>
		<div id="<?php echo AKTT::$prefix.'accounts'; ?>">
		<?php
			if (empty(AKTT::$accounts)) {
				?>
				<p class="updated">
					<?php printf(__('Add an account on the <a href="%s">Social settings page</a>.', 'twitter-tools'), admin_url('options-general.php?page=social.php')); ?>
				</p>
				<?php
			}
			else {
				?>
				<ul id="<?php echo AKTT::$prefix.'account_list'; ?>">
					<?php 
					foreach (AKTT::$accounts as $acct) {
						$acct->output_account_config();
					}
					?>
				</ul><!-- /<?php echo AKTT::$prefix.'account_list'; ?> -->
				<?php
			}
			?>
			
		</div><!-- <?php echo AKTT::$prefix.'accounts'; ?> -->
		<?php
	}
	
	
	/**
	 * Returns the nonce'd URL for manually kicking off updates
	 *
	 * @return string
	 */
	static function get_manual_update_url() {
		$url = add_query_arg(array('aktt_action' => 'manual_tweet_download'), admin_url());
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
			if (isset(AKTT::$accounts[$obj_id])) { continue; }
			
			/* Call a static method to load the object, so we 
			can ensure it was instantiated properly */
			$o = AKTT_Account::load(&$acct_obj);
			
			// Assign the object, only if we were successfully created
			if (is_a($o, 'AKTT_Account')) {
				AKTT::$accounts[$obj_id] = $o;
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
		AKTT::get_social_accounts();
		
		// See if we have any accounts to loop over
		if (!is_array(AKTT::$accounts) || empty(AKTT::$accounts)) {
			return;
		}
		
		// iterate over each account and download the tweets
		foreach (AKTT::$accounts as $id => $acct) {
			// Download the tweets for that acct
			if ($tweets = $acct->download_tweets()) {
				$acct->save_tweets($tweets);
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
		if ($hook_suffix == 'settings_page_twitter-tools') {
			wp_enqueue_script('suggest');
			add_action('admin_footer', array('AKTT', 'admin_js'));
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
				case 'admin_js':
					AKTT::admin_js();
					exit;
					break;
				case 'manual_tweet_download':
					// Permission checking
					if (!check_admin_referer('manual_tweet_download') || !current_user_can(AKTT::$cap_download)) { 
						wp_die(__('Sorry, try again.', 'twitter-tools'));
					}
					
					AKTT::import_tweets();
					wp_redirect(add_query_arg(array(
						'page' => AKTT::$menu_page_slug,
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
	
	function log($msg) {
		if (AKTT::$debug) {
			error_log($msg);
		}
	}
}
AKTT::add_actions();

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
