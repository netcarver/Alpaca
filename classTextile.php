<?php


/**
 * Exceptions.
 * Alpaca Textile can throw a few exceptions.
 */
class TextileUnexpectedException extends Exception {}
class TextileProgrammerException extends TextileUnexpectedException {}	# Thrown for incorrect setup which needs to fail early and loud.

/**
 * Helper class ...
 */
class TextileDataBag
{
	protected $data;

	public function __construct()
	{
		$this->data = array();
	}

	public function __get( $name )
	{
		$tmp = @$this->data[$name];
		return (string)$tmp;
	}

	public function __set( $name, $value )
	{
		$this->data[$name] = $value;
	}
	
	public function __call( $name, $args )
	{
		$this->data[$name] = $args[0]; # Unknown methods act as setters
		return $this;	# Allow chaining for multiple calls.
	}

	public function __toString()
	{
		return var_export($this->data, true);
	}
}

/**
 *
 */
class Textile
{
	protected $output_type      = null;
  protected $output_generator = null;
  protected $parse_listeners  = array();
	protected $block_handlers   = array();
	protected $glyphs           = array();
	protected $spans            = array();
	protected $patterns         = null;
	protected $blocktags        = array();
	protected $restricted       = true;

	/**
	 *
	 */
	public function __construct( $type = 'html' )
	{
		$this->output_type = $type;

		$this->patterns = new TextileDataBag();
		$this->patterns
			->hlgn("(?:\<(?!>)|(?<!<)\>|\<\>|\=|[()]+(?! ))")
		  ->vlgn("[\-^~]")
		  ->clas("(?:\([^)\n]+\))")		# Don't allow classes/ids/languages/styles to span across newlines
		  ->lnge("(?:\[[^]\n]+\])")
		  ->styl("(?:\{[^}\n]+\})")
		  ->cspn("(?:\\\\\d+)")
		  ->rspn("(?:\/\d+)")
		  ->a   ("(?:{$this->patterns->hlgn}|{$this->patterns->vlgn})*")
		  ->s   ("(?:{$this->patterns->cspn}|{$this->patterns->rspn})*")
		  ->c   ("(?:{$this->patterns->clas}|{$this->patterns->styl}|{$this->patterns->lnge}|{$this->patterns->hlgn})*")
		  ->lc  ("(?:{$this->patterns->clas}|{$this->patterns->styl}|{$this->patterns->lnge})*");

#$this->dump( $this->patterns );

		# Load the generator config...
		$generator = "./generators/$type.php";
		include_once( $generator );
		$this->output_generator = new TextileOutputGenerator( $this );
	}


  /**
	 * Allows textplugs to extend or override the default block handlers.
	 */
	public function RegisterBlockHandler( $block , $handler )
	{
		if( !is_string( $block ) || '' === $block )
			return false;
		$tmp = @$this->block_handlers[$block];
		if( isset($tmp) && is_callable( $tmp ) )
		  return false;
		$this->block_handlers[$block] = $handler;
		return true;
	}

  /**
	 * Adds a parse event listener. Not sure this is still needed.
	 */
	public function AddParseListener( $event, $listener )
	{
		if( !is_string($event) || empty($event) )
			throw new TextileProgrammerException( 'Invalid $event name supplied -- should be a string.' );
		if( !is_callable( $listener ) )
			throw new TextileProgrammerException( 'Invalid $listener supplied -- not callable.' );
		$this->parse_listeners[$event][] = $listener;
	}

  /**
   *	Interfaces for setting up Glyph & Span rules.
	 * What is a glyph and what is a span?
	 */
	public function DefineGlyph( $name, $pattern, $replacement )
	{
	}

	public function DefineSpan( $name, $marker, $replacement )
	{
	}


  /**
	 * @method Cleanse
	 *
	 * Cleans potentially malicious input -- used in cleansing block attributes.
	 */
	public function Cleanse( $in )
	{
	  $tmp    = $in;
	  $before = -1;
	  $after  = 0;
	  while( $after != $before )
	  {
  	  $before = strlen( $tmp );
  	  $tmp    = rawurldecode($tmp);
	    $after  = strlen( $tmp );
	  }

		$out = strtr( $tmp, array(
			'"'=>'',
			"'"=>'',
			'='=>'',
			));
		return $out;
	}

