<?php

@define('alpaca_quote_single_open',  '&#8216;');
@define('alpaca_quote_single_close', '&#8217;');
@define('alpaca_quote_double_open',  '&#8220;');
@define('alpaca_quote_double_close', '&#8221;');
@define('alpaca_apostrophe',         '&#8217;');
@define('alpaca_prime',              '&#8242;');
@define('alpaca_prime_double',       '&#8243;');
@define('alpaca_ellipsis',           '&#8230;');
@define('alpaca_emdash',             '&#8212;');
@define('alpaca_endash',             '&#8211;');
@define('alpaca_dimension',          '&#215;');
@define('alpaca_trademark',          '&#8482;');
@define('alpaca_registered',         '&#174;');
@define('alpaca_copyright',          '&#169;');
@define('alpaca_half',               '&#189;');
@define('alpaca_quarter',            '&#188;');
@define('alpaca_threequarters',      '&#190;');
@define('alpaca_degrees',            '&#176;');
@define('alpaca_plusminus',          '&#177;');

/**
 * Exceptions.
 * Alpaca can throw a few exceptions.
 */
class AlpacaUnexpectedException extends Exception {}
class AlpacaProgrammerException extends AlpacaUnexpectedException {}	# Thrown for incorrect setup which needs to fail early and loud.


/**
 *	Common base class with some common behaviour.
 */
abstract class AlpacaObject
{
	static public function validateString($s, $msg)
	{
		if(!is_string($s) || empty($s)) {
			if(!is_string($msg) || empty($msg))
				$msg = 'Parameter must be a non-empty string.';
			throw new AlpacaProgrammerException($msg);
		}
	}

	static public function validateExists($arg, $msg)
	{
		if(!isset($arg)) {
			if( !is_string($msg) || empty($msg) )
				$msg = 'Please supply an argument.';
			throw new AlpacaProgrammerException($msg);
		}
	}

