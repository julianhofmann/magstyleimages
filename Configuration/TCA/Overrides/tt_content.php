<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Webenergy\Magstyleimages\Preview\MsiPreviewRenderer;

defined('TYPO3') || die();

call_user_func(static function (): void {
    // Adds the content element to the "Type" dropdown
    ExtensionManagementUtility::addPlugin(
        [
            'LLL:EXT:magstyleimages/Resources/Private/Language/locallang.xlf:magstyleimages_images.title',
            'magstyleimages_images',
            'EXT:magstyleimages/Resources/Public/Icons/ce_wiz.gif',
        ],
        'CType',
        'magstyleimages'
    );
    // use the $GLOBALS['TCA'] definition from textpic
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['showitem'] = $GLOBALS['TCA']['tt_content']['types']['textpic']['showitem'];
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['columnsOverrides'] = $GLOBALS['TCA']['tt_content']['types']['textpic']['columnsOverrides'];
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['columnsOverrides']['image']['config']['maxitems'] = 8;
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['columnsOverrides']['image']['config']['minitems'] = 1;

    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['previewRenderer']
        = MsiPreviewRenderer::class;
});
