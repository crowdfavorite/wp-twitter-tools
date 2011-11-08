<?php 
class AKTT_Tweet {
	var $post_id = null;
	var $raw_data = null;
		
	static $prefix = 'aktt_tweet_';
	
	/**
	 * Set up the tweet with the ID from twitter
	 *
	 * @param mixed $data - tweet id or full tweet object from twitter API
	 * @param bool $from_db - Whether to auto-populate this object from the DB
	 */
	function __construct($data, $from_db = false) {
		if (is_object($data)) {
			$this->populate_from_twitter_obj($data);
		}
		else {
			// Assign the tweet ID to this object
			$this->id = $data;
			
			// Flag to populate the object from the DB on construct
			if ($from_db == true) {
				$this->populate_from_db();
			}
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
		if (is_wp_error($post) || empty($post)) {
			return false;
		}
		
		$this->title = $post->post_title;
		$this->text = $post->post_content;
		
		$this->raw_data = get_post_meta($post->ID, '_aktt_raw_data', true);
	}
	
	
	/**
	 * Populates the object from a twitter API response object
	 *
	 * @param object $tweet_obj 
	 * @return void
	 */
	function populate_from_twitter_obj($tweet_obj) {
		$this->raw_data = json_encode($tweet_obj);
		$this->title = substr($tweet_obj->text, 0, 50);
		if (strlen($tweet_obj->text) > 50) {
			$this->title .= '...';
		}
		$this->text = $tweet_obj->text;
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
	function exists() {
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
		return (bool) (count($posts) > 0);
	}

	
	/**
	 * Generate a GUID for WP post based on tweet ID.
	 *
	 * @return mixed
	 */
	static function guid_from_twid($tweet_id = null) {
		return (empty($tweet_id) ? false : 'http://twitter-'.$tweet_id);
	}


	/**
	 * Generate a GUID for WP post based on this objects' tweet ID.
	 *
	 * @uses guid_from_twid
	 *
	 * @return mixed
	 */
	function guid() {
		return $this->guid_from_twid($this->id);
	}
	
	
	/**
	 * Grabs the post from the DB
	 * 
	 * @uses get_posts
	 *
	 * @return obj|false 
	 */
	function get_post($post_type) {
// TODO - search by GUID instead
		$posts = get_posts(array(
			'post_type' 	=> $post_type,
			'meta_key' 		=> AKTT_Tweet::$prefix.'id',
			'meta_value' 	=> $this->id,
		));
		return is_array($posts) ? array_shift($posts) : false;
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
		$data = apply_filters('aktt_tweet_add', array(
			'post_title' => $this->title,
			'post_content' => $this->text,
			'post_status' => 'publish',
			'post_type' => AKTT::$post_type,
			'post_date' => date('Y-m-d H:i:s', AKTT_Tweet::twdate_to_time($this->meta['created_at'])),
			'guid' => $this->guid(),
			// 'post_date_gmt' => // @TODO 
		));
		
		$post_id = wp_insert_post($data, true);
		
		if (is_wp_error($id)) {
			AKTT::log('WP_Error:: '.$blog_post_id->get_error_message());
			return false;
		}
		
		update_post_meta($post_id, '_aktt_raw_data', $this->raw_data);
		
		// Set this tweet's post ID
		$this->add_prop('post_id', $post_id);
		
		// Allow things to hook in here
		do_action('AKTT_Tweet_add', $this);
		
		return true;
	}
	
	function create_blog_post($args = array()) {
		extract($args);
		
		// Add a space if we have a prefix
		$title_prefix = empty($title_prefix) ? '' : $title_prefix.' ';
		$post_type = 'post';
		
		// Build the post data
		$data = array(
			'post_title' => $title_prefix.$this->title, // @TODO how to build this (account config?)
			'post_content' => $this->text, // @TODO what should this be (account config?)
			'post_author' => $post_author,
			'tax_input' => array(
				'category' => array($post_category),
				'post_tag' => array_map(array('AKTT_Tweet', 'get_tag_name'), array($post_tags)),
			),
			'post_status' => 'publish',
			'post_type' => $post_type,
			'post_date' => date('Y-m-d H:i:s', AKTT_Tweet::twdate_to_time($this->meta['created_at'])),
			// 'post_date_gmt' => // @TODO 
		);
		
		$post_id = wp_insert_post($data, true);
		
		if (is_wp_error($post_id)) {
			AKTT::log('WP_Error:: '.$post_id->get_error_message());
			return false;
		}
		
		set_post_format($post_id, 'image');
		
		// Set the post's meta value for tweet post_id
		update_post_meta($post_id, AKTT_Tweet::$prefix.'id', $this->id); // twitter's tweet ID
		update_post_meta($post_id, AKTT_Tweet::$prefix.'post_id', $this->post_id); // the post_type's post ID for the tweet
		
		
// TODO - test this
		// Add post_meta so Social knows to aggregate info about this post
		update_post_meta($post_id, Social::$meta_prefix.'broadcasted', array('twitter' => '1'));
		update_post_meta($post_id, Social::$meta_prefix.'broadcasted_ids', array('twitter' => array(
			$this->meta['user_id'] => $this->id,
		)));
		
		
		// Set the tweets property to the blog post' ID
		$this->blog_post_id = $post_id;

		// Add it to the tweet's post_meta as well
		update_post_meta($this->post_id, AKTT_Tweet::$prefix.'blog_post_id', $post_id);
		
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

