<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008 Nikola Stojiljkovic (nikola@essentialdots.com)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * class 'tx_edtransliteration' for the 'ed_transliteracija' extension.
 *
 */
require_once(PATH_tslib.'class.tslib_content.php');

/**
 * Transliterate extension
 *
 * @author	Nikola Stojiljkovic <nikola@essentialdots.com>
 * @package TYPO3
 * @subpackage tx_edtransliteration
 */
class tx_edtransliteration {
	/**
	 * Ext key
	 *
	 * @var string
	 */
	protected $extKey = 'tx_edtransliteration';
	
	/**
	 * Configuration for the current domain
	 *
	 * @var array
	 */
	protected $extConf;	
	
	function encodeTitle($cfg, $pObj) {
		$title = $cfg['title'];
		
		// Fetch character set:
		$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;

		// Convert to lowercase:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $title, 'toLower');

		// Convert some special tokens to the space character:
		$space = isset($cfg['encodingConfiguration']['spaceCharacter']) ? $cfg['encodingConfiguration']['spaceCharacter'] : '-';
		$processedTitle = preg_replace('/[ \-+_]+/', $space, $processedTitle); // convert spaces

		$host = strtolower(t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'));

		$extConf = $TYPO3_CONF_VARS['EXTCONF']['realurl'];
		
		if (isset($extConf[$host])) {
			$this->extConf = $extConf[$host];

			// If it turned out to be a string pointer, then look up the real config:
			while (!is_null($this->extConf) && is_string($this->extConf)) {
				$this->extConf = $extConf[$this->extConf];
			}
			if (!is_array($this->extConf)) {
				$this->extConf = $extConf['_DEFAULT'];
			}
		}
		else {
			$this->extConf = (array)$extConf['_DEFAULT'];
		}		
		
		// Convert extended letters to ascii equivalents:
		if (strlen($this->extConf['init']['transliteration_table']))
		{
			$this->tableFile = $this->extConf['init']['transliteration_table'];
		}
		else
		{
			$this->tableFile = 'typo3conf/ext/ed_transliteration/table_sr.txt';
		}
		$processedTitle = $this->typoTransliterateTags($processedTitle);
		$processedTitle = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($charset, $processedTitle);

		// Strip the rest...:
		$processedTitle = preg_replace('/[^a-zA-Z0-9\\' . $space . ']/', '', $processedTitle); // strip the rest
		$processedTitle =  preg_replace('/\\' . $space . '{2,}/', $space, $processedTitle); // Convert multiple 'spaces' to a single one
		$processedTitle = trim($processedTitle, $space);
		
		return $processedTitle;
	}
	
	function user_transliterate($content,$conf)     {
		if (strlen($conf['transliteration_table']))
		{
			$this->tableFile = $conf['transliteration_table'];
		}
		else
		{
			// no default table here anymore ...
			//$this->tableFile = 'typo3conf/ext/ed_transliteration/table_sr.txt';
			$this->tableFile = '';
		}
		
		$this->regExpReplacements = $conf['regexp_replacements.'];
		
		$content = $this->typoTransliterateTags($content);
	  
		return $content;
	}

	/**
	 * Transliterate the content
	 *
	 * @param	object		$pObj: partent object
	 * @return	void
	 */
	function contentPostProc (&$params) {
		if($params['pObj']) {
			if (intval($params['pObj']->config['config']['transliteration_enable'])==1) {
				if (strlen($params['pObj']->config['config']['transliteration_table']))
				{
					$this->tableFile = $params['pObj']->config['config']['transliteration_table'];
				}
				else
				{
					$this->tableFile = 'typo3conf/ext/ed_transliteration/table_sr.txt';
				}
			} else {
				$this->tableFile = '';
			}
			$this->regExpReplacements = $params['pObj']->config['config']['regexp_replacements.'];
			
			$params['pObj']->content = $this->typoTransliterateTags($params['pObj']->content);
		}
	}
	

	function contentPostProcAll (&$params) {
		// only enter this hook if the page doesn't contains any COA_INT or USER_INT objects
		if ($GLOBALS['TSFE']->isINTincScript()) {
			return true;
		}

		return $this->contentPostProc($params);
	}
	
	public function contentPostProcOutput(&$params) {
		// only enter this hook if the page contains COA_INT or USER_INT objects
		if (!$GLOBALS['TSFE']->isINTincScript()) {
			return true;
		}

		return $this->contentPostProc($params);
	}
	
	function typoTransliterateTags(&$body) {
		$this->cObj = t3lib_div::makeInstance('tslib_cObj');
		
		$expBody = preg_split('/\<\!\-\-[\s]?TRANSLITERATE_/',$body);

		if(count($expBody)>1) {
			$body = '';

			foreach($expBody as $val)    {
				$part = explode('-->',$val,2);
				if(trim($part[0])=='begin') {
					$body.= $prev.$this->transliterate($part[1]);
					$prev = '';
				} elseif(trim($part[0])=='end') {
					$body.= $this->transliterate($prev);
					$prev = '';
					$body.= $part[1];
				} else {
					$prev = $val;
				}
			}
			return $body;
		} else {
			$body = $this->transliterate($body);
			return $body;
		}
	}
	
	function transliterate($input) {
		if ($this->tableFile) {
			$tableStr = file_get_contents($this->tableFile);
			if (is_object($GLOBALS['LANG']))	{
				$csConvObj = &$GLOBALS['LANG']->csConvObj;
			} elseif (is_object($GLOBALS['TSFE']))	{
				$csConvObj = &$GLOBALS['TSFE']->csConvObj;
			} else {
				$csConvObj = t3lib_div::makeInstance('t3lib_cs');
			}
			
         	   	if ($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'])  {
	                    		// when forceCharset is set, we store ALL labels in this charset!!!
	                	$targetCharset = $csConvObj->parse_charset($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']);
	            	} else {
                		$targetCharset = $csConvObj->parse_charset($csConvObj->charSetArray[$langKey] ? $csConvObj->charSetArray[$langKey] : 'iso-8859-1');
            		}			
			$tableStr = $csConvObj->utf8_decode($tableStr, $targetCharset);
			$tableArr = explode("\n", $tableStr);
	
			$transTable = array();
	
			foreach ($tableArr as $line) {
				$t = explode(',', $line);
				$transTable[trim($t[0])] = trim($t[1]);
			}
			
			$result = strtr($input, $transTable);
		} else {
			$result = $input;	
		}
		
		$result = $this->processRegExpReplacements($result);
		
		return $result;
	}
	
	function processRegExpReplacements($input) {
		$result = $input;
		
		if ($this->regExpReplacements) {
			foreach ($this->regExpReplacements as $replacement) {
				$this->currentReplacement = $replacement;
				
				if ($replacement['recursive']) {
					$this->matchFound = true;
					$recursionCount = 0;
					while ($this->matchFound && $recursionCount<100) {
						$this->matchFound = false;
						$recursionCount++;
						$temp_result = preg_replace_callback($replacement['source'], array( &$this, 'preg_callback_url'), $result);
						if (PREG_NO_ERROR === preg_last_error())
						{
						    $result = $temp_result;
						} else {
							// replacement failed due to pcre.backtrack_limit
						    error_log('replacement failed due to pcre.backtrack_limit: '.print_r($replacement, 1));
						}
					}	
				} else {
					
					$temp_result = preg_replace_callback($replacement['source'], array( &$this, 'preg_callback_url'), $result);
					if (PREG_NO_ERROR === preg_last_error())
					{
					    $result = $temp_result;
					} else {
						// replacement failed due to pcre.backtrack_limit
						    error_log('replacement failed due to pcre.backtrack_limit: '.print_r($replacement, 1));
					}
				}
			}
		}
		
		return $result;
	}
	

	function preg_callback_url($matches) {
		$this->matchFound = true;

		foreach ($matches as $k =>$v) {
			$this->cObj->LOAD_REGISTER(array(
				'match'.$k => $v,
			), '');
			
		}

		return $this->cObj->stdWrap($this->currentReplacement['destination'], $this->currentReplacement['destination.']);
	} 	
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ed_transliteration/class.tx_edtransliteration.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ed_transliteration/class.tx_edtransliteration.php']);
}
?>