<?php 
class AKTT_Tweet {
	var $post_id = null;
	var $meta = array(
		'in_reply_to_status_id_str' => '',
		'in_reply_to_user_id' => '',
		'in_reply_to_screen_name' => '',
		'in_reply_to_user_id_str' => '',
		'in_reply_to_status_id' => '',
		'contributors' => '',
		'geo' => '',
		'retweeted' => '',
		'coordinates' => '',
		'retweet_count' => '',
		'possibly_sensitive' => '',
		'place' => '',
		'created_at' => '',
		'source' => '',
		'id' => '',
		'favorited' => '',
		'truncated' => '',
		'user_id' => '',
		'blog_post_id' => null,
	);
		
	static $prefix = 'aktt_tweet_';
	static $ignored_meta = array(
		'id_str',
		'user', // We have the post_meta storing just the user id
		'text', // Not stored as meta...stored as post content
	);
	
	
	/**
	 * Set up the tweet with the ID from twitter
	 *
	 * @param int $id - tweet id from twitter API
	 * @param bool $from_db - Whether to auto-populate this object from the DB
	 */
	function __construct($id, $from_db = false) {
		// Assign the tweet ID to this object
		$this->add_prop('id', $id);
		
		// Flag to populate the object from the DB on construct
		if ($from_db == true) {
			$this->populate_from_db();
		}
	}
	
	
	/**
	 * Allows the object's properties to be populated from a post object in the DB
	 *
	 * @return void
	 */
	function populate_from_db() {
		$post = $this->get_post(AKTT::$post_type);

		// @TODO error handle
		if (is_wp_error($post) || empty($post)) {}
		
		// @TODO - Should these properties be filterable?? (i.e., use the functions to retrieve)
		$this->add_prop('title', $post->post_title);
		$this->add_prop('text', $post->post_content);
		
		// Get all post_meta
		$all_meta = get_post_custom($post->ID);
		foreach ($this->meta as $prop => $val) {
			$val = (isset($all_meta[AKTT_Tweet::$prefix.$prop])) ? $all_meta[AKTT_Tweet::$prefix.$prop][0] : '';
			$this->add_meta_prop($prop, $val);
		}
	}
	
	
	/**
	 * Populates the object from a twitter API response object
	 *
	 * @param object $tweet_obj 
	 * @return void
	 */
	function populate_from_twitter_obj($tweet_obj) {
		// Top-level properties
		$this->add_prop('title', $tweet_obj->text); // Setting this to the text, b/c the ID isn't useful to the user
		$this->add_prop('text', $tweet_obj->text);
		
		// Now the Meta
		foreach ($tweet_obj as $prop => $val) {
			if (!in_array($prop, AKTT_Tweet::$ignored_meta)) {
				$this->add_meta_prop($prop, $val);
			}
		}
		$this->add_meta_prop('user_id', $tweet_obj->user->id);
	}
	
	
	/**
	 * Adds a top-level property
	 *
	 * @param string $prop 
	 * @param mixed $val 
	 * @return void
	 */
	function add_prop($prop, $val) {
		$this->$prop = $val;
	}
	
	
	/**
	 * Adds an item to the $this->meta array
	 *
	 * @param string $prop 
	 * @param mixed $val 
	 * @return void
	 */
	function add_meta_prop($prop, $val) {
		$this->meta[$prop] = $val;
	}
	
	
	/**
	 * Takes the twitter date format and gets a timestamp from it
	 *
	 * @param string $date - "Fri Aug 05 20:33:38 +0000 2011"
	 * @return int - timestamp
	 */
	static function twdate_to_time($date) {
		$parts = explode(' ', $date);
		$date = strtotime($parts[1].' '.$parts[2].', '.$parts[5].' '.$parts[3]);
		return $date;
	}
	
	
	/**
	 * See if the tweet ID matches any tweet ID post meta value
	 *
	 * @return bool
	 */
	function tweet_exists() {
		$test = $this->get_post(AKTT::$post_type);
		return (bool) (count($test) > 0);
	}
	
	
	/**
	 * Checks the posts to see if this tweet has been attached to 
	 * any of them.
	 *
	 * @return bool
	 */
	function tweet_post_exists() {
		$posts = $this->get_post('post');
		return (bool) (count($test) > 0);
	}
	
	
	/**
	 * Grabs the post from the DB
	 * 
	 * @uses get_posts
	 *
	 * @return obj|false 
	 */
	function get_post($post_type) {
		$posts = get_posts(array(
			'post_type' 	=> $post_type,
			'meta_key' 		=> AKTT_Tweet::$prefix.'id',
			'meta_value' 	=> $this->id,
		));
		return is_array($posts) ? array_shift($posts) : false;
	}
	