	public function ParseBlockAttributes($in, $element = "", $include_id = 1) 
	{
		$style = '';
		$class = '';
		$lang = '';
		$colspan = '';
		$rowspan = '';
		$span = '';
		$width = '';
		$id = '';
		$atts = '';

		if (!empty($in)) {
			$matched = $in;
			if ($element == 'td') {
				if (preg_match("/\\\\(\d+)/", $matched, $csp)) $colspan = $csp[1];
				if (preg_match("/\/(\d+)/", $matched, $rsp)) $rowspan = $rsp[1];
			}

			if ($element == 'td' or $element == 'tr') {
				if (preg_match("/($this->patterns->vlgn)/", $matched, $vert))
					$style[] = "vertical-align:" . $this->vAlign($vert[1]);	# TODO no coverage of vAlign in current test cases.
			}

			if (preg_match("/\{([^}]*)\}/", $matched, $sty)) {
				$style[] = rtrim($sty[1], ';');
				$matched = str_replace($sty[0], '', $matched);
			}

			if (preg_match("/\[([a-zA-Z]{2}(?:\-[a-zA-Z]{2})?)\]/U", $matched, $lng)) {
				$lang = $lng[1];
				$matched = str_replace($lng[0], '', $matched);
			}

			# Only allow a restricted subset of the CSS standard characters for classes/ids. No encoding markers allowed...
			if (preg_match("/\(([-a-zA-Z0-9_\.\:\#]+)\)/U", $matched, $cls)) {
				$class = $cls[1];
				$matched = str_replace($cls[0], '', $matched);
			}

			if (preg_match("/([(]+)/", $matched, $pl)) {
				$style[] = "padding-left:" . strlen($pl[1]) . "em";
				$matched = str_replace($pl[0], '', $matched);
			}

			if (preg_match("/([)]+)/", $matched, $pr)) {
				$style[] = "padding-right:" . strlen($pr[1]) . "em";
				$matched = str_replace($pr[0], '', $matched);
			}

			if (preg_match("/({$this->patterns->hlgn})/", $matched, $horiz))
				$style[] = "text-align:" . $this->HorizontalAlign($horiz[1]);

      # If a textile class block attribute was found, split it into the css class and css id (if any)...
			if (preg_match("/^([-a-zA-Z0-9_]*)#([-a-zA-Z0-9_\.\:]*)$/", $class, $ids)) {
				$id = $ids[2];
				$class = $ids[1];
			}

			if ($element == 'col') {
				if (preg_match("/(?:\\\\(\d+))?\s*(\d+)?/", $matched, $csp)) {
					$span = isset($csp[1]) ? $csp[1] : '';
					$width = isset($csp[2]) ? $csp[2] : '';
				}
			}

			if ($this->restricted)
				return ($lang)	  ? ' lang="'	. $this->Cleanse($lang) . '"':'';

			$o = '';
			if( $style ) {
				foreach($style as $s) {
					$parts = explode(';', $s);
					foreach( $parts as $p ) {
						$p = trim($p, '; ');
						if( !empty( $p ) )
							$o .= $p.'; ';
					}
				}
				$style = trim( strtr($o, array("\n"=>'',';;'=>';')) );
			}

			return join('',array(
				($style)   ? ' style="'   . $this->Cleanse($style)    .'"':'',
				($class)   ? ' class="'   . $this->Cleanse($class)    .'"':'',
				($lang)    ? ' lang="'    . $this->Cleanse($lang)     .'"':'',
				($id and $include_id) ? ' id="' . $this->Cleanse($id) .'"':'',
				($colspan) ? ' colspan="' . $this->Cleanse($colspan)  .'"':'',
				($rowspan) ? ' rowspan="' . $this->Cleanse($rowspan)  .'"':'',
				($span)    ? ' span="'    . $this->Cleanse($span)     .'"':'',
				($width)   ? ' width="'   . $this->Cleanse($width)    .'"':'',
			));
		}
		return '';
	}



	/**
	 * Stores a fragment verbatim for later pasting back into the output. Prevents any handlers from further
	 * transforming it.
	 */
	public function ShelveFragment($val)
	{
		$i = uniqid(rand());	# TODO can there be collisions from this?
		$this->fragments[$i] = $val;
		return $i;
	}
	public function RetrieveFragment($text)
	{
		if (is_array($this->fragments))
			do {
				$old = $text;
				$text = strtr($text, $this->fragments);
			 } while ($text != $old);

		return $text;
	}


	public function GetFootnoteID( $key_or_default )
	{
		$id = @$this->fn[$key_or_default];
		if( empty( $id ) )
			return $key_or_default;

		return $id;
	}
	public function DefineFootnoteID( $id, $a )
	{
		$this->fn[$id] = $a;
	}

