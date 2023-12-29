<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Webenergy\Magstyleimages\Hooks\PageLayoutView\ImagesPreviewRenderer;

ExtensionManagementUtility::addPageTSConfig(
    "@import 'EXT:magstyleimages/Configuration/TsConfig/Page/Mod/Wizards/MagstyleimagesImages.tsconfig'"
);

// Register for hook to show preview of tt_content element of CType="magstyleimages_images" in page module
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem']['magstyleimages_images'] =
    ImagesPreviewRenderer::class;
