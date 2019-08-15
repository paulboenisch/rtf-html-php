<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:02
 */

namespace PaulBoenisch\RtfToHtml\Model;


/**
 * Class RtfElement
 * @package PaulBoenisch\RtfToHtml\Model
 */
class RtfElement {

	/**
	 * @param $level
	 */
	protected function indent( $level ) {
		for ( $i = 0; $i < $level * 2; $i ++ ) {
			echo "&nbsp;";
		}
	}
}