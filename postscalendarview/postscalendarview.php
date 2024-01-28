<?php
/*
Plugin Name: Posts Calendar View
Description:
Version: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: SAKURA WORKS
*/


namespace PostsCalendarView;


// ダイレクトアクセスを禁止 ----------------------------------------------------
defined('ABSPATH') || exit;


// ショートコード --------------------------------------------------------------
add_shortcode('postscalendarview', 'PostsCalendarView\get_posts_calendar_view');


// Javascript ------------------------------------------------------------------
function add_posts_calendar_view_scripts() {
	wp_enqueue_script(
		'postscalendarview.js',
		plugins_url('postscalendarview.js', __FILE__),
		['jquery']
	);

	wp_localize_script('postscalendarview.js', 'postscalendarview', array(
		'xhr_url'		=> plugins_url('xhr_getpostscalendarview.php', __FILE__),
	));
}
add_action('wp_enqueue_scripts', 'PostsCalendarView\add_posts_calendar_view_scripts');


// CSS -------------------------------------------------------------------------
function add_posts_calendar_view_css() {
	wp_enqueue_style('postscalendarview.css',  plugins_url('postscalendarview.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'PostsCalendarView\add_posts_calendar_view_css');


// Function --------------------------------------------------------------------
function get_posts_calendar_view($_opt) {
	global $post;


	// WordPress指定のタイムゾーン
	$timezone_string = get_option('timezone_string');
	// タイムゾーン
	$timezone = new \DateTimeZone($timezone_string);


	$opt_default = array(
		'target'			=> '',
		'config'			=> 'config',
		'xhr'				=> 0,
		'category'			=> '',
		'calendartype'		=> 'grid',
		'dispdetail'		=> 1,
		'dateclass'			=> '',
		'yearmonthformat'	=> 'Y.m',
		'dispnavi'			=> 1,
		'breakpoint'		=> 480,
		'start_dayofweek'	=> 0,
		'dayofweek_str'		=> array(
			'sun'	=> 'sun',
			'mon'	=> 'mon',
			'tue'	=> 'tue',
			'wed'	=> 'wed',
			'thu'	=> 'thu',
			'fri'	=> 'fri',
			'sat'	=> 'sat',
		),
		'navi_str' => array(
			'prev'		=> 'prev',
			'now'		=> 'now',
			'next'		=> 'next',
		),
		'go2post_str' 		=> '',
		'dispband' 			=> 1,
	);

	$opt = $opt_default;
	if (is_array($_opt) and sizeof($_opt) > 0) {
		$opt = array_merge($opt_default, $_opt);
	}

	// 設定ファイルで上書き
	$config = array();
	if (preg_match('/^[-_a-zA-Z0-9]+$/', $opt['config']) == 1) {
		$config_file = plugin_dir_path(__FILE__).$opt['config'].'.php';

		if (file_exists($config_file)) {
			$config = include($config_file);
		} else {
			return 'configuration file is not exist.';
		}
	} else {
		return 'configuration file error.';
	}

	$opt = array_merge($opt, $config);


	// 日付クラスファイル読み込み
	$dateclass_list = array();
	if (preg_match('/^[-_a-zA-Z0-9]+$/', $opt['dateclass']) == 1) {
		$dateclass_list_file = plugin_dir_path(__FILE__).$opt['dateclass'].'.php';

		if (file_exists($dateclass_list_file)) {
			$dateclass_list = include($dateclass_list_file);
		}
	}

	// 設定値チェック
	if (preg_match('/^(grid|tiny|list)$/', $opt['calendartype'], $_m) != 1) {
		return 'configuration is not valid.';
	}
	if (preg_match('/^[01]$/', $opt['dispdetail'], $_m) != 1) {
		return 'calendartype is not valid.';
	}
	if (preg_match('/^[01]$/', $opt['dispnavi'], $_m) != 1) {
		return 'dispnavi is not valid.';
	}
	if (preg_match('/^[0-9]+$/', $opt['breakpoint'], $_m) != 1) {
		return 'breakpoint is not valid.';
	}
	if (preg_match('/^[01]$/', $opt['start_dayofweek'], $_m) != 1) {
		return 'start_dayofweek is not valid.';
	}
	foreach (array_keys($opt_default['dayofweek_str']) as $_v) {
		if (!isset($config['dayofweek_str'][$_v])) {
			return 'dayofweek_str is not valid.';
		}
	}
	foreach (array_keys($opt_default['navi_str']) as $_v) {
		if (!isset($config['navi_str'][$_v])) {
			return 'navi_str is not valid.';
		}
	}
	if (preg_match('/^[01]$/', $opt['dispband'], $_m) != 1) {
		return 'dispband is not valid.';
	}


	// 表示対象年月日
	$current = new \DateTime($timezone_string);
	// 指定がある場合は1日の日付とする
	if (preg_match('/^([0-9]{4})-([0-9]{1,2})$/', $opt['target'], $_m) == 1) {
		$current = new \DateTime($_m[1].'-'.$_m[2].'-01', $timezone);
	}
	// 現在の日時
	$now = new \DateTime($timezone_string);
	// 表示対象の前月
	$prev = new \DateTime($current->format('Y-m-d').'first day of previous month', $timezone);
	// 表示対象の次月
	$next = new \DateTime($current->format('Y-m-d').'first day of next month', $timezone);
	// 表示対象の月初
	$month_1st = new \DateTime($current->format('Y-m-d').' first day of this month', $timezone);
	// 表示対象の月末
	$month_last = new \DateTime($current->format('Y-m-d').' last day of this month', $timezone);
	// カレンダーのマスの最初の日付
	$offset = (int)$month_1st->format('w') - $opt['start_dayofweek'];
	if ($offset < 0) {
		$offset = 6;
	}
	$calendar_start = new \DateTime($month_1st->format('Y-m-d 00:00:00').' - '.$offset.' day', $timezone);
	// カレンダーのマスの最後の日付
	$offset = 6 - (int)$month_last->format('w') + $opt['start_dayofweek'];
	if ($offset > 6) {
		$offset = 0;
	}
	$calendar_end = new \DateTime($month_last->format('Y-m-d 00:00:00').' + '.$offset.' day', $timezone);

	// カレンダーの表示開始曜日
	if ($opt['start_dayofweek'] == 1) {
		$_sun = array_shift($opt['dayofweek_str']);
		$opt['dayofweek_str'] = $opt['dayofweek_str'] + array('sun' => $_sun);
	}

	// 表示する総日数
	$period = $calendar_start->diff($calendar_end);


	// 表示用データ
	$target = clone $calendar_start;
	$calendar_list = array();
	for ($i = 0; $i < $period->days + 1; $i++) {
		$date = $target->format('Y-m-d');

		$calendar_list[$date] = (object) array(
			'date'			=> $date,
			'monthumber'	=> $target->format('m'),
			'daynumber'		=> $target->format('d'),
			'dayofweek'		=> strtolower($target->format('D')),
			'outside'		=> ( $target->format('m') == $current->format('m') ? false : true ),
			'event_list'	=> array(),
		);

		$target->modify('+1 day');
	}


	// イベントを取得
	$args = array(
		'post_status'		=> 'publish',
		'numberposts'		=> -1,
		'category'			=> $opt['category'],
		'meta_key'			=> 'postscalendarview_start',
		'order_by'			=> 'meta_value',
		'order'				=> 'ASC',
		'meta_query'		=> array(
			'relation'	=> 'OR',
			array(
				'key'		=> 'postscalendarview_start',
				'value'		=> $calendar_end->format('Y-m-d 00:00:00'),
				'compare'	=> '<=',
				'type'		=> 'DATE',
			),
			array(
				'key'		=> 'postscalendarview_end',
				'value'		=> $calendar_start->format('Y-m-d 23:59:59'),
				'compare'	=> '>=',
				'type'		=> 'DATE',
			),
		),
	);
	$posts_list = get_posts($args);

	// イベントを表示用データに追加
	foreach($posts_list as $post) {
		// CF・イベント開始日時が指定の書式で設定されていなければ処理しない
		if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}) ?([0-9]{2}:[0-9]{2})?$/', $post->postscalendarview_start, $_m) == 1) {
			$_start = $_m[0];
			$_date = ( isset($_m[1]) ? $_m[1] : '');
			$_time = ( isset($_m[2]) ? $_m[2] : '');
		} else {
			continue;
		}

		// CF・イベント終了日が指定の書式で設定されていなければ空文字列に強制変更し、複数日イベントとして扱わない
		if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})$/', $post->postscalendarview_end, $_m) == 1) {
			$_end = $_m[0];
		} else {
			$_end = '';
		}

		// CF・イベント終了日時が設定されている場合にリピート回数を設定
		$_repeat_times = 0;
		if ($_end != '') {
			$postscalendarview_end = new \DateTime($_end, $timezone);
			// 繰り返す回数（時刻を揃えて比較）
			$repeat_times_start = new \DateTime($_date.' 00:00:00', $timezone);
			$repeat_times_end = new \DateTime($postscalendarview_end->format('Y-m-d 00:00:00'), $timezone);
			$repeat_times = $repeat_times_start->diff($repeat_times_end);

			// 回数が正の場合は続行
			if ($repeat_times->invert == 0) {
				$_repeat_times = $repeat_times->days;
			}
		}

		$cat = get_the_category();
		$cat_slug = array();
		$cat_name = array();
		foreach ($cat as $_v) {
			$cat_slug[] = $_v->slug;
			$cat_name[] = $_v->name;
		}

		$tags = get_the_tags();
		$tag_slug = array();
		$tag_name = array();
		if ($tags != false) {
			foreach ($tags as $_v) {
				$tag_slug[] = $_v->slug;
				$tag_name[] = $_v->name;
			}
		}

		$_event = (object) array(
			'id'							=> $post->ID,
			'title'							=> $post->post_title,
			'url'							=> get_the_permalink(),
			'cat_slug'						=> implode(' ', $cat_slug),
			'cat_name'						=> $cat_name,
			'tag_slug'						=> implode(' ', $tag_slug),
			'tag_name'						=> $tag_name,
			'excerpt'						=> $post->post_excerpt,
			'thumbnail'						=> get_the_post_thumbnail_url(),
			'postscalendarview_start'		=> $_start,
			'postscalendarview_start_date'	=> $_date,
			'postscalendarview_start_time'	=> $_time,
			'postscalendarview_end'			=> $_end,
			'postscalendarview_str'			=> $post->postscalendarview_str,
			'repeat_times'					=> $_repeat_times,
			'repeat_first'					=> false,
			'repeat_last'					=> false,
		);


		// イベントは各日付のevent_listに入れる
		// 繰り返しの有無に関係なく、全て処理対象
		// 繰り返しの有無に関係なく、全て$_eventのクローンを利用する
		// 繰り返しイベントは各日付にコピーする
		// 2日間のイベントはrepeat_timesが1
		// $_eventはここで捨てる
		for ($i = 0; $i <= $_event->repeat_times; $i++) {
			$repeat_start = new \DateTime($_event->postscalendarview_start.' + '.$i.' day', $timezone);
			$repeat_start_str = $repeat_start->format('Y-m-d');

			$__event = clone $_event;

			if ($__event->repeat_times > 0) {
				// 最初の繰り返しイベント
				if ($i == 0) {
					$__event->repeat_first = true;
				// 最後の繰り返しイベント
				} elseif ($i == $__event->repeat_times) {
					$__event->repeat_last = true;
				}
			}
			if (array_key_exists($repeat_start_str, $calendar_list)) {
				$calendar_list[$repeat_start_str]->event_list[] = $__event;
			}
		}
	}


	// 各日付のevent_listの重ね順を並び替える。この順序にしないとJavascriptによるダミーの追加が正しく動かない
	foreach ($calendar_list as $_k => $_v) {
		if ($opt['calendartype'] == 'grid' and $opt['dispband'] == 1) {
			array_multisort(
				// 開始日が早い方が上
				array_column($_v->event_list, 'postscalendarview_start_date'), SORT_ASC, SORT_REGULAR,
				// 期間が長い方が上
				array_column($_v->event_list, 'repeat_times'), SORT_DESC, SORT_NUMERIC,
				// 開始時間が早い方が上
				array_column($_v->event_list, 'postscalendarview_start_time'), SORT_ASC, SORT_REGULAR,
				// 並び替えの対象
				$_v->event_list
			);
		} else {
			array_multisort(
				// 開始時間が早い方が上
				array_column($_v->event_list, 'postscalendarview_start_time'), SORT_ASC, SORT_REGULAR,
				// 期間が長い方が上
				array_column($_v->event_list, 'repeat_times'), SORT_DESC, SORT_NUMERIC,
				// 並び替えの対象
				$_v->event_list
			);
		}
	}


	$calendar_list = array_chunk($calendar_list, 7);


	$html = '';

	if ($opt['xhr'] === 0) {
		$_class = array('postscalendarview');
		array_push($_class, $opt['calendartype']);
		if ($opt['calendartype'] == 'grid' and $opt['dispband'] == 1) {
			array_push($_class, 'dispband');
		}
		if ($opt['calendartype'] == 'tiny' and $opt['dispdetail'] == 1) {
			array_push($_class, 'dispdetail');
		}
		$breakpoint = '';
		if ($opt['calendartype'] == 'grid') {
			$breakpoint = 'data-breakpoint="'.$opt['breakpoint'].'"';
		}

		$html .= '<div class="'.implode(' ', $_class).'" data-config="'.$opt['config'].'"'.$breakpoint.'>'.PHP_EOL;
	}


	$html .= '<div class="yearmonth">';
	$html .= $current->format($opt['yearmonthformat']);
	$html .= '</div>'.PHP_EOL;


	if ($opt['dispnavi']== 1) {
		$html .= '<div class="navi">'.PHP_EOL;
		$html .= '<a class="prev" href="javascript:void(0);" data-target="'.$prev->format('Y-m').'">'.$opt['navi_str']['prev'].'</a>'.PHP_EOL;
		$html .= '<a class="now" href="javascript:void(0);" data-target="'.$now->format('Y-m').'">'.$opt['navi_str']['now'].'</a>'.PHP_EOL;
		$html .= '<a class="next" href="javascript:void(0);" data-target="'.$next->format('Y-m').'">'.$opt['navi_str']['next'].'</a>'.PHP_EOL;
		$html .= '</div><!-- // .navi -->'.PHP_EOL;
	}


	$html .= '<div class="calendar">'.PHP_EOL;

	$html .= '<div class="header">'.PHP_EOL;
	foreach ($opt['dayofweek_str'] as $_k => $_v) {
		$html .= '<div class="day '.$_k.'"><div class="label">'.$_v.'</div></div>'.PHP_EOL;
	}
	$html .= '</div><!-- // .header -->'.PHP_EOL;


	foreach ($calendar_list as $week) {

		$html .= '<div class="week">'.PHP_EOL;

		foreach ($week as $_v) {
			$_day_class = array('day', $_v->dayofweek);
			if (sizeof($_v->event_list) > 0) {
				array_push($_day_class, 'have_event');
			}
			if ($_v->outside) {
				array_push($_day_class, 'outside');
			}
			if (isset($dateclass_list[$_v->date])) {
				array_push($_day_class, $dateclass_list[$_v->date]);
			}


			$html .= '<div class="'.implode(' ', $_day_class).'" data-date="'.$_v->date.'">'.PHP_EOL;

			$html .= '<div class="label">'.PHP_EOL;
			$html .= '<div class="daynumber">'.(int)$_v->daynumber.'</div>'.PHP_EOL;
			$html .= '<div class="dayofweek">'.$opt['dayofweek_str'][$_v->dayofweek].'</div>'.PHP_EOL;
			$html .= '</div><!-- // .label -->'.PHP_EOL;

			$html .= '<div class="pcve_list">'.PHP_EOL;

			if (sizeof($_v->event_list) > 0) {
				foreach ($_v->event_list as $_e) {
					$_event_class = array('pcve');
					if ($_e->cat_slug != '') {
						array_push($_event_class, $_e->cat_slug);
					}
					if ($_e->tag_slug != '') {
						array_push($_event_class, $_e->tag_slug);
					}
					if ($_e->repeat_times == 0) {
						array_push($_event_class, 'once');
					} elseif ($_e->repeat_times > 0) {
						array_push($_event_class, 'repeat');
					}
					if ($_e->repeat_first) {
						array_push($_event_class, 'repeat_first');
					}
					if ($_e->repeat_last) {
						array_push($_event_class, 'repeat_last');
					}
					if ($_e->repeat_times > 0 and !$_e->repeat_first and !$_e->repeat_last) {
						array_push($_event_class, 'repeat_middle');
					}


					$html .= '<a class="'.implode(' ', $_event_class).'" href="'.$_e->url.'" data-idx="'.$_e->id.'">'.PHP_EOL;
					if ($_e->thumbnail != '') {
						$html .= '<div class="fig"><img src="'.$_e->thumbnail.'"></div>'.PHP_EOL;
					}
					$html .= '<div class="category">';
					foreach ($_e->cat_name as $_cat) {
						$html .= '<span>'.$_cat.'</span>'.PHP_EOL;
					}
					$html .= '</div>'.PHP_EOL;
					if ($_e->postscalendarview_str != '') {
						$html .= '<div class="time">'.$_e->postscalendarview_str.'</div>'.PHP_EOL;
					} elseif ($_e->postscalendarview_start_time != '') {
						$html .= '<div class="time">'.$_e->postscalendarview_start_time.'</div>'.PHP_EOL;
					}
					$html .= '<div class="name">'.$_e->title.'</div>'.PHP_EOL;
					$html .= '<div class="excerpt">'.$_e->excerpt.'</div>'.PHP_EOL;
					$html .= '<div class="tags">';
					foreach ($_e->tag_name as $_tag) {
						$html .= '<span>'.$_tag.'</span>'.PHP_EOL;
					}
					$html .= '</div>'.PHP_EOL;
					if ($opt['go2post_str'] != '') {
						$html .= '<div class="go2post">'.$opt['go2post_str'].'</div>'.PHP_EOL;
					}
					$html .= '</a>'.PHP_EOL;
				}
			}

			$html .= '</div><!-- // .event_list -->'.PHP_EOL;

			$html .= '</div><!-- // .day -->'.PHP_EOL;
		}

		$html .= '</div><!-- // .week -->'.PHP_EOL;

	}

	$html .= '</div><!-- // .calendar -->'.PHP_EOL;

	$html .= '<div class="detail">'.PHP_EOL;
	$html .= '</div><!-- // .detail -->'.PHP_EOL;

	if ($opt['xhr'] === 0) {
		$html .= '</div><!-- // .postscalendarview -->'.PHP_EOL;
	}


	return $html;
}
?>