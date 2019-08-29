<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:03
 */

namespace PaulBoenisch\RtfToHtml\Services;

use PaulBoenisch\RtfToHtml\Model\RtfControlSymbol;
use PaulBoenisch\RtfToHtml\Model\RtfControlWord;
use PaulBoenisch\RtfToHtml\Model\RtfFont;
use PaulBoenisch\RtfToHtml\Model\RtfGroup;
use PaulBoenisch\RtfToHtml\Model\RtfImage;
use PaulBoenisch\RtfToHtml\Model\RtfState;
use PaulBoenisch\RtfToHtml\Model\RtfText;


/**
 * Class RtfHtml
 * @package PaulBoenisch\RtfToHtml\Model
 */
class RtfHtml {
	private $output = '';
	private $encoding;
	private $defaultFont;

	// Initialise Encoding
	public function __construct( $encoding = 'HTML-ENTITIES' ) {
		if ( $encoding != 'HTML-ENTITIES' ) {
			// Check if mbstring extension is loaded
			if ( ! extension_loaded( 'mbstring' ) ) {
				trigger_error( "PHP mbstring extension not enabled, reverting back to HTML-ENTITIES" );
				$encoding = 'HTML-ENTITIES';
				// Check if the encoding is reconized by mbstring extension
			} elseif ( ! in_array( $encoding, mb_list_encodings() ) ) {
				trigger_error( "Unrecognized Encoding, reverting back to HTML-ENTITIES" );
				$encoding = 'HTML-ENTITIES';
			}
		}
		$this->encoding = $encoding;
	}

	public function format( $root ) {
		// Keep track of style modifications
		$this->previousState = null;
		// and create a stack of states
		$this->states = array();
		// Put an initial standard state onto the stack
		$this->state = new RtfState();
		array_push( $this->states, $this->state );
		// Keep track of opened html tags
		$this->openedTags = array( 'span' => false, 'p' => false );
		// Create the first paragraph
		$this->openTag( 'p' );
		// Begin format
		$this->processGroup( $root );

		// Remove the last opened <p> tag and return
		return substr( $this->output, 0, - 3 );
	}

	protected function extractFontTable( $fontTblGrp ) {
		// {' \fonttbl (<fontinfo> | ('{' <fontinfo> '}'))+ '}'
		// <fontnum><fontfamily><fcharset>?<fprq>?<panose>?
		// <nontaggedname>?<fontemb>?<codepage>? <fontname><fontaltname>? ';'

		$fonttbl = array();
		$c       = count( $fontTblGrp );

		for ( $i = 1; $i < $c; $i ++ ) {
			$fname = '';
			$fN    = null;
			foreach ( $fontTblGrp[ $i ]->children as $child ) {

				if ( $child instanceof RtfControlWord ) {
					switch ( $child->word ) {
						case 'f':
							$fN             = $child->parameter;
							$fonttbl[ $fN ] = new RtfFont();
							break;

						// Font family names
						case 'froman':
							$fonttbl[ $fN ]->fontfamily = "serif";
							break;
						case 'fswiss':
							$fonttbl[ $fN ]->fontfamily = "sans-serif";
							break;
						case 'fmodern':
							$fonttbl[ $fN ]->fontfamily = "monospace";
							break;
						case 'fscript':
							$fonttbl[ $fN ]->fontfamily = "cursive";
							break;
						case 'fdecor':
							$fonttbl[ $fN ]->fontfamily = "fantasy";
							break;
						// case 'fnil': break; // default font
						// case 'ftech': break; // symbol
						// case 'fbidi': break; // bidirectional font
						case 'fcharset': // charset
							$fonttbl[ $fN ]->charset =
								$this->getEncodingFromCharset( $child->parameter );
							break;
						case 'cpg': // code page
							$fonttbl[ $fN ]->codepage =
								$this->getEncodingFromCodepage( $child->parameter );
							break;
						case 'fprq': // Font pitch
							$fonttbl[ $fN ]->fprq = $child->parameter;
							break;
						default:
							break;
					}
				} elseif ( $child instanceof RtfText ) {
					// Save font name
					$fname .= $child->text;
				}
				/*
				elseif ($child instanceof RtfGroup) {
					  // possible subgroups:
					  // '{\*' \falt #PCDATA '}' = alternate font name
					  // '{\*' \fontemb <fonttype> <fontfname>? <data>? '}'
					  // '{\*' \fontfile <codepage>? #PCDATA '}'
					  // '{\*' \panose <data> '}'
					  continue;
					} elseif ($child instanceof RtfControlSymbol) {
					  // the only authorized symbol here is '*':
					  // \*\fname = non tagged file name (only WordPad uses it)
					  continue;
					}
				*/
			}
			// Remove end ; delimiter from font name
			$fonttbl[ $fN ]->fontname = substr( $fname, 0, - 1 );

			// Save extracted Font
			RtfState::$fonttbl = $fonttbl;
		}
	}

