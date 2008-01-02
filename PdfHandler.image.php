<?php

 /**
  *
  * Copyright (C) 2007 Xarax <jodeldi@gmx.de>
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

 /** 
  * inspired by djvuimage from brion vibber 
  * modified and written by xarax 
  */

class PdfImage {

	function __construct( $filename ) {
		wfUsePHP( '5.1.3' ); // SimpleXMLElement::addChild() was added only then
		$this->mFilename = $filename;
	}

	public function isValid() {
		return true;
	}

	public function getImageSize() {
		global $wgPdfHandlerDpi;

		$tree = new SimpleXMLElement( $this->retrieveMetadata( ) );

		if ( !$tree ) return false;
						
		$o = $tree->BODY[0]->Pagesize;
		
		if ( $o ) {
			$size = explode("x", $o, 2);
															
			if ( $size ) {
				$width  = intval( round( trim($size[0]) / 72 * $wgPdfHandlerDpi ) );
				$height = explode( " ", trim($size[1]), 2 );
				$height = intval( round( trim($height[0]) / 72 * $wgPdfHandlerDpi ) );

				return array( $width, $height, 'Pdf',
					      "width=\"$width\" height=\"$height\"" );
			}
		}
		return false;
	}

	function retrieveMetaData() {
		global $wgPdfInfo;

		if ( $wgPdfInfo ) {
			wfProfileIn( 'pdfinfo' );
			$cmd = wfEscapeShellArg( $wgPdfInfo ) . " " . wfEscapeShellArg( $this->mFilename );
			$dump = wfShellExec( $cmd, $retval );
			$xml = $this->convertDumpToXML( $dump );
			wfProfileOut( 'pdfinfo' );
		} else {
			$xml = null;
		}
		return $xml;
	}

	function createMetadataBody() {
		$xml = "<?xml version=\"1.0\" ?>\n";
		$xml .= "<!DOCTYPE PdfXML PUBLIC \"-//W3C//DTD PdfXML 1.1//EN\" \"pubtext/PdfXML-s.dtd\">\n";
		$xml .= "<PdfXML>\n";
		$xml .= "<HEAD></HEAD>\n";
		$xml .= "<BODY>\n";
		$xml .= "</BODY></PdfXML>";
    		return $xml;
        }

	function convertDumpToXML( $dump ) {
		if ( strval( $dump ) == '' ) return false;

		$xml = $this->createMetadataBody();
		$doc = new SimpleXMLElement($xml);
							
		if (!$doc) return;

		$lines = explode("\n", $dump);

        	for ($i = 0; $i < count($lines); $i++) {
			$value = explode(':', trim($lines[$i]), 2);
			$doc->BODY[0]->addChild(str_replace(' ', '', $value[0]), trim($value[1]));
		}
		return $doc->asXML();
	}
}
