<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE == 'FE') {
	require_once(t3lib_extMgm::extPath('ed_transliteration').'class.tx_edtransliteration.php');
	
		// Transliterate the content
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all'][] =
		'EXT:ed_transliteration/class.tx_edtransliteration.php:&tx_edtransliteration->contentPostProcAll';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-output'][] =
		'EXT:ed_transliteration/class.tx_edtransliteration.php:&tx_edtransliteration->contentPostProcOutput';
}

?>