	protected function extractColorTable( $colorTblGrp ) {
		// {\colortbl;\red0\green0\blue0;}
		// Index 0 of the RTF color table  is the 'auto' color
		$colortbl = array();
		$c        = count( $colorTblGrp );
		$color    = '';
		for ( $i = 1; $i < $c; $i ++ ) { // Iterate through colors
			if ( $colorTblGrp[ $i ] instanceof RtfControlWord ) {
				// Extract RGB color and convert it to hex string
				$color = sprintf( '#%02x%02x%02x', // hex string format
					$colorTblGrp[ $i ]->parameter, // red
					$colorTblGrp[ $i + 1 ]->parameter, // green
					$colorTblGrp[ $i + 2 ]->parameter ); // blue
				$i     += 2;
			} elseif ( $colorTblGrp[ $i ] instanceof RtfText ) {
				// This is a delimiter ';' so
				if ( $i != 1 ) { // Store the already extracted color
					$colortbl[] = $color;
				} else { // This is the 'auto' color
					$colortbl[] = 0;
				}
			}
		}
		RtfState::$colortbl = $colortbl;
	}

	protected function extractImage( $pictGrp ) {
		$Image = new RtfImage();
		foreach ( $pictGrp as $child ) {
			if ( $child instanceof RtfControlWord ) {
				switch ( $child->word ) {
					// Picture Format
					case "emfblip":
						$Image->format = 'emf';
						break;
					case "pngblip":
						$Image->format = 'png';
						break;
					case "jpegblip":
						$Image->format = 'jpeg';
						break;
					case "macpict":
						$Image->format = 'pict';
						break;
					// case "wmetafile": $Image->format = 'bmp'; break;

					// Picture size and scaling
					case "picw":
						$Image->width = $child->parameter;
						break;
					case "pich":
						$Image->height = $child->parameter;
						break;
					case "picwgoal":
						$Image->goalWidth = $child->parameter;
						break;
					case "pichgoal":
						$Image->goalHeight = $child->parameter;
						break;
					case "picscalex":
						$Image->pcScaleX = $child->parameter;
						break;
					case "picscaley":
						$Image->pcScaleY = $child->parameter;
						break;

					// Binary or Hexadecimal Data ?
					case "bin":
						$Image->binarySize = $child->parameter;
						break;
					default:
						break;
				}

			} elseif ( $child instanceof RtfText ) { // store Data
				$Image->ImageData = $child->text;
			}
		}
		// output Image
		$this->output .= $Image->printImage();
		unset( $Image );
	}

