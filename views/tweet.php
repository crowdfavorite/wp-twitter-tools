<?php

// TODO 
// - link usernames if no @anyhere script
// - link hashtags
// - link URLs (replace t.co URLs)

echo wptexturize($tweet->post_content);

if (isset($tweet->tweet) && $tweet->tweet->is_reply()) {
	echo '<b>REPLY!!</b>';
}

// if (!empty($tweet->tw_reply_username)) {
// 	$output .= 	' <a href="'.aktt_status_url($tweet->tw_reply_username, $tweet->tw_reply_tweet).'" class="aktt_tweet_reply">'.sprintf(__('in reply to %s', 'twitter-tools'), $tweet->tw_reply_username).'</a>';
// }
// $time_display = aktt_relativeTime($tweet->tw_created_at, 3);
// 
// $output .= ' <a href="'.aktt_status_url($aktt->twitter_username, $tweet->tw_id).'" class="aktt_tweet_time">'.$time_display.'</a>';
// 
// echo $output;