<?php
/**
 * @author     lunt
 * @license    http://www.gnu.org/licenses/gpl.html GPL 2 or later
 * @version    $Id: secedit.inc.php 422 2008-11-19 10:33:42Z lunt $
 */

/**
 * Define heading style
 *
 * $1 = heading open tag <hx id="content_1_x">
 * $2 = heading string including anchor
 * $3 = link to secedit plugin
 * $4 = heading close tag </hx>
 */

// paraedit patch style
define('PLUGIN_SECEDIT_LINK_STYLE', '$1$2<a class="anchor_super" href="$3" title="Edit">' .
	' <img src="image/paraedit.png" width="9" height="9" alt="Edit" title="Edit" /></a>$4');

// Monobook style
// define('PLUGIN_SECEDIT_LINK_STYLE', '$1<span class="editsection">[<a href="$3">edit</a>]</span><span>$2</span>$4');

define('PLUGIN_SECEDIT_LEVEL', false);

define('PLUGIN_SECEDIT_ENABLE_ON_KEITAI_PROFILE', false);

// Remove #freeze written by hand
define('PLUGIN_SECEDIT_FREEZE_REGEX', '/^(?:#freeze(?!\w)\s*)+/im');
define('PLUGIN_SECEDIT_PAGE', $vars['page']);

function plugin_secedit_action()
{
	global $post;

	switch (true) {
	case isset($post['cancel']):
		$action = 'Cancel'; break;
	case isset($post['preview']):
		$action = 'Preview'; break;
	case isset($post['write']):
		$action = 'Write'; break;
	default:
		$action = 'Edit';
	}

	$action = 'Plugin_Secedit_' . $action;
	$obj    = &new $action();

	return $obj->process();
}

class Plugin_Secedit
{
	var $page;
	var $id;
	var $anchor;
	var $level;
	var $postdata;
	var $original;
	var $digest;
	var $notimestamp;
	var $pass;
	var $help;

	var $s_page;
	var $s_postdata;
	var $s_original;
	var $s_digest;

	var $sections;

	function init()
	{
		global $vars, $post;

		$this->page        = isset($vars['page']) ? $vars['page'] : '';
		$this->s_page      = htmlspecialchars($this->page);
		$this->id          = isset($vars['id']) ? $vars['id'] : 0;
		$this->anchor      = isset($vars['anchor']) ? $vars['anchor'] : '';
		$this->level       = isset($vars['level']) ? true : false;
		$this->postdata    = isset($post['msg']) ?
			preg_replace(PLUGIN_SECEDIT_FREEZE_REGEX, '', $post['msg']) : '';
		$this->original    = isset($post['original']) ?
			str_replace("\r", '', $post['original']) : '';
		$this->digest      = isset($post['digest']) ? $post['digest'] : '';
		$this->notimestamp = isset($post['notimestamp']) ? true : false;
		$this->pass        = isset($post['pass']) ? $post['pass'] : '';
		$this->help        = isset($vars['help']) ? true : false;
	}

	function check()
	{
		if (PKWK_READONLY) die_message('PKWK_READONLY prohibits editing');

		check_editable($this->page, true, true);

		if (! is_page($this->page)) die_message('No such page');
		if (! $this->sections->is_valid_id($this->id)) die_message('Invalid id');
	}

	function process()
	{
	}

	function redirect($page)
	{
		pkwk_headers_sent();
		header('Location: ' . get_script_uri() . '?' . rawurlencode($page));
		exit;
	}

	function form()
	{
		global $rows, $cols, $notimeupdate, $hr, $_msg_help;
		global $_btn_preview, $_btn_repreview, $_btn_update, $_btn_cancel, $_btn_notchangetimestamp;

		$script      = get_script_uri();
		$r_page      = rawurlencode($this->page);
		$btn_preview = strpos(get_class($this), 'Preview') ? $_btn_repreview : $_btn_preview;

		$level = $this->level ? '<input type="hidden" name="level"  value="true" />' : '';

		$add_notimestamp = '';
		if ($notimeupdate) {
			$checked   = $this->notimestamp ? ' checked="checked"' : '';
			$pass_form = ($notimeupdate == 2) ? '   <input type="password" name="pass" size="12" />' : '';
			$add_notimestamp = <<<EOD
   <input type="checkbox" name="notimestamp" id="_edit_form_notimestamp" value="true"$checked />
   <label for="_edit_form_notimestamp"><span class="small">$_btn_notchangetimestamp</span></label>
$pass_form
EOD;
		}

		$help  = $script . '?cmd=secedit&amp;help=true&amp;page=' . $r_page . '&amp;id=' . $this->id;
		$help .= $this->level ? '&amp;level=true' : '';
		$help  = $this->help ? $hr . catrule() :
			'<ul><li><a href="' . $help . '">' . $_msg_help . '</a></li></ul>';

		return <<<EOD
<div class="edit_form">
 <form action="$script" method="post" style="margin-bottom:0px;">
  <div>
   <input type="hidden" name="cmd"    value="secedit" />
   <input type="hidden" name="page"   value="$this->s_page" />
   <input type="hidden" name="id"     value="$this->id" />
   $level
   <input type="hidden" name="digest" value="$this->s_digest" />
   <textarea name="msg" rows="$rows" cols="$cols">$this->s_postdata</textarea>
   <br />
   <input type="submit" name="preview" value="$btn_preview"  accesskey="p" />
   <input type="submit" name="write"   value="$_btn_update"  accesskey="s" />
$add_notimestamp
   <input type="submit" name="cancel"  value="$_btn_cancel"  accesskey="c" />
   <textarea name="original" rows="1" cols="1" style="display:none">$this->s_original</textarea>
  </div>
 </form>
</div>
$help
EOD;
	}
}

