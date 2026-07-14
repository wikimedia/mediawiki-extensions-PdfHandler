<?php

namespace MediaWiki\Extension\PdfHandler\Test\Integration;

use MediaWiki\Extension\PdfHandler\PdfImage;
use MediaWiki\Extension\PdfHandler\PdfMetadataException;
use MediaWiki\FileRepo\File\File;
use MediaWiki\Shell\CommandFactory;
use MediaWikiIntegrationTestCase;
use Shellbox\Command\BoxedCommand;
use Shellbox\Command\BoxedResult;
use StatusValue;
use Wikimedia\TestingAccessWrapper;

/**
 * @group PdfHandler
 * @covers \MediaWiki\Extension\PdfHandler\PdfImage
 */
class PdfImageTest extends MediaWikiIntegrationTestCase {

	private const FILE_NAME = __DIR__ . '/../data/simple.pdf';

	private function markTestSkippedIfNoTools(): void {
		$config = $this->getServiceContainer()->getMainConfig();
		if ( !is_executable( $config->get( 'PdfInfo' ) ) || !is_executable( $config->get( 'PdftoText' ) ) ) {
			$this->markTestSkipped(
				'This test needs the installation of the pdfinfo and pdftotext tools'
			);
		}
	}

	private function assertFixtureMetadata( array $data ): void {
		$this->assertSame( '2', $data['Pages'] ?? null );
		$this->assertSame( 'PdfHandler test fixture', $data['Title'] ?? null );
		$this->assertSame( '612 x 792 pts (letter)', $data['pages'][1]['Page size'] ?? null );
		$this->assertSame( '0', $data['pages'][1]['Page rot'] ?? null );
		$this->assertSame( '90', $data['pages'][2]['Page rot'] ?? null );
		$this->assertArrayHasKey( 'mergedMetadata', $data );
		$this->assertCount( 2, $data['text'] ?? [] );
		$this->assertStringContainsString( 'Hello World', $data['text'][0] );
	}

	public function testRetrieveMetaDataWithPathOnly() {
		$this->markTestSkippedIfNoTools();

		$config = $this->getServiceContainer()->getMainConfig();
		$data = ( new PdfImage( self::FILE_NAME, $config ) )->retrieveMetaData();

		$this->assertFixtureMetadata( $data );
	}

	public function testRetrieveMetaDataWithFile() {
		$this->markTestSkippedIfNoTools();

		// Stand-in for File::addToShellboxCommand() resolving the file on its
		// backend; expects( once() ) verifies the by-reference path is taken.
		$file = $this->createMock( File::class );
		$file->expects( $this->once() )
			->method( 'addToShellboxCommand' )
			->willReturnCallback( static function ( BoxedCommand $command, string $boxedName ) {
				$command->inputFileFromFile( $boxedName, self::FILE_NAME );
				return StatusValue::newGood();
			} );

		$config = $this->getServiceContainer()->getMainConfig();
		$data = ( new PdfImage( self::FILE_NAME, $config, $file ) )->retrieveMetaData();

		$this->assertFixtureMetadata( $data );
	}

	public function testRetrieveMetaDataThrowsOnMissingFile() {
		$command = $this->createMock( BoxedCommand::class );
		foreach ( [
			'disableNetwork', 'firejailDefaultSeccomp', 'routeName',
			'params', 'inputFileFromFile', 'outputFileToString', 'environment',
		] as $method ) {
			$command->method( $method )->willReturnSelf();
		}
		$command->expects( $this->never() )->method( 'execute' );

		$factory = $this->createMock( CommandFactory::class );
		$factory->method( 'createBoxed' )->willReturn( $command );
		$this->setService( 'ShellCommandFactory', $factory );

		$file = $this->createMock( File::class );
		$file->expects( $this->once() )
			->method( 'addToShellboxCommand' )
			->willReturn( StatusValue::newFatal( 'backend-fail-notexists', 'file.pdf' ) );

		$config = $this->getServiceContainer()->getMainConfig();
		$this->expectException( PdfMetadataException::class );
		( new PdfImage( 'missing.pdf', $config, $file ) )->retrieveMetaData();
	}

	/**
	 * Make the shell command factory produce a command returning a canned result
	 *
	 * @param int|null $exitCode
	 * @param array $files Output file contents by name
	 */
	private function mockShellResult( ?int $exitCode, array $files ): void {
		$result = $this->createMock( BoxedResult::class );
		$result->method( 'getExitCode' )->willReturn( $exitCode );
		$result->method( 'getStderr' )->willReturn( '' );
		$result->method( 'getFileContents' )->willReturnCallback(
			static fn ( string $name ) => $files[$name] ?? null
		);
		$result->method( 'wasReceived' )->willReturnCallback(
			static fn ( string $name ) => isset( $files[$name] )
		);

		$command = $this->createMock( BoxedCommand::class );
		foreach ( [
			'disableNetwork', 'firejailDefaultSeccomp', 'routeName',
			'params', 'inputFileFromFile', 'outputFileToString', 'environment',
		] as $method ) {
			$command->method( $method )->willReturnSelf();
		}
		$command->method( 'execute' )->willReturn( $result );

		$factory = $this->createMock( CommandFactory::class );
		$factory->method( 'createBoxed' )->willReturn( $command );
		$this->setService( 'ShellCommandFactory', $factory );
	}

	/**
	 * pdfinfo failing with no output currently yields empty metadata, which is
	 * treated as invalid and re-extracted on every access.
	 */
	public function testRetrieveMetaDataWithNonZeroExitCode() {
		$this->mockShellResult( 1, [] );

		$config = $this->getServiceContainer()->getMainConfig();
		$this->assertSame( [], ( new PdfImage( 'test.pdf', $config ) )->retrieveMetaData() );
	}

