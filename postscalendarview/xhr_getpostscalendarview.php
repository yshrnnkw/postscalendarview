<?php
require '../../../wp-load.php';


defined('ABSPATH') || exit;


$opt = array();

$opt['xhr'] = 1;

if (isset($_GET['target'])) {
	$opt['target'] = $_GET['target'];
}

if (isset($_GET['config'])) {
	$opt['config'] = $_GET['config'];
}

echo PostsCalendarView\get_posts_calendar_view($opt);
?>