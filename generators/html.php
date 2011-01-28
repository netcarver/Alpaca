<?php

class TextileOutputGenerator
{
	static protected $parser  = null;
	static protected $verbose = false;


	/**
	 *	Constructor.
	 * Add your listeners and extension spans/blocks/glyphs in here...
	 */
	public function __construct( $parser )
	{
		self::$parser  = $parser;
		self::$verbose = false;			# change to true for more output.

		self::$parser->AddParseListener( '*', 'TextileOutputGenerator::ParseListener');	# We want to know *everything*
	}


  # ===========================================================================
	#
	# The following just provided as an illustration of listening to parse events. Not likely to be
	# too useful in an output generator as they are being called back by the very events that this would
	# listen to anyway. However, in the case of something like an Index or TOC generation textplug, this
	# listen ability will be useful.
	# 
  # ===========================================================================
	static public function ParseListener( $event )
	{
		$parse_event = $event[0];
		if( !in_array( $parse_event, array( 'initials', 'finals' ) ) )	# Indent non initials/finals events...
			$parse_event = "\t$parse_event";

		if( self::$verbose ) 
			self::$parser->dump( $parse_event /*, $event[1]*/ );

		if( $event[0] === 'finals' )	# limit debug to first full set (if verbose is on)
			self::$verbose = false;

		# Extensions could build auxiliary structures, 
		# (like a TOC by listening to block:h events) and later place them in the document with 
		# a PostParseHandler.
	}

	static public function TidyLineBreaks( &$in )
	{
//		$block = self::$parser->doPBr($block);
		$in = preg_replace('/<br>/', '<br />', $in);	# TODO: Speed this up -- No need for preg_ here.
	}


  # ===========================================================================
	#
	# Block handlers...
	#
  # ===========================================================================
	static public function notextile_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		$content = self::$parser->ShelveFragment($content);
		$o1 = $o2 = $c1 = $c2 = '';
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


	# TODO move this one to a new textplug.
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


	static public function default_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		#echo $content,"\n\n";
		if( $tag === '###' ) 
		  return array( '', '', '', '', '', true );

    $o2 = "\t<$tag$atts>";
		$c2 = "</$tag>";
    return array($o1, $o2, $content, $c2, $c1, $eat);
	}


	# ===========================================================================
	#
	# Footnote ID handlers...
	#
  # ===========================================================================
  static public function FootnoteIDHandler($id, $nolink, $t)
	{
		$backref = '';
	
		if( self::$parser->GetFootnoteID( $id ) === $id ) {
		  $a = uniqid(rand());
		  self::$parser->DefineFootnoteID( $id, $a );
			$backref = 'id="fnrev'.$a.'" ';
		}

		$fnid = self::$parser->GetFootnoteID( $id );

		$footref = ( '!' == $nolink ) ? $id : '<a href="#fn'.$fnid.'">'.$id.'</a>';
		$footref = '<sup '.$backref.'class="footnote">'.$footref.'</sup>';

		return $footref;
	}


  # ===========================================================================
	#
	# Span handlers...
	#
  # ===========================================================================
	static public function verbatim_SpanHandler( $span, $m )
	{
		list(, $pre, $tag, $atts, $cite, $content, $end, $closetag, $tail) = $m;
#self::$parser->dump( __METHOD__." -- Called with [$span], matched [$tag/$closetag]." );

		$content = self::$parser->ShelveFragment($atts.$content);
		return $pre.$content.$tail;
	}


	static public function code_SpanHandler( $span, $m )
	{
		list(, $pre, $tag, $atts, $cite, $content, $end, $closetag, $tail) = $m;
//self::$parser->dump( __METHOD__." -- Called with [$span], matched [$tag/$closetag]." );

		$content = self::$parser->ShelveFragment(self::$parser->ConditionallyEncodeHTML($atts.$content));

		return "$pre<code>$content</code>$tail";
	}


	/**
	 *	Default span handler -- in the case of HTML, the span name is the HTML tag to use to wrap the content.
	 **/
	static public function default_SpanHandler( $span, $m )
	{
		# Fixme the following two lines are needed as I chose to implement the span container as a bag...
		if( in_array($span, array('notextile','inlinetextile') ) )
			return self::verbatim_SpanHandler($span, $m);

		list(, $pre, $tag, $atts, $cite, $content, $end, $closetag, $tail) = $m;

		$atts = self::$parser->ParseBlockAttributes($atts);
		$atts .= ($cite != '') ? 'cite="' . $cite . '"' : '';
		$content = self::$parser->ParseSpans($content);
		$opentag = '<'.$span.$atts.'>';
		$closetag = '</'.$span.'>';
//		$tags = $this->storeTags($opentag, $closetag);	# TODO Will need to do this to allow glyphing around spans.
//		$out = "{$tags['open']}{$content}{$end}{$tags['close']}";
		$out = "$opentag{$content}{$end}$closetag"; # fixme this line is temp output -- remove when the two lines above are uncommented.

		if (($pre and !$tail) or ($tail and !$pre))
			$out = $pre.$out.$tail;

		return $out;
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

#eof
