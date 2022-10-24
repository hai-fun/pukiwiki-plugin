<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: region.inc.php,v 1.2 2005/01/22 15:50:00 xxxxx Exp $
//

function plugin_region_convert()
{
	static $builder = 0;
	if( $builder==0 ) $builder = new RegionPluginHTMLBuilder();

	// static ��������Ƥ��ޤä��Τǣ����ܸƤФ줿�Ȥ������ξ��󤬻ĤäƤ����Ѥ�ư��ˤʤ�Τǽ������
	$builder->setDefaultSettings();

	// ���������ꤵ��Ƥ���褦�ʤΤǲ���
	if (func_num_args() >= 1){
		$args = func_get_args();
		$builder->setDescription( array_shift($args) );
		foreach( $args as $value ){
			// opened �����ꤵ�줿����ɽ���ϳ��������֤�����
			if( preg_match("/^open/i", $value) ){
				$builder->setOpened();
			// closed �����ꤵ�줿����ɽ�����Ĥ������֤����ꡣ
			}elseif( preg_match("/^close/i", $value) ){
				$builder->setClosed();
			}
		}
	}
	// �ȣԣͣ��ֵ�
	return $builder->build();
}


// ���饹�κ������http://php.s3.to/man/language.oop.object-comparison-php4.html
class RegionPluginHTMLBuilder
{
	var $description;
	var $isopened;
	var $scriptVarName;
	//�� build�᥽�åɤ�Ƥ������򥫥���Ȥ��롣
	//�� ����ϡ����Υץ饰������������JavaScript��ǥ�ˡ������ѿ�̾�����ʤ��ѿ�̾�ˤ��������뤿��˻Ȥ��ޤ�
	var $callcount;

	function RegionPluginHTMLBuilder() {
		$this->callcount = 0;
		$this->setDefaultSettings();
	}
	function setDefaultSettings(){
		$this->description = "...";
		$this->isopened = false;
	}
	function setClosed(){ $this->isopened = false; }
	function setOpened(){ $this->isopened = true; }
	// convert_html()��Ȥäơ����פ���ʬ�˥֥�󥱥åȥ͡����Ȥ���褦�˲��ɡ�
	function setDescription($description){
		$this->description = convert_html($description);
		// convert_html��Ȥ��� <p>�����ǰϤޤ�Ƥ��ޤ���Mozzila����ɽ���������Τ�<p>������ä���
		$this->description = preg_replace( "/^<p>/i", "", $this->description);
		$this->description = preg_replace( "/<\/p>$/i", "", $this->description);
	}
	function build(){
		$this->callcount++;
		$html = array();
		// �ʹߡ��ȣԣ̺ͣ�������
		array_push( $html, $this->buildButtonHtml() );
		array_push( $html, $this->buildBracketHtml() );
		array_push( $html, $this->buildSummaryHtml() );
		array_push( $html, $this->buildContentHtml() );
		return join($html);
	}

	// �� �ܥ������ʬ��
	function buildButtonHtml(){
		$button = ($this->isopened) ? "-" : "+";
		// JavaScript��summaryrgn1��contentrgn1�ʤɤȤ��ä������Υ�ˡ������ѿ�̾����ѡ����֤ä���촬�ν����Ǥ��������٤���id���ꤻ�����֥������ȼ���褦�ʡ��ʤ󤫤褤��ˡ������Ф���������ɡ�
		return <<<EOD
<table cellpadding=1 cellspacing=2><tr>
<td valign=top>
	<span id=rgn_button$this->callcount style="cursor:pointer;font:normal 10px �ͣ� �Х����å�;border:gray 1px solid;"
	onclick="
	if(document.getElementById('rgn_summary$this->callcount').style.display!='none'){
		document.getElementById('rgn_summary$this->callcount').style.display='none';
		document.getElementById('rgn_content$this->callcount').style.display='block';
		document.getElementById('rgn_bracket$this->callcount').style.borderStyle='solid none solid solid';
		document.getElementById('rgn_button$this->callcount').innerHTML='-';
	}else{
		document.getElementById('rgn_summary$this->callcount').style.display='block';
		document.getElementById('rgn_content$this->callcount').style.display='none';
		document.getElementById('rgn_bracket$this->callcount').style.borderStyle='none';
		document.getElementById('rgn_button$this->callcount').innerHTML='+';
	}
	">$button</span>
</td>
EOD;
	}

	// �� Ÿ�������Ȥ��κ�¦�ΰϤ�����ʬ������ʤ�� �� [ �� �ܡ������Ǿ岼����solid����¦����none�ˤ��� [ �˸��������롣
	function buildBracketHtml(){
		$bracketstyle = ($this->isopened) ? "border-style: solid none solid solid;" : "border-style:none;";
		return <<<EOD
<td id=rgn_bracket$this->callcount style="font-size:1pt;border:gray 1px;$bracketstyle">&nbsp;</td>
EOD;
	}

	// �� �̾�ɽ�����Ƥ���Ȥ���ɽ�����ơ�
	function buildSummaryHtml(){
		$summarystyle = ($this->isopened) ? "display:none;" : "display:block;";
		return <<<EOD
<td id=rgn_summary$this->callcount style="color:gray;border:gray 1px solid;$summarystyle">$this->description</td>
EOD;
	}

	// �� Ÿ��ɽ�����Ƥ���Ȥ���ɽ�����ƥإå���ʬ��������<td>���Ĥ������� endregion ¦�ˤ��롣
	function buildContentHtml(){
		$contentstyle = ($this->isopened) ? "display:block;" : "display:none;";
		return <<<EOD
<td valign=top id=rgn_content$this->callcount style="$contentstyle">
EOD;
	}

}// end class RegionPluginHTMLBuilder

?>
