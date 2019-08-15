<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:03
 */

namespace PaulBoenisch\RtfToHtml\Model;


/**
 * Class RtfImage
 * @package PaulBoenisch\RtfToHtml\Model
 */
class RtfImage {
	public function __construct()
	{
		$this->reset();
	}

	public function reset()
	{
		$this->format = 'bmp';
		$this->width = 0; // in xExt if wmetafile otherwise in px
		$this->height = 0; // in yExt if wmetafile otherwise in px
		$this->goalWidth = 0; // in twips
		$this->goalHeight = 0; // in twips
		$this->pcScaleX = 100; // 100%
		$this->pcScaleY = 100; // 100%
		$this->binarySize = null; // Number of bytes of the binary data
		$this->ImageData = null; // Binary or Hexadecimal Data
	}

	public function printImage()
	{
		// <img src="data:image/{FORMAT};base64,{#BDATA}" />
		$output = "<img src=\"data:image/{$this->format};base64,";

		if (isset($this->binarySize)) { // process binary data
			return;
		} else { // process hexadecimal data
			$output .= base64_encode(pack('H*',$this->ImageData));
		}

		$output .= "\" />";
		return $output;
	}
}