<?php

// Upgrade data from Twitter Tools 2.x to 3.0

global $wpdb;
$wpdb->aktt = $wpdb->prefix.'ak_twitter';

function aktt_upgrade_30() {
	global $wpdb;
	$body = $head = $foot = '';
	$errors = array();
// check for username in wp_options data
	$username = get_option('aktt_twitter_username');
$username = 'foo';
	if (empty($username)) {
		$errors[] = '<div class="error"><p>'.__('Sorry, unable to find the legacy Twitter Tools username. Check that the <code>aktt_twitter_username</code> is set in your Options table.', 'twitter-tools').'</p></div>';
	}
// add upgraded col if needed
	$cols = $wpdb->get_results("
		DESCRIBE $wpdb->aktt;
	");
	$upgrade_col = false;
	foreach ($cols as $col) {
		if ($col->Field == 'upgrade_30') {
			$upgrade_col = true;
			break;
		}
	}
	if (!$upgrade_col) {
		$result = $wpdb->query("
			ALTER TABLE $wpdb->aktt
			ADD `upgrade_30` tinyint(1) default 0
			AFTER modified
		");
		if (!$result) {
			$errors[] = '<div class="error"><p>'.__('Sorry, unable to alter the database table as needed for the upgrade. Please check your database user permissions.', 'twitter-tools').'</p></div>';
		}
	}
// make sure we have some tweets to upgrade
	$count = $wpdb->get_var("
		SELECT count(id)
		FROM $wpdb->aktt
		WHERE upgrade_30 = 0
	");
	if (!$count) {
		$body = '<div class="error"><p>'.__('Sorry, it doesn\'t look like any tweets need upgrading.', 'twitter-tools').'</p></div>';
	}
// prep output
	if (count($errors)) {
		$body = implode("\n", $errors);
	}
	else {
		ob_start();
?>
<style type="text/css">
h3, p, p.step {
	text-align: center;
}
.step {
	margin-bottom: 35px;
}
.warning {
	background: #ffc;
	padding: 10px;
}
.dim {
	opacity: .3;
}
.button {
	margin: 0 10px;
}
.padded {
	padding: 10px 0 30px;
}
.progress {
	background: #eee;
	border: 1px solid #ccc;
	box-shadow: inset 0 0 2px #aaa;
	height: 30px;
	margin: auto;
	width: 400px;
}
.progress .bar {
	background: #aaa;
	height: 30px;
	width: 2px;
}
#process_complete {
	display: none;
}
</style>
<p class="warning"><?php _e('<b>WARNING!</b> Before you upgrade, please back up your data. Y\'know, just in case.', 'twitter-tools'); ?></p>
<div id="process">
	<div class="padded dim">
		<p><?php printf(__('Found %s tweets to upgrade', 'twitter-tools'), $count); ?></p>
		<div class="progress">
			<div class="bar" data-total="<?php echo esc_attr($count); ?>"></div>
		</div>
	</div>
	<p class="step">
		<a href="#" class="button" id="aktt_run_upgrade"><?php _e('Run Upgrade', 'twitter-tools'); ?></a>
		or <a href="javascript:history.go(-1);"><?php _e('Cancel', 'twitter-tools'); ?></a>
	</p>
</div>
<div id="process_complete">
	<div class="padded">
		<h3><?php _e('Yay!', 'twitter-tools'); ?></h3>
		<p><?php printf(__('Your tweets have been upgraded successfully.', 'twitter-tools'), esc_url(admin_url(''))); ?></p>
		<p><?php printf(__('Head back to your <a href="%s">Twitter Tools settings</a>.', 'twitter-tools'), esc_url(admin_url('options-general.php?page=twitter-tools'))); ?></p>
	</div>
</div>
<script type="text/javascript">
jQuery(function($) {
	$('#aktt_run_upgrade').click(function(e) {
		e.preventDefault();
		$button = $(this);
		$('.padded.dim').removeClass('dim');
		$('.warning').addClass('dim');
		$button.attr('disabled', true).addClass('dim');
		$.get(
			'<?php echo wp_nonce_url('index.php'); ?>',
			{
				'aktt_action': 'upgrade-3.0-run',
				'nonce': '<?php echo wp_create_nonce('upgrade-3.0-run'); ?>'
			},
			function(response) {
				if (response.result == 'error') {
					alert(response.message);
					return;
				}
				if (response.result == 'success') {
// update status bar
					var $bar = $('.progress .bar');
					var total = parseInt($bar.data('total'));
					var remaining = parseInt(response.to_upgrade);
					$bar.animate({ width: Math.ceil(((total - remaining) / total) * 400) + 'px' });
					if (remaining > 0) {
// request again
						$button.click();
					}
					else {
// complete?
						$('p.warning, #process').fadeOut('fast', function() {
							$('#process_complete').fadeIn('fast');
						});
					}
					return;
				}
				alert("<?php _e('Sorry, something didn\'t go as planned. Please try again.', 'twitter-tools'); ?>");
			},
			'json'
		);
		$(this).html('<?php _e('Upgrade Running&hellip;', 'twitter-tools'); ?>').attr('disabled', true);
	});
});
</script>
<?php
		$body = ob_get_clean();
	}
	echo aktt_upgrade_30_shell(__('Twitter Tools Upgrade', 'twitter-tools'), $body, $head, $foot);
}

function aktt_upgrade_30_run($count = 25) {
	global $wpdb;
// pull next tweet(s)
	$count = intval($count);
	$tweets = $wpdb->get_results("
		SELECT *
		FROM $wpdb->aktt
		WHERE upgrade_30 = 0
		LIMIT $count
	");
// upgrade
	if (count($tweets)) {
		$upgraded = array();
		$username = get_option('aktt_twitter_username'); // already passed sanity check to make sure this exists
		foreach ($tweets as $tweet) {
			if (trim($tweet->tw_text) == '') {
				continue;
			}
			$t = new AKTT_Tweet($tweet->tw_id);
			
			$t->data = new stdClass;
			$t->data->id = $t->data->id_str = $tweet->tw_id;
			$t->data->text = $tweet->tw_text;
			$t->data->created_at = date('D M d H:i:s +0000 Y', strtotime($tweet->tw_created_at.' +0000'));
			$t->data->in_reply_to_screen_name = $tweet->tw_reply_username;
			$t->data->in_reply_to_status_id = $t->data->in_reply_to_status_id_str = $tweet->tw_reply_tweet;
			
			$t->data->user = new stdClass;
			$t->data->user->screen_name = $username;
			
			$t->raw_data = json_encode($t->data);
			
			// skip if duplicate
			if ($t->exists_by_guid()) {
// already there, so mark as upgraded
				$upgraded[] = intval($tweet->id);
				continue;
			}
			
			if ($t->add()) {
// add meta - upgraded tweet
				update_post_meta($t->post_id, '_aktt_upgraded_30', 1);
				update_post_meta($t->post_id, '_aktt_30_backfill_needed', 1);
				$upgraded[] = intval($tweet->id);
			}
		}
		if (count($upgraded)) {
			$wpdb->query("
				UPDATE $wpdb->aktt
				SET upgrade_30 = 1
				WHERE id IN (".implode(',', $upgraded).")
			");
		}
	}
// return stats
	$to_upgrade = $wpdb->get_var("
		SELECT count(id)
		FROM $wpdb->aktt
		WHERE upgrade_30 = 0
	");
	return $to_upgrade;
}

function aktt_upgrade_30_shell($title = '', $body = '', $head = '', $foot = '') {
	ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $title; ?></title>
	<script type="text/javascript" src="<?php echo esc_url(includes_url('/js/jquery/jquery.js')); ?>"></script>
<?php
wp_admin_css('install', true);
do_action('admin_enqueue_styles');
do_action('admin_print_styles');

echo $head;
?>
</head>
<body>
<h1 id="logo"><?php echo $title; ?></h1>
<?php

echo $body.$foot;

?>
</body>
</html>
<?php
	return ob_get_clean();
}