	static public function validateCallable($function, $msg)
	{
		if( !is_callable($function) ) {
			if( !is_string($msg) || empty($msg) )
				$msg = 'Please supply a callable function.';
			throw new AlpacaProgrammerException($msg);
		}
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
class AlpacaDataBag extends AlpacaObject
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
class AlpacaSpan
{
	protected $data;

	public function __construct( $name, $open, $close )
	{
		$this->data['name']    = $name;
		$this->data['open']    = $open;
		$this->data['close']   = $close;
	}
	public function __get($key)           { return $this->data[$key]; }
		public function getKey($open,$close)  { if( ($open === $this->data['open']) && ($close === $this->data['close']) ) return $this->data['name']; else return false; }
}


/**
 *	Simple class to hold a set of Spans.
 */
class AlpacaSpanSet extends AlpacaObject
{
	protected $enabled;
	protected $disabled;
	protected $label;

	public function __construct($label)
	{
		self::validateString($label, 'Invalid SpanSets $label -- must be a non-empty string');
		$this->label    = $label;
		$this->enabled  = array();
		$this->disabled = array();
	}
	public function getData()
	{
		return $this->enabled;
	}
	public function disable($name)
	{
		self::validateString($name, 'Invalid span $name -- must be a non-empty string');
		if( empty($this->enabled) )
			return $this;
		foreach ($this->enabled as $key=>$span) {
			if ($span->name === $name) {
				$this->disabled[$key] = $this->enabled[$key];
				unset($this->enabled[$key]);
			}
		}
		ksort($this->disabled);	# ksorts needed to restore original order after transfer (in which keys are preserved but order isn't)
		return $this;
	}
	public function enable($name)
	{
		self::validateString($name, 'Invalid span $name -- must be a non-empty string');
		if( empty($this->disabled) )
			return $this;
		foreach ($this->disabled as $key=>$span) {
			if ($span->name === $name) {
				$this->enabled[$key] = $this->disabled[$key];
				unset($this->disabled[$key]);
			}
		}
		ksort($this->enabled);
		return $this;
	}
	public function addAsymmetricSpan( $name, $open, $close )
	{
		self::validateString($name,  'Invalid span $name -- must be non-empty string');
		self::validateString($open,  'Invalid span $open -- must be non-empty string');
		self::validateString($close, 'Invalid span $close -- must be non-empty string');

		$this->enabled[] = new AlpacaSpan( $name, $open, $close );
		return $this;
	}
	public function lookupSpanName($open,$close=null) # TODO fix this to work with span objects...
	{
		if( null === $close ) $close = $open;
		$key = false;
		foreach( $this->enabled as $entry ) {
			if( ($entry['open'] == $open) && ($entry['close'] == $close) )
				return $entry['name'];
		}
		return $key;
	}
	public function __call($name, $args)
	{
		return $this->addAsymmetricSpan( $name, @$args[0], ( (isset($args[1]))? $args[1] : @$args[0]) );
	}
	public function dump()
	{
		parent::dump( "=== Data for {$this->label}... ===" , "Enabled..." , $this->enabled , "Disabled..." , $this->disabled );
		return $this;
	}
}

/**
 *	Iterator for spans -- uses a copy of the span set's data to iterate over as it's used in a recursive context.
 */
class AlpacaSpanIterator implements Iterator
{
	protected $position = 0;
	protected $array;

	public function __construct( AlpacaSpanSet &$set ) { $this->array = $set->getData(); }
	public function rewind()    { $this->position = 0; }
	public function current()   { return $this->array[$this->position]; }
	public function key()       { return $this->position; }
	public function next()      { ++$this->position; }
	public function valid()     { return isset($this->array[$this->position]); }
}


/**
 *
 */
class Textile extends AlpacaObject
{
	protected $output_type      = null;
	protected $output_generator = null;
	protected $parse_listeners  = array();	# DB of listeners to parse events.
	protected $block_handlers   = array();	# DB of registered textplug callbacks
	protected $glyphs           = null;			# standard-textile glyph markers
	protected $regex            = null;			# standard-textile regex patterns
	protected $spans            = null;			# standard-textile span start/end markers
	protected $blocktags        = array();
	protected $restricted       = true;			# Alpaca runs in restricted mode unless invoked via 'TextileThis()'
	protected $span_depth;
	protected $max_span_depth;
	protected $fragments				= array();	# Stores completed output fragments for latter stitching back together.
	protected $hu;
	protected $url_schemes;
	protected $ds;
	protected $doc_root;


	/**
	 *
	 */
	public function __construct( $type = 'html' )
	{
		$this->output_type = $type;
		$this->hu = (defined('hu')) ? hu : '';
		$this->url_schemes = array('http','https','ftp','mailto');

		if (defined('DIRECTORY_SEPARATOR'))
			$this->ds = constant('DIRECTORY_SEPARATOR');
		else
			$this->ds = '/';

		@define('txt_plugin_directory', dirname(__FILE__) . $this->ds . 'textplugs-enabled');

		$this->doc_root = @$_SERVER['DOCUMENT_ROOT'];
		if (!$this->doc_root)
			$this->doc_root = @$_SERVER['PATH_TRANSLATED']; // IIS
		$this->doc_root = rtrim($this->doc_root, $this->ds).$this->ds;

		@define('alpaca_has_unicode', @preg_match('/\pL/u', 'a')); // Detect if Unicode is compiled into PCRE

		$this->regex = new AlpacaDataBag('General regex patterns');
		if( alpaca_has_unicode ) {
			$this->regex
				->acr('\p{Lu}\p{Nd}')
				->abr('\p{Lu}')
				->nab('\p{Ll}')
				->wrd('(?:\p{L}|\p{M}|\p{N}|\p{Pc})')
				->mod('u');
		} else {
			$this->regex
				->acr('A-Z0-9')
				->abr('A-Z')
				->nab('a-z')
				->wrd('\w')
				->mod('');
		}
		$this->regex
			->hlgn("(?:\<(?!>)|(?<!<)\>|\<\>|\=|[()]+(?! ))")
			->vlgn("[\-^~]")
			->clas("(?:\([^)\n]+\))")		# Don't allow classes/ids/languages/styles to span across newlines
			->lnge("(?:\[[^]\n]+\])")
			->styl("(?:\{[^}\n]+\})")
			->cspn("(?:\\\\\d+)")
			->rspn("(?:\/\d+)")
			->a("(?:{$this->regex->hlgn}|{$this->regex->vlgn})*")
			->s("(?:{$this->regex->cspn}|{$this->regex->rspn})*")
			->c("(?:{$this->regex->clas}|{$this->regex->styl}|{$this->regex->lnge}|{$this->regex->hlgn})*")
			->lc("(?:{$this->regex->clas}|{$this->regex->styl}|{$this->regex->lnge})*")
			->urlch('[\w"$\-_.+!*\'(),";\/?:@=&%#{}|\\^~\[\]`]')
			->pnc('[[:punct:]]')
			#->dump()
			;

		/**
		 *	By default, the standard textile spans are now *named* after the HTML tag that should be emmitted for them.
		 */
		$this->spans = new AlpacaSpanSet('Spans');
		$this->spans	# TODO make sure each of these spans is covered in the test cases.
			->verbatim('==')
			->verbatim('<notextile>', '</notextile>')
			->code('@')
			->code('<code>','</code>')
			->pre('<pre>','</pre>')
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
			->disable('verbatim')	# default disabled for lite mode (!lite mode will enable these)
			->disable('code')		# ditto...
			->disable('pre')		# ditto... TODO (check this)
			//->dump()
			;

		$this->glyphs = new AlpacaDataBag('Glyph match patterns');
		$this->glyphs
			->apostrophe('/('.$this->regex->wrd.')\'('.$this->regex->wrd.')/'.$this->regex->mod)
			->initapostrophe('/(\s)\'(\d+'.$this->regex->wrd.'?)\b(?![.]?['.$this->regex->wrd.']*?\')/'.$this->regex->mod)
			->singleclose('/(\S)\'(?=\s|'.$this->regex->pnc.'|<|$)/')
			->singleopen('/\'/')
			->doubleclose('/(\S)\"(?=\s|'.$this->regex->pnc.'|<|$)/')
			->doubleopen('/"/')
			->abbr('/\b(['.$this->regex->abr.']['.$this->regex->acr.']{2,})\b(?:[(]([^)]*)[)])/'.$this->regex->mod)
			->caps('/(?<=\s|^|[>(;-])(['.$this->regex->abr.']{3,})(['.$this->regex->nab.']*)(?=\s|'.$this->regex->pnc.'|<|$)(?=[^">]*?(<|$))/'.$this->regex->mod )
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
		$this->output_generator = new AlpacaOutputGenerator( $this );

		$this->span_depth = 0;
		$this->max_span_depth = 5;	# TODO make this configurable

		#		$this->glyphs->dump();
	}


	/**
	 *	Loads all textile plugins of the given type in non-lite modes. Plugins are loaded from the textplug directory.
	 */
	protected function LoadTextplugs( $quiet=true )
	{
		if( $this->lite )
			return;

		$cwd = getcwd();
		$textplugs = txt_plugin_directory;

		if (!is_dir($textplugs)) {
			if(!$quiet)
				throw new AlpacaProgrammerException ( "Textplug location '{$textplugs}' is not a directory (or doesn't exist.)" );
			else
				return;
		}
		elseif( !is_readable($textplugs)) {
			if(!$quiet)
				throw new AlpacaProgrammerException ( "Textplug directory '{$textplugs}' is not readable." );
			else
				return;
		}

		if( !chdir($textplugs) ) {
			if(!$quiet)
				throw new AlpacaProgrammerException( "Couldn't chdir to '{$textplugs}'." );
			return;
		}

		if(!$quiet) $this->dump("Textplug directory '$textplugs'");

		foreach (glob("*.textplug.php") as $textplug) {
			if( is_dir($textplug) )
				continue;

			if(!$quiet) $this->dump( "Loading textplug '$textplug'..." );
			$current_config = include_once($textplug);

			$init_function = strtr( $textplug, array( 'textplug.php'=>'TextplugInit', '.'=>'_' ) );
			if( is_callable( $init_function ) ) {
				call_user_func( $init_function, $this );
			} else {
				throw new AlpacaProgrammerException( "Textplugs must provide a callable TextplugInit() method. '$init_function' could not be called." );
			}
		}

		chdir($cwd);
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
			$spans = new AlpacaSpanIterator( $this->spans );
			foreach($spans as $span)
			{
				$open  = strtr( $span->open,  $subs );
				$close = strtr( $span->close, $subs );
				$this->current_span[] = $span->name;
				$regex = "/
					(^|(?<=[\s>$pnct\(])|[{[])        # pre
					($open)(?!$open)                  # tag
					({$this->regex->c})           	  # atts
					(?::(\S+))?                       # cite
					([^\s$close]+|\S.*?[^\s$close\n]) # content
					([$pnct]*)                        # end
					($close)													# closetag
					($|[\]}]|(?=[[:punct:]]{1,2}|\s|\))) # tail
					/x";
				#$this->dump( "Looking for {$span->name} span matching...", $regex );
				$text = preg_replace_callback( $regex, array(&$this, "_FoundSpan"), $text);
				array_pop( $this->current_span );
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
		return $this;
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
		return $this;
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
		return $this;
	}

	/**
	 *	Allows reverse lookup of span names from the open and (optionally) close markers.
	 */
	public function LookupSpanName( $open, $close=null )
	{
		return $this->spans->lookupSpanName($open, $close);
	}

	public function GetType()
	{
		return $this->output_type;
	}

	public function GetGenerator()
	{
		return $this->output_generator;
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
		$i      = 0;
		$max    = 3;
		while( ($after != $before) && ($i < $max) )
		{
			$before = strlen( $tmp );
			$tmp    = rawurldecode($tmp);
			$after  = strlen( $tmp );
			$i++;
		}

		if( $i === $max )
			$out = '';
		else
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
			if ($element == 'td') {	# TODO make element abstract
				if (preg_match("/\\\\(\d+)/", $matched, $csp)) $colspan = $csp[1];
				if (preg_match("/\/(\d+)/", $matched, $rsp)) $rowspan = $rsp[1];
			}

			if ($element == 'td' or $element == 'tr') { # TODO make these elements abstract
				if (preg_match("/({$this->regex->vlgn})/", $matched, $vert))
					$style[] = "vertical-align:" . $this->VerticalAlign($vert[1]);	# TODO no coverage of vAlign in current test cases.
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
			if (preg_match("/\(([-a-zA-Z 0-9_\.\:\#]+)\)/U", $matched, $cls)) {
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

			if (preg_match("/({$this->regex->hlgn})/", $matched, $horiz))
				$style[] = "text-align:" . $this->HorizontalAlign($horiz[1]);

			# If a textile class block attribute was found, split it into the css class and css id (if any)...
			if (preg_match("/^([-a-z A-Z0-9_]*)#([-a-zA-Z0-9_\.\:]*)$/", $class, $ids)) {
				$id = $ids[2];
				$class = $ids[1];
			}

			if ($element == 'col') { # TODO make element abstract
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

			return join('',array(		# TODO move this to generator
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
			return str_replace( '"', '&quot;', $str );
		return $this->EncodeHTML($str, $quotes);
	}
	function CleanWhiteSpace($text)
	{
		$out = preg_replace("/^\xEF\xBB\xBF|\x1A/", '', $text); # Byte order mark (if present)
		$out = preg_replace("/\r\n?/", "\n", $out); # DOS and MAC line endings to *NIX style endings
		$out = preg_replace("/^[ \t]*\n/m", "\n", $out);	# lines containing only whitespace
		$out = preg_replace("/\n{3,}/", "\n\n", $out);	# 3 or more line ends
		$out = preg_replace("/^\n*/", "", $out);		# leading blank lines
		//		$out = strtr( $out, array( "\x00"=>'' ) );	# null bytes
		return $out;
	}


	protected function _ParseParagraph( $text )
	{
		#
		#	The following non-lite block is now taken care of by the span parser. TODO: Determine if this is sufficient.
		#
		#		if (!$this->lite) {
		#			$text = $this->noTextile($text);
		#			$text = $this->code($text);
		#		}

		$text = $this->_ParseSharedLinkRefs($text);	# TODO move into _ParseLinks()?
		$text = $this->_ParseLinks($text);
		if (!$this->noimage)
			$text = $this->parseImages($text);

		if (!$this->lite) {
			$text = $this->parseTables($text);
			$text = $this->parseLists($text);
		}

		$text = $this->ParseSpans($text);
		$text = $this->_ParseFootnoteRefs($text);
		#		$text = $this->noteRef($text); # TODO -- add NoteList parsing & generation
		$text = $this->ParseGlyphs($text);

		return rtrim($text, "\n");
	}


	# Reads the foot-note like [name]http://url declarations of shared URLs.
	public function _ParseSharedLinkRefs($text)
	{
		return preg_replace_callback("/^\[(.+)\]((?:http:\/\/|\/)\S+)(?=\s|$)/Um",
			array(&$this, "_storeRef"), $text);
	}
	public function _storeRef($m)
	{
		$this->TriggerParseEvent( 'url:shared', $m );
		list(, $flag, $url) = $m;
		$this->urlrefs[$flag] = $url;
		return '';
	}

	#
	# Table handling...
	#
	public function parseTables($text)
	{
		$text = $text . "\n\n";
		return preg_replace_callback("/^(?:table(_?{$this->regex->s}{$this->regex->a}{$this->regex->c})\.(.*)?\n)?^({$this->regex->a}{$this->regex->c}\.? ?\|.*\|)[\s]*\n\n/smU",
			array(&$this, "_foundTable"), $text);
	}

	public function _foundTable( $matches )
	{
		$args['atts'] = $tatts = $this->ParseBlockAttributes($matches[1], 'table');
		$args['summary'] = $sum = trim($matches[2]) ? ' summary="'.htmlspecialchars(trim($matches[2])).'"' : '';
		$args['caption'] = '';
		$args['colgroup'] = '';

		$this->TriggerParseEvent( 'table:new', $matches );
		$table['start'] = $this->TryOutputHandler( 'TableStartHandler' , $args );
		$table['caption'] = '';
		$table['colgroup'] = '';
		$table['end'] = '';

		$colgrp = $last_rgrp = '';
		$c_row = 1;

		foreach(preg_split("/\|\s*?$/m", $matches[3], -1, PREG_SPLIT_NO_EMPTY) as $row)
		{
			$row = ltrim($row);

			#
			# Caption -- can only occur on row 1, otherwise treat '|=. foo |...' as a normal center-aligned cell.
			#
			if ( ($c_row <= 1) && preg_match("/^\|\=({$this->regex->s}{$this->regex->a}{$this->regex->c})\. ([^\n]*)(.*)/s", ltrim($row), $cmtch))
			{
				$args['caption_atts'] = $this->ParseBlockAttributes($cmtch[1]);
				$args['caption'] = $cmtch[2];
				$table['caption'] = $cap = $this->TryOutputHandler( "TableCaptionHandler", $args );
				$this->TriggerParseEvent( 'table:caption' );
				$row = ltrim($cmtch[3]);
				if( empty($row) )
					continue;
			}
			$c_row += 1;

			#
			# Colgroup
			#
			if (preg_match("/^\|:({$this->regex->s}{$this->regex->a}{$this->regex->c}\. .*)/m", ltrim($row), $gmtch))
			{
				$nl = strpos($row,"\n");	# Is this colgroup def missing a closing pipe? If so, there will be a newline in the middle of $row somewhere.
				foreach (explode('|', str_replace('.', '', $gmtch[1])) as $col)
				{
					$args['gatts'][] = $this->ParseBlockAttributes(trim($col), 'col');
				}
				$table['colgroup'] = $this->TryOutputHandler( 'TableColgroupHandler', $args );

				if($nl === false)
					continue;
				else
					$row = ltrim(substr( $row, $nl ));		# Recover from our missing pipe and process the rest of the line...
			}

			preg_match("/(:?^\|({$this->regex->vlgn})({$this->regex->s}{$this->regex->a}{$this->regex->c})\.\s*$\n)?^(.*)/sm", ltrim($row), $grpmatch);

			#
			# Row group
			#
			static $rowgroup_mappings = array( '^'=>'head', '~'=>'foot', '-'=>'body' );
			$rgrp = '';
			if( isset($grpmatch[2]) && array_key_exists($grpmatch[2], $rowgroup_mappings) )
				$rgrp = $rowgroup_mappings[ $grpmatch[2] ];

			#			$rgrp = isset($grpmatch[2]) ? (($grpmatch[2] == '^') ? 'head' : ( ($grpmatch[2] == '~') ? 'foot' : (($grpmatch[2] == '-') ? 'body' : '' ) ) ) : '';
			$rgrpatts = isset($grpmatch[3]) ? $this->ParseBlockAttributes($grpmatch[3]) : '';
			$row = $grpmatch[4];

			if (preg_match("/^({$this->regex->a}{$this->regex->c}\. )(.*)/m", ltrim($row), $rmtch))
			{
				$ratts = $this->ParseBlockAttributes($rmtch[1], 'tr');
				$row = $rmtch[2];
			}
			else
				$ratts = '';

			$cells = array();
			$cellctr = 0;
			foreach(explode("|", $row) as $cell)
			{
				$ctyp = "d";
				if (preg_match("/^_/", $cell)) $ctyp = "h";
				if (preg_match("/^(_?{$this->regex->s}{$this->regex->a}{$this->regex->c}\. )(.*)/", $cell, $cmtch))
				{
					$catts = $this->ParseBlockAttributes($cmtch[1], 'td');
					$cell = $cmtch[2];
				} else $catts = '';

				$cell = $this->_ParseParagraph($cell);

				if ($cellctr>0) // Ignore first 'cell': it precedes the opening pipe
					$cells[] = $this->doTagBr("t$ctyp", "\t\t\t<t$ctyp$catts>$cell</t$ctyp>");

				$cellctr++;
			}

			$grp = (($rgrp && $last_rgrp) ? "\t</t".$last_rgrp.">\n" : '') . (($rgrp) ? "\t<t".$rgrp.$rgrpatts.">\n" : '');
			$last_rgrp = ($rgrp) ? $rgrp : $last_rgrp;

			$rows[] = $grp."\t\t<tr$ratts>\n" . join("\n", $cells) . ($cells ? "\n" : "") . "\t\t</tr>";
			unset($cells, $catts);
		}

		#
		# Assemble the rows...
		#
		$table['rows'] = join( "\n", $rows )."\n";
		if( $last_rgrp )
			$table['rows'] .= "\t</t".$last_rgrp.">\n";

		$this->TriggerParseEvent( 'table:done' );
		$table['end'] = $this->TryOutputHandler( 'TableEndHandler', $args );
		#		$this->dump( $table );

		return $table['start'] . $table['caption'] . $table['colgroup'] . $table['rows'] . $table['end'];
	}

	#
	# List handling...
	#
	public function parseLists($text)
	{
		return preg_replace_callback("/^({$this->regex->lc})?([#*;:]{$this->regex->lc}[ .].*)$(?![^#*;:])/sU", array(&$this, "_foundList"), $text);
	}

	function getListType($in) # Todo use internal list type rather than HTML?
	{
		return preg_match("/^#+/", $in) ? 'o' : ((preg_match("/^\*+/", $in)) ? 'u' : 'd');
	}


	function doTagBr($tag, $in)  # TODO move this into the generator...
	{
		return preg_replace_callback('@<('.preg_quote($tag).')([^>]*?)>(.*)(</\1>)@s', array(&$this, 'fBr'), $in);
	}
	function fBr($m)
	{
		$content = preg_replace("@(.+)(?<!<br>|<br />)\n(?![#*;:\s|])@", '$1<br />'."\n", $m[3]);
		return '<'.$m[1].$m[2].'>'.$content.$m[4];
	}

	public function _foundList($m)
	{
		$this->TriggerParseEvent( 'list:new', $m[0] );
		$text = preg_split('/\n(?=[*#;:])/m', $m[0]);
		$lastlist = '';
		foreach($text as $rownum => $line) {
			$nextline = isset($text[$rownum+1]) ? $text[$rownum+1] : false;
			if (preg_match("/^({$this->regex->lc})?([#*;:]+)({$this->regex->lc})[ .](.*)$/s", $line, $m)) {
				list(, $listatts, $thislist, $atts, $content) = $m;

				$outline = '';
				$info = array();
				$info['content'] = $content = trim($content);
				$atts = $this->ParseBlockAttributes($atts);
				$info['has_content'] = $has_content = (strlen($content) > 0);
				$is_redcloth = ((strlen($listatts) > 0) && $has_content);
				$info['listtype'] = $ltype = $this->getListType($thislist);
				$is_dlist = ('d' === $ltype);

				#
				# In the case of redcloth textile -- $listatts will have the list attributes.
				# In the case of php-textile 2.2+ -- $atts will have the list atts if the content is empty or it's a dl, else it will have the item atts (for ul+ol).
				#
				if ($is_redcloth)
					$listatts = $this->ParseBlockAttributes($listatts);
				elseif (!$has_content || $is_dlist )
					$listatts = $atts;
				$info['listatts'] = $listatts;

				$info['litem'] = $litem = (strpos($thislist, ';') !== false) ? 'dt' : ((strpos($thislist, ':') !== false) ? 'dd' : 'li'); # TODO : html

				$nextlist = '';
				if (preg_match("/^([#*;:]+)({$this->regex->lc})[ .].*/", $nextline, $nm))
					$nextlist = $nm[1];

				if ((strpos($lastlist, ';') !== false) && (strpos($thislist, ':') !== false)) {
					$lists[$thislist] = 2; // We're already in a <dl> so flag not to start another  # QUESTION: why the magic number?
				}

				if (!isset($lists[$thislist])) {
					$lists[$thislist] = 1;	# QUESTION: Why the magic number?

					if ($is_dlist)
						$atts = '';	# Grrr defn lists -- you apply first line atts to the list, not the item, even if not empty -- Grrr.

					$this->TriggerParseEvent( 'list:start', $info );
					$outline = $this->TryOutputHandler( 'ListStartHandler', $info );
				}

				if( $has_content )
				{
					$info['atts'] = $atts;
					$this->TriggerParseEvent( 'list:start-item', $info );
					$outline .= $this->TryOutputHandler( 'ListStartItemHandler', $info );
				}

				# This closes off the previous list item (if not empty and no increase in list nesting) list:end-item event...
				if((strlen($nextlist) <= strlen($thislist)))
				{
					$outline .= $this->TryOutputHandler( 'ListEndItemHandler', $info);
					$this->TriggerParseEvent( 'list:end-item', $info );
				}

				foreach(array_reverse($lists) as $k => $v) {
					if(strlen($k) > strlen($nextlist)) {
						# Close down one level of list nesting... list:end event...
						if( $v != 2 )
						{
							$outline .= $this->TryOutputHandler( 'ListEndHandler', $info);
							$this->TriggerParseEvent( 'list:end', $info );
						}
						if((strlen($k) > 1) && ($v != 2))
						{
							$outline .= $this->TryOutputHandler( 'ListEndItemHandler', $info);
							$this->TriggerParseEvent( 'list:end-item', $info );
						}
						unset($lists[$k]);
					}
				}
				$lastlist = $thislist; // Remember the current Textile tag
			}
			else {
				$outline .= "\n"; # Add a newline, this wasn't really a valid list.
			}
			$outlines[] = $outline;
		}

		$this->TriggerParseEvent( 'list:done' );
		return $this->doTagBr($litem, join("\n", $outlines));
	}


	public function parseImages($text)
	{
		return preg_replace_callback("/
			(?:[[{])?		            # pre
			\!				              # opening !
			(\<|\=|\>)? 	          # optional alignment atts
			({$this->regex->c})	# optional style,class atts
			(?:\. )?		            # optional dot-space
			([^\s(!]+)		          # presume this is the src
			\s? 			              # optional space
			(?:\(([^\)]+)\))?       # optional title
			\!				              # closing
			(?::(\S+))? 	          # optional href
			(?:[\]}]|(?=\s|$|\)))   # lookahead: space or end of string
			/x", array(&$this, "_foundImage"), $text);
	}

	public function _foundImage($m)
	{
		$this->TriggerParseEvent( 'image:image', $m );
		$out = $this->TryOutputHandler('ImageHandler', $m);
		if( false === $out )
			$out = $m[0];
		return $out;
	}


	protected function _ParseLinks($text)
	{
		return preg_replace_callback('/
			(^|(?<=[\s>.\(])|[{[]) # $pre
			"                      # start
			('.$this->regex->c.')     # $atts
			([^"]+?)               # $text
			(?:\(([^)]+?)\)(?="))? # $title
			":
			('.$this->regex->urlch.'+?)   # $url
			(\/)?                  # $slash
			([^\w\/;]*?)           # $post
			([\]}]|(?=\s|$|\)))
			/x', array(&$this, "_foundLink"), $text);
	}


	public function _foundLink( &$m)
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
		$handler = 'AlpacaOutputGenerator::'.$this->current_glyph.'_GlyphHandler';
		if( is_callable( $handler ) )
			return call_user_func( $handler, $this->current_glyph, $m );
		elseif( is_callable('AlpacaOutputGenerator::default_GlyphHandler') )
			return call_user_func( 'AlpacaOutputGenerator::default_GlyphHandler' , $this->current_glyph, $m );
		else
			return $m[0];
	}


	protected function _FoundSpan( $m )
	{
		$span_name = end($this->current_span);
		$this->TriggerParseEvent( 'span:' . $span_name, $m );
		$handler = 'AlpacaOutputGenerator::'.$span_name.'_SpanHandler';
		if( is_callable( $handler ) )
			return call_user_func( $handler, $span_name, $m );
		elseif( is_callable('AlpacaOutputGenerator::default_SpanHandler') )
			return call_user_func( 'AlpacaOutputGenerator::default_SpanHandler' , $span_name, $m );
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
		return call_user_func( 'AlpacaOutputGenerator::FootnoteIDHandler', $id, $nolink, $t );
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
			if (preg_match("/^($tre)({$this->regex->a}{$this->regex->c})\.(\.?)(?::(\S+))? (.*)$/s", $block, $m)) {

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

			$this->TriggerParseEvent( 'block:TidyLineBreaks', $block );
			$block = $this->TryOutputHandler( 'TidyLineBreaks', $block );

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
		# TODO This list needs to be expandable! This also has HTML in it -- so it shouldn't really be in the parser.
		// checks whether the text has text not already enclosed by a block tag
		$r = trim(preg_replace('@<(p|blockquote|div|form|table|ul|ol|dl|pre|h\d)[^>]*?'.chr(62).'.*</\1>@s', '', trim($text)));
		$r = trim(preg_replace('@<(hr|br)[^>]*?/>@', '', $r));
		return '' != $r;
	}


	protected function TryOutputHandler( $name, &$in )
	{
		$name = "AlpacaOutputGenerator::$name";
		if( !is_callable( $name ) )
			return false;	# TODO -- should this return $in if the output handler can't be found???

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
		$this->tag_index  = 1;
		$this->blocktags  = array('p','bq');

		#
		#	TODO determine if this is dead code -- Is 'encode' mode used anywhere?
		#
		if ($encode) {
			$text = preg_replace("/&(?![#a-z0-9]+;)/i", "x%x%", $text);
			$text = str_replace("x%x%", "&amp;", $text);
			return $text;
		}

		if (!$lite) {
			$this->spans
				->enable('verbatim')
				->enable('code')
				->enable('pre');
			$this->LoadTextplugs();
			$this->blocktags = array('h[1-6]', 'p', 'notextile', 'pre', '###', 'fn\d+', 'bq', 'bc' );
		}

		# Do standard textile initialisation...
		$this->TriggerParseEvent( 'doc:initials' );
		$this->TryOutputHandler( 'Initials', $text );

		if (!$strict)
			$text = $this->CleanWhiteSpace( $text );

		#
		#	Start parsing...
		#
		$text = $this->_ParseBlocks( $text );

		#
		#	Replacement time...
		#
		$text = $this->RetrieveFragment($text);
		$text = preg_replace('/glyph:([^<]+)/','$1',$text);	# Replace the glyph marker -- this was added for 2.2 to allow caps spans in table cells. Might be better to fix the table stuff!
		$text = $this->retrieveTags($text);
		$text = $this->RetrieveURLs($text);

		$this->TriggerParseEvent( 'doc:finals' );
		$this->TryOutputHandler( 'Finals', $text );

		if (!$lite) {
			$this->spans
				->disable('verbatim')
				->disable('code')
				->disable('pre');
		}
		$this->span_depth = 0;

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
		elseif( is_callable( "AlpacaOutputGenerator::{$roottag}_BlockHandler" ) ) {
			list( $o1, $o2, $content, $c2, $c1, $eat ) = call_user_func( "AlpacaOutputGenerator::{$roottag}_BlockHandler", $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat );
		}

		#
		# Finally, pass control to the default output handler...
		#
		else
			list( $o1, $o2, $content, $c2, $c1, $eat ) = call_user_func( 'AlpacaOutputGenerator::default_BlockHandler', $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat );

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
		$listeners = @$this->parse_listeners['*'];	# Send the event to any 'global' event listeners...
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

	public function ImageAlign($in)
	{
		$vals = array(
			'<' => 'left',
			'=' => 'center',
			'>' => 'right');
		return (isset($vals[$in])) ? $vals[$in] : '';
	}

	public function VerticalAlign($in)
	{
		$vals = array(
			'^' => 'top',
			'-' => 'middle',
			'~' => 'bottom');
		return (isset($vals[$in])) ? $vals[$in] : '';
	}


}

#eof
