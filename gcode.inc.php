<?php
// $Id: gcode.inc.php,v1.0 2021-12-25 00:00:00 haifun $

// 行番号を有効にする
define("PLUGIN_GCODE_LINENUMS", true);

// 行番号をすべての行に付ける, falseの際は5行ずつとなる
define("PLUGIN_GCODE_ALL_LINENUMS", true);

// Google Code Prettifyのスキン (https://rawgit.com/google/code-prettify/master/styles/index.html)
define("PLUGIN_GCODE_SKIN", "sunburst");



define("PLUGIN_GCODE_CODE_PRETTIFY_URL", "https://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js");
define("PLUGIN_GCODE_USAGE", "Usage: #gcode([(Lang)]){{<br />[source code]<br />}}");
define("PLUGIN_GCODE_STYLE", "pre.prettyprint { padding-top: 2em; padding-bottom: 2em; }"); // 左寄せ等
define("PLUGIN_GCODE_ALL_LN_STYLE", "pre.prettyprint ol.linenums > li {list-style-type: decimal; }"); // すべての行に番号をつける

function plugin_gcode_init() {
	global $head_tags;
	$head_tags[] = "<script src=\"" . PLUGIN_GCODE_CODE_PRETTIFY_URL . "?skin=" . PLUGIN_GCODE_SKIN . "\"></script>";
	$head_tags[] = "<style>" . PLUGIN_GCODE_STYLE . "</style>";
	if (PLUGIN_GCODE_ALL_LINENUMS) $head_tags[] = "<style>" . PLUGIN_GCODE_ALL_LN_STYLE . "</style>";
}

function plugin_gcode_convert() {
	$num = func_num_args();
	if ($num < 1)
		return PLUGIN_GCODE_USAGE;
	$args = func_get_args();
	$code = '';
	$lang = '';
	$classAttr = 'prettyprint';
	if ($num > 1) {
		$lang = htmlsc(strtolower($args[0]));
		$code = htmlsc($args[$num - 1]);
	} else {
		$code = htmlsc($args[0]);
	}
	$classAttr .= ' lang-' . $lang;
	if (PLUGIN_GCODE_LINENUMS == true)
		$classAttr .= ' linenums';
	
	return "<pre class=\"" . $classAttr . "\">" . $code . "</pre>";
}