<?php
require_once(__DIR__ . '/sonots.class.php');
require_once(__DIR__ . '/metapage.class.php');
//error_reporting(E_ALL);

/**
 * Table of Contents Class
 *
 * This class tries to gather all information at the constructor
 * including information which may not be used by you
 * because a cache file must contain all information for future. 
 * Therefore, there is no setter function unlike PluginSonotsPagelist(),
 * and process $headlines at your codes. 
 *
 * Example)
 * <code>
 *  $toc = new PluginSonotsToc($page, true); // use cache
 *  $headlines = $toc->get_headlines();
 *  // you may do something for $headlines here. 
 *  PluginSonotsToc::display_toc($headlines);
 * </code>
 *
 * PHP5 has static member variable supports, thus PHP5 allows
 * following smart design, but PHP4 does not
 * <code>
 *  PluginSonotsToc::syntax['headline'] = '/^(\*{1,5})/';
 *  $toc1 = new PluginSonotsToc($page1, true);
 *  $toc2 = new PluginSonotsToc($page2, true);
 *  // $cachefile = PluginSonotsToc::syntax['cachefile']($page1);
 * </code>
 * To use static member variable in PHP4, I used PHP4 static 
 * variable trick and use of this class became as followings:
 * <code>
 *  $toc = new PluginSonotsToc(); // this $toc is to set static var
 *  $toc->syntax['headline'] = '/^(\*{1,5})/';
 *  $toc1 = new PluginSonotsToc($page1, true);
 *  $toc2 = new PluginSonotsToc($page2, true);
 *  // $cachefile = $toc->syntax['cachefile']($page1);
 * </code>
 * Use this form in both PHP4 and PHP5. 
 *
 * @package    PluginSonots
 * @license    http://www.gnu.org/licenses/gpl.html GPL v2
 * @author     sonots <http://lsx.sourceforge.jp>
 * @version    $Id: toc.class.php,v 1.10 2008-07-16 07:23:17Z sonots $
 * @require    sonots     v 1.11
 * @require    metapage   v 1.10
 */
class PluginSonotsToc
{
	/**
	 * Pagename
	 *
	 * @var string
	 */
	var $page;
	/**
	 * Use cache or not
	 *
	 * @var boolean
	 */
	var $usecache = null;
	/**
	 * Title of this page (if available, or null)
	 *
	 * @var string
	 */
	var $title = null;
	/**
	 * Including pages
	 *
	 * keys are line numbers and values are names of
	 * including pages
	 *
	 * @var array
	 */
	var $includes = array();
	/**
	 * Headlines of this page
	 *
	 * keys are line numbers and values are objects of
	 * PluginSonotsHeadline
	 *
	 * @var array
	 */
	var $headlines = array();
	/**
	 * Extra information such as line number of #readmore
	 *
	 * @var array
	 */
	var $extra = array();
	/**
	 * Definition of PukiWiki Syntax to be used
	 *
	 * @static
	 * @var array
	 * - headline
	 * - include
	 * - title
	 * - contents
	 * - readmore
	 * - cachefile
	 */
	var $syntax; // static

	/**
	 * Constructor
	 *
	 * If a page is given, the true constructor self::init() is called.
	 * Otherwise, this only initializes static member variables. 
	 * PHP4 static member variable trick.
	 *
	 * @access public
	 * @param string $page
	 * @param boolean $usecache
	 * @uses init
	 */
	function __construct($page = '', $usecache = true)
	{
		static $syntax = array();
		if (empty($syntax)) {
			$syntax = array(
				 'headline'   => '/^(\*{1,3})/',
				 'include'	=> '/^#include.*\((.+)\)/',
				 'title'	  => '/^TITLE:(.+)/',
				 'contents'   => '/^#contents/',
				 'readmore'   => '/^#readmore/',
				 'cachefile'  => fn($x) => CACHE_DIR . encode($x) . ".toc",
			);
		}
		$this->syntax = &$syntax; // php4 static trick
		if ($page === '' && $usecache === false) return; 
		$this->init($page, $usecache);
	}

	/**
	 * True Constructor
	 *
	 * @access private
	 * @param string $page
	 * @param boolean $usecache
	 */
	function init($page, $usecache)
	{
		$toc = null;
		if (! $usecache) {
			$this->construct_toc($page);
			return;
		}
		// use cache
		$cachefile = $this->syntax['cachefile']($page);
		// check whether renewing is required or not
		// renew cache if one of including pages are newer than cache
		$renew = false; 
		if ((new sonots())->is_page_newer($page, $cachefile, true)) {
			$renew = true;
		} else { // check all including pages too
			// including pages are obtained from cache
			$toc = unserialize(file_get_contents($cachefile));
			if (!isset($toc->includes)) return;
			$pages = array_keys($toc->includes);
			for ($i = 1; $i < count($pages); ++$i) {// first is the current page, already done
				if ((new sonots())->is_page_newer($pages[$i], $cachefile)) {
					$renew = true; break;
				}
			}
		}
		if ($renew) { // recreate and write
			$this->construct_toc($page);
			$contents = serialize($this->all);
			file_put_contents($cachefile, $contents);
		} else { // read from cache
			// cache is already read
			(new PluginSonotsToc())->shallow_copy($toc, $this);
			$this->usecache = $usecache;
		}
	}

