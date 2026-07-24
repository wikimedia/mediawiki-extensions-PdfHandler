<?php

namespace MediaWiki\Extension\PdfHandler\Test\Integration;

use MediaWiki\Extension\PdfHandler\PdfHandler;
use MediaWiki\FileRepo\File\File;
use MediaWiki\Media\MediaHandler;
use MediaWiki\Media\MediaHandlerState;
use MediaWiki\Media\TransformParameterError;
use MediaWikiIntegrationTestCase;

/**
 * Coverage for PdfHandler behavior that reaches for services.
 * Pure param/formatting logic lives in the unit test.
 *
 * @group PdfHandler
 * @covers \MediaWiki\Extension\PdfHandler\PdfHandler
 */
class PdfHandlerTest extends MediaWikiIntegrationTestCase {

	private const FIXTURE = __DIR__ . '/../data/simple.pdf';

	private function markTestSkippedIfNoTools(): void {
		$config = $this->getServiceContainer()->getMainConfig();
		if ( !is_executable( $config->get( 'PdfInfo' ) ) || !is_executable( $config->get( 'PdftoText' ) ) ) {
			$this->markTestSkipped( 'This test needs the installation of the pdfinfo and pdftotext tools' );
		}
	}

	private function getFileWithMetadata( array $items ): File {
		$file = $this->createMock( File::class );
		$file->method( 'getMetadataItems' )->willReturnCallback(
			static fn ( array $names ) => array_intersect_key( $items, array_fill_keys( $names, true ) )
		);
		$file->method( 'getMetadataItem' )->willReturnCallback(
			static fn ( string $name ) => $items[$name] ?? false
		);
		$file->method( 'getSha1' )->willReturn( 'testsha1' );
		return $file;
	}

	/**
	 * getThumbType() ignores its $mime argument: the `static $mime` declaration
	 * shadows the parameter, so the type is always recomputed from config (and
	 * then cached process-wide). Passing a mime does not change the result.
	 */
	public function testGetThumbTypeIgnoresPassedMime() {
		$this->overrideConfigValue( 'PdfOutputExtension', 'jpg' );
		$this->assertSame(
			[ 'jpg', 'image/jpeg' ],
			( new PdfHandler() )->getThumbType( 'pdf', 'application/pdf' )
		);
	}

	public function testGetSizeAndMetadata() {
		$this->markTestSkippedIfNoTools();
		$this->overrideConfigValue( 'PdfHandlerDpi', 150 );

		// A plain MediaHandlerState (not a LocalFile) makes PdfImage read the
		// fixture from the local path rather than via addToShellboxCommand().
		$state = $this->createMock( MediaHandlerState::class );
		$result = ( new PdfHandler() )->getSizeAndMetadata( $state, self::FIXTURE );

		// Page 1 is 612 x 792 pts (letter); at 150 DPI that is 1275 x 1650.
		$this->assertSame( 1275, $result['width'] );
		$this->assertSame( 1650, $result['height'] );
		$this->assertSame( '2', $result['metadata']['Pages'] ?? null );
		$this->assertArrayHasKey( 'mergedMetadata', $result['metadata'] );
	}

	public function testNormaliseParams() {
		$this->overrideConfigValue( 'ThumbnailSteps', [] );
		$image = $this->createMock( File::class );
		$image->method( 'pageCount' )->willReturn( 1 );
		$image->method( 'getWidth' )->willReturn( 1000 );
		$image->method( 'getHeight' )->willReturn( 2000 );

		$params = [ 'width' => 500, 'page' => 1 ];
		$this->assertTrue( ( new PdfHandler() )->normaliseParams( $image, $params ) );
		$this->assertSame( 500, $params['physicalWidth'] );
		$this->assertSame( 1000, $params['physicalHeight'] );
		$this->assertSame( 1000, $params['height'] );
	}

	public function testPageCountAndDimensions() {
		$this->overrideConfigValue( 'PdfHandlerDpi', 72 );
		$file = $this->getFileWithMetadata( [
			'Pages' => '2',
			'pages' => [
				1 => [ 'Page size' => '72 x 144', 'Page rot' => '0' ],
				2 => [ 'Page size' => '72 x 144', 'Page rot' => '90' ],
			],
		] );

		$handler = new PdfHandler();
		$this->assertSame( 2, $handler->pageCount( $file ) );
		$this->assertSame( [ 'width' => 72, 'height' => 144 ], $handler->getPageDimensions( $file, 1 ) );
		$this->assertSame( [ 'width' => 144, 'height' => 72 ], $handler->getPageDimensions( $file, 2 ) );
	}

	public function testPageCountReturnsFalseWithoutMetadata() {
		$this->assertFalse( ( new PdfHandler() )->pageCount( $this->getFileWithMetadata( [] ) ) );
	}

	public function testFormatMetadataReturnsFalseWhenEmpty() {
		$this->assertFalse(
			( new PdfHandler() )->formatMetadata( $this->getFileWithMetadata( [ 'mergedMetadata' => [] ] ) )
		);
	}

	public function testFormatMetadataFormatsPopulatedMetadata() {
		$file = $this->getFileWithMetadata( [
			'mergedMetadata' => [ 'pdf-Producer' => 'Ghostscript', 'ObjectName' => 'A title' ],
		] );
		$result = ( new PdfHandler() )->formatMetadata( $file );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'visible', $result );
		$this->assertArrayHasKey( 'collapsed', $result );
	}

	public function testDoTransformRejectsParamsWithoutWidth() {
		$result = ( new PdfHandler() )
			->doTransform( $this->createMock( File::class ), '/tmp/thumb.jpg', 'http://thumb', [] );
		$this->assertInstanceOf( TransformParameterError::class, $result );
	}

	/**
	 * @dataProvider provideIsFileMetadataValid
	 */
	public function testIsFileMetadataValid( array $items, $expected ) {
		$this->assertSame(
			$expected,
			( new PdfHandler() )->isFileMetadataValid( $this->getFileWithMetadata( $items ) )
		);
	}

	public static function provideIsFileMetadataValid() {
		$goodPages = [
			1 => [ 'Page size' => '612 x 792 pts (letter)', 'Page rot' => '0' ],
			2 => [ 'Page size' => '612 x 792 pts (letter)', 'Page rot' => '90' ],
		];
		return [
			'permanent error marker' => [
				[ 'error' => 'pdfinfo exited with code 1' ],
				// Bad metadata, but not retryable
				MediaHandler::METADATA_GOOD,
			],
			'empty metadata' => [ [], MediaHandler::METADATA_BAD ],
			'missing page list' => [ [ 'Pages' => '2' ], MediaHandler::METADATA_BAD ],
			'incomplete page list' => [
				[ 'Pages' => '2', 'pages' => [ 1 => $goodPages[1] ] ],
				MediaHandler::METADATA_COMPATIBLE,
			],
			'zero size page' => [
				[ 'Pages' => '1', 'pages' => [ 1 => [ 'Page size' => '0 x 0' ] ] ],
				MediaHandler::METADATA_COMPATIBLE,
			],
			'valid without mergedMetadata' => [
				[ 'Pages' => '2', 'pages' => $goodPages ],
				MediaHandler::METADATA_COMPATIBLE,
			],
			'valid' => [
				[ 'Pages' => '2', 'pages' => $goodPages, 'mergedMetadata' => [] ],
				MediaHandler::METADATA_GOOD,
			],
		];
	}
}
