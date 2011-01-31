<?php

/**
 * @copyright Copyright (c) Jdlx 2011, https://github.com/jdlx.
 * @copyright Copyright (c) netcarver 2011, https://github.com/netcarver
 * @license 3 clause BSD.
 *
 * Adds support for HTML horizontal rule via hr. blocks.
 *
 * Examples...
 * HTML output generation gives...
 *     hr. -> <hr />
 *     hr(class). Rule title. -> <hr class="class" title="Rule title." />
 * Text output generation gives...
 *     hr. -> ______________________________________________________________
 *     (attributes and titles are not output)
 */

function hr_TextplugInit( Textile &$parser )
{
	$oh = new hr_OutputGenerator( $parser );
	$parser->RegisterBlockHandler( 'hr', array(&$oh, 'hr') );
}

/**
 * 
 **/
class hr_OutputGenerator
{
	protected	$parser;
	function __construct(Textile &$parser)
	{
		$this->parser = $parser;
	}

	public function hr( $tag, $att, $atts, $ext, $cite, $o1, $o2, $content, $c2, $c1, $eat )
	{
		switch( $this->parser->GetType() ) {
			case 'text' :
				$content = str_repeat( '-', 60 );
				break;

			case 'html' :
			default			:
				$o1 = "<hr$atts";
				$c1 = ' />';
				$o2 = $c2 = '';
				$content = rtrim( $content );
				if( $content !== '' )
				{
					$o2 = ' title="';
					$c2 = '"';
					$content = $this->parser->ShelveFragment($this->parser->ConditionallyEncodeHTML($content));
				}
				break;
		}


		return array($o1, $o2, $content, $c2, $c1, $eat);
	}

}

#eof