	function tweet_is_post_notification() {
		global $aktt;
		if (substr($this->tw_text, 0, strlen($aktt->tweet_prefix)) == $aktt->tweet_prefix) {
			return true;
		}
		return false;
	}
	
	
	/**
	 * Twitter data changed - users still expect anything starting with @ is a reply
	 *
	 * @return bool
	 */
	function is_reply() {
		return (bool) (substr($this->tw_text, 0, 1) == '@');
	}
	
	
	/**
	 * Look up whether this tweet came from a broadcast
	 *
	 * @return bool
	 */
	function was_broadcast() {
		if (isset($this->meta['blog_post_id']) && !empty($this->meta['blog_post_id'])) {
			$broadcasted_ids = get_post_meta($this->meta['blog_post_id'], Social::$prefix.'broadcasted_ids', true);
			if (isset($broadcasted_ids['twitter']) && is_array($broadcasted_ids['twitter'])) {
				AKTT::log('Looking through blog post ('.$this->meta['blog_post_id'].') broadcasted IDs for '.$this->id);
				return (bool) in_array($this->id, $broadcasted_ids['twitter']);
			}
		}
		return false;
	}
	
	
	/**
	 * Creates an aktt_tweet post_type with its meta
	 *
	 * @param array $args 
	 * @return void
	 */
	function add() {
		// Build the post data
		$data = array(
			'post_title' 	=> $this->title,
			'post_content'	=> $this->text,
			'post_author' 	=> $post_author,
			'post_status' 	=> 'publish',
			'post_type' 	=> AKTT::$post_type,
			'post_date'		=> date('Y-m-d H:i:s', AKTT_Tweet::twdate_to_time($this->meta['created_at'])),
			// 'post_date_gmt' => // @TODO 
		);
		
		$id = wp_insert_post($data, true);
		
		if (is_wp_error($id)) {
			AKTT::log('WP_Error:: '.$blog_post_id->get_error_message());
			return false;
		}
		
		// Set this tweet's post ID
		$this->add_prop('post_id', $id);
		
		// Add all the various post_meta items
		$this->add_metas();
		
		// Allow things to hook in here
		do_action('AKTT_Tweet_add', $this);
		
		return true;
	}
	
	/**
	 * Adds various post_meta to the tweet
	 *
	 * @return void
	 */
	function add_metas() {
		if (empty($this->post_id)) { return; }
		
		foreach ($this->meta as $prop => $val) {
			add_post_meta($this->post_id, AKTT_Tweet::$prefix.$prop, $val);
		}
	}
	
	function create_blog_post($args = array()) {
		extract($args);
		
		// Add a space if we have a prefix
		$title_prefix = empty($title_prefix) ? '' : $title_prefix.' ';
		$post_type = 'post';
		
		// Build the post data
		$data = array(
			'post_title' 	=> $title_prefix.$this->title, // @TODO how to build this (account config?)
			'post_content'	=> $this->text, // @TODO what should this be (account config?)
			'post_author' 	=> $post_author,
			'tax_input'		=> array(
				'category' => array($post_category),
				'post_tag' => array_map(array(AKTT_Tweet, 'get_tag_name'), array($post_tags)),
			),
			'post_status' 	=> 'publish',
			'post_type' 	=> $post_type,
			'post_date'		=> date('Y-m-d H:i:s', AKTT_Tweet::twdate_to_time($this->meta['created_at'])),
			// 'post_date_gmt' => // @TODO 
		);
		
		// Add a post format in, we the theme and post_type supports it
		if (post_type_supports($post_type, 'post-formats')) {
			AKTT::log('Setting post_format to "status"');
			$data['tax_input']['post_format'] = 'status';
		}
		
		$blog_post_id = wp_insert_post($data, true);
		
		if (is_wp_error($blog_post_id)) {
			AKTT::log('WP_Error:: '.$blog_post_id->get_error_message());
			return false;
		}
		
		// Set the post's meta value for tweet post_id
		update_post_meta($blog_post_id, AKTT_Tweet::$prefix.'id', $this->id); // twitter's tweet ID
		update_post_meta($blog_post_id, AKTT_Tweet::$prefix.'post_id', $this->post_id); // the post_type's post ID for the tweet
		
		
		// Add post_meta so Social knows to aggregate info about this post
		update_post_meta($blog_post_id, Social::$meta_prefix.'broadcasted', array('twitter' => '1'));
		update_post_meta($blog_post_id, Social::$meta_prefix.'broadcasted_ids', array('twitter' => array(
			$this->meta['user_id'] => $this->id,
		)));
		
		
		// Set the tweets property to the blog post' ID
		$this->add_meta_prop('blog_post_id', $blog_post_id);
		// Add it to the tweet's post_meta as well
		update_post_meta($this->post_id, AKTT_Tweet::$prefix.'blog_post_id', $blog_post_id);
		
		// Let the account know we were successful
		return true;
	}
	
	
	/**
	 * Gets the name of a tag from its ID
	 *
	 * @param int $tag_id 
	 * @return string
	 */
	function get_tag_name($tag_id) {
		return get_term_field('name', $tag_id, 'post_tag', 'db');
	}
	
}

?>