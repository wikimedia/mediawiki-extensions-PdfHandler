<?php
/**
 * Copyright © 2026 Derk-Jan Hartman <hartman.wiki@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\PdfHandler;

use MediaWiki\FileRepo\Hook\LocalFilePurgeThumbnailsHook;
use Wikimedia\ObjectCache\WANObjectCache;

class PdfHandlerHooks implements LocalFilePurgeThumbnailsHook {

	public function __construct( private readonly WANObjectCache $mainWANObjectCache ) {
	}

	/**
	 * Invalidate the dimension info cache when a PDF file page is purged,
	 * so that incomplete or stale metadata does not persist for the full TTL.
	 *
	 * @inheritDoc
	 */
	public function onLocalFilePurgeThumbnails( $file, $archiveName, $urls ): void {
		if ( $file->getMimeType() !== 'application/pdf' ) {
			return;
		}
		$this->mainWANObjectCache->delete(
			$this->mainWANObjectCache->makeKey( 'file-pdf-dimensions', $file->getSha1() )
		);
	}
}
