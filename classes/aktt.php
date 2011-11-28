<?php

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

		add_shortcode('aktt_tweets', 'aktt_shortcode_tweets');
		add_shortcode('aktt_tweet', 'aktt_shortcode_tweet');

		// General Hooks
		add_action('wp', array('AKTT', 'controller'), 1);
		add_filter('the_post', array('AKTT', 'the_post'));
		add_filter('post_type_link', array('AKTT', 'get_tweet_permalink'), 10, 2);
		add_action('social_account_disconnected', array('AKTT', 'social_account_disconnected'), 10, 2);
		add_action('social_broadcast_response', array('AKTT', 'social_broadcast_response'), 10, 3);
		
		// Admin Hooks
		add_action('admin_init', array('AKTT', 'init_settings'), 0);
		add_action('admin_init', array('AKTT', 'admin_controller'), 1);
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
				'label' => __('Write log messages to the PHP error log', 'twitter-tools'),
				'label_first' => false,
				'type' => 'int',
			),
			'credit' => array(
				'value' => 1,
				'label' => __('Give Twitter Tools credit', 'twitter-tools'),
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
			'aktt_account' => array(
				'var' => 'account',
				'strip' => array()
			),
			'aktt_hashtags' => array(
				'var' => 'hashtags',
				'strip' => array('#')
			),
			'aktt_mentions' => array(
				'var' => 'mentions',
				'strip' => '@'
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
				'key' => AKTT_Tweet::$prefix.'id',
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
			$tax_query[] = array(
				'taxonomy' => 'aktt_types',
				'field' => 'slug',
				'terms' => array('social-broadcast'),
				'operator' => 'NOT IN'
			);
			$type_terms = array();
// initial, more efficient check
			if (!$params['include_rts'] && !$params['include_replies']) {
				$type_terms[] = 'status';
			}
			else {
				$type_terms[] = ($params['include_rts'] ? 'retweet' : 'not-a-retweet');
				$type_terms[] = ($params['include_replies'] ? 'reply' : 'not-a-reply');
			}
			if (count($type_terms)) {
				$tax_query[] = array(
					'taxonomy' => 'aktt_types',
					'field' => 'slug',
					'terms' => $type_terms,
				);
			}
			$query_data['tax_query'] = $tax_query;
		}
// error_log(print_r($args, true));
// error_log(print_r($query_data, true));
		$query = new WP_Query(apply_filters('aktt_get_tweets', $query_data));
		return $query->posts;
	}
	
	function the_post($post) {
		if ($post->post_type == self::$post_type) {
			if ($raw_data = get_post_meta($post->ID, AKTT_Tweet::$prefix.'raw_data', true)) {
				$post->tweet = new AKTT_Tweet(json_decode($raw_data));
			}
			else {
				$post->tweet = new AKTT_Tweet(false);
			}
		}
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
				foreach (array_keys(AKTT_Account::$config) as $key) {
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
		return self::sanitize_setting($key, $value, self::$default_settings[$key]['type']);
	}
	
	static function sanitize_account_setting($key, $value) {
		return self::sanitize_setting($key, $value, AKTT_Account::$config[$key]['type']);
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
		}
		return $value;
	}
	
	
	/**
	 * Outputs the plugin's settings form.  Utilizes the "settings" API in WP
	 *
	 * @return void
	 */
	static function output_settings_page() {
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
?>
		<div class="wrap" id="<?php echo self::$prefix.'options_page'; ?>">
			<?php screen_icon(); ?>
			<h2><?php _e('Twitter Tools', 'twitter-tools'); ?></h2>

<?php
		if (self::$enabled) {
			if ($upgrade_needed || 1) {
?>
			<a href="<?php echo esc_url(admin_url('index.php?aktt_action=upgrade-3.0')); ?>" class="aktt-upgrade-3.0 button-secondary"><?php _e('Upgrade Previous Twitter Tools Data', 'twitter-tools'); ?></a>
<?php
			}
			
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
	 * Return the first account from the list, at random.
	 *
	 * @return mixed AKTT_Account object|bool
	 */
	static function default_account() {
		self::get_social_accounts();
		if (count(self::$accounts)) {
			foreach (self::$accounts as $account) {
				if ($account->get_option('enabled')) {
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
			if ($acct->get_option('enabled')) {
				// could time out with lots of accounts, so a new request for each
				$url = site_url('index.php?'.http_build_query(array(
					'aktt_action' => 'download_account_tweets',
					'acct_id' => $id,
					'social_api_key' => Social::option('system_cron_api_key')
				)));
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
	 * Request handler for admin
	 *
	 * @return void
	 */
	function controller(){
		if (isset($_GET['aktt_action'])) {
			switch ($_GET['aktt_action']) {
				case 'download_account_tweets':
					if (empty($_GET['acct_id']) || stripslashes($_GET['social_api_key']) != Social::option('system_cron_api_key')) {
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
					wp_redirect(add_query_arg(array(
						'page' => self::$menu_page_slug,
						'tweets_updated' => '1'),
						admin_url('options-general.php')
					));
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
	
	static function profile_url($username) {
		return 'http://twitter.com/'.$username;
	}

	static function profile_link($username) {
		return '<a href="'.esc_url(self::profile_url($username)).'">'.esc_html($username).'</a>';
	}
}
