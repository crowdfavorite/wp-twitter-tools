<?php

// TODO
/*

- look for old table with tweets in it, if found show upgrade button

UPGRADE

- convert old tweets to new tweets

- show admin page
	- show warning
	- start button
	- choose Twitter account to use
- batch process/AJAX


CREATE BLOG POSTS

- create blog posts for new tweets that do not contain the base site URL
	- if contains t.co URL, need to convert 
	- need to convert t.co URLs before checking

*/

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
	$wpdb->aktt = $wpdb->prefix.'ak_twitter';
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
p, p.step {
	text-align: center;
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
</style>
<p class="warning"><?php _e('<b>WARNING!</b> Before you upgrade, please back up your data. Y\'know, just in case.</p>'); ?></p>
<form id="setup" method="post" action="<?php echo esc_url(admin_url('index.php?aktt_action=upgrade-3.0-run')); ?>">
	<?php wp_nonce_field(); ?>
	<div class="padded dim">
		<p><?php printf(__('Found %s tweets to upgrade', 'twitter-tools'), $count); ?></p>
		<div class="progress">
			<div class="bar"></div>
		</div>
		<p class="status">0%</p>
	</div>
	<p class="step">
		<a href="#" class="button"><?php _e('Run Upgrade', 'twitter-tools'); ?></a>
		or <a href="javascript:history.go(-1);"><?php _e('Cancel', 'twitter-tools'); ?></a>
	</p>
</form>
<script type="text/javascript">
</script>
<?php
		$body = ob_get_clean();
	}
	echo aktt_upgrade_30_shell(__('Twitter Tools Upgrade', 'twitter-tools'), $body, $head, $foot);
}

function aktt_upgrade_30_run($count = 10) {
	
}

function aktt_upgrade_30_shell($title = '', $body = '', $head = '', $foot = '') {
	ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $title; ?></title>
	<?php
	wp_admin_css('install', true);
	do_action('admin_enqueue_styles');
	do_action('admin_print_styles');
	echo $head;
	?>
</head>
<body>
<h1 id="logo"><?php echo $title; ?></h1>
<?php echo $body; ?>
<script type="text/javascript" src="<?php echo esc_url(includes_url('/js/jquery/jquery.js')); ?>"></script>
<?php echo $foot; ?>
</body>
</html>
<?php
	return ob_get_clean();
}
