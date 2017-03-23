<?php
namespace Webenergy\Magstyleimages;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Julian Hofmann <julian.hofmann@webenergy.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
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
 * ************************************************************* */

use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Class that adds the wizard icon.
 *
 * @author        Julian Hofmann <julian.hofmann@webenergy.de>
 * @package       Webenergy\Magstyleimges
 */
class Wizicon
{

    /**
     * Processing the wizard items array
     *
     * @param    array $wizardItems : The wizard items
     *
     * @return    array Modified array with wizard items
     */
    public function proc($wizardItems)
    {
        $LL = $this->includeLocalLang();
        $wizardItems['plugins_tx_magstyleimages_images'] = [
            'icon' => 'EXT:magstyleimages/Resources/Public/Icons/ce_wiz.gif',
            'title' => $this->getLanguage()
                ->getLLL('magstyleimages_images.wizard.title', $LL),
            'description' => $this->getLanguage()
                ->getLLL('magstyleimages_images.wizard.description', $LL),
            'params' => '&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=magstyleimages_images'
        ];

        return $wizardItems;
    }

    /**
     * Get language service
     *
     * @return LanguageService
     */
    protected function getLanguage()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Reads the [extDir]/locallang.xml and returns the $LOCAL_LANG array found in that file.
     *
     * @return  array   The array with language labels
     */
    public function includeLocalLang()
    {
        $llFile = ExtensionManagementUtility::extPath('magstyleimages') . 'Resources/Private/Language/locallang.xlf';
        /** @var XliffParser $parser */
        $parser = GeneralUtility::makeInstance(XliffParser::class);
        $LOCAL_LANG = $parser->getParsedData($llFile, $GLOBALS['LANG']->lang);
        return $LOCAL_LANG;
    }
}
