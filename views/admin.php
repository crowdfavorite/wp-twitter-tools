<style>
.dim {
	opacity: .5;
}
.button-secondary {
	margin-left: 10px;
}
.aktt-form {
	margin-bottom: 40px;
}
.aktt-upgrade-needed {
	background: #c5e0ef url(<?php echo esc_url(plugins_url('assets/img/bg-clouds.png', AKTT_FILE)); ?>) bottom center repeat-x;
	margin-top: 20px;
	padding: 15px 10px 5px;
}
.aktt-upgrade-needed h3,
.aktt-upgrade-needed p {
	margin: 0;
	padding: 0 0 10px;
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
	color: #666;
	margin: 0;
	padding: 0;
}
.aktt-account-enabled.aktt-account-collapsed {
	color: #333;
	opacity: 1;
}
.aktt-account h3 {
	cursor: hand;
	cursor: pointer;
	line-height: 48px;
	margin: 0;
	padding: 0 0 0 58px;
}
.aktt-account-disabled-notice {
	color: #999;
}
.aktt-account-enabled .aktt-account-disabled-notice {
	display: none;
}
.aktt-none .aktt-account h3 {
	cursor: default;
}
.aktt-account .settings {
	display: none;
	padding-left: 58px;
}
.depends-on-create-posts {
	background: #eee;
	margin: 0 0 10px;
	padding: 10px 10px 0;
}
.depends-on-create-posts.dim {
	background: transparent;
}
.depends-on-create-posts h4,
.depends-on-create-posts p {
	margin: 0 0 10px;
	padding: 0;
}
.depends-on-create-posts label.right {
	display: block;
	padding-left: 150px;
}
.depends-on-create-posts label.left {
	display: block;
	float: left;
	width: 150px;
}
table.form-table .depends-on-create-posts .help {
	color: #666;
}
.aktt-manual-update-request {
	vertical-align: middle;
	visibility: hidden;
}
.aktt-manual-update-running {
	margin-left: 10px;
}
</style>
<div class="wrap" id="<?php echo AKTT::$prefix.'options_page'; ?>">
	<?php screen_icon(); ?>
	<h2><?php _e('Twitter Tools', 'twitter-tools'); ?></h2>

<?php
if (AKTT::$enabled) {
	if ($upgrade_needed) {
?>
	<div class="aktt-upgrade-needed">
		<h3><?php _e('Upgrade Needed!', 'twitter-tools'); ?></h3>
		<p><?php _e('Looks like you have upgraded from a previous version of Twitter Tools.', 'twitter-tools'); ?>
		<a href="<?php echo esc_url(admin_url('index.php?aktt_action=upgrade-3.0')); ?>" class="aktt-upgrade-3.0 button-secondary"><?php _e('Upgrade Your Tweets', 'twitter-tools'); ?></a></p>
	</div>
<?php
	}
	
?>
	<form class="aktt-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
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
								<div class="aktt-account">
									<h3 style="background: url(http://www.gravatar.com/avatar/205e460b479e2e5b48aec07710c08d50?d=mm&f=y&s=48) left top no-repeat;"><?php _e('(no accounts)', 'twitter-tools'); ?></h3>
								</div>
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
	<p>
		<?php _e('Tweets are downloaded automatically every 15 minutes. Can\'t wait?', 'twitter-tools'); ?>
		<a href="<?php echo esc_url(AKTT::get_manual_update_url()); ?>" class="aktt-manual-update button-secondary"><?php _e('Download Tweets Now', 'twitter-tools'); ?></a>
		<span class="aktt-manual-update-running"></span>
		<img alt="" class="aktt-manual-update-request" src="<?php echo admin_url('images/wpspin_light.gif'); ?>">
	</p>

<?php
}
?>
</div><!-- /wrap -->
<script>
function akttSetState(elem) {
	var $account = jQuery(elem);
	var $settings = $account.find('.settings');
	var $enabled = $account.find('input.enabled');
	var $createPosts = $account.find('input.create-posts');
// toggle enabled icon
	if ($enabled.is(':checked')) {
		$account.addClass('aktt-account-enabled');
	}
	else {
		$account.removeClass('aktt-account-enabled');
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
			$account.find('.settings').slideToggle(function() {
				akttSetState($account);
			});
		}).end().find('input[type="checkbox"].enabled, input[type="checkbox"].create-posts').change(function() {
			akttSetState($account);
		});
	});
	$('#aktt-account-list').css({
		visibility: 'visible'
	});
	$('.aktt-manual-update').click(function(e) {
		e.preventDefault();
		var $this = $(this);
		var $request = $('.aktt-manual-update-request');
		var $running = $('.aktt-manual-update-running');
		$this.hide();
		$running.html('');
		$request.css({ visibility: 'visible' });
		$.get(
			$this.attr('href'),
			{
				aktt_actions: 'manual_tweet_download'
			},
			function (response) {
				$request.css({ visibility: 'hidden' });
				$this.show();
				$running.hide().html(response.msg).fadeIn();
			},
			'json'
		);
	});
});
</script>
