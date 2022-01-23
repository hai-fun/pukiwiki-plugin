<?php
/**
 *  TagCloud Plugin
 *
 *  @author     sonots
 *  @license    http://www.gnu.org/licenses/gpl.html    GPL
 *  @link       http://lsx.sourceforge.jp/?Plugin%2Ftag.inc.php
 *  @version    $Id: tagcloud.inc.php,v 1.1 2008-03-19 07:23:17Z sonots $
 *  @uses       tag.inc.php
 *  @package    plugin
 */
 // v1.11 PHP8.0対応 2021-12-15 byはいふん

exist_plugin('tag') or die_message('tag.inc.php does not exist.');
class PluginTagcloud
{
	var $plugin_tag;

	function __construct()
	{
		static $default_options = array();
		if (empty($default_options)) {
			$default_options['limit']   = NULL;
			$default_options['related'] = NULL;
			$default_options['cloud']   = TRUE;
		}
		// static
		$this->default_options = & $default_options;
		// init
		$this->options = $default_options;
		global $plugin_tag_name;
		$this->plugin_tag = new $plugin_tag_name();
	}
		
	function PluginTagcloud() {
		$this->__construct();
	}

	function convert() // tagcloud
	{
		$args  = func_get_args();
		parse_options($args, $this->options);
		if ($this->options['limit'] === "0") {
			$this->options['limit'] = NULL;
		}
		if ($this->options['cloud'] === 'off' ||
			$this->options['cloud'] === 'false' ) {
			$this->options['cloud'] = FALSE;
		}
		//print_r($this->options);
		if ($this->options['cloud']) {
			$html = $this->plugin_tag->display_tagcloud($this->options['limit'], $this->options['related']);
		} else {
			$html = $this->plugin_tag->display_taglist($this->options['limit'], $this->options['related']);
		}
		return $html;
	}
}

define('PLUGIN_TAGCLOUD_CSS', <<<EOD
#body .htmltagcloud{
  font-size: 12px;
  line-height:340%;
}
.menubar .htmltagcloud{ 
  font-size: 6px;
  line-height:340%;
}

.menubar .htmltagcloud span{
  display: block;
}

.tagcloud0  { font-size: 100%;} 
.tagcloud1  { font-size: 110%;} 
.tagcloud2  { font-size: 120%;} 
.tagcloud3  { font-size: 130%;} 
.tagcloud4  { font-size: 140%;} 
.tagcloud5  { font-size: 150%;} 
.tagcloud6  { font-size: 160%;} 
.tagcloud7  { font-size: 170%;} 
.tagcloud8  { font-size: 180%;} 
.tagcloud9  { font-size: 190%;} 
.tagcloud10 { font-size: 200%;} 
.tagcloud11 { font-size: 210%;} 
.tagcloud12 { font-size: 220%;} 
.tagcloud13 { font-size: 230%;} 
.tagcloud14 { font-size: 240%;} 
.tagcloud15 { font-size: 250%;} 
.tagcloud16 { font-size: 260%;} 
.tagcloud17 { font-size: 270%;} 
.tagcloud18 { font-size: 280%;} 
.tagcloud19 { font-size: 290%;} 
.tagcloud20 { font-size: 300%;} 
.tagcloud21 { font-size: 310%;} 
.tagcloud22 { font-size: 320%;} 
.tagcloud23 { font-size: 330%;} 
.tagcloud24 { font-size: 340%;} 
EOD
);

function plugin_tagcloud_init()
{
	global $plugin_tagcloud_name, $head_tags;
	if (class_exists('PluginTagcloudUnitTest')) {
		$plugin_tagcloud_name = 'PluginTagcloudUnitTest';
	} elseif (class_exists('PluginTagcloudUser')) {
		$plugin_tagcloud_name = 'PluginTagcloudUser';
	} else {
		$plugin_tagcloud_name = 'PluginTagcloud';
	}
	$head_tags[] = "<style>" . PLUGIN_TAGCLOUD_CSS . "</style>";
	plugin_tag_init();
}

function plugin_tagcloud_convert()
{
	global $plugin_tagcloud, $plugin_tagcloud_name;
	$plugin_tagcloud = new $plugin_tagcloud_name();
	$args = func_get_args();
	return call_user_func_array(array(&$plugin_tagcloud, 'convert'), $args);
}
?>
