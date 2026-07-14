<?php

namespace MediaWiki\Extension\PdfHandler\Test\Unit;

use MediaWiki\Extension\PdfHandler\PdfHandler;
use MediaWiki\Extension\PdfHandler\PdfHandlerHooks;
use MediaWiki\FileRepo\File\File;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @group PdfHandler
 * @covers \MediaWiki\Extension\PdfHandler\PdfHandlerHooks
 */
class PdfHandlerHooksTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideOnLocalFilePurgeThumbnails
	 */
	public function testOnLocalFilePurgeThumbnails( string $mime, bool $expectedDeleted ) {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$sha1 = 'testsha1';
		$key = $cache->makeKey( PdfHandler::DIMENSIONS_CACHE_KEY, $sha1 );
		$data = [ 'pageCount' => 2 ];
		$cache->set( $key, $data );

		$file = $this->createMock( File::class );
		$file->method( 'getMimeType' )->willReturn( $mime );
		$file->method( 'getSha1' )->willReturn( $sha1 );

		( new PdfHandlerHooks( $cache ) )
			->onLocalFilePurgeThumbnails( $file, false, [] );

		if ( $expectedDeleted ) {
			$this->assertFalse( $cache->get( $key ) );
		} else {
			$this->assertSame( $data, $cache->get( $key ) );
		}
	}

	public static function provideOnLocalFilePurgeThumbnails() {
		return [
			'PDF file' => [ 'application/pdf', true ],
			'PNG file' => [ 'image/png', false ],
			'Other file' => [ 'text/plain', false ],
		];
	}
}
