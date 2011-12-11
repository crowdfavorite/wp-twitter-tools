<style>
.dim {
	opacity: .5;
}
.aktt-upgrade-needed {
	background: #ffc;
	margin-top: 20px;
	padding: 20px;
}
table.form-table {
	margin: 20px 0;
}
table.form-table .help {
	color: #999;
}
table.form-table h3 {
	margin-top: 0;
}
#aktt-account-list {
	border-top: 1px solid #ddd;
	margin: 0;
	visibility: hidden;
}
.aktt-account {
	border-bottom: 1px solid #ddd;
	margin: 0;
	padding: 0;
}
.aktt-account-enabled {
	background: url(<?php echo esc_url(admin_url('images/yes.png')); ?>) right center no-repeat;
	opacity: 1;
}
.aktt-account h3 {
	cursor: hand;
	cursor: pointer;
	line-height: 48px;
	margin: 0;
	padding: 0 0 0 58px;
}
.aktt-account .settings {
	display: none;
	padding-left: 58px;
}
.depends-on-create-posts {
	background: #eee;
}
.depends-on-create-posts.dim {
	background: transparent;
}
</style>
<script>
function akttSetState(elem) {
	var $account = jQuery(elem);
	var $settings = $account.find('.settings');
	var $enabled = $account.find('input.enabled');
	var $createPosts = $account.find('input.create-posts');
// toggle enabled icon
	if (!$settings.is(':visible')) {
		if ($enabled.is(':checked')) {
			$account.addClass('aktt-account-enabled');
		}
		else {
			$account.addClass('dim');
		}
	}
// toggle enabled/dimmed for enabled dependent fields
	if ($enabled.is(':checked')) {
		$account.find('.depends-on-enabled').removeClass('dim');
	}
	else {
		$account.find('.depends-on-enabled').addClass('dim');
	}
// toggle enabled/dimmed for create blog posts dependent fields
	if ($createPosts.is(':checked')) {
		$account.find('.depends-on-create-posts').removeClass('dim');
	}
	else {
		$account.find('.depends-on-create-posts').addClass('dim');
	}
}
jQuery(function($) {
// toggle settings
	$('.aktt-account').each(function() {
		var $account = $(this);
		akttSetState($account);
		$account.find('h3').click(function() {
			$account.removeClass('dim aktt-account-enabled').find('.settings').slideToggle(function() {
				akttSetState($account);
			});
		}).end().find('input[type="checkbox"].enabled, input[type="checkbox"].create-posts').change(function() {
			akttSetState($account);
		});
	});
	$('#aktt-account-list').css({
		visibility: 'visible'
	});
});
</script>
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

		<div id="aktt-accounts">
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<h3><?php _e('Accounts', 'twitter-tools'); ?></h3>
						<?php printf(__('Manage Twitter accounts on your <a href="%s">Social settings page</a>.', 'twitter-tools'), admin_url('options-general.php?page=social.php')); ?>
					</th>
					<td>
						<ul id="aktt-account-list">
<?php
		if (empty(self::$accounts)) {
?>
							<li class="aktt-none">
								<?php _e('No Accounts.', 'twitter-tools'); ?>
							</li>
<?php
		}
		else {
			foreach (self::$accounts as $account) {
				include(AKTT_PATH.'/views/admin-account.php');
			}
		}
?>
						</ul><!-- /aktt-account-list -->
					</td>
				</tr>
			</table>
		</div><!-- <?php echo self::$prefix.'accounts'; ?> -->
	
<?php
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