class Plugin_Secedit_Edit extends Plugin_Secedit
{
	function init()
	{
		parent::init();

		$source = get_source($this->page, true, true);

		$this->sections = &new Plugin_Secedit_Sections($source);

		if ($this->anchor) {
			$id = $this->sections->anchor2id($this->anchor);
			$this->id = $id ? $id : $this->id;
		}

		$this->s_postdata = htmlspecialchars($this->sections->get_section($this->id, $this->level));
		$this->s_original = htmlspecialchars($source);
		$this->s_digest   = htmlspecialchars(md5($source));
	}

	function check()
	{
		parent::check();

		if ($this->anchor && $this->id && ! $this->sections->is_unique_anchor($this->anchor)) {
			die_message('The anchor ' . htmlspecialchars($this->anchor) . ' is nonunique.');
		}
	}

	function process()
	{
		global $_title_edit;

		$this->init();
		$this->check();

		return array('msg' => $_title_edit, 'body' => $this->form());
	}
}

class Plugin_Secedit_Preview extends Plugin_Secedit
{
	function init()
	{
		parent::init();

		$this->sections   = &new Plugin_Secedit_Sections($this->original);
		$this->s_postdata = htmlspecialchars($this->postdata);
		$this->s_original = htmlspecialchars($this->original);
		$this->s_digest   = htmlspecialchars($this->digest);
	}

	function check()
	{
		parent::check();

		if ($this->original === '') die_message('No original');
		if ($this->digest === '') die_message('No digest');
	}

	function process()
	{
		global $_title_preview, $_msg_preview, $_msg_preview_delete;

		$this->init();
		$this->check();

		$this->sections->set_section($this->id, $this->postdata, $this->level);

		$msg  = $_msg_preview . "<br />\n";
		$msg .= ($this->sections->get_source() === '') ? "<strong>$_msg_preview_delete</strong>" : '';
		$msg .= "<br />\n";

		$preview = '';
		if ($this->postdata !== '') {
			$src     = preg_replace(PLUGIN_SECEDIT_FREEZE_REGEX, '', $this->postdata);
			$src     = make_str_rules($src);
			$preview = '<div id="preview">' . drop_submit(convert_html($src)) . "</div>\n";
		}

		return array('msg' => $_title_preview, 'body' => $msg . $preview . $this->form());
	}
}

class Plugin_Secedit_Write extends Plugin_Secedit_Preview
{
	function process()
	{
		global $_title_collided, $_msg_collided, $_msg_collided_auto, $do_update_diff_table;
		global $_title_edit, $_title_deleted, $notimeupdate, $_msg_invalidpass;

		$this->init();
		$this->check();

		$this->sections->set_section($this->id, $this->postdata, $this->level);
		$postdata = $this->sections->get_source();

		$current_src = get_source($this->page, true, true);
		$current_md5 = md5($current_src);

		if ($this->digest !== $current_md5) {
			list($postdata, $auto) = do_update_diff($current_src, $postdata, $this->original);
			$this->s_postdata = htmlspecialchars($postdata);
			$this->s_digest   = htmlspecialchars($current_md5);
			$body  = ($auto ? $_msg_collided_auto : $_msg_collided) . "\n";
			$body .= $do_update_diff_table . edit_form($this->page, $postdata, $current_md5, false);
			return array(
				'msg'  => $_title_collided,
				'body' => $body,
			);
		}

		if ($postdata === '') {
			page_write($this->page, $postdata);
			return array(
				'msg'  => $_title_deleted,
				'body' => str_replace('$1', $this->s_page, $_title_deleted),
			);
		}

		if ($notimeupdate > 1 && $this->notimestamp && ! pkwk_login($this->pass)) {
			return array(
				'msg'  => $_title_edit,
				'body' => "<p><strong>$_msg_invalidpass</strong></p>\n" . $this->form()
			);
		}

		if (md5($postdata) === $current_md5) {
			$this->redirect($this->page);
		}

		page_write($this->page, $postdata, $notimeupdate != 0 && $this->notimestamp);
		$this->redirect($this->page);
	}
}

class Plugin_Secedit_Cancel extends Plugin_Secedit
{
	function process()
	{
		$this->init();

		if (is_page($this->page)) {
			$this->redirect($this->page);
		}

		return;
	}
}

class Plugin_Secedit_Sections
{
	var $sections;

	function Plugin_Secedit_Sections($text)
	{
		$this->sections = $this->_parse($text);
	}

