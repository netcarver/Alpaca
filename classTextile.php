<?php

@define('txt_quote_single_open',  '&#8216;');
@define('txt_quote_single_close', '&#8217;');
@define('txt_quote_double_open',  '&#8220;');
@define('txt_quote_double_close', '&#8221;');
@define('txt_apostrophe',         '&#8217;');
@define('txt_prime',              '&#8242;');
@define('txt_prime_double',       '&#8243;');
@define('txt_ellipsis',           '&#8230;');
@define('txt_emdash',             '&#8212;');
@define('txt_endash',             '&#8211;');
@define('txt_dimension',          '&#215;');
@define('txt_trademark',          '&#8482;');
@define('txt_registered',         '&#174;');
@define('txt_copyright',          '&#169;');
@define('txt_half',               '&#189;');
@define('txt_quarter',            '&#188;');
@define('txt_threequarters',      '&#190;');
@define('txt_degrees',            '&#176;');
@define('txt_plusminus',          '&#177;');

/**
 * Exceptions.
 * Alpaca Textile can throw a few exceptions.
 */
class TextileUnexpectedException extends Exception {}
class TextileProgrammerException extends TextileUnexpectedException {}	# Thrown for incorrect setup which needs to fail early and loud.


/**
 *	Common base class with some common behaviour.
 */
abstract class TextileObject
{
	static protected function validateString($s, $msg)	
	{ 
		if(!is_string($s) || empty($s)) 
			throw new TextileProgrammerException($msg);
	}

	static protected function validateExists($arg, $msg)
	{
		if(!isset($arg)) 
			throw new TextileProgrammerException($msg);
	}

	static protected function validateCallable($function, $msg)
	{
		if( !is_callable($function) )
			throw new TextileProgrammerException($msg);
	}

	/**
	 * 
	 */
  public function dump()
	{
		$html = true;
	 	static $bool = array( 0=>'false', 1=>'true' );
		foreach (func_get_args() as $a) {
		  if( $html ) 
				echo "\n<pre>", (is_array($a)) ? htmlspecialchars(var_export($a, true)) : ((is_bool($a)) ? $bool[(int)$a] : htmlspecialchars($a)), "</pre>\n";
			else 
				echo "\n", (is_array($a)) ? var_export($a,true) : ((is_bool($a)) ? $bool[(int)$a] : $a), "\n";
		}
		return $this;
	}
}


/**
 * Helper class allows function chaining to set data...
 * data_bag->name1(value1)->name2(value2);
 */
class TextileDataBag extends TextileObject
{
	protected $data;
	protected $container_name;

	public function __construct($name)        { self::validateString($name, "Container must be named."); $this->container_name = $name; $this->data = array(); }
	public function __get( $name )          	{ return (string)@$this->data[$name]; }
	public function __set( $name, $value )   	{ $this->data[$name] = $value; return $this; }
	public function add( $name, $value )      { $this->data[$name] = $value; return $this; }
	public function remove($name)             { unset($this->data[$name]); return $this; }
	public function getData()                 { return $this->data; }
	public function get($name)                { return (string)@$this->data[$name]; }
	public function __toString()              { return var_export($this->data, true); }
	public function __call( $name, $args )
	{
		self::validateExists(@$args[0], "Please supply a value for member [$name].");
		$this->data[$name] = $args[0]; # Unknown methods act as setters
		return $this;	# Allow chaining for multiple calls.
	}
	public function dump() { parent::dump("=== Data for $this->container_name... ==="); parent::dump($this->data); return $this; }
}

/**
 *	Simple class to represent spans.
 * Spans may have symmetric open and close markers (as in textile's '*abc*') or
 * asymmetric ones (like '<notextile>abc</notextile>')
 */
class TextileSpan
{
	protected $data;
	
	public function __construct( $name, $open, $close )
	{
		$this->data['name']  = $name;
		$this->data['open']  = $open;
		$this->data['close'] = $close;
	}
	public function __get($key)           { return $this->data[$key]; }
	public function getKey($open,$close)  { if( ($open === $this->data['open']) && ($close === $this->data['close']) ) return $this->data['name']; else return false; }
}


