<?php

class AKTT_Widget extends WP_Widget {
	function __construct() {
		// widget actual processes
		parent::__construct(
			'aktt-widget', 
			__('Twitter Tools', 'twitter-tools'),
			array(
				'classname' => 'aktt-widget',
				'description' => __('Show your recent tweets.', 'twitter-tools')
			)
		);
	}

	function form($instance) {
		$account_options = array();
		foreach (AKTT::$accounts as $account) {
			$account_options[] = $account->social_acct->name();
		}
		$defaults = array(
			'account' => '',
			'count' => 3,
			'include_rts' => 0,
			'include_replies' => 0,
			'credit' => 1,
		);
		foreach ($defaults as $k => $v) {
			if (!isset($instance[$k])) {
				$instance[$k] = $v;
			}
		}
?>
<p>
	<label for="<?php echo $this->get_field_id('account'); ?>"><?php _e('Account', 'twitter-tools'); ?></label>
	<select name="<?php echo $this->get_field_id('account'); ?>" id="<?php echo $this->get_field_id('account'); ?>">
<?php
		foreach ($account_options as $account_option) {
?>
		<option value="<?php echo esc_attr($account_option); ?>" <?php selected($instance['account'], $account_option); ?>><?php echo esc_html($account_option); ?></option>
<?php
		}
?>
	</select>
</p>
<p>
	<label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('How Many Tweets?', 'twitter-tools'); ?></label>
	<input type="text" size="3" name="<?php echo $this->get_field_name('count'); ?>" id="<?php echo $this->get_field_id('count'); ?>" value="<?php echo esc_attr($instance['count']); ?>" />
</p>
<p>
	<?php _e('Include Retweets?', 'twitter-tools'); ?>
	&nbsp;
	<input type="radio" name="<?php echo $this->get_field_name('include_rts'); ?>" id="<?php echo $this->get_field_id('include_rts_1'); ?>" value="1" <?php checked($instance['include_rts'], 1); ?> />
	<label for="<?php echo $this->get_field_id('include_rts_1'); ?>"><?php _e('Yes', 'twitter-tools'); ?></label>
	&nbsp;
	<input type="radio" name="<?php echo $this->get_field_name('include_rts'); ?>" id="<?php echo $this->get_field_id('include_rts_0'); ?>" value="0" <?php checked($instance['include_rts'], 0); ?> />
	<label for="<?php echo $this->get_field_id('include_rts_0'); ?>"><?php _e('No', 'twitter-tools'); ?></label>
</p>
<p>
	<?php _e('Include Replies?', 'twitter-tools'); ?>
	&nbsp;
	<input type="radio" name="<?php echo $this->get_field_name('include_replies'); ?>" id="<?php echo $this->get_field_id('include_replies_1'); ?>" value="1" <?php checked($instance['include_replies'], 1); ?> />
	<label for="<?php echo $this->get_field_id('include_replies_1'); ?>"><?php _e('Yes', 'twitter-tools'); ?></label>
	&nbsp;
	<input type="radio" name="<?php echo $this->get_field_name('include_replies'); ?>" id="<?php echo $this->get_field_id('include_replies_0'); ?>" value="0" <?php checked($instance['include_replies'], 0); ?> />
	<label for="<?php echo $this->get_field_id('include_replies_0'); ?>"><?php _e('No', 'twitter-tools'); ?></label>
</p>
<p><?php _e('Give Twitter Tools Credit?', 'twitter-tools'); ?></p>
<p>
	<input type="radio" name="<?php echo $this->get_field_name('credit'); ?>" id="<?php echo $this->get_field_id('credit_1'); ?>" value="1" <?php checked($instance['credit'], 1); ?> />
	<label for="<?php echo $this->get_field_id('credit_1'); ?>"><?php _e('Yes', 'twitter-tools'); ?></label>
	&nbsp;
	<input type="radio" name="<?php echo $this->get_field_name('credit'); ?>" id="<?php echo $this->get_field_id('credit_0'); ?>" value="0" <?php checked($instance['credit'], 0); ?> />
	<label for="<?php echo $this->get_field_id('credit_0'); ?>"><?php _e('No', 'twitter-tools'); ?></label>
</p>
<?php
	}

	function update($new_instance, $old_instance) {
		// processes widget options to be saved
		$instance = $old_instance;
		$instance['account'] = strip_tags($new_instance['account']);
		if (!($count = intval($new_instance['count']))) {
			$count = 1;
		}
		$instance['count'] = $count;
		$instance['include_rts'] = (int) $new_instance['include_rts'];
		$instance['include_replies'] = (int) $new_instance['include_replies'];
		$instance['credit'] = (int) $new_instance['credit'];
		return $instance;
	}

	function widget($args, $instance) {
		// outputs the content of the widget
		$tweets = new WP_Query(array(
			'post_type' => AKTT::$post_type,
		));
		include('views/tweet-list.php');
	}

}
add_action('widgets_init', function() {
	AKTT::get_social_accounts();
	if (count(AKTT::$accounts)) {
		return register_widget('AKTT_Widget');
	}
	return false;
});
