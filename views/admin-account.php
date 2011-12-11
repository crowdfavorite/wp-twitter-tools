<?php

$name = 'aktt_v3_accounts['.$account->id.'][settings][%s]';

?>
<div class="aktt-account">
	<h3 style="background: url(<?php echo esc_url($account->social_acct->avatar()); ?>) left top no-repeat;"><?php echo esc_html($account->social_acct->name()); ?></h3>
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
			<p>
				<label for="<?php echo esc_attr(sprintf($name, 'exclude_reply_tweets')); ?>">
					<input type="checkbox" name="<?php echo esc_attr(sprintf($name, 'exclude_reply_tweets')); ?>" id="<?php echo esc_attr(sprintf($name, 'exclude_reply_tweets')); ?>" value="1" <?php checked('1', $account->option('exclude_reply_tweets')); ?> />
					<?php _e('Don\'t create posts for reply tweets', 'twitter-tools'); ?>
				</label>
			</p>
		</fieldset>
	</div>
</div>