/**
 *	Simple class to hold a set of Spans.
 */
class TextileSpanSet extends TextileObject
{
	protected $data;

	public function __construct()             { $this->data = array(); }
	public function __call($name, $args)      { return $this->addAsymmetricSpan( $name, @$args[0], ( (isset($args[1]))? $args[1] : @$args[0]) ); }
	public function getData()                 { return $this->data; }

	public function addAsymmetricSpan( $name, $open, $close )
	{
		self::validateString($name,  'Invalid span $name -- must be non-empty string');
		self::validateString($open,  'Invalid span $open -- must be non-empty string');
		self::validateString($close, 'Invalid span $close -- must be non-empty string');

		$this->data[] = new TextileSpan( $name, $open, $close );
		return $this;
	}

	public function lookupSpanName($open,$close=null) # TODO fix this to work with span objects...
	{
		if( null === $close ) $close = $open;
		$key = false;
		foreach( $this->data as $entry ) {
			if( ($entry['open'] == $open) && ($entry['close'] == $close) )
				return $entry['name'];
		}
		return $key;
	}
	public function dump($label) { if(is_string($label) && !empty($label)) parent::dump("=== Data for $label... ==="); parent::dump($this->data); return $this; }
}

/**
 *	Iterator for spans -- uses a copy of the span set's data to iterate over as it's used in a recursive context.
 */
class TextileSpanIterator implements Iterator
{
	protected $position = 0;
	protected $array;

	public function __construct( TextileSpanSet &$set ) { $this->array = $set->getData(); }
	public function rewind()    { $this->position = 0; }
	public function current()   { return $this->array[$this->position]; }
	public function key()       { return $this->position; }
	public function next()      { ++$this->position; }
	public function valid()     { return isset($this->array[$this->position]); }
}


/**
 *
 */
class Textile extends TextileObject
{
	protected $output_type      = null;
  protected $output_generator = null;
  protected $parse_listeners  = array();	# DB of listeners to parse events.
	protected $block_handlers   = array();	# DB of registered textplug callbacks
	protected $glyphs           = null;			# standard-textile glyph markers
	protected $patterns         = null;			# standard-textile regex patterns
	protected $spans            = null;			# standard-textile span start/end markers
	protected $blocktags        = array();
	protected $restricted       = true;			# Textile runs in restricted mode unless invoked via 'TextileThis()'
	protected $span_depth;
	protected $max_span_depth;
	protected $fragments				= array();	# Stores completed output fragments for latter stitching back together.
	protected $hu;
	protected $url_schemes;

