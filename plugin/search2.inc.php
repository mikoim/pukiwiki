<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// search2.inc.php
// Copyright 2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Search2 plugin - Show detail result using JavaScript

define('PLUGIN_SEARCH2_MAX_LENGTH', 80);
define('PLUGIN_SEARCH2_MAX_BASE',   16); // #search(1,2,3,...,15,16)

// Show a search box on a page
function plugin_search2_convert()
{
	$args = func_get_args();
	return plugin_search_search_form('', '', $args);
}

function plugin_search2_action()
{
	global $vars, $_title_search, $_title_result;

	$action = isset($vars['action']) ? $vars['action'] : '';
	$base = isset($vars['base']) ? $vars['base'] : '';
	$bases = array();
	if ($base !== '') {
		$bases[] = $base;
	}
	if ($action === '') {
		$q = isset($vars['q']) ? $vars['q'] : '';
		if ($q === '') {
			return array('msg' => $_title_search,
				'body' => plugin_search2_search_form($q, '', $bases));
		} else {
			$msg  = str_replace('$1', htmlsc($q), $_title_result);
			return array('msg' => $msg,
					'body' => plugin_search2_search_form($q, '', $bases));
		}
	} else if ($action === 'query') {
		$text = isset($vars['q']) ? $vars['q'] : '';
		plugin_search2_do_search($text, $base);
		exit;
	}
}

function plugin_search2_do_search($query_text, $base)
{
	global $whatsnew, $non_list, $search_non_list;
	global $_msg_andresult, $_msg_orresult, $_msg_notfoundresult;
	global $search_auth;

	$type = 'AND';
	$word = $query_text;
	$retval = array();

	$b_type_and = ($type == 'AND'); // AND:TRUE OR:FALSE
	$keys = get_search_words(preg_split('/\s+/', $word, -1, PREG_SPLIT_NO_EMPTY));
	foreach ($keys as $key=>$value)
		$keys[$key] = '/' . $value . '/S';

	$pages = get_existpages();

	// Avoid
	if ($base != '') {
		$pages = preg_grep('/^' . preg_quote($base, '/') . '/S', $pages);
	}
	if (! $search_non_list) {
		$pages = array_diff($pages, preg_grep('/' . $non_list . '/S', $pages));
	}
	natsort($pages);
	$pages = array_flip($pages);
	unset($pages[$whatsnew]);
	$page_names = array_keys($pages);

	$found_pages = array();
	foreach ($page_names as $page) {
		$b_match = FALSE;
		$pagename_only = false;
		if (! is_page_readable($page)) {
			if ($search_auth) {
				// $search_auth - 1: User can know page names that contain search text if the page is readable
				continue;
			}
			// $search_auth - 0: All users can know page names that conntain search text
			$pagename_only = true;
		}
		// Search for page name and contents
		$raw_lines = get_source($page, TRUE, FALSE);
		$lines = remove_author_lines($raw_lines);
		$body = join('', $raw_lines);
		$target = $page . '\n' . join('', $lines);
		foreach ($keys as $key) {
			$b_match = preg_match($key, $target);
			if ($b_type_and xor $b_match) break; // OR
		}
		if ($b_match) {
			// Found!
			if ($pagename_only) {
				// The user cannot read this page body
				$found_pages[] = array('name' => (string)$page,
					'url' => get_page_uri($page), 'body' => '',
					'pagename_only' => 1);
			} else {
				$found_pages[] = array('name' => (string)$page,
				'url' => get_page_uri($page), 'body' => (string)$body);
			}
			continue;
		}
	}
	$s_word = htmlsc($word);
	if (empty($found_pages)) {
		$message = str_replace('$1', $s_word, $_msg_notfoundresult);
		$result_obj = array('message' => $message, 'results' => array());
		print(json_encode($result_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		return;
	}
	$message = str_replace('$1', $s_word, str_replace('$2', count($found_pages),
		str_replace('$3', count($page_names), $b_type_and ? $_msg_andresult : $_msg_orresult)));

	$result_obj = array('message' => $message, 'results' => $found_pages);
	print(json_encode($result_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function plugin_search2_search_form($s_word = '', $type = '', $bases = array())
{
	global $_btn_search;
	global $_search_pages, $_search_all;
	global $_msg_andresult, $_msg_orresult, $_msg_notfoundresult;
	global $_search_detail, $_search_searching;

	$script = get_base_uri();
	$h_search_text = htmlsc($s_word);

	$base_option = '';
	if (!empty($bases)) {
		$base_msg = '';
		$_num = 0;
		$check = ' checked="checked"';
		foreach($bases as $base) {
			++$_num;
			if (PLUGIN_SEARCH_MAX_BASE < $_num) break;
			$label_id = '_p_search_base_id_' . $_num;
			$s_base   = htmlsc($base);
			$base_str = '<strong>' . $s_base . '</strong>';
			$base_label = str_replace('$1', $base_str, $_search_pages);
			$base_msg  .=<<<EOD
 <div>
  <input type="radio" name="base" id="$label_id" value="$s_base" $check />
  <label for="$label_id">$base_label</label>
 </div>
EOD;
			$check = '';
		}
		$base_msg .=<<<EOD
  <input type="radio" name="base" id="_p_search_base_id_all" value="" />
  <label for="_p_search_base_id_all">$_search_all</label>
EOD;
		$base_option = '<div class="small">' . $base_msg . '</div>';
	}
	$result_page_panel =<<<EOD
<div id="_plugin_search2_search_status"></div>
<div id="_plugin_search2_message"></div>
<input type="checkbox" id="_plugin_search2_detail" checked><label for="_plugin_search2_detail">$_search_detail</label>
<input type="hidden" id="_plugin_search2_msg_searching" value="$_search_searching">
<input type="hidden" id="_plugin_search2_msg_result_notfound" value="$_search2_result_notfound">
<input type="hidden" id="_plugin_search2_msg_result_found" value="$_search2_result_found">
EOD;
	if ($h_search_text == '') {
		$result_page_panel = '';
	}

	return <<<EOD
<form action="$script" method="GET">
 <div>
  <input type="hidden" name="cmd" value="search2">
  <input type="search"  name="q" id="_plugin_search2_searchtext" value="$h_search_text" size="30">
  <input type="submit" value="$_btn_search">
 </div>
$base_option
</form>
$result_page_panel
<ul id="result-list">
</ul>
EOD;
}
