<?php
$output = aktt_make_clickable(wp_specialchars($tweet->tw_text));
if (!empty($tweet->tw_reply_username)) {
	$output .= 	' <a href="'.aktt_status_url($tweet->tw_reply_username, $tweet->tw_reply_tweet).'" class="aktt_tweet_reply">'.sprintf(__('in reply to %s', 'twitter-tools'), $tweet->tw_reply_username).'</a>';
}
$time_display = aktt_relativeTime($tweet->tw_created_at, 3);

$output .= ' <a href="'.aktt_status_url($aktt->twitter_username, $tweet->tw_id).'" class="aktt_tweet_time">'.$time_display.'</a>';

echo $output;