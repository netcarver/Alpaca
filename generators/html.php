<?php

class TextileOutputGenerator
{
	static protected $parser = null;

	public function __construct( $parser )
	{
		self::$parser = $parser;
	}

	static public function TidyLineBreaks( &$in )
	{
//		$block = self::$parser->doPBr($block);
		$block = preg_replace('/<br>/', '<br />', $block);
	}


	static public function notextile_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		$content = self::$parser->ShelveFragment($content);
		$o1 = $o2 = '';
		$c1 = $c2 = '';
		return array($o1, $o2, $content, $c2, $c1, $eat);
	}


	static public function bq_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
#//			$cite = $this->shelveURL($cite);
		$cite = ($cite != '') ? ' cite="' . $cite . '"' : '';
		$o1 = "\t<blockquote$cite$atts>\n";
		$o2 = "\t\t<p".self::$parser->ParseBlockAttributes($att, '', 0).">";
		$c2 = "</p>";
		$c1 = "\n\t</blockquote>";
		return array($o1, $o2, $content, $c2, $c1, $eat);
  }


	static public function bc_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		$o1 = "<pre$atts>";
		$o2 = "<code".self::$parser->ParseBlockAttributes($att, '', 0).">";
		$c2 = "</code>";
		$c1 = "</pre>";
		$content = self::$parser->ShelveFragment(self::$parser->ConditionallyEncodeHTML(rtrim($content, "\n")."\n"));
		return array($o1, $o2, $content, $c2, $c1, $eat);
	}


	static public function hr_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		$o1 = "<hr$atts";
		$c1 = ' />';
		$o2 = $c2 = '';
		$content = rtrim( $content );
		if( $content !== '' )
		{
			$o2 = ' title="';
			$c2 = '"';
			$content = self::$parser->ShelveFragment(self::$parser->ConditionallyEncodeHTML($content));
		}
		return array($o1, $o2, $content, $c2, $c1, $eat);
	}


	static public function fn_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		if (preg_match("/fn(\d+)/", $tag, $fns)) {
			$tag = 'p';
			$fnid = self::$parser->GetFootnoteID($fns[1]);

			# If there is an author-specified ID goes on the wrapper & the auto-id gets pushed to the <sup>
			$supp_id = '';
			if (strpos($atts, ' id=') === false)
				$atts .= ' id="fn' . $fnid . '"';
			else
				$supp_id = ' id="fn' . $fnid . '"';

			if (strpos($atts, 'class=') === false)
				$atts .= ' class="footnote"';

			$backlink = (strpos($att, '^') === false) ? $fns[1] : '<a href="#fnrev' . $fnid . '">'.$fns[1].'</a>';
			$sup = "<sup$supp_id>$backlink</sup>";

			$content = $sup . ' ' . $content;
	    $o2 = "\t<p$atts>";
			$c2 = "</p>";
    	return array($o1, $o2, $content, $c2, $c1, $eat);
		}
	}



	static public function _BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
	}



	static public function default_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
#echo $content,"\n\n";
		if( $tag === '###' ) 
		  return array( '', '', '', '', '', true );

    $o2 = "\t<$tag$atts>";
		$c2 = "</$tag>";
    return array($o1, $o2, $content, $c2, $c1, $eat);
	}

/*
		if( $tag === 'p' ) {
			# Is this an anonymous block with a note definition?
			$notedef = preg_replace_callback("/
					^note\#               #  start of note def marker
					([$wrd:-]+)           # !label
					([*!^]?)              # !link
					({$this->c})          # !att
					\.[\s]+               #  end of def marker
					(.*)$                 # !content
				/x$mod", array(&$this, "fParseNoteDefs"), $content);

			if( '' === $notedef ) # It will be empty if the regex matched and ate it.
				return array($o1, $o2, $notedef, $c2, $c1, true);
			}
*/



}