	function EncodeHTML($str, $quotes=1)
	{
		$a = array(
			'&' => '&amp;',
			'<' => '&lt;',
			'>' => '&gt;',
		);
		if ($quotes) $a = $a + array(
			"'" => '&#39;', // numeric, as in htmlspecialchars
			'"' => '&quot;',
		);

		return strtr($str, $a);
	}
	function ConditionallyEncodeHTML($str, $quotes=1)
	{
		// in restricted mode, input has already been escaped
		if ($this->restricted)
			return $str;
		return $this->EncodeHTML($str, $quotes);
	}
	function CleanWhiteSpace($text)
	{
		$out = preg_replace("/^\xEF\xBB\xBF|\x1A/", '', $text); # Byte order mark (if present)
		$out = preg_replace("/\r\n?/", "\n", $out); # DOS and MAC line endings to *NIX style endings
		$out = preg_replace("/^[ \t]*\n/m", "\n", $out);	# lines containing only whitespace
		$out = preg_replace("/\n{3,}/", "\n\n", $out);	# 3 or more line ends
		$out = preg_replace("/^\n*/", "", $out);		# leading blank lines
		return $out;
	}


  protected function _ParseParagraph( $text )
	{
		if (!$this->lite) {
#			$text = $this->noTextile($text);
#			$text = $this->code($text);
		}

#		$text = $this->getRefs($text);
#		$text = $this->links($text);
#		if (!$this->noimage)
#			$text = $this->image($text);

		if (!$this->lite) {
#			$text = $this->table($text);
#			$text = $this->lists($text);
		}

#		$text = $this->span($text);
		$text = $this->_ParseFootnoteRefs($text);
#		$text = $this->noteRef($text);
#		$text = $this->glyphs($text);

		return rtrim($text, "\n");
	}


	protected function _ParseFootnoteRefs($text)
	{
		return preg_replace(
		  '/(?<=\S)\[([0-9]+)([\!]?)\](\s)?/Ue',
		  '$this->_FootnoteRefFound(\'\1\',\'\2\',\'\3\')', 
			$text
			);
	}
	protected function _FootnoteRefFound($id, $nolink, $t)
	{
		$this->TriggerParseEvent( 'graf:fnref' , $id , $nolink, $t );
#		if( is_callable( TextileOutputGenerator::FootnoteIDHandler( $id, $nolink, $t ) ) )
			return call_user_func( 'TextileOutputGenerator::FootnoteIDHandler', $id, $nolink, $t );
#		return $t;
	}


  /**
	 * @method _ParseBlocks
	 */
  protected function _ParseBlocks( $text )
	{
		$find = $this->blocktags;

		if( $this->lite === '' ) {
			$find = array_merge( $find, array_keys( $this->block_handlers ) );
		}
		$tre  = implode('|', $find);

    $blocks = explode("\n\n", $text);

		$tag = 'p';
		$atts = $cite = $graf = $ext = '';
		$eat = false;
		$out = array();

		foreach($blocks as $block) {
			$anon = 0;
			if (preg_match("/^($tre)({$this->patterns->a}{$this->patterns->c})\.(\.?)(?::(\S+))? (.*)$/s", $block, $m)) {
				
				if ($ext) // last block was extended, so close it
					$out[count($out)-1] .= $c1;
				
				list(,$tag,$atts,$ext,$cite,$graf) = $m; // new block
				list($o1, $o2, $content, $c2, $c1, $eat) = $this->_fBlock(array(0,$tag,$atts,$ext,$cite,$graf));

				// leave off c1 if this block is extended, we'll close it at the start of the next block
				if ($ext)
					$block = $o1.$o2.$content.$c2;
				else
					$block = $o1.$o2.$content.$c2.$c1;
			}
			else {
				// anonymous block
				$anon = 1;
				if ($ext or !preg_match('/^ /', $block)) {
					list($o1, $o2, $content, $c2, $c1, $eat) = $this->_fBlock(array(0,$tag,$atts,$ext,$cite,$block));
					// skip $o1/$c1 because this is part of a continuing extended block
					if ($tag == 'p' and !$this->HasRawText($content)) {
						$block = $content;
					}
					else {
						$block = $o2.$content.$c2;
					}
				}
				else {
					$block = $this->_ParseParagraph($block);
				}
			}


			self::TryOutputHandler( 'TidyLineBreaks', $block );

			if ($ext and $anon)
				$out[count($out)-1] .= "\n".$block;
			elseif(!$eat)
				$out[] = $block;

			if (!$ext) {
				$tag = 'p';
				$atts = '';
				$cite = '';
				$graf = '';
				$eat = false;
			}
		}
		if ($ext) $out[count($out)-1] .= $c1;
		$out = implode("\n\n", $out);
//$this->dump( $out );		
		return $out;
	}