	/**
	 * Copy members of Toc objects
	 *
	 * @access public
	 * @static
	 * @param PluginSonotsToc $source
	 * @param PluginSonotsToc &$target
	 * @return void
	 */
	static function shallow_copy($source, &$target)
	{
		$vars = get_object_vars($source);
		unset($vars['usecache']);
		unset($vars['syntax']);
		foreach ($vars as $key => $val) {
			$target->$key = $val;
		}
	}

	/**
	 * Get TITLE of this page if available
	 *
	 * @access public
	 * @return string|null
	 */
	function get_title()
	{
		return $this->title;
	}

	/**
	 * Get Includes (Direct access is okay, though)
	 *
	 * @access public
	 * @return array array of PluginSonotsInclude(s)
	 * @see get_includepages
	 */
	function get_includes()
	{
		return $this->includes;
	}

	/**
	 * Get Headlines (Direct access is okay, though)
	 *
	 * @access public
	 * @return array array of PluginSonotsHeadline(s)
	 * @see get_firsthead
	 */
	function get_headlines()
	{
		return $this->headlines;
	}

	/**
	 * Get Extra (Direct access is okay, though)
	 *
	 * @access public
	 * @return array extra info
	 * @see get_readmore
	 * @see get_fromhere
	 */
	function get_extra()
	{
		return $this->extra;
	}

	/**
	 * Get including pages in this page. 
	 *
	 * @access public
	 * @return array
	 */
	function get_includepages()
	{
		return (new sonots())->get_members($this->includes, 'page');
	}

	/**
	 * Get First Headline String of this page if available
	 *
	 * @access public
	 * @return string|null
	 */
	function get_firsthead()
	{
		if (empty($this->headlines)) return null;
		$firsthead = reset($this->headlines);
		return $firsthead->string;
	}

	/**
	 * Get line number of #readmore line if available
	 *
	 * @access public
	 * @return int|null
	 */
	function get_readmore()
	{
		return $this->extra['readmore'] ?? null;
	}

	/**
	 * Get line number of #contents line if available
	 *
	 * @access public
	 * @return int|null
	 */
	function get_fromhere()
	{
		return $this->extra['fromhere'] ?? null;
	}

	/**
	 * Display Table of Contents
	 *
	 * @access public
	 * @static
	 * @param array &$headlines
	 * @param string $cssclass css class
	 * @param string $optlink link option
	 * @param boolean $optinclude show toc of including pages too or not
	 * @return string list html
	 */
	static function display_toc(&$headlines, $cssclass = '', $optlink = 'anchor')
	{
		$links = $levels = array();
		foreach ($headlines as $i => $headline) {
			$linkstr = strip_htmltag(make_link($headline->string));
			$link = match ($optlink) {
				'page' => (new sonots())->make_pagelink_nopg($headline->page, $linkstr, '#' . $headline->anchor),
				'off' => $linkstr,
				default => (new sonots())->make_pagelink_nopg('', $linkstr, '#' . $headline->anchor),
			};
			$links[$i] = $link;
			$levels[$i] = $headline->depth;
		}
		return (new sonots())->display_list($links, $levels, $cssclass);
	}

	/**
	 * Compact depth of headlines
	 *
	 * @access public
	 * @access static
	 * @param array &$headlines
	 */
	static function compact_depth(&$headlines)
	{
		$pages = (new sonots())->get_members($headlines, 'page');
		// perform compact separately for each page
		foreach (array_unique($pages) as $page) {
			$keys = (new sonots())->grep_array($page, $pages, 'eq');
			$page_headlines = array_intersect_key($headlines, $keys);
			$depths = (new sonots())->get_members($page_headlines, 'depth');
			$depths = (new sonots())->compact_list($depths);
			foreach ($depths as $key => $depth) {
				$headlines[$key]->depth = $depth;
			}
		}
	}

