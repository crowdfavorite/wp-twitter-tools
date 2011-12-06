<div class="wrap" id="<?php echo AKTT::$prefix.'options_page'; ?>">
	<?php screen_icon(); ?>
	<h2><?php _e('Twitter Tools', 'twitter-tools'); ?></h2>

<?php
if (AKTT::$enabled) {
	if ($upgrade_needed || 1) {
?>
	<a href="<?php echo esc_url(admin_url('index.php?aktt_action=upgrade-3.0')); ?>" class="aktt-upgrade-3.0 button-secondary"><?php _e('Upgrade Previous Twitter Tools Data', 'twitter-tools'); ?></a>
<?php
	}
	
?>
	<form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
	
<?php 
	// Output the nonces, and hidden fields for the page
	settings_fields(AKTT::$menu_page_slug);
	
	// Output the visible settings fields
	do_settings_sections(AKTT::$menu_page_slug);
?>
		
		<input type="submit" class="button-primary" value="<?php _e('Save Settings', 'twitter-tools'); ?>" />
	</form>

	<h3><?php _e('Download Tweets', 'twitter-tools'); ?></h3>

	<a href="<?php echo esc_url(AKTT::get_manual_update_url()); ?>" class="aktt-manual-update button-secondary"><?php _e('Download Tweets Now', 'twitter-tools'); ?></a>

<?php
}
?>
</div><!-- /wrap -->
