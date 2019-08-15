<?php
/**
 * Created by PhpStorm.
 * User: boenischp
 * Date: 15.08.19
 * Time: 18:42
 */


namespace PaulBoenisch\RtfToHtml\Tests;

use PaulBoenisch\RtfToHtml\Services\RtfHtml;
use PaulBoenisch\RtfToHtml\Services\RtfReader;

/**
 * Class RtfHtmlTest
 */
class RtfHtmlTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Tests formatted output
	 */
	public function testFormat(){
		$rtf = file_get_contents(__DIR__.'/assets/sample.rtf');
		$plain = file_get_contents(__DIR__.'/assets/sample.expected');
		$reader = new RtfReader();
		$reader->parse($rtf);
		$formatter = new RtfHtml();
		$html = $formatter->format($reader->root);
		$string = strip_tags($html);
		$string = html_entity_decode($string);
		$this->assertEquals($plain, $string);
	}
}