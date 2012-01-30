<?php

$content = $tweet->post_content;
if (isset($tweet->tweet)) {
	$content = $tweet->tweet->link_entities();
}

echo wptexturize($content);

if (isset($tweet->tweet)) {
	$reply_id = $tweet->tweet->reply_id();
	if (!empty($reply_id)) {
?>
 <a href="<?php echo esc_url(AKTT::status_url($tweet->tweet->reply_screen_name(), $reply_id)); ?>" class="aktt_tweet_reply"><?php printf(__('in reply to %s', 'twitter-tools'), esc_html($tweet->tweet->reply_screen_name())); ?></a>
<?php
	}
?>
 <a href="<?php echo esc_url($tweet->tweet->status_url()); ?>" class="aktt_tweet_time"><?php echo sprintf(__('%s ago', 'twitter-tools'), human_time_diff(strtotime($tweet->post_date_gmt))); ?></a>
<?php
}
