<?php
// $Id: gcharts.inc.php,v1.0 2022-02-26 00:00:00 haifun $

define("PLUGIN_GCHARTS_LIB_URL", "https://www.gstatic.com/charts/loader.js");
define("PLUGIN_GCHARTS_USAGE", "Usage: #gcharts([title],[height],[str_name],[num_name],[type],[(string)->(int)]...)");

function plugin_gcharts_init()
{
	global $head_tags;
	$head_tags[] = "<script src=\"" . PLUGIN_GCHARTS_LIB_URL . "\"></script>";
}

function plugin_gcharts_convert()
{
	static $time = 0;
	++$time;
	$num = func_num_args();
	if ($num < 6)
		return PLUGIN_GCHARTS_USAGE;
	$args = func_get_args();

	$rows = $args;
	array_shift($rows); // delete title
	array_shift($rows); // delete height
	array_shift($rows); // delete string
	array_shift($rows); // delete number
	array_shift($rows); // delete type
	$rows = array_map(function ($value) {
		$array = explode('->', $value);
		$array[0] = "'" . $array[0] . "'";
		return '[' . implode(', ', $array) . "],";
	}, $rows);
	$row = substr(implode($rows), 0, -1);
	return <<<EOD
<div id="gcharts_{$time}" style="width:100%"></div>
<script type="text/javascript">
	google.charts.load('current', {'packages':['corechart']});
	google.charts.setOnLoadCallback(draw_gcharts_{$time});
	function draw_gcharts_{$time}() {
		var data = new google.visualization.DataTable();
		data.addColumn('string', '{$args[2]}');
		data.addColumn('number', '{$args[3]}');
		data.addRows([
			{$row}
		]);
		var options = { 'title':'{$args[0]}', 'height':{$args[1]} };
		var chart = new google.visualization.{$args[4]}Chart(document.getElementById('gcharts_{$time}'));
		chart.draw(data, options);
	}
	window.onresize = function(){
		draw_gcharts_{$time}();
	}
</script>
EOD;
}
