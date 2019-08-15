<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:08
 */

use PaulBoenisch\RtfToHtml\Model\RtfControlSymbol;
use PaulBoenisch\RtfToHtml\Model\RtfControlWord;
use PaulBoenisch\RtfToHtml\Model\RtfGroup;
use PaulBoenisch\RtfToHtml\Model\RtfText;

/**
 * Class RtfReader
 */
class RtfReader {
	public $root = null;

	protected function getChar()
	{
		$this->char = null;
		if ($this->pos < strlen($this->rtf)) {
			$this->char = $this->rtf[$this->pos++];
		} else {
			$this->err = "Tried to read past EOF, RTF is probably truncated";
		}
	}

	protected function parseStartGroup()
	{
		// Store state of document on stack.
		$group = new RtfGroup();
		if($this->group != null) $group->parent = $this->group;
		if($this->root == null) {
			// First group of the RTF document
			$this->group = $group;
			$this->root = $group;
			// Create uc stack and insert the first default value
			$this->uc = array(1);
		} else {
			array_push($this->uc, end($this->uc));
			array_push($this->group->children, $group);
			$this->group = $group;
		}
	}

	protected function isLetter()
	{
		if(ord($this->char) >= 65 && ord($this->char) <= 90) return True;
		if(ord($this->char) >= 97 && ord($this->char) <= 122) return True;
		return False;
	}

	protected function isDigit()
	{
		if(ord($this->char) >= 48 && ord($this->char) <= 57) return True;
		return False;
	}

	/*
	 *  Checks for end of line (EOL)
	 */
	protected function isEndOfFile()
	{
		if ($this->char == "\r" || $this->char == "\n") {
			// Checks for a Windows/Acron type EOL
			if( $this->rtf[$this->pos] == "\n" || $this->rtf[$this->pos] == "\r" ) {
				$this->getChar();
			}
			return TRUE;
		}
		return FALSE;
	}

	/*
	 *  Checks for a space delimiter
	 */
	protected function isSpaceDelimiter()
	{
		if ($this->char == " " || $this->isEndOfFile()) return TRUE;
		return FALSE;
	}

	protected function parseEndGroup()
	{
		// Retrieve state of document from stack.
		$this->group = $this->group->parent;
		// Retrieve last uc value from stack
		array_pop($this->uc);
	}

	protected function parseControlWord()
	{
		$this->getChar();
		$word = "";

		while($this->isLetter())
		{
			$word .= $this->char;
			$this->getChar();
		}

		// Read parameter (if any) consisting of digits.
		// Paramater may be negative.
		$parameter = null;
		$negative = False;
		if($this->char == '-') {
			$this->getChar();
			$negative = True;
		}
		while($this->isDigit())
		{
			if($parameter == null) $parameter = 0;
			$parameter = $parameter * 10 + $this->char;
			$this->getChar();
		}
		// if no parameter assume control word's default (usually 1)
		// if no default then assign 0 to the parameter
		if($parameter === null) $parameter = 1;

		// convert to a negative number when applicable
		if($negative) $parameter = -$parameter;

		// Update uc value
		if ($word == "uc") {
			array_pop($this->uc);
			$this->uc[] = $parameter;
		}

		// Skip space delimiter
		if(!$this->isSpaceDelimiter()) $this->pos--;

		// If this is \u, then the parameter will be followed
		// by {$this->uc} characters.
		if($word == "u") {
			// Convert parameter to unsigned decimal unicode
			if($negative) $parameter = 65536 + $parameter;

			// Will ignore replacement characters $uc times
			$uc = end($this->uc);
			while ($uc > 0) {
				$this->getChar();
				// If the replacement character is encoded as
				// hexadecimal value \'hh then jump over it
				if($this->char == '\\' && $this->rtf[$this->pos]=='\'')
					$this->pos = $this->pos + 3;

				// Break if it's an RTF scope delimiter
				elseif ($this->char == '{' || $this->char == '{')
					break;

				// - To include an RTF delimiter in skippable data, it must be
				//  represented using the appropriate control symbol (that is,
				//  escaped with a backslash,) as in plain text.
				// - Any RTF control word or symbol is considered a single character
				//  for the purposes of counting skippable characters. For this reason
				//  it's more appropriate to create à $skip flag and let the parse()
				//  function take care of the skippable characters.
				$uc--;
			}
		}

		$rtfword = new RtfControlWord();
		$rtfword->word = $word;
		$rtfword->parameter = $parameter;
		array_push($this->group->children, $rtfword);
	}