	/**
	 *
	 */
	public function __construct( $type = 'html' )
	{
		$this->output_type = $type;
		$this->hu = (defined('hu')) ? hu : '';
		$this->url_schemes = array('http','https','ftp','mailto');

		@define('txt_has_unicode', @preg_match('/\pL/u', 'a')); // Detect if Unicode is compiled into PCRE

		$this->patterns = new TextileDataBag('General regex patterns');
		if( txt_has_unicode ) {
			$this->patterns
				->acr('\p{Lu}\p{Nd}')
				->abr('\p{Lu}')
				->nab('\p{Ll}')
				->wrd('(?:\p{L}|\p{M}|\p{N}|\p{Pc})')
				->mod('u');
		} else {
			$this->patterns
				->acr('A-Z0-9')
				->abr('A-Z')
				->nab('a-z')
				->wrd('\w')
				->mod('');
		}
		$this->patterns
			->hlgn("(?:\<(?!>)|(?<!<)\>|\<\>|\=|[()]+(?! ))")
		  ->vlgn("[\-^~]")
		  ->clas("(?:\([^)\n]+\))")		# Don't allow classes/ids/languages/styles to span across newlines
		  ->lnge("(?:\[[^]\n]+\])")
		  ->styl("(?:\{[^}\n]+\})")
		  ->cspn("(?:\\\\\d+)")
		  ->rspn("(?:\/\d+)")
		  ->a("(?:{$this->patterns->hlgn}|{$this->patterns->vlgn})*")
		  ->s("(?:{$this->patterns->cspn}|{$this->patterns->rspn})*")
		  ->c("(?:{$this->patterns->clas}|{$this->patterns->styl}|{$this->patterns->lnge}|{$this->patterns->hlgn})*")
		  ->lc("(?:{$this->patterns->clas}|{$this->patterns->styl}|{$this->patterns->lnge})*")
			->urlch('[\w"$\-_.+!*\'(),";\/?:@=&%#{}|\\^~\[\]`]')
			->pnc('[[:punct:]]')
			#->dump()
			;

		/**
		 *	By default, the standard textile spans are now *named* after the HTML tag that should be emmitted for them.
		 */
		$this->spans = new TextileSpanSet();
		$this->spans	# TODO make sure each of these spans is covered in the test cases.
			->verbatim('==')
			->verbatim('<notextile>', '</notextile>')
			->code('@')
			->code('<code>','</code>')
			->b('**')
			->strong('*')
			->cite('??')
		  ->del('-')
      ->i('__')
			->em('_')
		  ->span('%')
			->ins('+')
			->sub('~')
			->sup('^')
			#->dump('spans')
			;

		$this->glyphs = new TextileDataBag('Glyph match patterns');
		$this->glyphs
		  ->apostrophe('/('.$this->patterns->wrd.')\'('.$this->patterns->wrd.')/'.$this->patterns->mod)
			->initapostrophe('/(\s)\'(\d+'.$this->patterns->wrd.'?)\b(?![.]?['.$this->patterns->wrd.']*?\')/'.$this->patterns->mod)
			->singleclose('/(\S)\'(?=\s|'.$this->patterns->pnc.'|<|$)/')
			->singleopen('/\'/')
			->doubleclose('/(\S)\"(?=\s|'.$this->patterns->pnc.'|<|$)/')
			->doubleopen('/"/')
			->abbr('/\b(['.$this->patterns->abr.']['.$this->patterns->acr.']{2,})\b(?:[(]([^)]*)[)])/'.$this->patterns->mod)
			->caps('/(?<=\s|^|[>(;-])(['.$this->patterns->abr.']{3,})(['.$this->patterns->nab.']*)(?=\s|'.$this->patterns->pnc.'|<|$)(?=[^">]*?(<|$))/'.$this->patterns->mod )
		  ->ellipsis('/([^.]?)\.{3}/')
		  ->emdash('/(\s?)--(\s?)/')
			->endash('/\s-(?:\s|$)/')
			->dimension('/(\d+)( ?)x( ?)(?=\d+)/')
			->trademark('/(\b ?|\s|^)[([]TM[])]/i')
			->registered('/(\b ?|\s|^)[([]R[])]/i')
			->copyright('/(\b ?|\s|^)[([]C[])]/i')
			->quarter('/[([]1\/4[])]/')
			->half('/[([]1\/2[])]/')
			->threequarters('/[([]3\/4[])]/')
			->degrees('/[([]o[])]/')
			->plusminus('/[([]\+\/-[])]/')
			;

		# Load the generator config...
		$generator = "./generators/$type.php";	# TODO allow location to be redefined.
		include_once( $generator );
		$this->output_generator = new TextileOutputGenerator( $this );

		$this->span_depth = 0;
		$this->max_span_depth = 5;	# TODO make this configurable

#		$this->glyphs->dump();
	}


