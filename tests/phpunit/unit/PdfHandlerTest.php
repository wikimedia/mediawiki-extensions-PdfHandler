<?php

namespace MediaWiki\Extension\PdfHandler\Test\Unit;

use MediaWiki\Extension\PdfHandler\PdfHandler;
use MediaWiki\FileRepo\File\File;
use MediaWikiUnitTestCase;
use ReflectionClass;
use Wikimedia\TestingAccessWrapper;

/**
 * PdfHandler's constructor depends on the main config from the service container
 * we bypass it: none of the methods exercised here should touch $this->config.
 *
 * @group PdfHandler
 * @covers \MediaWiki\Extension\PdfHandler\PdfHandler
 */
class PdfHandlerTest extends MediaWikiUnitTestCase {

	private function newHandler(): PdfHandler {
		return ( new ReflectionClass( PdfHandler::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @dataProvider provideValidateParam
	 */
	public function testValidateParam( string $name, $value, bool $expected ) {
		$this->assertSame( $expected, $this->newHandler()->validateParam( $name, $value ) );
	}

	public static function provideValidateParam() {
		return [
			'page as int' => [ 'page', 3, true ],
			'page as digit string' => [ 'page', '3', true ],
			'page with surrounding space' => [ 'page', ' 3 ', true ],
			'page with trailing caption' => [ 'page', '3 of the document', false ],
			'page non-numeric' => [ 'page', 'foo', false ],
			'width positive' => [ 'width', '100', true ],
			'width zero' => [ 'width', '0', false ],
			'height negative' => [ 'height', '-5', false ],
			'unknown param' => [ 'rotation', '100', false ],
		];
	}

	/**
	 * @dataProvider provideMakeParamString
	 */
	public function testMakeParamString( array $params, $expected ) {
		$this->assertSame( $expected, $this->newHandler()->makeParamString( $params ) );
	}

	public static function provideMakeParamString() {
		return [
			'physicalWidth preferred over width' => [
				[ 'physicalWidth' => 800, 'width' => 400, 'page' => 2 ], 'page2-800px',
			],
			'falls back to width' => [ [ 'width' => 400 ], 'page1-400px' ],
			'defaults page to 1' => [ [ 'width' => 400, 'page' => ' 1 ' ], 'page1-400px' ],
			'no width returns false' => [ [ 'page' => 3 ], false ],
		];
	}

	/**
	 * @dataProvider provideParseParamString
	 */
	public function testParseParamString( string $str, $expected ) {
		$this->assertSame( $expected, $this->newHandler()->parseParamString( $str ) );
	}

	public static function provideParseParamString() {
		return [
			'valid' => [ 'page2-800px', [ 'width' => '800', 'page' => '2' ] ],
			'unparseable' => [ 'garbage', false ],
			'missing px suffix' => [ 'page2-800', false ],
		];
	}

	public function testParamStringRoundTrip() {
		$handler = $this->newHandler();
		$str = $handler->makeParamString( [ 'width' => 800, 'page' => 2 ] );
		$this->assertSame( [ 'width' => '800', 'page' => '2' ], $handler->parseParamString( $str ) );
	}

	/**
	 * @dataProvider provideGetPageText
	 */
	public function testGetPageText( $textItem, int $page, $expected ) {
		$file = $this->createMock( File::class );
		$file->method( 'getMetadataItem' )->with( 'text' )->willReturn( $textItem );
		$this->assertSame( $expected, $this->newHandler()->getPageText( $file, $page ) );
	}

	public static function provideGetPageText() {
		$pages = [ 'first page', 'second page' ];
		return [
			'page 1 maps to index 0' => [ $pages, 1, 'first page' ],
			'page 2 maps to index 1' => [ $pages, 2, 'second page' ],
			'out of range page' => [ $pages, 3, false ],
			'no text layer' => [ false, 1, false ],
		];
	}

	/**
	 * @dataProvider provideFormatTag
	 */
	public function testFormatTag( string $key, $vals, $expected ) {
		$handler = TestingAccessWrapper::newFromObject( $this->newHandler() );
		$this->assertSame( $expected, $handler->formatTag( $key, $vals ) );
	}

	public static function provideFormatTag() {
		return [
			'Producer escaped' => [ 'pdf-Producer', 'A & B <x>', 'A &amp; B &lt;x&gt;' ],
			'Version escaped' => [ 'pdf-Version', '1.5', '1.5' ],
			'Encrypted escaped' => [ 'pdf-Encrypted', 'yes <print>', 'yes &lt;print&gt;' ],
			'PageSize escapes each value' => [
				'pdf-PageSize', [ 'A4 <x>', 'Letter' ], [ 'A4 &lt;x&gt;', 'Letter' ],
			],
			'unknown key uses default formatting' => [ 'pdf-Other', 'v', false ],
		];
	}
}
