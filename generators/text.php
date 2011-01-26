<?php

class TextileOutputGenerator
{
	static protected $controller = null;

	public function __construct( $controller )
	{
		self::$controller = $controller;
	}


	static public function default_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		if( $tag === '###' ) 
			return array( '', '', '', '', '', true );

#echo $content,"\n\n";
    return array($o1, $o2, $content, $c2, $c1, $eat);
	}



	static public function bq_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		$content = "  Quote: \"$content\" $att\n";
#echo $content,"\n";
    return array($o1, $o2, $content, $c2, $c1, $eat);
	}


	static public function fn_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		if (preg_match("/fn(\d+)/", $tag, $fns)) {
		  $fnn = sprintf( '% 2s', $fns[1] );
			$content = "Footnote $fnn: $content";
#echo $content,"\n";
    }
    return array($o1, $o2, $content, $c2, $c1, $eat);
	}


	static public function hr_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
  {
	  $o1 = $o2 = $c2 = $c1 = '';
		$content = str_repeat('_', 40);
#echo $content,"\n";
    return array($o1, $o2, $content, $c2, $c1, $eat);
  }


	static public function h_BlockHandler( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
  {
		$headings = array('h1' => '=', 'h2' => '-', 'h3' => '~', 'h4' => '', 'h5' => '', 'h6'=>'' );
    if( array_key_exists( $tag, $headings ) ) {
		  $o1 = $o2 = $c2 = $c1 = '';
			$content = trim($content);
			$underline = str_repeat( $headings[$tag], strlen($content) );
			if( strlen( $underline ) )
				$content = $content."\n".$underline;
    }
#echo $content,"\n";
    return array($o1, $o2, $content, $c2, $c1, $eat);
  }

}

