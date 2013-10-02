<?php

$name = 'aktt_v3_accounts['.$account->id.'][settings][%s]';

?>
<div class="aktt-account">
	<h3 style="background: url(<?php echo esc_url($account->social_acct->avatar()); ?>) left top no-repeat;"><?php echo esc_html($account->social_acct->name()); ?> <span class="aktt-account-disabled-notice"><?php _e('(disabled)', 'twitter-tools'); ?></span></h3>
	<div class="settings">
		<p>
			<label for="<?php echo esc_attr(sprintf($name, 'enabled')); ?>">
				<input type="checkbox" name="<?php echo esc_attr(sprintf($name, 'enabled')); ?>" id="<?php echo esc_attr(sprintf($name, 'enabled')); ?>" value="1" <?php checked('1', $account->option('enabled')); ?> class="enabled" />
				<?php _e('Enable Twitter Tools', 'twitter-tools'); ?>
			</label>
		</p>
		<p class="depends-on-enabled">
			<label for="<?php echo esc_attr(sprintf($name, 'create_posts')); ?>">
				<input type="checkbox" name="<?php echo esc_attr(sprintf($name, 'create_posts')); ?>" id="<?php echo esc_attr(sprintf($name, 'create_posts')); ?>" value="1" <?php checked('1', $account->option('create_posts')); ?> class="create-posts" />
				<?php _e('Create blog posts from tweets', 'twitter-tools'); ?>
			</label>
		</p>
		<fieldset class="depends-on-create-posts">
			<h4><?php _e('Settings for blog posts', 'twitter-tools'); ?></h4>
			<p>
				<label class="left" for="<?php echo esc_attr(sprintf($name, 'blog_post_title')); ?>"><?php _e('Title prefix', 'twitter-tools'); ?></label>
				<input type="text" name="<?php echo esc_attr(sprintf($name, 'blog_post_title')); ?>" id="<?php echo esc_attr(sprintf($name, 'blog_post_title')); ?>" value="<?php echo esc_attr($account->option('blog_post_title')); ?>" />  <span class="help"><?php _e('(<b>Tweet:</b> = <b>Tweet:</b> This is my tweet...)', 'twitter-tools'); ?></span>
			</p>
			<p>
				<label class="left" for="<?php echo esc_attr(sprintf($name, 'post_author')); ?>"><?php _e('Author', 'twitter-tools'); ?></label>
<?php
wp_dropdown_users(array(
	'name' => sprintf($name, 'post_author'),
	'id' => sprintf($name, 'post_author'),
	'selected' => $account->option('post_author'),
	'who' => 'authors',
));
?>
			</p>
			<p>
				<label class="left" for="<?php echo esc_attr(sprintf($name, 'post_category')); ?>"><?php _e('Category', 'twitter-tools'); ?></label>
<?php
wp_dropdown_categories(array(
	'name' => sprintf($name, 'post_category'),
	'id' => sprintf($name, 'post_category'),
	'selected' => $account->option('post_category'),
	'hide_empty' => 0,
	'taxonomy' => 'category',
));
?>
			</p>
			<p>
				<label class="left" for="<?php echo esc_attr(sprintf($name, 'post_status')); ?>"><?php _e('Status', 'twitter-tools'); ?></label>
				<select name="<?php echo esc_attr(sprintf($name, 'post_status')); ?>" id="<?php echo esc_attr(sprintf($name, 'post_status')); ?>">
<?php
$post_statuses = get_post_stati(array('show_in_admin_status_list' => true), 'objects');
foreach ($post_statuses as $post_status_key => $post_status) {
	echo '<option class="level-0" value="', esc_attr($post_status->name), '"', selected($account->option('post_status'), $post_status->name, false),'>', esc_html($post_status->label), '</option>';
}
?>
				</select>
			</p>
			<p>
				<label class="left" for="<?php echo esc_attr(sprintf($name, 'post_tags')); ?>"><?php _e('Tags', 'twitter-tools'); ?></label>
				<input type="text" class="type-ahead" data-tax="post_tag" name="<?php echo esc_attr(sprintf($name, 'post_tags')); ?>" id="<?php echo esc_attr(sprintf($name, 'post_tags')); ?>" value="<?php echo esc_attr($account->option('post_tags')); ?>" />  <span class="help"><?php _e('(comma separated)', 'twitter-tools'); ?></span>
			</p>
			<p>
				<label class="right" for="<?php echo esc_attr(sprintf($name, 'exclude_reply_tweets')); ?>">
					<input type="checkbox" name="<?php echo esc_attr(sprintf($name, 'exclude_reply_tweets')); ?>" id="<?php echo esc_attr(sprintf($name, 'exclude_reply_tweets')); ?>" value="1" <?php checked('1', $account->option('exclude_reply_tweets')); ?> />
					<?php _e('Don\'t create posts for reply tweets', 'twitter-tools'); ?>
				</label>
			</p>
			<p>
				<label class="right" for="<?php echo esc_attr(sprintf($name, 'exclude_retweets')); ?>">
					<input type="checkbox" name="<?php echo esc_attr(sprintf($name, 'exclude_retweets')); ?>" id="<?php echo esc_attr(sprintf($name, 'exclude_retweets')); ?>" value="1" <?php checked('1', $account->option('exclude_retweets')); ?> />
					<?php _e('Don\'t create posts for re-tweets (RTs)', 'twitter-tools'); ?>
				</label>
			</p>
		</fieldset>
<?php

do_action('aktt_admin_account_settings_form');

?>
	</div>
</div>

