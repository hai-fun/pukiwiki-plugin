<?php
// $Id: recentupdates.inc.php,v 1.1 2022/01/07 01:54:29 はいふん Exp $

// Origin:
// PukiWiki - Yet another WikiWikiWeb clone
// recent.inc.php
// Copyright
//   2002-2017 PukiWiki Development Team
//   2002      Y.MASUI http://masui.net/pukiwiki/ masui@masui.net
// License: GPL v2 or (at your option) any later version
//
// Recent plugin -- Show RecentChanges list
//   * Usually used at 'MenuBar' page
//   * Also used at special-page, without no #recnet at 'MenuBar'

// Default event display
define('PLUGIN_RECENTUPDATES_DEFAULT_SHOW_LINES', 50);

// max of event display
define('PLUGIN_RECENTUPDATES_LIMIT_SHOW_LINES', 500);

// number of Page of operation
define('PLUGIN_RECENTUPDATES_OPERATION_COUNT', 9);

// Limit number of executions
define('PLUGIN_RECENTUPDATES_EXEC_LIMIT', 3); // N times per one output

// Display author of page
define('PLUGIN_RECENTUPDATES_DISPLAY_AUTHOR', TRUE);

// ----

define('PLUGIN_RECENTUPDATES_USAGE', '#recentupdates(number-to-show)');

// Place of the cache of 'RecentChanges'
define('PLUGIN_RECENTUPDATES_CACHE', CACHE_DIR . 'recent.dat');
define('PLUGIN_RECENTUPDATES_EXTENTION_CACHE', CACHE_DIR . 'recentupdates.dat');

function plugin_recentupdates_init() {
	// ja.lng.php
	$messages = array(
		'_recentupdates_messages' => array(
			'msg_display_number' => '表示件数',
			'msg_title' => '最近の更新',
			'msg_all' => 'すべて',
			'btn_prev' => '前へ',
			'btn_next' => '次へ'
		)
	);
	
	/*
	// en.lng.php
	$messages = array(
		'_recentupdates_messages' => array(
			'msg_display_number' => 'Event display',
			'msg_all' => 'ALL',
			'btn_prev' => 'Prev',
			'btn_next' => 'Next'
		)
	);
	*/
	
	set_plugin_messages($messages);
}

