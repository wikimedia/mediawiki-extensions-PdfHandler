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

		'ar' => array(
			'pdf_no_xml' => 'لم يمكن أخذ معلومات ميتا من pdf',
			'pdf_page_error' => 'رقم الصفحة ليس في النطاق',
		),

		'de' => array(
			'pdf_no_xml' => 'Keine Metadaten im PDF vorhanden.',
			'pdf_page_error' => 'Seitenzahl außerhalb des Dokumentes.',
		),

		'fr' => array(
			'pdf_no_xml' => 'Ne peut obtenir les métadonnées du fichier PDF',
			'pdf_page_error' => 'Le numéro de page n\'est pas dans l\'étendue.',
		),

		'gl' => array(
			'pdf_no_xml' => 'non se puideron obter os metadatos do pdf',
			'pdf_page_error' => 'o número de páxina non está no rango',
		),

		'hsb' => array(
			'pdf_no_xml' => 'W pdf žane metadaty njejsu.',
			'pdf_page_error' => 'Ličba strony zwonka dokumenta.',
		),

		'nl' => array(
			'pdf_no_xml' => 'de metadata van de PDF kan niet uitgelezen worden',
			'pdf_page_error' => 'paginanummer komt niet voor in document',
		),

		'no' => array(
			'pdf_no_xml' => 'kan ikke hente metadata fra PDF',
			'pdf_page_error' => 'sidenummer ikke innen rekkevidde',
		),

/** Occitan (Occitan)
 * @author Cedric31
 */
		'oc' => array(
			'pdf_no_xml'     => 'Pòt pas obténer las metadonadas del fichièr PDF',
			'pdf_page_error' => "Lo numèro de pagina es pas dins l'espandida.",
		),

		'pl' => array(
			'pdf_no_xml' => 'nie można pobrać metadanych z pliku PDF',
			'pdf_page_error' => 'numer strony poza zakresem',
		),

		'pms' => array(
			'pdf_no_xml' => 'as peulo nen pijesse ij metadat dal pdf',
			'pdf_page_error' => 'ël nùmer ëd pàgina a resta fòra da cole ch\'a-i son',
		),

		'pt' => array(
			'pdf_no_xml' => 'não foi possível obter os metadados do pdf',
			'pdf_page_error' => 'página indisponível neste intervalo',
		),

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
		'stq' => array(
			'pdf_no_xml'     => 'Neen Metadoaten in dät PDF deer.',
			'pdf_page_error' => 'Siedentaal buute dät Dokument.',
		),

		'yue' => array(
			'pdf_no_xml' => '唔能夠響PDF度拎metadata',
			'pdf_page_error' => '頁數唔響範圍度',
		),

		'zh-hans' => array(
			'pdf_no_xml' => '无法在PDF中撷取元数据',
			'pdf_page_error' => '页数不在范围中',
		),

		'zh-hant' => array(
			'pdf_no_xml' => '無法在PDF中擷取元數據',
			'pdf_page_error' => '頁數不在範圍中',
		),
	);

	$messages['zh'] = $messages['zh-hans'];
	$messages['zh-cn'] = $messages['zh-hans'];
	$messages['zh-hk'] = $messages['zh-hant'];
	$messages['zh-sg'] = $messages['zh-hans'];
	$messages['zh-tw'] = $messages['zh-hant'];
	$messages['zh-yue'] = $messages['yue'];

	return $messages;
}
