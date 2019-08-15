<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:04
 */

namespace PaulBoenisch\RtfToHtml\Model;


/**
 * Class RtfText
 * @package PaulBoenisch\RtfToHtml\Model
 */
class RtfText extends RtfElement {
	public $text;

	public function dump($level)
	{
		echo "<div style='color:red'>";
		$this->indent($level);
		echo "TEXT {$this->text}";
		echo "</div>";
	}
}