function plugin_recentupdates_convert()
{
	global $_recentupdates_messages, $_LANG;
	global $vars;
	global $date_format, $time_format, $weeklabels;
	static $exec_count = 1;
	$script = get_script_uri();

	$isMax = false;
	$isAll = $vars['limit'] == 0 && isset($vars['limit']);

	$show_lines = PLUGIN_RECENTUPDATES_DEFAULT_SHOW_LINES;
	if (func_num_args()) {
		$args = func_get_args();
		if (! is_numeric($args[0]) || isset($args[1])) {
			return PLUGIN_RECENTUPDATES_USAGE . '<br />';
		} else {
			$show_lines = $args[0];
		}
	}
	
	$isAction = false;
	if (strtolower($vars['plugin']) == 'recentupdates' || strtolower($vars['cmd']) == 'recentupdates') $isAction = true;
	
	$from = 1;
	if (isset($vars['limit'])) $show_lines = $vars['limit'];
	if (isset($vars['from'])) $from = $vars['from'];
	// int型へキャスト
	if (!is_int($show_lines)) $show_lines = (int) $show_lines;
	if (!is_int($from)) $from = (int) $from;
	--$from;
	if ($from < 0) $from = 0;

	// Show only N times
	if ($exec_count > PLUGIN_RECENTUPDATES_EXEC_LIMIT) {
		return '#recentupdates(): You called me too much' . '<br />' . "\n";
	} else {
		++$exec_count;
	}

	if (! file_exists(PLUGIN_RECENTUPDATES_CACHE)) {
		put_lastmodified();
		if (! file_exists(PLUGIN_RECENTUPDATES_CACHE)) {
			return '#recentupdates(): ' . PLUGIN_RECENTUPDATES_CACHE . '/ not found' . '<br />';
		}
	}
	// Cache
	if (! file_exists(PLUGIN_RECENTUPDATES_EXTENTION_CACHE) || filemtime(PLUGIN_RECENTUPDATES_CACHE) != filemtime(PLUGIN_RECENTUPDATES_EXTENTION_CACHE)) {
		$lines = file(PLUGIN_RECENTUPDATES_CACHE);
		pkwk_touch_file(PLUGIN_RECENTUPDATES_EXTENTION_CACHE);
		$fp = fopen(PLUGIN_RECENTUPDATES_EXTENTION_CACHE, 'r+') or
			die_message('Cannot open' . PLUGIN_RECENTUPDATES_EXTENTION_CACHE);
		set_file_buffer($fp, 0);
		flock($fp, LOCK_EX);
		ftruncate($fp, 0);
		rewind($fp);
		foreach ($lines as $line) {
			list($time, $page) = explode("\t", rtrim($line));
			$page_head = file_head(get_filename($page))[0];
			$author = '';
			if (preg_match("/#author\(\".*?\",\".*?\",\"(.*?)\"\)/", $page_head, $matches)) {
				$author = $matches[1];
			}
			fputs($fp, $time . "\t" . $page . "\t" . plugin_recentupdates_get_diff_str($page) . "\t" . $author . "\n");
		}
		flock($fp, LOCK_UN);
		fclose($fp);
		
		// 最終更新をrecent.datに合わせる。
		touch(PLUGIN_RECENTUPDATES_EXTENTION_CACHE, filemtime(PLUGIN_RECENTUPDATES_CACHE));
		if (! file_exists(PLUGIN_RECENTUPDATES_EXTENTION_CACHE)) {
			return '#recentupdates(): ' . PLUGIN_RECENTUPDATES_EXTENTION_CACHE . '/ not found' . '<br />';
		}
	}

	// 定義より大きい場合、定義値にする
	if ($show_lines > PLUGIN_RECENTUPDATES_LIMIT_SHOW_LINES) {
		$show_lines = PLUGIN_RECENTUPDATES_LIMIT_SHOW_LINES;
	}

	// Get latest N changes
	if ($show_lines == 0) {
		$lines = array_slice(file(PLUGIN_RECENTUPDATES_EXTENTION_CACHE), $from);
		
		if ($lines == FALSE) return '#recentupdates(): File can not open' . '<br />' . "\n";
	} else {
		$lines_p = array_slice(file_head(PLUGIN_RECENTUPDATES_EXTENTION_CACHE, $show_lines + $from + 1), $from);
		$lines = array_slice($lines_p, 0, $show_lines);
		if ($lines == FALSE) return '#recentupdates(): File can not open' . '<br />' . "\n";
		$next_line = array_slice($lines_p, $show_lines);
		if ($next_line == FALSE) $isMax = true;
		unset($lines_p);
		unset($next_line);
	}
	$page_count = 0;
	$fp = fopen(PLUGIN_RECENTUPDATES_CACHE, 'r');
	for($page_count = 0; fgets($fp); ++$page_count);
	fclose($fp);
	if ($isAll) $show_lines = $page_count;
	$num_operation = '';
	$move_n = round(PLUGIN_RECENTUPDATES_OPERATION_COUNT / 2);
	for ($c = 1;$c <= PLUGIN_RECENTUPDATES_OPERATION_COUNT; ++$c) {		
		$n = ($from + $show_lines) / $show_lines;
		$disp_num = $c;
		if ($n > $move_n) $disp_num = $c + $n - $move_n;
		$f = $disp_num * $show_lines - $show_lines + 1;
		if ($f > $page_count) break;
		$num_operation .= '<' . ($f == $from + 1 ? 'span' : 'a' ) . ' href="' . ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page'])) . (isset($vars['limit']) ? '&limit=' . $vars['limit'] : '') . '&from=' . ($f) . '"><strong>' . $disp_num . '</strong></' . ($f == $from + 1 ? 'span' : 'a' ) . '> | ';
	}
	$num_operation = substr($num_operation, 0, -3);
	if ($isAll) {
		$operation = '';
	} else {
		$operation = '
		<div style="text-align:center">
			' . ($from > 0 ? '<a href="' . ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page'])) . (isset($vars['limit']) ? '&limit=' . $vars['limit'] : '') . '&from=' . ($from + 1 - $show_lines) . '"><strong>' . $_recentupdates_messages['btn_prev'] . '</strong></a> | ' : '') . '
			' . $num_operation . '
			' . ($isMax != true ? ' | <a href="' . ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page'])) . (isset($vars['limit']) ? '&limit=' . $vars['limit'] : '') . '&from=' . ($from + 1 + $show_lines) . '"><strong>' . $_recentupdates_messages['btn_next'] . '</strong></a>' : '') . '
		</div>
		';
	}
	$option = '
	<div>
		' . $_recentupdates_messages['msg_display_number'] . ': [ <' . ($show_lines == 25 ? 'span' : 'a' ) . ' href="' . ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page'])) . '&limit=25' . (isset($vars['from']) ? '&from=' . $vars['from'] : '') . '">25</a> | 
		<' . ($show_lines == 50 ? 'span' : 'a' ) . ' href="' . ($isAction ? $script . '?plugin=recentupdates' : ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page']))) . '&limit=50' . (isset($vars['from']) ? '&from=' . $vars['from'] : '') . '">50</a> | 
		<' . ($show_lines == 100 ? 'span' : 'a' ) . ' href="' . ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page'])) . '&limit=100' . (isset($vars['from']) ? '&from=' . $vars['from'] : '') . '">100</a> | 
		<' . ($show_lines == 250 ? 'span' : 'a' ) . ' href="' . ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page'])) . '&limit=250' . (isset($vars['from']) ? '&from=' . $vars['from'] : '') . '">250</a> |
		<' . ($vars['limit'] == 0  && isset($vars['limit']) ? 'span' : 'a' ) . ' href="' . ($isAction ? $script . '?plugin=recentupdates' : get_page_uri($vars['page'])) . '&limit=0' . (isset($vars['from']) ? '&from=' . $vars['from'] : '') . '">' . $_recentupdates_messages['msg_all'] . '</a> ]
	</div>
	';
	$date = $items = '';
	foreach ($lines as $line) {
		list($time, $page, $diff_html, $author_tmp) = explode("\t", rtrim($line));
		$_date = get_date($date_format, $time) . ' (' . $weeklabels[get_date('w', $time)] . ') ';
		if ($date != $_date) {
			// End of the day
			if ($date != '') $items .= '</ul>' . "\n";

			// New day
			$date = $_date;
			$items .= '<strong>' . $date . '</strong>' . "\n" .
				'<ul class="recent_list">' . "\n";
		}
		$tag_plugin = "";
		if (exist_plugin("tag")) {
			$tagfile = CACHE_DIR . encode($page) . "_page.tag";
			if (file_exists($tagfile)) {
				$tags = implode(', ', array_map(
					function($str) {
						return '<a href="' . $script . '?cmd=taglist&tag=' . $str . '">' . htmlsc($str) . "</a>";
					}, 
				array_map("rtrim", file($tagfile))
				));
				$tag_plugin = ' (Tag: ' . $tags . ")";
				unset($tags);
			}
		}
		$author = "";
		if (PLUGIN_RECENTUPDATES_DISPLAY_AUTHOR) {
			$author = $author_tmp;
		}
		$s_page = htmlsc($page);
		$attrs = get_page_link_a_attrs($page);
		$items .= ' <li>
			' . get_date($time_format, $time) . ' - ' . '[ <a href="' . $script . '?cmd=diff&page=' . $s_page . '">' . $_LANG['skin']['diff'] . '</a>' . ' | <a href="' . $script . '?cmd=backup&page=' . $s_page . '">' . $_LANG['skin']['backup'] . '</a> ] ' . 
			'<a href="' . get_page_uri($page) . '" class="' .
			$attrs['class'] . '" data-mtime="' . $attrs['data_mtime'] .
			'">' . $s_page . '</a>' .
			' -- ' . 
			($author == "" ? "" : make_pagelink($author) . ' ') .
			$diff_html .
			$tag_plugin .
			'</li>' . "\n";
	}
	// End of the day
	if ($date != '') $items .= '</ul>' . "\n";

	return $operation . $items . $operation . $option;
}

function plugin_recentupdates_action()
{
	global $_recentupdates_messages;
	return array('msg' => $_recentupdates_messages['msg_title'], 'body' => plugin_recentupdates_convert());
}

function plugin_recentupdates_get_diff_str($page) {
	// 差分の文字数計算
	$diff_len = 0;
	$diff_len_str = '';
	$diff_file = DIFF_DIR . encode($page) . '.txt';
	if (file_exists($diff_file)) {
		foreach (file($diff_file) as $line) {
			$head = $line[0];
			if ($head == "+") {
				$diff_len += mb_strlen($line) - 1;
			}
			else if ($head == "-") {
				$diff_len -= mb_strlen($line) - 1;
			}
		}
	} else if (is_page($page)) {
		$diff_len = mb_strlen(join('', get_source($page)));
	} else {
		$diff_len = 0;
	}
	if ($diff_len == 0) $diff_len_str = '<span class="diff_unchanged">(0)</span>';
	if ($diff_len > 0) $diff_len_str = '<span class="diff_added">(+' . $diff_len . ')</span>';
	if ($diff_len < 0) $diff_len_str = '<span class="diff_removed">(' . $diff_len . ')</span>';
	return $diff_len_str;
}