	protected function parseControlSymbol()
	{
		// Read symbol (one character only).
		$this->getChar();
		$symbol = $this->char;

		// Symbols ordinarily have no parameter. However,
		// if this is \', then it is followed by a 2-digit hex-code:
		$parameter = 0;
		// Treat EOL symbols as \par control word
		if ($this->isEndOfFile()) {
			$rtfword = new RtfControlWord();
			$rtfword->word = 'par';
			$rtfword->parameter = $parameter;
			array_push($this->group->children, $rtfword);
			return;

		} elseif($symbol == '\'') {
			$this->getChar();
			$parameter = $this->char;
			$this->getChar();
			$parameter = hexdec($parameter . $this->char);
		}

		$rtfsymbol = new RtfControlSymbol();
		$rtfsymbol->symbol = $symbol;
		$rtfsymbol->parameter = $parameter;
		array_push($this->group->children, $rtfsymbol);
	}

	protected function parseControl()
	{
		// Beginning of an RTF control word or control symbol.
		// Look ahead by one character to see if it starts with
		// a letter (control world) or another symbol (control symbol):
		$this->getChar();
		$this->pos--;
		if($this->isLetter())
			$this->parseControlWord();
		else
			$this->parseControlSymbol();
	}

	protected function parseText()
	{
		// parse plain text up to backslash or brace,
		// unless escaped.
		$text = "";
		$terminate = False;
		do
		{
			// Ignore EOL characters
			if($this->char == "\r" || $this->char == "\n") {
				$this->getChar();
				continue;
			}
			// Is this an escape?
			if($this->char == '\\') {
				// Perform lookahead to see if this
				// is really an escape sequence.
				$this->getChar();
				switch($this->char)
				{
					case '\\': break;
					case '{': break;
					case '}': break;
					default:
						// Not an escape. Roll back.
						$this->pos = $this->pos - 2;
						$terminate = True;
						break;
				}
			} elseif($this->char == '{' || $this->char == '}') {
				$this->pos--;
				$terminate = True;
			}

			if(!$terminate) {
				// Save plain text
				$text .= $this->char;
				$this->getChar();
			}
		}
		while(!$terminate && $this->pos < $this->len);

		$rtftext = new RtfText();
		$rtftext->text = $text;

		// If group does not exist, then this is not a valid RTF file.
		// Throw an exception.
		if($this->group == null) {
			$err = "parse error occured";
			trigger_error($err);
			throw new Exception($err);
		}

		array_push($this->group->children, $rtftext);
	}

	/*
	 * Attempt to parse an RTF string. Parsing returns TRUE on success
	 * or FALSE on failure
	 */
	public function parse($rtf)
	{
		try {
			$this->rtf = $rtf;
			$this->pos = 0;
			$this->len = strlen($this->rtf);
			$this->group = null;
			$this->root = null;

			while($this->pos < $this->len)
			{
				// Read next character:
				$this->getChar();

				// Ignore \r and \n
				if($this->char == "\n" || $this->char == "\r") continue;

				// What type of character is this?
				switch($this->char)
				{
					case '{':
						$this->parseStartGroup();
						break;
					case '}':
						$this->parseEndGroup();
						break;
					case '\\':
						$this->parseControl();
						break;
					default:
						$this->parseText();
						break;
				}
			}

			return True;
		}
		catch(Exception $ex) {
			return False;
		}
	}
}