	protected function processGroup( $group ) {
		if(empty($group)){
			return;
		}
		// Can we ignore this group?
		switch ( $group->getType() ) {
			case "fonttbl": // Extract Font table
				$this->extractFontTable( $group->children );

				return;
			case "colortbl": // Extract color table
				$this->extractColorTable( $group->children );

				return;
			case "stylesheet":
				// Stylesheet extraction not yet supported
				return;
			case "info":
				// Ignore Document information
				return;
			case "pict":
				$this->extractImage( $group->children );

				return;
			case "nonshppict":
				// Ignore alternative images
				return;
			case "*": // Process destionation
				$this->processDestination( $group->children );

				return;
		}

		// Pictures extraction not yet supported
		//if(substr($group->getType(), 0, 4) == "pict") return;

		// Push a new state onto the stack:
		$this->state = clone $this->state;
		array_push( $this->states, $this->state );

		foreach ( $group->children as $child ) {
			$this->formatEntry( $child );
		}

		// Pop state from stack
		array_pop( $this->states );
		$this->state = $this->states[ sizeof( $this->states ) - 1 ];
	}

	protected function processDestination( $dest ) {
		if ( ! $dest[1] instanceof RtfControlWord ) {
			return;
		}
		// Check if this is a Word 97 picture
		if ( $dest[1]->word == "shppict" ) {
			$c = count( $dest );
			for ( $i = 2; $i < $c; $i ++ ) {
				$this->formatEntry( $dest[ $i ] );
			}
		}
	}

	protected function formatEntry( $entry ) {
		if ( $entry instanceof RtfGroup ) {
			$this->processGroup( $entry );
		} elseif ( $entry instanceof RtfControlWord ) {
			$this->formatControlWord( $entry );
		} elseif ( $entry instanceof RtfControlSymbol ) {
			$this->formatControlSymbol( $entry );
		} elseif ( $entry instanceof RtfText ) {
			$this->formatText( $entry );
		}
	}

	protected function formatControlWord( $word ) {
		// plain: reset font formatting properties to default.
		// pard: reset to default paragraph properties.
		if ( $word->word == "plain" || $word->word == "pard" ) {
			$this->state->reset( $this->defaultFont );

			// Font formatting properties:
		} elseif ( $word->word == "b" ) {
			$this->state->bold = $word->parameter; // bold
		} elseif ( $word->word == "i" ) {
			$this->state->italic = $word->parameter; // italic
		} elseif ( $word->word == "ul" ) {
			$this->state->underline = $word->parameter; // underline
		} elseif ( $word->word == "ulnone" ) {
			$this->state->underline = false; // no underline
		} elseif ( $word->word == "strike" ) {
			$this->state->strike = $word->parameter; // strike through
		} elseif ( $word->word == "v" ) {
			$this->state->hidden = $word->parameter; // hidden
		} elseif ( $word->word == "fs" ) {
			$this->state->fontsize = ceil( ( $word->parameter / 24 ) * 16 ); // font size
		} elseif ( $word->word == "f" ) {
			$this->state->font = $word->parameter;

			// Colors:
		} elseif ( $word->word == "cf" || $word->word == "chcfpat" ) {
			$this->state->fontcolor = $word->parameter;
		} elseif ( $word->word == "cb" || $word->word == "chcbpat" ) {
			$this->state->background = $word->parameter;
		} elseif ( $word->word == "highlight" ) {
			$this->state->hcolor = $word->parameter;

			// RTF special characters:
		} elseif ( $word->word == "lquote" ) {
			$this->write( "&lsquo;" ); // &#145; &#8216;
		} elseif ( $word->word == "rquote" ) {
			$this->write( "&rsquo;" );  // &#146; &#8217;
		} elseif ( $word->word == "ldblquote" ) {
			$this->write( "&ldquo;" ); // &#147; &#8220;
		} elseif ( $word->word == "rdblquote" ) {
			$this->write( "&rdquo;" ); // &#148; &#8221;
		} elseif ( $word->word == "bullet" ) {
			$this->write( "&bull;" ); // &#149; &#8226;
		} elseif ( $word->word == "endash" ) {
			$this->write( "&ndash;" ); // &#150; &#8211;
		} elseif ( $word->word == "emdash" ) {
			$this->write( "&mdash;" ); // &#151; &#8212;

			// more special characters:
		} elseif ( $word->word == "enspace" ) {
			$this->write( "&ensp;" ); // &#8194;
		} elseif ( $word->word == "emspace" ) {
			$this->write( "&emsp;" ); // &#8195;
			//}elseif($word->word == "emspace" || $word->word == "enspace"){ $this->write("&nbsp;"); // &#160; &#32;
		} elseif ( $word->word == "tab" ) {
			$this->write( "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" ); // character value 9
		} elseif ( $word->word == "line" ) {
			$this->output .= "<br>"; // character value (line feed = &#10;) (carriage return = &#13;)

			// Unicode characters:
		} elseif ( $word->word == "u" ) {
			$uchar = $this->decodeUnicode( $word->parameter );
			$this->write( $uchar );

			// End of paragraph:
		} elseif ( $word->word == "par" || $word->word == "row" ) {
			// Close previously opened tags
			$this->closeTags();
			// Begin a new paragraph
			$this->openTag( 'p' );

			// Store defaults
		} elseif ( $word->word == "deff" ) {
			$this->defaultFont = $word->parameter;
		} elseif ( in_array( $word->word, array( 'ansi', 'mac', 'pc', 'pca' ) ) ) {
			$this->RTFencoding = $this->getEncodingFromCodepage( $word->word );
		} elseif ( $word->word == "ansicpg" && $word->parameter ) {
			$this->RTFencoding = $this->getEncodingFromCodepage( $word->parameter );
		}
	}

