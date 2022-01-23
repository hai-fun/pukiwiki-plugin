<?php
define('_PARAEDIT_VERSION', 1.0);

/*

* パラグラフ指向化プラグイン - paraedit 1.0

PukiWikiでパラグラフ単位の編集をできるようにするプラグインです。

***********************************************************
 taru : paraedit0.7a 変更点について

 1.     PHP5.2.0より正規表現関数への制限としてphp.iniに
        pcre.backtrack_limitとpcre.recursion_limitの設定が
        追加されています。
        　この動作変更により、paraedit 0.6の仕様ではページ
        内の文字数が制限値へダイレクトに影響してしまい、
        制限値を超える文字を加えた場合、空データを返して
        しまう事がわかりました。
        　調べてみると問題になっている部分のparaedit 0.6
        の処理は、無駄っぽいため、直接ページデータ渡す処理
        に変更してみました。
        ※php.iniを変更できる管理者であれば、設定値を調整
          することで、問題の現象を回避することが出来るかも
          しれません。

 2.     「テキスト整形のルール」のリンク処理を変更


 taru : paraedit0.8 変更点について
        v0.9 split() を explode() に書き換え、fatal errorを回避した物
 はいふん：v1.0 PHP8対応 2021-12-20

 1.     function plugin_paraedit_init()で参照しているinit.php
        がPukiWiki 1.4.7ではlibフォルダに格納されているから
        pukiwiki.phpの定義をコピペしてみた。
        但し、必要なのか不明
 2.     UTF-8環境でEUC-JPにて書かれたプログラムをそのまま使う
        人が多いようなのでUTF-8Nで保存しなおします。

 対象環境: PukiWiki-1.5.x UTF-8N
           PHP5.2.0以降

 http://taru.s223.xrea.com/
***********************************************************

** Copyright
tmk http://linux.s33.xrea.com:8080/SxWiki/

** Licence
GPL2 (GNU General Public License version 2)


*/

// 編集リンクの文字列・スタイルを指定
//   %s に URL が入る

// * 文字バージョン ([edit])
define('_EDIT_LINK', '<span style="float:right; font-size: small; font-weight: lighter; padding: 0px 0px 0px 1em; ">[<a href="%s">edit</a>]</span>');

// * 画像バージョン
//define('_EDIT_LINK', '<span style="float:right; font-size: small; font-weight: lighter; padding: 0px 0px 0px 1em; "><a href="%s"><img src="' . IMAGE_DIR . 'paraedit.png" style="width:9px;height:9px;" /></a></span>');


// 編集リンクの挿入箇所を指定
//   <h2>header</h2> の時、$1:<h2>, $2:header, $3:</h2> となるので $link を好きな場所に移動
// (例)
//　   define(_PARAEDIT_LINK_POS, '$1$2$link$3'); // </h2>の前
       define('_PARAEDIT_LINK_POS', '$1$2$link$3'); // </h2>の前
//   define(_PARAEDIT_LINK_POS, '$link$1$2$3'); // <h2>の前
//   define(_PARAEDIT_LINK_POS, '$1$2$3$link'); // </h2>の後ろ




// 改行の代替文字列
//   <input type=hidden value=XXXXX> で改行(CR,LFなど)の変わりに使用する文字列
define('_PARAEDIT_SEPARATE_STR', '_PaRaeDiT_');


function plugin_paraedit_init()
{
	// init
	// プログラムファイル読み込み
	require_once(LIB_DIR . 'init.php'); // Kさんより
}


function plugin_paraedit_convert()
{
	// HTML にコンバート時に呼び出される-
	return 'ParaEdit version '. _PARAEDIT_VERSION . "\n";
}


