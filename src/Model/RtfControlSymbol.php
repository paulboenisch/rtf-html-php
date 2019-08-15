<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:01
 */

namespace PaulBoenisch\RtfToHtml\Model;
/**
 * Class RtfControlSymbol
 */
class RtfControlSymbol extends RtfElement {
	public $symbol;
	public $parameter = 0;

	public function dump($level)
	{
		echo "<div style='color:blue'>";
		$this->indent($level);
		echo "SYMBOL {$this->symbol} ({$this->parameter})";
		echo "</div>";
	}
}