	function get_source()
	{
		return implode('', $this->sections);
	}

	function &get_section($id, $with_subsection = false)
	{
		if (! $this->is_valid_id($id)) {
			return false;
		}

		if ($with_subsection) {
			return $this->get_section_with_subsection($id);
		} else {
			return $this->sections[$id];
		}
	}

	function &get_section_with_subsection($id)
	{
		$source = '';
		$count  = $id + $this->_count_subsection($id) + 1;

		for ($i = $id; $i < $count; $i++) {
			$source .= $this->sections[$i];
		}
		return $source;
	}

	function set_section($id, $text, $with_subsection = false)
	{
		if (! $this->is_valid_id($id)) {
			return false;
		}

		if (substr($text, -1) !== "\n") {
			$text .= "\n";
		}

		if ($with_subsection) {
			$this->set_section_with_subsection($id, $text);
		} else {
			$this->sections[$id] = $text;
		}
	}

	function set_section_with_subsection($id, $text)
	{
		array_splice($this->sections, $id, $this->_count_subsection($id) + 1, array($text));
		$this->sections = $this->_parse($this->get_source());
	}

	function is_valid_id($id)
	{
		if (is_string($id) && ($id === '' || ! ctype_digit($id))) {
			return false;
		}
		return isset($this->sections[$id]) && $id > 0;
	}

	function anchor2id($anchor)
	{
		foreach ($this->sections as $id => $section) {
			if (preg_match('/^\*{1,3}.*?(?:\[#([A-Za-z][\w-]*)\]\s*)\n/', $section, $matches) &&
				$anchor === $matches[1])
			{
				return $id;
			}
		}
		return false;
	}

	function is_unique_anchor($anchor)
	{
		foreach ($this->sections as $section) {
			if (preg_match('/^\*{1,3}.*?(?:\[#([A-Za-z][\w-]*)\]\s*)\n/', $section, $matches)) {
				$anchors[$matches[1]]++;
			}
			if (isset($anchors[$anchor]) && $anchors[$anchor] > 1) {
				return false;
			}
		}
		return true;
	}

	function _parse($text)
	{
		$id           = 0;
		$sections[0]  = '';
		$in_multiline = false;

		foreach (explode("\n", $text) as $line) {
			if (! PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK && ! $in_multiline &&
				preg_match('/^#[^{]+(\{{2,})\s*$/', $line, $matches))
			{
				$in_multiline    = true;
				$close_multiline = str_repeat('}', strlen($matches[1]));
			} elseif ($in_multiline && $line === $close_multiline) {
				$in_multiline = false;
			}
			if (! $in_multiline && strpos($line, '*') === 0) {
				$sections[++$id] = '';
			}
			$sections[$id] .= $line . "\n";
		}
		$sections[count($sections)-1] = substr($sections[count($sections)-1], 0, -1);

		return $sections;
	}

	function _count_subsection($id)
	{
		$count = 0;
		$level = $this->_level($id);

		for ($i = $id + 1; $i < count($this->sections); $i++) {
			if ($this->_level($i) <= $level) {
				break;
			}
			$count++;
		}
		return $count;
	}

	function _level($id)
	{
		return min(3, strspn($this->sections[$id], '*'));
	}
}

function plugin_secedit_wrap(&$string, &$tag, &$param, &$id)
{
	global $vars;

	$page = isset($vars['page']) ? $vars['page'] : '';
	list($dummy, $callcount, $secid) = explode('_', $id);

	if (! plugin_secedit_should_display_editlink($page, (int)$callcount)) {
		return false;
	}

	$secid = '&amp;id=' . strval($secid + 1);
	if ($callcount > 1 && preg_match('/<a[^>]+id="([A-Za-z][\w-]*)"/', $string, $matches)) {
		$secid = '&amp;anchor=' . $matches[1];
	} elseif ($callcount > 1) {
		return false;
	}

	$open  = '<' . $tag . $param . '>';
	$close = '</' . $tag . '>';
	$link  = get_script_uri() . '?cmd=secedit&amp;page=' . rawurlencode($page) . $secid;
	$link .= PLUGIN_SECEDIT_LEVEL ? '&amp;level=true' : '';

	return str_replace(
		array('$1', '$2', '$3', '$4'),
		array($open, $string, $link, $close),
		PLUGIN_SECEDIT_LINK_STYLE);
}

function plugin_secedit_should_display_editlink($page, $callcount)
{
	global $vars, $retvars;
	static $is_editable;

	if (PKWK_READONLY) {
		return false;
	}

	if (! PLUGIN_SECEDIT_ENABLE_ON_KEITAI_PROFILE && UA_PROFILE === 'keitai') {
		return false;
	}

	if (! (isset($vars['cmd']) && $vars['cmd'] === 'read' && ! $retvars['body'])) {
		return false;
	}

	if (! isset($is_editable[$page])) {
		$is_editable[$page] = check_editable($page, false, false);
	}
	if (! $is_editable[$page]) {
		return false;
	}

	if ($callcount === 1 || PLUGIN_SECEDIT_PAGE !== $page) {
		return true;
	}

	return false;
}
?>
