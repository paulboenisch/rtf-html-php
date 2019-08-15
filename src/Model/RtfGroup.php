<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:03
 */

namespace PaulBoenisch\RtfToHtml\Model;


/**
 * Class RtfGroup
 * @package PaulBoenisch\RtfToHtml\Model
 */
class RtfGroup extends RtfElement {
	public $parent;
	public $children;

	public function __construct()
	{
		$this->parent = null;
		$this->children = array();
	}

	public function getType()
	{
		// No children?
		if(sizeof($this->children) == 0) return null;
		// First child not a control word?
		$child = $this->children[0];
		if($child instanceof RtfControlWord)
			return $child->word;
		elseif ($child instanceof RtfControlSymbol)
			return ($child->symbol == '*') ? '*' : null;

		return null;
	}

	public function isDestination()
	{
		// No children?
		if(sizeof($this->children) == 0) return null;
		// First child not a control symbol?
		$child = $this->children[0];
		if(!$child instanceof RtfControlSymbol) return null;
		return $child->symbol == '*';
	}

	public function dump($level = 0)
	{
		echo "<div>";
		$this->indent($level);
		echo "{";
		echo "</div>";

		foreach($this->children as $child)
		{
			if($child instanceof RtfGroup) {
				if ($child->getType() == "fonttbl") continue;
				if ($child->getType() == "colortbl") continue;
				if ($child->getType() == "stylesheet") continue;
				if ($child->getType() == "info") continue;
				// Skip any pictures:
				if (substr($child->getType(), 0, 4) == "pict") continue;
				if ($child->isDestination()) continue;
			}
			$child->dump($level + 2);
		}

		echo "<div>";
		$this->indent($level);
		echo "}";
		echo "</div>";
	}
}