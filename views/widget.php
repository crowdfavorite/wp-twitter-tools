<?php

include('tweet-list.php');

?>
	<p class="aktt_more_updates"><a href="<?php echo esc_url(AKTT::profile_url($username)); ?>"><?php _e('Follow Me on Twitter', 'twitter-tools'); ?></a></p>
<?php
if (AKTT::option('credit')) {
?>
	<p class="aktt_credit"><?php _e('Powered by <a href="http://crowdfavorite.com/wordpress/plugins/twitter-tools/">Twitter Tools</a>', 'twitter-tools'); ?></p>
<?php
}
?>
