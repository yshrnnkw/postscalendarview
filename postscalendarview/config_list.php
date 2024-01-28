<?php
defined('ABSPATH') || exit;


return [
	// カテゴリーID get_post()と同じ指定
	'category' => '3',

	// カレンダータイプ grid, list, tiny
	'calendartype' => 'list',

	// tinyで詳細表示 0:表示しない, 1:表示する
	'dispdetail' => 1,

	// dateclass
	'dateclass' => 'dateclass',

	// 年月の表示
	// https://www.php.net/manual/ja/datetime.format.php
	'yearmonthformat' => 'Y.m',

	// ナビの表示
	'dispnavi' => 1,

	// breakpoint
	'breakpoint' => 480,	// max-width

	// カレンダーの開始曜日 0:日曜日, 1:月曜日
	'start_dayofweek' => 1,

	// 曜日の表記
	'dayofweek_str' => array(
		'sun'	=> '日',
		'mon'	=> '月',
		'tue'	=> '火',
		'wed'	=> '水',
		'thu'	=> '木',
		'fri'	=> '金',
		'sat'	=> '土',
	),

	// ナビの表記
	'navi_str' => array(
		'prev'		=> '前月',
		'now'		=> '今月',
		'next'		=> '次月',
	),

	// 詳細リンクの表記
	'go2post_str'	=> '詳細 &raquo;',

	// バンド表示 0:表示する, 1:表示しない
	'dispband' => 1,
];
?>