<?php 

class AKTT_Account {
	var $id = null; // This is the Social Service's user ID for the specified account
	var $social_acct = null;
	
	static $settings_option_name = 'aktt_account_settings'; // The name of the option where we store account settings
	static $config = array();
	
	public static function init() {
		// Set our default configs
		AKTT_Account::set_default_config();
	}
	
	public static function set_default_config() {
		// Set default configs
		AKTT_Account::$config = array(
			'create_posts' => array( // author to assign to new posts
				'label' => __('Create posts for each tweet?', 'twitter-tools'),
				'label_first' => false,
				'value' => 0,
				'type' 	=> 'int',
			),
			'post_author' => array( // author to assign to new posts
				'label' => __('Post Author', 'twitter-tools'),
				'label_first' => true,
				'value' => 0,
				'type' 	=> 'int',
			),
			'post_category' => array( // cats to add to posts created from this acct
				'label' => __('Post Category', 'twitter-tools'),
				'label_first' => true,
				'value' => 0,
				'type' 	=> 'is_cat',
			),
			'post_tags' => array( // tags to add to posts created from this acct
				'label' => __('Post Tag', 'twitter-tools'),
				'label_first' => true,
				'value' => array(),
				'type' 	=> 'is_tag',
			),
			'hashtag' => array( // hashtag to create blog posts from
				'label' => __('Hashtag', 'twitter-tools'),
				'label_first' => true,
				'value' => '',
				'type' 	=> 'no_html',
			),
			'exclude_reply_tweets' => array( // Exclude tweets that are a reply from creating their own blog posts?
				'label' => __('Exclude reply tweets from post creation', 'twitter-tools'),
				'label_first' => false,
				'value' => 1,
				'type' 	=> 'int',
			),
			'blog_post_title_prefix' => array( // Structure of the blog post Title
				'label' => __('Blog Post Title Prefix', 'twitter-tools'),
				'label_first' => true,
				'value' => '',
				'type' 	=> 'no_html',
			),
		);
	}
	
	/**
	 * Safe constructor.  
	 *
	 * @param object $acct Social Twitter Account (stdObj)
	 * @return AKTT_Account | false
	 */
	public static function load($acct = null) {
		// Make sure we have the appropriate classes, etc. for the AKTT_Account object
		if (
			is_null($acct)
			|| !is_object($acct) // Ensure we have an account object
			|| !isset(Social::$combined_services['twitter']) // Ensure we have twitter as a service
			|| !is_a(Social::$combined_services['twitter'], 'Social_Twitter') // Ensure we have a Social_Twitter object
			) {
			return false;
		}
		
		// We passed the gauntlet, return a new object
		return new AKTT_Account($acct);
	}
	
	function __construct($acct = null) {
		// Set our ID
		$this->id = $acct->user->id;
		
		// Set the account (stdClass Obj)
		$this->social_acct = $acct;
		
		// For convenience, set a reference to the service which has all the account methods
		$this->service = &Social::$combined_services['twitter'];
		
	}
	
	function output_account_config() {
		$name = $this->service->profile_name($this->social_acct);
		$avatar = $this->service->profile_avatar($this->social_acct);
		$img = empty($avatar) ? '' : '<img class="avatar" src="'.esc_url($avatar).'" />';
		?>
		<li class="aktt_acct_item">
			<h3><?php echo esc_html($name); ?></h3>
			<?php echo $img; ?>
			<ul class="aktt-account-settings">
				<?php 
				foreach (AKTT_Account::$config as $key => $config) {
					$this->output_config_item($key, $config);
				}
				?>
			</ul><!-- /aktt-account-settings -->
		</li>
		<?php
	}
	