	function HasRawText($text)
	{
	  # TODO This list needs to be expandable!
		// checks whether the text has text not already enclosed by a block tag
		$r = trim(preg_replace('@<(p|blockquote|div|form|table|ul|ol|dl|pre|h\d)[^>]*?'.chr(62).'.*</\1>@s', '', trim($text)));
		$r = trim(preg_replace('@<(hr|br)[^>]*?/>@', '', $r));
		return '' != $r;
	}


	static protected function TryOutputHandler( $name, &$in )
	{
		# TODO: Trigger parse event
		$name = "TextileOutputGenerator::$name";
		if( !is_callable( $name ) ) {
#echo "No output handler matching [$name] found.\n";
		  return;
		}

		call_user_func( $name, $in );
	}


	public function TextileThis( $text, $lite = '', $encode = '', $noimage = '', $strict = '', $rel = '' )
	{
		$this->lite       = $lite;
		$this->encode     = $encode;
		$this->noimage    = $noimage;
		$this->strict     = $strict;
		$this->rel        = $rel;
		$this->restricted = false;

		#
		$text = $this->CleanWhiteSpace( $text );

		#
		#	Setup the standard block handlers...
		#
		$this->blocktags = array('h[1-6]', 'p', 'notextile', 'pre', '###', 'fn\d+', 'hr', 'bq', 'bc' );

		#
		#	Start parsing...
		#
		return $this->_ParseBlocks( $text );
	}


	protected function _fBlock($m)
	{
//		extract($this->regex_snippets);
		list(, $tag, $att, $ext, $cite, $content) = $m;
		$atts = $this->ParseBlockAttributes($att);

		$o1 = $o2 = $c2 = $c1 = '';
		$eat = false;

		#
		#	Let any listeners that might be building additional output structures know about this tag...
		#
		$this->TriggerParseEvent( "block:$tag", $content );

		#
		#	Strip numerics from tags to get the handler name (so h1-h6 all map to h_Handler & fn1-fn999 all map to fn_Handler)...
		#
		$roottag = str_replace( array('0','1','2','3','4','5','6','7','8','9'), '', $tag );

		#
		#	First shot at processing the tag goes to any textplug...
		#
		if( ($this->lite === '') && array_key_exists( $roottag, $this->block_handlers ) && is_callable($this->block_handlers[$roottag]) ) {
			list( $o1, $o2, $content, $c2, $c1, $eat ) = call_user_func( $this->block_handlers[$roottag], $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat );
		}
	
		#
		# Otherwise try to pass controll to a standard output generator...
		#
		elseif( is_callable( "TextileOutputGenerator::{$roottag}_BlockHandler" ) ) {
#$this->dump( "Calling {$roottag}_Handler($tag)" );
			list( $o1, $o2, $content, $c2, $c1, $eat ) = call_user_func( "TextileOutputGenerator::{$roottag}_BlockHandler", $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat );
		}

		#
	  # Finally, pass control to the default output handler...
		else {
#$this->dump( "Calling default_Handler($tag)" );
			list( $o1, $o2, $content, $c2, $c1, $eat ) = call_user_func( 'TextileOutputGenerator::default_BlockHandler', $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat );
		}

//$this->dump( $content );

		$content = (!$eat) ? $this->_ParseParagraph($content) : '';
		return array($o1, $o2, $content, $c2, $c1, $eat);
	}



	/**
	 * Sends parse event notifications to all registered listeners.
	 */
	protected function TriggerParseEvent( $name )
	{
#$this->dump( __METHOD__."($name)");
		if( empty( $this->parse_listeners[$name] ) )
			return;

		foreach( $this->parse_listeners[$name] as $listener ) {
			$args = array_slice( func_get_args(), 1 );
			call_user_func( $listener, $args );
		}
	}

	protected function HorizontalAlign($in)
	{
		$vals = array(
			'<'  => 'left',
			'='  => 'center',
			'>'  => 'right',
			'<>' => 'justify');
		return (isset($vals[$in])) ? $vals[$in] : '';
	}


	/**
	 * 
	 */
  public function dump()
	{
	 	static $bool = array( 0=>'false', 1=>'true' );
		foreach (func_get_args() as $a)
			echo (($this->output_type!='text')? "\n<pre>" : ''),(is_array($a)) ? print_r($a) : ((is_bool($a)) ? $bool[(int)$a] : $a), (($this->output_type!='text')? "\n</pre>" : ''), "\n";
		return $this;
	}

}

#eof
