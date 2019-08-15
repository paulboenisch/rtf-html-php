<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:02
 */

namespace PaulBoenisch\RtfToHtml\Model;


/**
 * Class RtfControlWord
 * @package PaulBoenisch\RtfToHtml\Model
 */
class RtfControlWord extends RtfElement {
	public $word;
	public $parameter;

	public function dump($level)
	{
		echo "<div style='color:green'>";
		$this->indent($level);
		echo "WORD {$this->word} ({$this->parameter})";
		echo "</div>";
	}
}