function plugin_paraedit_action()
{
	// GET POST 時に呼び出される
	global $get, $post, $vars;
	global $_title_edit; // $LANG.lng で定義済
	
	$script = get_script_uri();
	// 編集不可能なページを編集しようとしたとき
	if (S_VERSION < 1.4) {
		if (is_freeze($vars['page']) || !is_editable($vars['page']) || $vars["page"] == "")
		{
			$wikiname = rawurlencode($vars['page']);
			header("Location: " . $script . "?cmd=edit&page=$wikiname");
			die();
		}
	} else {
		// check_editable($page, BASIC認証表示, NG画面に遷移)
		check_editable($vars['page'], true, true);
	}

	// pukiwiki.php から拝借
	$postdata = @join("",get_source($get['page']));
	if($postdata == "") {
		$postdata = auto_template($get['page']); //# should be test
	}
	$postdata = htmlsc($postdata);

//	#$page = str_replace('$1',make_search($get['page']), $_title_edit);
	$page = $_title_edit;
	
	// edit_form() で $postdata = $vars[refer] . $postdata; となるため小細工
	$refer = $vars['refer'];
	$vars['refer'] = '';
	
	$textdata = '___paraedit_taxtarea___';
	if (S_VERSION < 1.4) {
		$body = edit_form($textdata, $get['page']); // v 1.3.5
	} else {
		$body = edit_form($get['page'], $textdata);  // v 1.4
	}
	
	$vars['refer'] = $refer;
	
	// <textarea name="msg" ...> 前後で分割
	$lines = array();
	$textareas = array(); // 0: whole, 1: before msg, 2: textarea tag, 3: msg 4: after msg
	preg_match("/^(.*?)(<textarea .*?>)(___paraedit_taxtarea___)(<\/textarea>.*)$/is", $body, $textareas);
	
	// 改行コードを \n に統一
//	$vars['msg'] = preg_replace("/((\x0D\x0A)|(\x0D)|(\x0A))/", "\n", $vars["msg"]);
	$vars["msg"] = str_replace("\r", "\n", str_replace("\r\n", "\n", $vars["msg"]));
	
	// $vars[msg] を分割
	$msg_before; $msg_now; $msg_after; // 編集行とその前後
	$part = $vars['parnum'];
	$index_num = 0;
	$is_first_line = 1;
	foreach (explode ("\n", $postdata) as $line) {
		if (preg_match("/^\*{1,3}/", $line)) {
			$index_num++;
		}
		if (!$is_first_line) { $line = "\n$line"; } else { $is_first_line = 0; }
		if ($index_num < $part) {
			$msg_before .= $line;
		} else if ($index_num == $part) {
			$msg_now .= $line;
		} else if ($index_num > $part) {
			$msg_after .= $line;
		}
	}
	
	// 微調整 (silly!)
	$msg_before = preg_replace("/^\n/", "", $msg_before);
	if ($msg_before) { $msg_before .= "\n"; }
	
	// 改行コードを書換え
	$msg_before = preg_replace("/\n/", _PARAEDIT_SEPARATE_STR, $msg_before);
	$msg_after  = preg_replace("/\n/", _PARAEDIT_SEPARATE_STR, $msg_after);
	
	// 結合
	$body = $textareas[1]
		. '<input type="hidden" name="msg_before" value="' . $msg_before . '" />' . "\n"
		. '<input type="hidden" name="msg_after"  value="' . $msg_after  . '" />' . "\n"
		. $textareas[2]  . $msg_now . $textareas[4];

	// ヘルプ表示 : リンク書き換え
	$body = preg_replace("/cmd=edit(&amp;help=true)/", "plugin=paraedit&amp;parnum=$vars[parnum]$1&amp;refer=" . rawurlencode($vars['page']), $body);

	return array("msg" => $page, "body" => $body);
}

function _plugin_paraedit_mkeditlink($body)
{
	// [edit]リンクの作成
	global $get, $post, $vars;
	$lines = explode("\n", $body);
	$script = get_script_uri();
	
	$para_num = 1;
	$lines2 = array();
	foreach ($lines as $line) {
//		#if (preg_match("/<\/h\d>$/", $line)) {
		if (preg_match("/<h\d .*? paraedit_flag=on/", $line)) {
			#$link = "$script?plugin=paraedit&parnum=$para_num&page=" . rawurlencode($vars[page]); // v 1.3.5
			$line = preg_replace("/ paraedit_flag=on/", "", $line);
			$link = "$script?plugin=paraedit&amp;parnum=$para_num&amp;page=" . rawurlencode($vars['page']) . '&amp;refer=' . rawurlencode($vars['page']); // v 1.4
			$link = sprintf(_EDIT_LINK, $link);
			$replaced = _PARAEDIT_LINK_POS;
			eval(" \$replaced = \"$replaced\"; ");
			$line = preg_replace("/(<h\d.*?>)(.*)(<\/h\d>)/", $replaced, $line);

			$para_num++;
		}
		array_push($lines2, $line);
	}
	
	$body = @join("\n", $lines2);
	return $body;
}

function _plugin_paraedit_parse_postmsg($msg_before, $msg_now, $msg_after)
{
	// pukiwiki.php から呼び出し、
	// $post["msg_*"] を整形・結合して $post["msg"] を返す
	
	if ($msg_before == "" && $msg_after == "") { return $msg_now; }
	
	// 改行代替文字列を \n に変換
	$msg_before = str_replace(_PARAEDIT_SEPARATE_STR, "\n", $msg_before);
	$msg_now    = str_replace(_PARAEDIT_SEPARATE_STR, "\n", $msg_now);
	$msg_after  = str_replace(_PARAEDIT_SEPARATE_STR, "\n", $msg_after);
	
	// 整形
	//$msg_before .= (preg_match("/\n$/", $msg_before)) ? "" : "\n";
	//$msg_now    .= (preg_match("/\n$/", $msg_now)   ) ? "" : "\n";
	
	// 結合
	return $msg_before . $msg_now . $msg_after;
}

?>