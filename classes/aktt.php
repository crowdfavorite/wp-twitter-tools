<?php

class AKTT {
	// settings: aktt_v3_settings
	static $ver = '3.1';
	static $enabled = false;
	static $prefix = 'aktt_';
	static $post_type = 'aktt_tweet';
	static $text_domain = 'twitter-tools';
	static $menu_page_slug = 'twitter-tools';
	static $plugin_settings_section_slug = 'aktt_plugin_settings_group';
	static $account_settings_section_slug = 'aktt_account_settings';
	static $cap_options = 'manage_options';
	static $cap_download = 'publish_posts';
	static $admin_notices = array();
	static $settings = array();
	static $accounts = array();
	static $debug = false;
	
	/**
	 * Sets whether or not the plugin should be enabled.  Also initialize the plugin's settings.
	 *
	 * @return void
	 */
	static function after_setup_theme() {
		self::add_thumbnail_support();
	}
	
	static function add_thumbnail_support() {
		$thumbnails = get_theme_support('post-thumbnails');
		if (is_array($thumbnails)) {
			add_theme_support('post-thumbnails', array_merge($thumbnails[0], array(self::$post_type)));
		}
		else if (!$thumbnails) {
			add_theme_support('post-thumbnails', array(self::$post_type));
		}
		// else already enabled for all post types
	}