	protected function decodeUnicode( $code, $srcEnc = 'UTF-8' ) {
		$utf8 = '';

		if ( $srcEnc != 'UTF-8' ) { // convert character to Unicode
			$utf8 = iconv( $srcEnc, 'UTF-8', chr( $code ) );
		}

		if ( $this->encoding == 'HTML-ENTITIES' ) {
			return $utf8 ? "&#{$this->ord_utf8($utf8)};" : "&#{$code};";

		} elseif ( $this->encoding == 'UTF-8' ) {
			return $utf8 ? $utf8 : mb_convert_encoding( "&#{$code};", $this->encoding, 'HTML-ENTITIES' );

		} else {
			return $utf8 ? mb_convert_encoding( $utf8, $this->encoding, 'UTF-8' ) :
				mb_convert_encoding( "&#{$code};", $this->encoding, 'HTML-ENTITIES' );
		}
	}

	protected function write( $txt ) {
		// Create a new 'span' element only when a style change occur
		// 1st case: style change occured
		// 2nd case: there is no change in style but the already created 'span'
		// element is somehow closed (ex. because of an end of paragraph)
		if ( ! $this->state->isLike( $this->previousState ) ||
		     ( $this->state->isLike( $this->previousState ) && ! $this->openedTags['span'] ) ) {
			// If applicable close previously opened 'span' tag
			$this->closeTag( 'span' );

			$style = $this->state->printStyle();

			// Keep track of preceding style
			$this->previousState = clone $this->state;

			// Create style attribute and open span
			$attr = $style ? "style=\"{$style}\"" : "";
			$this->openTag( 'span', $attr );
		}
		$this->output .= $txt;
	}

	protected function openTag( $tag, $attr = '' ) {
		$this->output             .= $attr ? "<{$tag} {$attr}>" : "<{$tag}>";
		$this->openedTags[ $tag ] = true;
	}

	protected function closeTag( $tag ) {
		if ( $this->openedTags[ $tag ] ) {
			// Check for empty html elements
			if ( substr( $this->output, - strlen( "<{$tag}>" ) ) == "<{$tag}>" ) {
				switch ( $tag ) {
					case 'p': // Replace empty 'p' element with a line break
						$this->output = substr( $this->output, 0, - 3 ) . "<br>";
						break;
					default: // Delete empty elements
						$this->output = substr( $this->output, 0, - strlen( "<{$tag}>" ) );
						break;
				}
			} else {
				$this->output             .= "</{$tag}>";
				$this->openedTags[ $tag ] = false;
			}
		}
	}