	/**
	 * Expand headlines of included pages
	 *
	 * $visited is used only inside for recursive call, you do not need to use it.
	 *
	 * @access public
	 * @param array $visited a flag to check whether the page is processed already. 
	 * @return array $visited
	 * @see shrink_includes
	 */
	function expand_includes($visited = array())
	{
		if (! in_array($this->page, $visited)) $visited[] = $this->page;
		// combine headlines and includes lines
		$hlines = array_map(fn() => "h", $this->headlines);
		$ilines = array_map(fn() => "i", $this->includes);
		$lines = $hlines + $ilines;
		ksort($lines);

		$headlines = array();
		foreach ($lines as $linenum => $flag) {
			if ($flag === 'h') {
				$headline = $this->headlines[$linenum];
				$headline->page = $this->page;
				$headlines[] = $headline;
			} else { // expand included page
				$include = $this->includes[$linenum];
				if (in_array($include->page, $visited)) continue;
				$headlines[] = $include->headline($this->usecache);
				$toc = new PluginSonotsToc($include->page, $this->usecache);
				$visited = $toc->expand_includes($visited);
				$headlines = array_merge($headlines, $toc->headlines);
			}
		}
		$this->headlines = $headlines;
		return $visited;
	}

	/**
	 * Reverse expand_includes
	 *
	 * @access public
	 * @return void
	 * @see expand_includes
	 */
	function shrink_includes()
	{
		$pages = (new sonots())->get_members($this->headlines, 'page');
		$keys = (new sonots())->grep_array($this->page, $pages, 'eq');
		$this->headlines = array_intersect_key($this->headlines, $keys);
	}

	/**
	 * Read a page and construct Toc (This is THE constructor)
	 *
	 * @access public
	 * @todo should be able to separate functionalities more
	 */
	function construct_toc($page)
	{
		$lines = get_source($page);
		$title = null;
		$headlines = $includes = $extra = array();
		(new sonots())->remove_multilineplugin_lines($lines);
		foreach ($lines as $linenum => $line) {
			if (! isset($extra['fromhere'])) {
				if (preg_match($this->syntax['contents'], (string) $line, $matches)) {
					$extra['fromhere'] = $linenum;
					continue;
				}
			}
			if (! isset($extra['readmore'])) {
				if (preg_match($this->syntax['readmore'], (string) $line, $matches)) {
					$extra['readmore'] = $linenum;
					continue;
				}
			}
			
			if (preg_match($this->syntax['headline'], (string) $line, $matches)) {
				$depth	= strlen((string) $matches[1]);
				[$string, $anchor] = (new sonots())->make_heading($line);
				$headlines[$linenum] 
					= new PluginSonotsHeadline($page, $linenum, $depth, $string, $anchor);
				continue;
			}

			if (preg_match($this->syntax['include'], (string) $line, $matches)) {
				$inclargs = csv_explode(',', $matches[1]);
				$inclpage = array_shift($inclargs);
				$inclpage = get_fullname($inclpage, $page);
				if (! is_page($inclpage)) continue;
				$includes[$linenum] 
					= new PluginSonotsIncludeline($linenum, $inclpage, $inclargs);
				continue;
			}
			
			if (preg_match($this->syntax['title'], (string) $line, $matches)) {
				$title = $matches[1];
				continue;
			}
		}
		$this->page  = $page;
		$this->title = $title;
		$this->headlines = $headlines;
		$this->includes = $includes;
		$this->extra = $extra;
	}
}

/**
 * #include line structure
 *
 * $linenum: #include($page[,$arg,...])
 *
 * @package    PluginSonots
 * @license    http://www.gnu.org/licenses/gpl.html GPL v2
 * @author     sonots <http://lsx.sourceforge.jp/>
 * @version    $Id: v 1.0 2008-06-05 11:14:46 sonots $
 */
class PluginSonotsIncludeline
{
	function __construct($linenum, $page, $args)
	{
	}

	/**
	 * Convert Includeline to Headline
	 *
	 * @return PluginSonotsHeadline
	 */
	function headline($usecache = true)
	{
		$linenum = $this->linenum;
		$depth   = 0;
		$options = (new sonots())->parse_options($this->args, array('titlestr'=>'title'));
		$string  = PluginSonotsMetapage::linkstr($this->page, $options['titlestr'], $usecache);
		$anchor  = (new sonots())->make_pageanchor($this->page);
		return new PluginSonotsHeadline($this->page, $linenum, $depth, $string, $anchor);
	}
}

/**
 * Headline Structure
 *
 * @package    PluginSonots
 * @license    http://www.gnu.org/licenses/gpl.html GPL v2
 * @author     sonots <http://lsx.sourceforge.jp/>
 * @version    $Id: v 1.0 2008-06-05 11:14:46 sonots $
 */
class PluginSonotsHeadline
{
	function __construct($page, $linenum, $depth, $string, $anchor = '')
	{
	}
}

?>
