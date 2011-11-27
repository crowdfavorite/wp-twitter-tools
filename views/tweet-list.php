<div class="aktt_tweets">
	<ul>
<?php
if (count($tweets) > 0) {
	foreach ($tweets as $tweet) {
		echo '		<li>'."\n";
		include('tweet.php');
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
	<p class="aktt_more_updates"><a href="<?php echo AKTT::profile_url($username) ?>"><?php _e('Follow Me on Twitter', 'twitter-tools'); ?></a></p>
<?php
if (AKTT::get_option('credit')) {
?>
	<p class="aktt_credit"><?php _e('Powered by <a href="http://crowdfavorite.com/wordpress/plugins/twitter-tools/">Twitter Tools</a>', 'twitter-tools'); ?></p>
<?php
}
?>
</div>