	protected function closeTags() {
		// Close all opened tags
		foreach ( $this->openedTags as $tag => $b ) {
			$this->closeTag( $tag );
		}
	}

	protected function formatControlSymbol( $symbol ) {
		if ( $symbol->symbol == '\'' ) {
			$enc   = $this->getSourceEncoding();
			$uchar = $this->decodeUnicode( $symbol->parameter, $enc );
			$this->write( $uchar );
		} elseif ( $symbol->symbol == '~' ) {
			$this->write( "&nbsp;" ); // Non breaking space
		} elseif ( $symbol->symbol == '-' ) {
			$this->write( "&#173;" ); // Optional hyphen
		} elseif ( $symbol->symbol == '_' ) {
			$this->write( "&#8209;" ); // Non breaking hyphen
		}
	}

	protected function formatText( $text ) {
		// Convert special characters to HTML entities
		$txt = htmlspecialchars( $text->text, ENT_NOQUOTES, 'UTF-8' );
		if ( $this->encoding == 'HTML-ENTITIES' ) {
			$this->write( $txt );
		} else {
			$this->write( mb_convert_encoding( $txt, $this->encoding, 'UTF-8' ) );
		}
	}

	protected function getSourceEncoding() {
		if ( isset( $this->state->font ) ) {
			if ( isset( RtfState::$fonttbl[ $this->state->font ]->codepage ) ) {
				return RtfState::$fonttbl[ $this->state->font ]->codepage;

			} elseif ( isset( RtfState::$fonttbl[ $this->state->font ]->charset ) ) {
				return RtfState::$fonttbl[ $this->state->font ]->charset;
			}
		}

		return $this->RTFencoding;
	}

	protected function getEncodingFromCharset( $fcharset ) {
		/* maps windows character sets to iconv encoding names */
		$charset = array(
			0   => 'CP1252', // ANSI: Western Europe
			1   => 'CP1252', //*Default
			2   => 'CP1252', //*Symbol
			3   => null,     // Invalid
			77  => 'MAC',    //*also [MacRoman]: Macintosh
			128 => 'CP932',  //*or [Shift_JIS]?: Japanese
			129 => 'CP949',  //*also [UHC]: Korean (Hangul)
			130 => 'CP1361', //*also [JOHAB]: Korean (Johab)
			134 => 'CP936',  //*or [GB2312]?: Simplified Chinese
			136 => 'CP950',  //*or [BIG5]?: Traditional Chinese
			161 => 'CP1253', // Greek
			162 => 'CP1254', // Turkish (latin 5)
			163 => 'CP1258', // Vietnamese
			177 => 'CP1255', // Hebrew
			178 => 'CP1256', // Simplified Arabic
			179 => 'CP1256', //*Traditional Arabic
			180 => 'CP1256', //*Arabic User
			181 => 'CP1255', //*Hebrew User
			186 => 'CP1257', // Baltic
			204 => 'CP1251', // Russian (Cyrillic)
			222 => 'CP874',  // Thai
			238 => 'CP1250', // Eastern European (latin 2)
			254 => 'CP437',  //*also [IBM437][437]: PC437
			255 => 'CP437'
		); //*OEM still PC437

		if ( isset( $charset[ $fcharset ] ) ) {
			return $charset[ $fcharset ];
		} else {
			trigger_error( "Unknown charset: {$fcharset}" );
		}
	}

