<?php
defined('TYPO3_MODE') || die();

call_user_func(function () {
    // Adds the content element to the "Type" dropdown
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
       [
          'LLL:EXT:magstyleimages/Resources/Private/Language/locallang.xlf:magstyleimages_images.title',
          'magstyleimages_images',
          'EXT:magstyleimages/Resources/Public/Icons/magstyleimages_images.gif'
       ],
       'CType',
       'magstyleimages'
    );
    // use the $GLOBALS['TCA'] definition from textpic
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['showitem'] = $GLOBALS['TCA']['tt_content']['types']['textpic']['showitem'];
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['columnsOverrides'] = $GLOBALS['TCA']['tt_content']['types']['textpic']['columnsOverrides'];
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['columnsOverrides']['image']['config']['maxitems'] = 8;
    $GLOBALS['TCA']['tt_content']['types']['magstyleimages_images']['columnsOverrides']['image']['config']['minitems'] = 1;

    if (TYPO3_MODE == 'BE') {
        $GLOBALS['TBE_MODULES_EXT']['xMOD_db_new_content_el']['addElClasses'][\Webenergy\Magstyleimages\Wizicon::class] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('magstyleimages') . 'Classes/Wizicon.php';
    }
});