	function output_config_item($key, $config) {
		// Build our complex option name
		$name = AKTT_Account::$settings_option_name.'['.$this->id.'][settings]['.$key.']';
		
		// Get our label's HTML
		$label_html = '<label for="'.esc_html($name).'" class="aktt-account-setting">'.esc_html($config['label']).'</label>';
		
		// Get our field's HTML
		ob_start();
		switch ($key) {
			case 'create_posts':
			case 'exclude_reply_tweets':
				?>
				<input type="checkbox" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="1"<?php checked('1', $this->get_option($key)); ?> />
				<?php
				break;
			case 'post_author':
				wp_dropdown_users(array(
					'name' => $name,
					'id' => $name,
					'selected' => $this->get_option($key),
					'who' => 'authors',
				));
				break;
			case 'post_category':
				wp_dropdown_categories(array(
					'name' => $name,
					'id' => $name,
					'selected' => $this->get_option($key),
					'hide_empty' => 0,
					'taxonomy' => 'category',
					'show_option_none' => '&mdash; Please Select &mdash;',
				));
				break;
			case 'post_tags':
				// The DB value is an integer.  We need to display the name for the input
				$term = get_term_by('id', $this->get_option($key), 'post_tag');
				$value = (!$term) ? '' : $term->name;
				?>
				<input type="text" class="type-ahead" data-tax="post_tag" name="<?php echo esc_html($name); ?>" id="<?php echo esc_html($name); ?>" value="<?php echo esc_attr($value); ?>" />
				<?php
				break;
			case 'hashtag':
			default:
				?>
				<input type="text" name="<?php echo esc_html($name); ?>" id="<?php echo esc_html($name); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" />
				<?php 
				break;
		}
		// Get our field's HTML
		$field_html = ob_get_clean();
		
		?>
		<li>
			<?php
			if ($config['label_first']) {
				echo $label_html.$field_html;
			}
			else {
				echo $field_html.$label_html;
			}
			?>
		</li>
		<?php
	}
	
	/**
	 * Get default setting's value
	 *
	 * @param string $setting 
	 * @return mixed
	 */
	function get_default_option($setting) {
		return isset(AKTT_Account::$config[$setting]) ? AKTT_Account::$config[$setting]['value'] : null;
	}
	
	
	/**
	 * Get an option from the DB, and fall back to the default setting
	 *
	 * @param string $setting 
	 * @return mixed
	 */
	function get_option($setting) {
		// Get a default
		$val = $this->get_default_option($setting);
		
		// Now look inside the option to see if it's set there
		$option = get_option(AKTT_Account::$settings_option_name);
		if (
			!empty($option) 
			&& is_array($option) 
			&& isset($option[$this->id])
			&& isset($option[$this->id]['settings'])
			&& isset($option[$this->id]['settings'][$setting])
			) {
			$val = $option[$this->id]['settings'][$setting];
		}
		return apply_filters(AKTT::$prefix.'account_get_option', $val, $setting);
	}
	
	
	function download_tweets() {
		// Use Social to download tweets for this account
		$request = $this->service->request($this->social_acct, 'statuses/user_timeline', array(
			'count' => apply_filters('aktt_account_api_download_count', 20) // default to twitter's default 
		));
		
		// If it was successful, store the tweets to the account
		if (
			$request->result == 'success' 
			&& isset($request->response) 
			&& is_array($request->response) 
			&& count($request->response)
			) {
			return $request->response;
		}
		return false;
	}
	
	
	/**
	 * Saves the tweets passed in.
	 *
	 * @param array $tweets - safe tweets (do error checking before passing to this function)
	 * @return int - number of tweets saved
	 */
	function save_tweets($tweets) {
		// Set the args for any blog posts created
		$post_tweet_args = array(
			'post_author' 	=> $this->get_option('post_author'),
			'post_category' => $this->get_option('post_category'),
			'post_tags' 	=> $this->get_option('post_tags'),
			'title_prefix' 	=> $this->get_option('blog_post_title_prefix'),
		);
		
		// Loop over our tweets
		foreach ($tweets as $tweet) {
			// Start up a tweet object
			$t = new AKTT_Tweet($tweet->id);
			
			// See if this tweet exists...continue if it does
			if ($t->tweet_exists()) { continue; }
			
			// Add if it doesn't, create it
			$t->populate_from_twitter_obj($tweet); // populate the meta from the object
			$result = $t->add(); // insert it into the database
			
			// If we failed
			if (!$result) {
				AKTT::log('There was an error creating the tweet for the custom post_type.  Not attempting to create blog post from tweet. Tweet ID: '.$t->id);
				continue;
			}
			
			// Now conditionially create the associated blog post
			if (
				// If we are set to create blog posts
				$this->get_option('create_posts') == 1
				
				// AND this tweet hasn't created a post yet
				&& !$t->tweet_post_exists()
				
				// AND NOT we aren't supposed to do reply tweets and this is a reply
				&& !($this->get_option('exclude_reply_tweets') && $t->is_reply())
				
				// AND the tweet didn't come from a Social broadcast (ie, was originally a blog post)
				&& !$t->was_broadcast()
				){
				AKTT::log('Creating a blog post for tweet ID: '.$t->id);
				$t->create_blog_post($post_tweet_args);
			}
		}
	}

}
add_action('init', array(AKTT_Account, 'init'));
?>