	/**
	 * Sets whether or not the plugin should be enabled.  Also initialize the plugin's settings.
	 *
	 * @return void
	 */
	static function init() {
		add_action('admin_notices', array('AKTT', 'admin_notices'));

		self::$enabled = class_exists('Social');
		if (!self::$enabled) {
			self::add_admin_notice(sprintf(__('Twitter Tools relies on the <a href="%s">Social plugin</a>, please install this plugin.', 'twitter-tools'), 'http://wordpress.org/extend/plugins/social/'), 'error');
			return;
		}
		
		/* Set our default settings.  We need to do this at init() so 
		that any text domains (i18n) are registered prior to us setting 
		the labels. */
		self::set_default_settings();
		
		self::register_post_type();
		self::register_taxonomies();

		add_shortcode('aktt_tweets', 'aktt_shortcode_tweets');
		add_shortcode('aktt_tweet', 'aktt_shortcode_tweet');

		// General Hooks
		add_action('wp', array('AKTT', 'controller'), 1);
		add_filter('the_post', array('AKTT', 'the_post'));
		add_filter('the_posts', array('AKTT', 'the_posts'));
		add_action('social_account_disconnected', array('AKTT', 'social_account_disconnected'), 10, 2);
		add_action('social_broadcast_response', array('AKTT', 'social_broadcast_response'), 10, 3);
		
		// Admin Hooks
		add_action('admin_init', array('AKTT', 'init_settings'), 0);
		add_action('admin_init', array('AKTT', 'admin_controller'), 1);
		add_action('admin_menu', array('AKTT', 'admin_menu'));
		add_filter('plugin_action_links', array('AKTT', 'plugin_action_links'), 10, 2);
		add_action('admin_enqueue_scripts', array('AKTT', 'admin_enqueue_scripts'));
		
		// Cron Hooks
		add_action('socialcron15', array('AKTT', 'import_tweets'));
		add_action('aktt_backfill_tweets', array('AKTT', 'backfill_tweets'));
		
		// Set logging to admin screen settings
		self::$debug = self::option('debug');
	}
	
	
	/**
	 * Sets the default settings for the plugin
	 *
	 * @return void
	 */
	static function set_default_settings() {
		// Set default settings
		$yn_options = array(
			'1' => __('Yes', 'twitter-tools'),
			'0' => __('No', 'twitter-tools')
		);
		$settings = array(
			'tweet_admin_ui' => array(
				'name' => 'tweet_admin_ui',
				'value' => 1,
				'label' => __('Show admin screens for tweets', 'twitter-tools'),
				'type' => 'radio',
				'options' => $yn_options,
			),
			'tweet_visibility' => array(
				'name' => 'tweet_visibility',
				'value' => 1,
				'label' => __('Create URLs for tweets', 'twitter-tools'),
				'type' => 'radio',
				'options' => array(
					'1' => sprintf(__('Yes <span class="help">(%s)</span>', 'twitter-tools'), home_url('tweet/{tweet-id}')),
					'0' => __('No', 'twitter-tools')
				),
			),
			'credit' => array(
				'name' => 'credit',
				'value' => 1,
				'label' => __('Give Twitter Tools credit', 'twitter-tools'),
				'type' => 'radio',
				'options' => $yn_options,
			),
			'debug' => array(
				'name' => 'debug',
				'value' => 0,
				'label' => __('Debug logging', 'twitter-tools'),
				'type' => 'radio',
				'options' => array(
					'0' => __('Disabled', 'twitter-tools'),
					'1' => __('Enabled <span class="help">(written to the PHP error log)</span>', 'twitter-tools'),
				),
			),
		);
		self::$settings = apply_filters('aktt_default_settings', $settings);
	}
	
	
	/**
	 * Append a message of a certain type to the admin notices.
	 *
	 * @param string $msg 
	 * @param string $type 
	 * @return void
	 */
	static function add_admin_notice($msg, $type = 'updated') {
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
				'thumbnail',
			),
			'public' => (bool) self::option('tweet_visibility'),
			'show_ui' => (bool) self::option('tweet_admin_ui'),
			'rewrite' => array(
				'slug' => 'tweets',
				'with_front' => false
			),
			'has_archive' => true,
		));
	}
	
	
	/**
	 * Register our taxonomies.
	 * 
	 * @return void
	 */
	static function register_taxonomies() {
		$defaults = array(
			'public' => (bool) self::option('tweet_visibility'),
			'show_ui' => (bool) self::option('tweet_admin_ui'),
		);
		$taxonomies = array(
			'aktt_accounts' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Accounts', 'twitter-tools'),
					'singular_name' => __('Account', 'twitter-tools')
				),
				'rewrite' => array(
					'slug' => 'tweet-accounts',
					'with_front' => false
				),
			)),
			'aktt_mentions' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Mentions', 'twitter-tools'),
					'singular_name' => __('Mention', 'twitter-tools')
				),
				'rewrite' => array(
					'slug' => 'tweet-mentions',
					'with_front' => false
				),
			)),
			'aktt_hashtags' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Hashtags', 'twitter-tools'),
					'singular_name' => __('Hashtag', 'twitter-tools')
				),
				'rewrite' => array(
					'slug' => 'tweet-hashtags',
					'with_front' => false
				),
			)),
			'aktt_types' => array_merge($defaults, array(
				'labels' => array(
					'name' => __('Types', 'twitter-tools'),
					'singular_name' => __('Type', 'twitter-tools')
				),
				'rewrite' => array(
					'slug' => 'tweet-types',
					'with_front' => false
				),
				'public' => false,
				'show_ui' => false,
			)),
		);
		foreach ($taxonomies as $tax => $args) {
			register_taxonomy($tax, self::$post_type, $args);
		}
	}
	
	
	/**
	 * Get or update an option from the DB, and fall back to the default setting
	 *
	 * @param string $setting 
	 * @return mixed
	 */
	static function option($key, $value = null) {
		// Do we have an option?
		$option = get_option('aktt_v3_settings');
		if (!is_null($value)) {
			$option[$key] = $value;
			return update_option('aktt_v3_settings', $option);
		}
		if (!empty($option) && is_array($option) && isset($option[$key])) {
			$val = $option[$key];
		}
		else { // Get a default
			$val = isset(self::$settings[$key]) ? self::$settings[$key]['value'] : null;
		}
		return apply_filters('aktt_get_option', $val, $key);
	}
	
	
	/**
	 * Utility function to get tweets (used by shortcode, widget, etc.)
	 *
	 * @param array $args 
	 * @return array
	 */
	static function get_tweets($args) {
		$defaults = array(
			'account' => array(),
			'id' => null,
			'count' => 5,
			'offset' => 0,
			'mentions' => array(),
			'hashtags' => array(),
			'include_rts' => 0,
			'include_replies' => 0,
		);
		$taxonomies = array(
			'aktt_accounts' => array(
				'var' => 'account',
				'strip' => array()
			),
			'aktt_hashtags' => array(
				'var' => 'hashtags',
				'strip' => array('#')
			),
			'aktt_mentions' => array(
				'var' => 'mentions',
				'strip' => array('@')
			)
		);
		foreach ($taxonomies as $data) {
			$tax = $data['var'];
			$strip = $data['strip'];
			if (isset($args[$tax])) {
				$terms = array();
				foreach(explode(',', $args[$tax]) as $term) {
					$term = trim(str_replace($strip, '', $term));
					if (!empty($term)) {
						$terms[] = $term;
					}
				}
				$args[$tax] = $terms;
			}
		}
		$params = array_merge($defaults, $args);
		$query_data = array(
			'post_type' => 'aktt_tweet',
			'posts_per_page' => $params['count'],
			'offset' => $params['offset'],
		);
// set tweet ID
		if (!empty($params['id'])) {
			$query_data['meta_query'] = array(array(
				'key' => '_aktt_tweet_id',
				'value' => $params['id'],
				'compare' => '='
			));
		}
		else {
// process tax data
			$tax_query = array(
				'relation' => 'AND'
			);
// set accounts, mentions, hashtags
			foreach ($taxonomies as $tax => $data) {
				$var = $data['var'];
				if (isset($params[$var]) && count($params[$var])) {
					$query = array(
						'taxonomy' => $tax,
						'field' => 'slug',
						'terms' => array()
					);
					foreach ($params[$var] as $term) {
						$query['terms'][] = $term;
					}
					$tax_query[] = $query;
				}
			}
// always hide broadcasts - can be overridden with filter below
			$type_terms = array(
				'social-broadcast'
			);
// other exclusions - this is a NOT IN query
			if (!$params['include_rts']) {
				$type_terms[] = 'retweet';
			}
			if (!$params['include_replies']) {
				$type_terms[] = 'reply';
			}
			$tax_query[] = array(
				'taxonomy' => 'aktt_types',
				'field' => 'slug',
				'terms' => $type_terms,
				'operator' => 'NOT IN'
			);
			$query_data['tax_query'] = $tax_query;
		}
		$query = new WP_Query(apply_filters('aktt_get_tweets', $query_data));
		return $query->posts;
	}
	
	/**
	 * Attach tweet data to post and replace entities in the post content
	 *
	 * @param stdClass $post
	 * @return stdClass
	 */
	static function the_post($post) {
		if ($post->post_type == self::$post_type && empty($post->tweet)) {
			if ($raw_data = get_post_meta($post->ID, '_aktt_tweet_raw_data', true)) {
				$post->tweet = new AKTT_Tweet(json_decode($raw_data));
				$post->post_content = $post->tweet->link_entities();
			}
			if (has_post_thumbnail($post->ID)) {
				$size = apply_filters('aktt_featured_image_size', 'medium');
				$post->post_content .= "\n\n".get_the_post_thumbnail(null, $size);
			}
		}
		return $post;
	}
	
	/**
	 * Attach tweet data to posts
	 *
	 * @param array $posts
	 * @return array
	 */
	static function the_posts($posts) {
		foreach ($posts as &$post) {
			AKTT::the_post($post);
		}
		return $posts;
	}
	
	/**
	 * Prepends a "settings" link for our plugin on the plugins.php page
	 *
	 * @param array $links 
	 * @param string $file -- filename of plugin 
	 * @return array
	 */
	function plugin_action_links($links, $file) {
		if (basename($file) == basename(AKTT_FILE)) {
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
			array('AKTT', 'settings_page')
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
			'aktt_v3_settings', // option name
			array('AKTT', 'sanitize_plugin_settings') // Sanitize callback
		);
		
		// Register our account settings
		register_setting(
			self::$menu_page_slug, // Page it belongs to
			'aktt_v3_accounts', // option name
			array('AKTT', 'sanitize_account_settings') // Sanitize callback
		);
		
	}
	
	
	/**
	 * Sanitization of values
	 *
	 * @param mixed $value 
	 * @return int
	 */
	static function sanitize_plugin_settings($value) {
		self::maybe_create_db_index('guid');
		flush_rewrite_rules(false);
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
				foreach (array_keys(AKTT_Account::$settings) as $key) {
					if (!isset($acct['settings'][$key])) {
						$acct['settings'][$key] = null;
					}
					$acct['settings'][$key] = self::sanitize_account_setting($key, $acct['settings'][$key]);
				}
			}
		}
		else {
			$value = null;
		}
		return $value;
	}
	
	
	static function sanitize_plugin_setting($key, $value) {
		return self::sanitize_setting($key, $value, self::$settings[$key]['type']);
	}
	
	static function sanitize_account_setting($key, $value) {
		return self::sanitize_setting($key, $value, AKTT_Account::$settings[$key]['type']);
	}
	
	
	/**
	 * Sanitizes a setting, based on a big switch statement 
	 * that has each setting, and how to clean it.
	 *
	 * @param string $key 
	 * @param mixed $value 
	 * @param string $type - type of setting (int, etc.)
	 * @return mixed - Clean value **If it matched a switch case**
	 */
	static function sanitize_setting($key, $value, $type) {
		switch ($type) {
			case 'int':
				$value = is_array($value) ? array_map('intval', $value) : intval($value);
				break;
			case 'no_html':
				$value = is_array($value) ? array_map('wp_filter_nohtml_kses', $value) : wp_filter_nohtml_kses($value);
				break;
			case 'tags':
				$value = trim($value);
				if (!empty($value)) {
					$tags_clean = array();
					$tags_input = array_map('trim', explode(',', $value));
					foreach ($tags_input as $tag) {
						if (!empty($tag)) {
							$tags_clean[] = $tag;
							if (!get_term_by('name', $tag, 'post_tag')) {
								wp_insert_term($tag, 'post_tag');
							}
						}
					}
					unset($tags_input);
					$value = implode(', ', $tags_clean);
				}
				break;
			case 'is_cat':
				$term = get_term_by('id', $value, 'category');
				$value = (!$term) ? 0 : $term->term_id;
				break;
			default:
				$value = apply_filters('aktt_sanitize_setting', $value, $key, $type);
		}
		return $value;
	}
	
	
	/**
	 * Outputs the plugin's settings form.  Utilizes the "settings" API in WP
	 *
	 * @return void
	 */
	static function settings_page() {
		global $wpdb;
		$wpdb->aktt = $wpdb->prefix.'ak_twitter';
		$upgrade_needed = in_array($wpdb->aktt, $wpdb->get_col("
			SHOW TABLES
		"));
		if ($upgrade_needed) {
			$upgrade_col = false;
			$cols = $wpdb->get_results("
				DESCRIBE $wpdb->aktt
			");
			foreach ($cols as $col) {
				if ($col->Field == 'upgrade_30') {
					$upgrade_col = true;
					break;
				}
			}
			if ($upgrade_col) {
				$upgrade_needed = (bool) $wpdb->get_var("
					SELECT COUNT(*)
					FROM $wpdb->aktt
					WHERE upgrade_30 = 0
				");
			}
		}
// check to see if CRON for backfilling data is scheduled
		if (wp_next_scheduled('aktt_backfill_tweets') === false) {
// check to see if it should be
			$query = new WP_Query(array(
				'post_type' => AKTT::$post_type,
				'posts_per_page' => 10,
				'meta_key' => '_aktt_30_backfill_needed',
			));
			if (count($query->posts)) {
// schedule
				wp_schedule_event(time() + 900, 'hourly', 'aktt_backfill_tweets');
			}
			unset($query);
		}
		self::get_social_accounts();
		include(AKTT_PATH.'/views/admin.php');
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
			$o = AKTT_Account::load($acct_obj);
			
			// Assign the object, only if we were successfully created
			if (is_a($o, 'AKTT_Account')) {
				self::$accounts[$obj_id] = $o;
			}
		}
	}
	
	/**
	 * Return the first account from the list, at random.
	 *
	 * @return mixed AKTT_Account object|bool
	 */
	static function default_account() {
		self::get_social_accounts();
		if (count(self::$accounts)) {
			foreach (self::$accounts as $account) {
				if ($account->option('enabled')) {
					return $account;
				}
			}
		}
		return false;
	}
	
	/**
	 * Remove an account when it is removed from Social
	 *
	 * @return void
	 */
	static function social_account_disconnected($service, $id) {
		if ($service == 'twitter') {
			$accounts = get_option('aktt_v3_accounts');
			if (is_array($accounts) && count($accounts) && isset($accounts[$id])) {
				$account = Social::instance()->service('twitter')->account($id);
				// If the account being removed was only a universal account, it will no longer
				// be available (false). If it is still around as a personal account (but is not
				// a universal account), then the !universal() check will handle that.
				if ($account === false or !$account->universal()) {
					unset($accounts[$id]);
					update_option('aktt_v3_accounts', $accounts);
				}
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
			if ($acct->option('enabled')) {
				// could time out with lots of accounts, so a new request for each
				$url = home_url('index.php').'?'.http_build_query(array(
					'aktt_action' => 'download_account_tweets',
					'acct_id' => $id,
					'social_api_key' => Social::option('system_cron_api_key')
				), null, '&');
				self::log('Downloading tweets for '.$acct->social_acct->name().': '.$url);
				wp_remote_get(
					$url,
					array(
						'timeout' => 0.01,
						'blocking' => false,
						'sslverify' => apply_filters('https_local_ssl_verify', true),
					)
				);
			}
		}
	}
	
	
	/**
	 * Find 10 tweets, backfill the data from Twitter
	 *
	 * @param int $count 
	 * @return bool
	 */
	function backfill_tweets($count = 10) {
		self::log('#### Backfilling tweets ####');
		$query = new WP_Query(array(
			'post_type' => AKTT::$post_type,
			'posts_per_page' => 10,
			'meta_key' => '_aktt_30_backfill_needed',
		));
		if (!count($query->posts)) {
			if (($timestamp = wp_next_scheduled('aktt_backfill_tweets')) !== false) {
				wp_unschedule_event($timestamp, 'aktt_backfill_tweets');
			}
			return false;
		}
		foreach ($query->posts as $post) {
			$tweet_id = get_post_meta($post->ID, '_aktt_tweet_id', true);
			if (empty($tweet_id)) {
				continue;
			}
			$url = home_url('index.php').'?'.http_build_query(array(
				'aktt_action' => 'backfill_tweet_data',
				'tweet_id' => $tweet_id,
				'social_api_key' => Social::option('system_cron_api_key')
			), null, '&');
			self::log('Backfilling tweet '.$tweet_id.' '.$url);
			wp_remote_get(
				$url,
				array(
					'timeout' => 0.01,
					'blocking' => false,
					'sslverify' => apply_filters('https_local_ssl_verify', true),
				)
			);
		}
		return true;
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
			if ($account->option('enabled') && $account->social_acct->id() == $tweet->user->id) {
// populate AKTT_Tweet object, save
				$t = new AKTT_Tweet($tweet);
				$t->add();
				break;
			}
		}
	}
	
	/**
	 * Check for auth against Social's api key
	 *
	 * @return book
	 */
	static function social_key_auth() {
		return (bool) (!empty($_GET['social_api_key']) && stripslashes($_GET['social_api_key']) == Social::option('system_cron_api_key'));
	}
	
	
	/**
	 * Request handler
	 *
	 * @return void
	 */
	function controller(){
		if (isset($_GET['aktt_action'])) {
			switch ($_GET['aktt_action']) {
				case 'download_account_tweets':
					if (empty($_GET['acct_id']) || !AKTT::social_key_auth()) {
						wp_die(__('Sorry, try again.', 'twitter-tools'));
					}
					$acct_id = intval($_GET['acct_id']);
					self::get_social_accounts();
					if (isset(self::$accounts[$acct_id])) {
						if ($tweets = self::$accounts[$acct_id]->download_tweets()) {
							self::$accounts[$acct_id]->save_tweets($tweets);
						}
					}
					die();
					break;
				case 'import_tweet':
// check for status_id && auth key
					if (empty($_GET['tweet_id']) || !AKTT::social_key_auth()) {
						wp_die(__('Sorry, try again.', 'twitter-tools'));
					}
// check for account_name
					$username = (!empty($_GET['username']) ? stripslashes($_GET['username']) : null);
// download tweet
					$tweet = self::download_tweet($_GET['tweet_id'], $username);
					if (!is_a($tweet, 'stdClass')) {
						wp_die('Failed to download tweet.');
					}
// store tweet
					$t = new AKTT_Tweet($tweet);
					if (!$t->exists_by_guid()) {
						$t->add();
					}
					die();
					break;
				case 'backfill_tweet_data':
					if (empty($_GET['tweet_id']) || !AKTT::social_key_auth()) {
						wp_die(__('Sorry, try again.', 'twitter-tools'));
					}
					$t = new AKTT_Tweet(stripslashes($_GET['tweet_id']));
					if (!$t->get_post()) {
						die();
					}
					$usernames = wp_get_object_terms($t->post->ID, 'aktt_accounts');
					$username = $usernames[0]->slug;
					
					$tweet = self::download_tweet($_GET['tweet_id'], $username);
					
					if (!is_a($tweet, 'stdClass')) {
						wp_die('Failed to download tweet');
					}
					$t->update_twitter_data($tweet);
					die();
					break;
			}
		}
	}
	
	
	/**
	 * Request handler for admin
	 *
	 * @return void
	 */
	function admin_controller(){
		if (isset($_GET['aktt_action'])) {
			switch ($_GET['aktt_action']) {
				case 'manual_tweet_download':
					// Permission & nonce checking
					if (!check_admin_referer('manual_tweet_download') || !current_user_can(self::$cap_download)) { 
						wp_die(__('Sorry, try again.', 'twitter-tools'));
					}
					
					self::import_tweets();
					echo json_encode(array(
						'result' => 'success',
						'msg' => __('Tweets are downloading&hellip;', 'twitter-tools')
					));
					die();
					break;
				case 'upgrade-3.0':
					// Permission checking
					if (!current_user_can(self::$cap_options)) { 
						wp_die(__('Sorry, try again.', 'twitter-tools'));
					}
					include(AKTT_PATH.'/upgrade/3.0.php');
					aktt_upgrade_30();
					die();
					break;
				case 'upgrade-3.0-run':
					// Permission checking
					if (!current_user_can(self::$cap_options) || !wp_verify_nonce($_GET['nonce'], 'upgrade-3.0-run')) { 
						header('Content-type: application/json');
						echo json_encode(array(
							'result' => 'error',
							'message' => __('Sorry, try again.', 'twitter-tools')
						));
						die();
					}
					include(AKTT_PATH.'/upgrade/3.0.php');
					$to_upgrade = aktt_upgrade_30_run();
					header('Content-type: application/json');
					echo json_encode(array(
						'result' => 'success',
						'to_upgrade' => $to_upgrade
					));
					die();
					break;
				case 'tweets_updated':
					self::add_admin_notice(__('Tweets are downloading...', 'twitter-tools'));
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
	 * Output the admin-side JavaScript
	 *
	 * @return void
	 */
	static function admin_js() {
?>
<script type="text/javascript">
jQuery(function($) {
	$('a[href="post-new.php?post_type=aktt_tweet"]').hide().parent('li').hide();
	if (location.href.indexOf('edit-tags.php?taxonomy=aktt_accounts') != -1 ||
		location.href.indexOf('edit-tags.php?taxonomy=aktt_mentions') != -1 ||
		location.href.indexOf('edit-tags.php?taxonomy=aktt_hashtags') != -1 ||
		location.href.indexOf('edit-tags.php?taxonomy=aktt_types') != -1
	) {
		$('#col-left .form-wrap').hide();
	}
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
				multiple: true 
			}
		);
	});
});
</script>
<?php
	}
	
	function log($msg) {
		if (self::$debug) {
			error_log($msg);
		}
	}
	
	static function profile_url($username) {
		return 'http://twitter.com/'.urlencode($username);
	}

	static function profile_link($username) {
		return '<a href="'.esc_url(self::profile_url($username)).'">'.esc_html(self::profile_prefix($username)).'</a>';
	}

	static function profile_prefix($username, $prefix = '@') {
		if (AKTT::substr($username, 0, 1) != '#') {
			$username = '@'.$username;
		}
		return $username;
	}

	static function hashtag_url($hashtag) {
		$hashtag = self::hashtag_prefix($hashtag);
		return 'http://twitter.com/search?q='.urlencode($hashtag);
	}

	static function hashtag_link($hashtag) {
		$hashtag = self::hashtag_prefix($hashtag);
		return '<a href="'.esc_url(self::hashtag_url($hashtag)).'">'.esc_html($hashtag).'</a>';
	}
	
	static function hashtag_prefix($hashtag, $prefix = '#') {
		if (AKTT::substr($hashtag, 0, 1) != '#') {
			$hashtag = '#'.$hashtag;
		}
		return $hashtag;
	}
	
	static function status_url($username, $id) {
		return 'http://twitter.com/'.urlencode($username).'/status/'.urlencode($id);
	}
	
	static function download_tweet($status_id, $username = null) {
		if (empty(AKTT::$accounts)) {
			return false;
		}
		$account_found = $tweet = false;
		if (!empty($username)) {
			AKTT::get_social_accounts();
			foreach (AKTT::$accounts as $id => $account) {
				if ($username == $account->social_acct->name()) {
					// proper account stored as $account
					$account_found = true;
					break;
				}
			}
			if (!$account_found) {
				$account = AKTT::$accounts[0]; // use any account
			}
			$response = Social::instance()->service('twitter')->request(
				$account->social_acct,
				'1.1/statuses/show/'.urlencode($t->id).'.json',
					array(
					'include_entities' => 1, // include explicit hashtags and mentions
					'include_rts' => 1, // include retweets
				)
			);
			$content = $response->body();
			if ($content->result == 'success') {
				$tweets = $content->response;
				if (!$tweets || !is_array($tweets) || count($tweets) != 1) {
					$tweet = $tweet[0];
				}
			}
		}
		return $tweet;
	}
	
	static function gmt_to_wp_time($gmt_time) {
		$timezone_string = get_option('timezone_string');
		if (!empty($timezone_string)) {
			// Not using get_option('gmt_offset') because it gets the offset for the
			// current date/time which doesn't work for timezones with daylight savings time.
			$gmt_date = date('Y-m-d H:i:s', $gmt_time);
			$datetime = new DateTime($gmt_date);
			$datetime->setTimezone(new DateTimeZone(get_option('timezone_string')));
			$offset_in_secs = $datetime->getOffset();
			
			return $gmt_time + $offset_in_secs;
		}
		else {
			return $gmt_time + (get_option('gmt_offset') * 3600);
		}
	}

	static function substr_replace($string, $replacement, $start, $length = null, $encoding = null) {
		// from http://www.php.net/manual/en/function.substr-replace.php#90146
		// via https://github.com/ruanyf/wp-twitter-tools/commit/56d1a4497483b2b39f434fdfab4797d8574088e5
		if (extension_loaded('mbstring') === true) {
			$string_length = (is_null($encoding) === true) ? mb_strlen($string) : mb_strlen($string, $encoding);
			
			if ($start < 0) {
				$start = max(0, $string_length + $start);
			}
			else if ($start > $string_length) {
				$start = $string_length;
			}
			if ($length < 0) {
				$length = max(0, $string_length - $start + $length);
			}
			else if ((is_null($length) === true) || ($length > $string_length)) {
				$length = $string_length;
			}
			if (($start + $length) > $string_length) {
				$length = $string_length - $start;
			}
			if (is_null($encoding) === true) {
				return mb_substr($string, 0, $start) . $replacement 
					. mb_substr($string, $start + $length, $string_length - $start - $length);
			}
			return mb_substr($string, 0, $start, $encoding) . $replacement 
				. mb_substr($string, $start + $length, $string_length - $start - $length, $encoding);
		}
		else {
			return (is_null($length) === true) ? substr_replace($string, $replacement, $start) : substr_replace($string, $replacement, $start, $length);
		}
	}

	static function strlen($str, $encoding = null) {
		if (function_exists('mb_strlen')) {
			if (is_null($encoding) === true) {
				return mb_strlen($str);
			}
			else {
				return mb_strlen($str, $encoding);
			}
		}
		else {
			return strlen($str);
		}
	}

	static function substr($str, $start, $length) {
		if (function_exists('mb_substr')) {
			return mb_substr($str, $start, $length);
		}
		else {
			return substr($str, $start, $length);
		}
	}

}