	protected function getEncodingFromCodepage( $cpg ) {
		$codePage = array(
			'ansi' => 'CP1252',
			'mac'  => 'MAC',
			'pc'   => 'CP437',
			'pca'  => 'CP850',
			437    => 'CP437', // United States IBM
			708    => 'ASMO-708', // also [ISO-8859-6][ARABIC] Arabic
			/*  Not supported by iconv
			709, => '' // Arabic (ASMO 449+, BCON V4)
			710, => '' // Arabic (transparent Arabic)
			711, => '' // Arabic (Nafitha Enhanced)
			720, => '' // Arabic (transparent ASMO)
			*/
			819    => 'CP819',   // Windows 3.1 (US and Western Europe)
			850    => 'CP850',   // IBM multilingual
			852    => 'CP852',   // Eastern European
			860    => 'CP860',   // Portuguese
			862    => 'CP862',   // Hebrew
			863    => 'CP863',   // French Canadian
			864    => 'CP864',   // Arabic
			865    => 'CP865',   // Norwegian
			866    => 'CP866',   // Soviet Union
			874    => 'CP874',   // Thai
			932    => 'CP932',   // Japanese
			936    => 'CP936',   // Simplified Chinese
			949    => 'CP949',   // Korean
			950    => 'CP950',   // Traditional Chinese
			1250   => 'CP1250',  // Windows 3.1 (Eastern European)
			1251   => 'CP1251',  // Windows 3.1 (Cyrillic)
			1252   => 'CP1252',  // Western European
			1253   => 'CP1253',  // Greek
			1254   => 'CP1254',  // Turkish
			1255   => 'CP1255',  // Hebrew
			1256   => 'CP1256',  // Arabic
			1257   => 'CP1257',  // Baltic
			1258   => 'CP1258',  // Vietnamese
			1361   => 'CP1361'
		); // Johab

		if ( isset( $codePage[ $cpg ] ) ) {
			return $codePage[ $cpg ];
		} else {
			// Debug Error
			trigger_error( "Unknown codepage: {$cpg}" );
		}
	}

	protected function ord_utf8( $chr ) {
		$ord0 = ord( $chr );
		if ( $ord0 >= 0 && $ord0 <= 127 ) {
			return $ord0;
		}

		$ord1 = ord( $chr[1] );
		if ( $ord0 >= 192 && $ord0 <= 223 ) {
			return ( $ord0 - 192 ) * 64 + ( $ord1 - 128 );
		}

		$ord2 = ord( $chr[2] );
		if ( $ord0 >= 224 && $ord0 <= 239 ) {
			return ( $ord0 - 224 ) * 4096 + ( $ord1 - 128 ) * 64 + ( $ord2 - 128 );
		}

		$ord3 = ord( $chr[3] );
		if ( $ord0 >= 240 && $ord0 <= 247 ) {
			return ( $ord0 - 240 ) * 262144 + ( $ord1 - 128 ) * 4096 + ( $ord2 - 128 ) * 64 + ( $ord3 - 128 );
		}

		$ord4 = ord( $chr[4] );
		if ( $ord0 >= 248 && $ord0 <= 251 ) {
			return ( $ord0 - 248 ) * 16777216 + ( $ord1 - 128 ) * 262144 + ( $ord2 - 128 ) * 4096 + ( $ord3 - 128 ) * 64 + ( $ord4 - 128 );
		}

		if ( $ord0 >= 252 && $ord0 <= 253 ) {
			return ( $ord0 - 252 ) * 1073741824 + ( $ord1 - 128 ) * 16777216 + ( $ord2 - 128 ) * 262144 + ( $ord3 - 128 ) * 4096 + ( $ord4 - 128 ) * 64 + ( ord( $chr[5] ) - 128 );
		}

		trigger_error( "Invalid Unicode character: {$chr}" );
	}
}

if ( __FILE__ === realpath( $_SERVER['SCRIPT_NAME'] ) && php_sapi_name() === 'cli' ) {
	if ( isset( $_SERVER['argv'][1] ) && ( $_SERVER['argv'][1] !== '-' ) ) {
		$file = $_SERVER['argv'][1];
	} else {
		$file = 'php://stdin';
	}

	$reader = new RtfReader();
	$rtf    = file_get_contents( $file );
	if ( $reader->parse( $rtf ) ) {
		$formatter = new RtfHtml();
		echo $formatter->format( $reader->root );
	} else {
		echo "parse error occured";
	}
}