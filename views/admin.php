<style>
.aktt-upgrade-needed {
	background: #ffc;
	padding: 20px;
}
form .help {
	color: #999;
}
</style>
<div class="wrap" id="<?php echo AKTT::$prefix.'options_page'; ?>">
	<?php screen_icon(); ?>
	<h2><?php _e('Twitter Tools', 'twitter-tools'); ?></h2>

<?php
if (AKTT::$enabled) {
	if ($upgrade_needed || 1) {
?>
	<div class="aktt-upgrade-needed">
		<a href="<?php echo esc_url(admin_url('index.php?aktt_action=upgrade-3.0')); ?>" class="aktt-upgrade-3.0 button-secondary"><?php _e('Upgrade Previous Twitter Tools Data', 'twitter-tools'); ?></a>
	</div>
<?php
	}
	
?>
	<form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
		<table class="form-table">
<?php
	foreach (AKTT::$settings as $setting) {
?>
			<tr valign="top">
				<th scope="row"><?php echo $setting['label']; ?></th>
				<td>
<?php
		$options = array();
		foreach ($setting['options'] as $k => $v) {
			$name = 'aktt_v3_settings['.$setting['name'].']';
			$id = $name.'-'.$k;
			ob_start();
?>
					<label for="<?php echo esc_attr($id); ?>">
						<input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($k); ?>" id="<?php echo esc_attr($id); ?>" <?php checked($k, AKTT::option($setting['name'])); ?> />
						<?php echo $v; ?> 
					</label>
<?php
			$options[] = ob_get_clean();
		}
		echo implode('<br />', $options);
		if (!empty($setting['help'])) {
?>
					<br /><span class="help"><?php echo esc_html($setting['help']); ?></span>
<?php
		}
?>
				</td>
			</tr>
<?php
	}
?>
		</table>
	
<?php
	
	AKTT::output_account_settings_section();

	// Output the nonces, and hidden fields for the page
	settings_fields(AKTT::$menu_page_slug);
	
?>
		
		<input type="submit" class="button-primary" value="<?php _e('Save Settings', 'twitter-tools'); ?>" />
	</form>

	<h3><?php _e('Download Tweets', 'twitter-tools'); ?></h3>

	<a href="<?php echo esc_url(AKTT::get_manual_update_url()); ?>" class="aktt-manual-update button-secondary"><?php _e('Download Tweets Now', 'twitter-tools'); ?></a>

<?php
}
?>
</div><!-- /wrap -->