	/**
	 * A run killed after emitting a partial page list currently keeps that
	 * truncated output: 'Pages' claims 3, but only one page is described, so
	 * the remaining pages render as 0x0 (T420341).
	 */
	public function testRetrieveMetaDataWhenKilledMidExtraction() {
		$this->mockShellResult( 137, [
			'meta' => '',
			'pages' => "Title: Test\nPages: 3\nPage 1 size: 612 x 792 pts (letter)\nPage 1 rot: 0",
		] );

		$config = $this->getServiceContainer()->getMainConfig();
		$data = ( new PdfImage( 'test.pdf', $config ) )->retrieveMetaData();
		$this->assertSame( '3', $data['Pages'] ?? null );
		$this->assertCount( 1, $data['pages'] ?? [] );
	}

	/**
	 * A successful run whose page list is shorter than 'Pages' is currently
	 * kept as well, with the same 0x0 consequence (T420341).
	 */
	public function testRetrieveMetaDataWithTruncatedPageList() {
		$this->mockShellResult( 0, [
			'meta' => '',
			'pages' => "Title: Test\nPages: 3\nPage 1 size: 612 x 792 pts (letter)\nPage 1 rot: 0",
		] );

		$config = $this->getServiceContainer()->getMainConfig();
		$data = ( new PdfImage( 'test.pdf', $config ) )->retrieveMetaData();
		$this->assertSame( '3', $data['Pages'] ?? null );
		$this->assertCount( 1, $data['pages'] ?? [] );
	}

	private function newPdfImage(): PdfImage {
		return new PdfImage( 'test.pdf', $this->getServiceContainer()->getMainConfig() );
	}

	/**
	 * @dataProvider provideConvertDumpToArray
	 */
	public function testConvertDumpToArray( string $metaDump, string $infoDump, array $expected ) {
		$wrapped = TestingAccessWrapper::newFromObject( $this->newPdfImage() );
		$result = $wrapped->convertDumpToArray( $metaDump, $infoDump );

		if ( $expected === [] ) {
			$this->assertSame( [], $result );
			return;
		}

		foreach ( $expected as $key => $value ) {
			if ( $key === 'mergedMetadata' ) {
				foreach ( $value as $mk => $mv ) {
					$this->assertSame( $mv, $result['mergedMetadata'][$mk] ?? null, "mergedMetadata[$mk]" );
				}
			} else {
				$this->assertSame( $value, $result[$key] ?? null, $key );
			}
		}
	}

	public static function provideConvertDumpToArray() {
		return [
			'key/value parsing' => [
				'',
				"Title: Valid Title\nPages: 1",
				[ 'Title' => 'Valid Title', 'Pages' => '1' ],
			],
			'empty info dump yields empty array' => [
				'Some Meta',
				'',
				[],
			],
			'keywords split and empty keywords are filtered' => [
				'',
				"Keywords: key1   key2 \nPages: 1",
				[ 'mergedMetadata' => [ 'Keywords' => [ 0 => 'key1', 3 => 'key2' ] ] ],
			],
			'timestamps converted to EXIF' => [
				'',
				"CreationTime: 20240101120000\nModTime: 20240101130000\nPages: 1",
				[ 'mergedMetadata' => [
					'DateTimeDigitized' => '2024:01:01 12:00:00',
					'DateTime' => '2024:01:01 13:00:00',
				] ],
			],
			'per-page size and rotation regex' => [
				'',
				"Pages: 1\nPage 1 size: 595 x 841 pts (A4)\nPage 1 rot: 90",
				[ 'pages' => [ 1 => [ 'Page size' => '595 x 841 pts (A4)', 'Page rot' => '90' ] ] ],
			],
			'unique page sizes collected' => [
				'',
				"Page size: 595 x 841 pts (A4)\nPages: 1\nPage 1 size: 595 x 841 pts (A4)",
				[ 'mergedMetadata' => [ 'pdf-PageSize' => [ '595 x 841 pts (A4)' ] ] ],
			],
		];
	}

	/**
	 * @dataProvider provideGetPageSize
	 */
	public function testGetPageSize( array $data, int $page, $expected ) {
		$this->overrideConfigValue( 'PdfHandlerDpi', 72 );
		$this->assertSame( $expected, PdfImage::getPageSize( $data, $page ) );
	}

	public static function provideGetPageSize() {
		return [
			'top-level page size' => [
				[ 'Page size' => '72 x 72' ], 1, [ 'width' => 72, 'height' => 72 ],
			],
			'decimal size with unit suffix is truncated' => [
				[ 'Page size' => '595.276 x 841.89 pts (A4)' ], 1, [ 'width' => 595, 'height' => 841 ],
			],
			'page size from pages array' => [
				[ 'pages' => [ 1 => [ 'Page size' => '144 x 72' ] ] ], 1, [ 'width' => 144, 'height' => 72 ],
			],
			'rotation 90 swaps dimensions' => [
				[ 'Page size' => '144 x 72', 'Page rot' => 90 ], 1, [ 'width' => 72, 'height' => 144 ],
			],
			'rotation 180 keeps dimensions' => [
				[ 'Page size' => '144 x 72', 'Page rot' => 180 ], 1, [ 'width' => 144, 'height' => 72 ],
			],
			'rotation 270 swaps dimensions' => [
				[ 'Page size' => '144 x 72', 'Page rot' => 270 ], 1, [ 'width' => 72, 'height' => 144 ],
			],
			'missing page size' => [
				[ 'Pages' => 1 ], 1, false,
			],
		];
	}
}
