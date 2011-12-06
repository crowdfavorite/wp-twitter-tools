<div class="aktt_tweets">
	<ul>
<?php
if (count($tweets) > 0) {
	foreach ($tweets as $tweet) {
		echo '		<li>'."\n";
		include(AKTT_PATH.'/views/tweet.php');
		echo '</li>'."\n";
	}
}
else {
?>
		<li><?php _e('No tweets available at the moment.', 'twitter-tools'); ?></li>
<?php
}
?>
	</ul>
</div>
