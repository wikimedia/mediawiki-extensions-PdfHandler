<?php

/**
 * Internationalisation file for PDFHandler extension
 */
function efPdfHandlerMessages() {

	$messages = array(
		'en' => array(
			'pdf_no_xml' => 'cannot get metadata from pdf',
			'pdf_page_error' => 'page number not in range',
	),

		'de' => array(
			'pdf_no_xml' => 'Keine Metadaten im PDF vorhanden.',
			'pdf_page_error' => 'Seitenzahl außerhalb des Dokumentes.',
	),
);

	return $messages;
}
