<?php

 /**
  *
  * Copyright (C) 2007 Martin Seidel (Xarax) <jodeldi@gmx.de>
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
  *
  */

	# Not a valid entry point, skip unless MEDIAWIKI is defined
	if (!defined('MEDIAWIKI')) {
		echo "PdfHandler extension";
		exit(1);
	}

	$wgExtensionCredits['other'][] = array(
		'name' => 'PDF Handler',
		'version' => '1.1',
		'author' =>' Xarax',
		'description' => 'Handler for viewing PDF files in image mode',
		'url' => 'http://www.mediawiki.org/wiki/Extension:PdfHandler',
	);

	if ( !isset( $wgPdfOutputExtension ) ) $wgPdfOutputExtension = "jpg";
	if ( !isset( $wgPdfHandlerDpi ) ) $wgPdfHandlerDpi = 150;

	$wgAutoloadClasses['PdfImage'] = dirname(__FILE__) . '/PdfHandler.image.php';
	$wgAutoloadClasses['PdfHandler'] = dirname(__FILE__) . '/PdfHandler_body.php';
	$wgMediaHandlers['application/pdf'] = 'PdfHandler';
	$wgExtensionFunctions[] = 'wfPdfHandlerLoadMessages';

	/* load messages */
	function wfPdfHandlerLoadMessages() {
    		global $wgMessageCache;
		static $msgLoaded = false;

		if ( $msgLoaded )
			return false;

		$msgLoaded = true;
		require( dirname( __FILE__ ) . '/PdfHandler.i18n.php' );

		foreach ( efPdfHandlerMessages() as $lang => $messagesForLang ) {
			$wgMessageCache->addMessages( $messagesForLang, $lang );
		}

		return true;
	}