	/**
   *	Span parser.
	 *  Public to allow output generators to recursively span content. eg. for *_abc_* etc.
	 */
	public function ParseSpans($text)	
	{
		$this->span_depth++;

		static $subs = null;
		static $pnct = null;
		if( null === $subs ) {
		  $pnct = ".,\"'?!;:‹›«»„“”‚‘’";
			$subs = array( '-'=>'\-', '_'=>'\_', '*'=>'\*', '^'=>'\^', '+'=>'\+', '?'=>'\?', '/'=>'\/', '['=>'\[', ']'=>'\]', '('=>'\(', ')'=>'\)', '{'=>'\{', '}'=>'\}' );
		}

		if( $this->span_depth <= $this->max_span_depth )
		{
		  $spans = new TextileSpanIterator( $this->spans ); 
			foreach($spans as $span)
			{
				$open  = strtr( $span->open,  $subs );
				$close = strtr( $span->close, $subs );
				$this->current_span = $span->name;
				$text = preg_replace_callback("/
					(^|(?<=[\s>$pnct\(])|[{[])        # pre
					($open)(?!$open)                  # tag
					({$this->patterns->c})            # atts
					(?::(\S+))?                       # cite
					([^\s$close]+|\S.*?[^\s$close\n]) # content
					([$pnct]*)                        # end
					($close)													# closetag
					($|[\]}]|(?=[[:punct:]]{1,2}|\s|\))) # tail
				/x", array(&$this, "_FoundSpan"), $text);
			}
		}
		$this->span_depth--;
		return $text;
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
		self::validateString( $event, 'Invalid $event name supplied -- should be a non-empty string.' );
		self::validateCallable( $listener, 'Invalid $listener supplied -- not callable.' );
		$this->parse_listeners[$event][] = $listener;
	}

  /**
   *	Interfaces for setting up Glyph & Span rules.
	 * What is a glyph and what is a span?
	 */
	public function DefineGlyph( $name, $pattern )
	{
		self::validateString($name, 'Invalid glyph $name supplied -- should be a non-empty string.' );
		self::validatestring($pattern, 'Invalid glyph $pattern supplied -- should be a non-empty string.' );
		$this->glyphs->add($name, $pattern);
	}

	public function RemoveGlyph( $name )
	{
		self::validateString($name, 'Invalid glyph $name supplied -- should be a non-empty string.' );
		$this->glyphs->remove($name);
	}

	/**
	 *	allows plugins to extend the standard set of textile spans...
	 */
	public function DefineSpan( $name, $openmarker, $closemarker = null )
	{
		if( null === $closemarker) 
			$closemarker = $openmarker;
		else
			self::validateString( $closemarker, 'invalid $closemarker given -- should be ommited, set to null or a non-empty string.' );

		$this->spans->addAsymmetricSpan( $name, $openmarker, $closemarker );
	}

	/**
	 *	Allows reverse lookup of span names from the open and (optionally) close markers.
	 */
	public function LookupSpanName( $open, $close=null )
	{
		return $this->spans->lookupSpanName($open, $close);
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

			if (preg_match("/([(]+)/", $matched, $pl)) {	# TODO: Add unit switching - pts/pixels/etc?
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


  # ===========================================================================
	#
	# Storage of parsed fragments / glyphs / urls / tags
	#
	# ===========================================================================
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

	# Allows correct glyphing around spans.
	public function StoreTags($opentag,$closetag='')
	{
		$key = ($this->tag_index++);

		$key = str_pad( (string)$key, 10, '0', STR_PAD_LEFT ); # $key must be of fixed length to allow proper matching in retrieveTags
		$this->tagCache[$key] = array('open'=>$opentag, 'close'=>$closetag);
		$tags = array(
			'open'  => "textileopentag{$key} ",
			'close' => " textileclosetag{$key}",
		);
		return $tags;
	}

	public function RetrieveTags($text)
	{
		$text = preg_replace_callback('/textileopentag([\d]{10}) /' , array(&$this, 'fRetrieveOpenTags'),  $text);
		$text = preg_replace_callback('/ textileclosetag([\d]{10})/', array(&$this, 'fRetrieveCloseTags'), $text);
		return $text;
	}

	public function fRetrieveOpenTags($m)
	{
		list(, $key ) = $m;
		return $this->tagCache[$key]['open'];
	}

	public function fRetrieveCloseTags($m)
	{
		list(, $key ) = $m;
		return $this->tagCache[$key]['close'];
	}

	public function ShelveURL($text)
	{
		if ('' === $text) return '';
		$ref = md5($text);
		$this->urlshelf[$ref] = $text;	# Unify the shelving mechanism for different types of fragment?
		return 'urlref:'.$ref;
	}

	public function RetrieveURLs($text)
	{
		return preg_replace_callback('/urlref:(\w{32})/',
			array(&$this, "retrieveURL"), $text);
	}

	public function retrieveURL($m)
	{
		$ref = $m[1];
		if (!isset($this->urlshelf[$ref]))
			return $ref;
		$url = $this->urlshelf[$ref];
		if (isset($this->urlrefs[$url]))
			$url = $this->urlrefs[$url];
		return $this->ConditionallyEncodeHTML($this->relURL($url));
	}

	public function relURL($url)
	{
		$parts = @parse_url(urldecode($url));
		if ((empty($parts['scheme']) or @$parts['scheme'] == 'http') and
			 empty($parts['host']) and
			 preg_match('/^\w/', @$parts['path']))
			$url = $this->hu.$url;
		if ($this->restricted and !empty($parts['scheme']) and
				!in_array($parts['scheme'], $this->url_schemes))
			return '#';
		return $url;
	}

	function isRelURL($url)
	{
		$parts = @parse_url($url);
		return (empty($parts['scheme']) and empty($parts['host']));
	}


  # ===========================================================================
	#
	# Footnote ID interface.
	#
  # ===========================================================================
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
		$out = strtr( $out, array( "\x00"=>'' ) );	# null bytes
		return $out;
	}


  protected function _ParseParagraph( $text )
	{
		if (!$this->lite) {
#			$text = $this->noTextile($text);
#			$text = $this->code($text);
		}

#		$text = $this->getRefs($text);
		$text = $this->_ParseLinks($text);
#		if (!$this->noimage)
#			$text = $this->image($text);

		if (!$this->lite) {
#			$text = $this->table($text);
#			$text = $this->lists($text);
		}

		$text = $this->ParseSpans($text);
		$text = $this->_ParseFootnoteRefs($text);
#		$text = $this->noteRef($text);
		$text = $this->ParseGlyphs($text);

		return rtrim($text, "\n");
	}

	protected function _ParseLinks($text)
	{
		return preg_replace_callback('/
			(^|(?<=[\s>.\(])|[{[]) # $pre
			"                      # start
			(' . $this->patterns->c . ')     # $atts
			([^"]+?)               # $text
			(?:\(([^)]+?)\)(?="))? # $title
			":
			('.$this->patterns->urlch.'+?)   # $url
			(\/)?                  # $slash
			([^\w\/;]*?)           # $post
			([\]}]|(?=\s|$|\)))
		/x', array(&$this, "_FoundLink"), $text);
	}


	public function _FoundLink( &$m)
	{
		$this->TriggerParseEvent( 'link:link', $m );
		$out = $this->TryOutputHandler('LinkHandler', $m);
		if( false === $out )
		  $out = $m[0];
		return $out;
	}


	public function ParseGlyphs($text)
	{
		// fix: hackish -- adds a space if final char of text is a double quote.
#		$text = preg_replace('/"\z/', "\" ", $text);

		#
		#   We don't want to do glyph replacements inside any HTML tags that might be in the source text
		# so split on <tag...> boundaries to give a sequence of text <tag> text <tag> fragments...
		#
		$text = preg_split("@(<[\w/!?].*>)@Us", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		$i = 0;
		$glyphs = $this->glyphs->getData();
		foreach($text as $line) {
			if (++$i % 2) {
				// raw < > & chars are already entity encoded in restricted mode
				if (!$this->restricted) {
					$line = preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $line);
					$line = strtr($line, array('<' => '&lt;', '>' => '&gt;'));
				}

				foreach( $glyphs as $name => $regex ) {
//$this->dump("Doing glyph[$name] with regex[$regex].");
					$this->current_glyph = $name;
	        $line = preg_replace_callback( $regex, array(&$this, "_FoundGlyph"), $line );
				}
			}
			$glyph_out[] = $line;
		}
		return join('', $glyph_out);
		
		return $text;
	}

	protected function _FoundGlyph( $m )
	{
		$this->TriggerParseEvent( 'glyph:' . $this->current_glyph, $m );
		$handler = 'TextileOutputGenerator::'.$this->current_glyph.'_GlyphHandler';
		if( is_callable( $handler ) )
			return call_user_func( $handler, $this->current_glyph, $m );
		elseif( is_callable('TextileOutputGenerator::default_GlyphHandler') )
			return call_user_func( 'TextileOutputGenerator::default_GlyphHandler' , $this->current_glyph, $m );
		else
			return $m[0];
	}


	protected function _FoundSpan( $m )
	{
		$this->TriggerParseEvent( 'span:' . $this->current_span, $m );
		$handler = 'TextileOutputGenerator::'.$this->current_span.'_SpanHandler';
		if( is_callable( $handler ) )
			return call_user_func( $handler, $this->current_span, $m );
		elseif( is_callable('TextileOutputGenerator::default_SpanHandler') )
			return call_user_func( 'TextileOutputGenerator::default_SpanHandler' , $this->current_span, $m );
		else
			return $m[0];
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
				list($o1, $o2, $content, $c2, $c1, $eat) = $this->_FoundBlock(array(0,$tag,$atts,$ext,$cite,$graf));

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
					list($o1, $o2, $content, $c2, $c1, $eat) = $this->_FoundBlock(array(0,$tag,$atts,$ext,$cite,$block));
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

			$this->TryOutputHandler( 'TidyLineBreaks', $block );

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


	protected function TryOutputHandler( $name, &$in )
	{
		$this->TriggerParseEvent( $name, $in );
		$name = "TextileOutputGenerator::$name";
		if( !is_callable( $name ) )
		  return false;

		return call_user_func( $name, $in );
	}


	public function TextileThis( $text, $lite = '', $encode = '', $noimage = '', $strict = '', $rel = '' )
	{
		$this->lite       = $lite;
		$this->encode     = $encode;
		$this->noimage    = $noimage;
		$this->strict     = $strict;
		$this->rel        = ($rel) ? ' rel="'.$rel.'"' : '';
		$this->restricted = false;

		$this->tag_index = 1;

		#
		#	TODO determine if this is dead code -- Is 'encode' mode used anywhere?
		#
		if ($encode) {
			$text = preg_replace("/&(?![#a-z0-9]+;)/i", "x%x%", $text);
			$text = str_replace("x%x%", "&amp;", $text);
			return $text;
		}

		# Do standard textile initialisation...
		$this->TryOutputHandler( 'Initials', $text );

		if( !$strict )
			$text = $this->CleanWhiteSpace( $text );

		#
		#	Setup the standard block handlers...
		#
		$this->blocktags = array('h[1-6]', 'p', 'notextile', 'pre', '###', 'fn\d+', 'hr', 'bq', 'bc' );

		#
		#	Start parsing...
		#
		if( !$lite ) {
			$text = $this->_ParseBlocks( $text );
		}

		#
		#	Replacement time...
		#
		$text = $this->RetrieveFragment($text);
		$text = preg_replace('/glyph:([^<]+)/','$1',$text);	# Replace the glyph marker -- this was added for 2.2 to allow caps spans in table cells. Might be better to fix the table stuff!
		$text = $this->retrieveTags($text);
		$text = $this->RetrieveURLs($text);

		$this->span_depth = 0;

		$this->TryOutputHandler( 'Finals', $text );

		return $text;
	}


	protected function _FoundBlock($m)
	{
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
			list( $o1, $o2, $content, $c2, $c1, $eat ) = call_user_func( "TextileOutputGenerator::{$roottag}_BlockHandler", $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat );
		}

	  #
		# Finally, pass control to the default output handler...
		#
		else 
			list( $o1, $o2, $content, $c2, $c1, $eat ) = call_user_func( 'TextileOutputGenerator::default_BlockHandler', $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat );

		$content = (!$eat) ? $this->_ParseParagraph($content) : '';
		return array($o1, $o2, $content, $c2, $c1, $eat);
	}


	/**
	 * Sends parse event notifications to all registered listeners.
	 */
	protected function TriggerParseEvent( $name )
	{
		$listeners = @$this->parse_listeners[$name];
		if( !empty($listeners) ) {
			foreach( $listeners as $listener ) {
  			$args = func_get_args();
	  		call_user_func( $listener, $args );
			}
		}
		$listeners = $this->parse_listeners['*'];	# Send the event to any 'global' event listeners...
		if( !empty($listeners) ) {
			foreach( $listeners as $listener ) {
				$args = func_get_args();
				call_user_func( $listener, $args );
			}
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


}

#eof
