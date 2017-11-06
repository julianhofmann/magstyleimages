<?php
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
    mod.wizards.newContentElement.wizardItems.common {
        elements {
            magstyleimages_images {
                iconIdentifier = your-icon-identifier
                title = EXT:magstyleimages/Resources/Private/Language/locallang.xlf:magstyleimages_images.wizard.title
                description = EXT:magstyleimages/Resources/Private/Language/locallang.xlf:magstyleimages_images.wizard.description
                tt_content_defValues {
                    CType = magstyleimages_images
                }
            }
        }
    }
    mod.wizards.newContentElement.wizardItems.common.show := addToList(magstyleimages_images)
');

// Register for hook to show preview of tt_content element of CType="magstyleimages_images" in page module
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem']['magstyleimages_images'] =
   \Webenergy\Magstyleimages\Hooks\PageLayoutView\ImagesPreviewRenderer::class;
