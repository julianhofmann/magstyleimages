<?php

declare(strict_types=1);
namespace Webenergy\Magstyleimages\Hooks\PageLayoutView;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2017-2019 Julian Hofmann <julian.hofmann@webenergy.de>
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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutViewDrawItemHookInterface;

/**
 * Contains a preview rendering for the page module of CType="magstyle_images"
 */
class ImagesPreviewRenderer implements PageLayoutViewDrawItemHookInterface
{
    /**
     * Preprocesses the preview rendering of a content element of type "Magazine Style Images"
     *
     * @param \TYPO3\CMS\Backend\View\PageLayoutView $pageLayoutView Calling parent object
     * @param bool $drawItem Whether to draw the item using the default functionality
     * @param string $headerContent Header content
     * @param string $itemContent Item content
     * @param array $row Record row of tt_content
     */
    public function preProcess(
        PageLayoutView &$pageLayoutView,
        &$drawItem,
        &$headerContent,
        &$itemContent,
        array &$row
    ): void {
        if ($row['CType'] === 'magstyleimages_images') {
            if ($row['bodytext']) {
                $bodytext = $pageLayoutView->renderText($row['bodytext']);
                $maxLength = 250;
                $itemContent .= $pageLayoutView->linkEditContent(substr((string)$bodytext, 0, $maxLength), $row) . (\strlen((string)$bodytext) > $maxLength ? '...' : '') . '<br />';
            }

            if ($row['image']) {
                $itemContent .= $pageLayoutView->linkEditContent($pageLayoutView->getThumbCodeUnlinked($row, 'tt_content', 'image'), $row) . '<br />';

                $fileReferences = BackendUtility::resolveFileReferences('tt_content', 'image', $row);

                if ($fileReferences !== null && $fileReferences !== []) {
                    $linkedContent = '';

                    foreach ($fileReferences as $fileReference) {
                        if ($fileReference->getDescription() !== '' && $fileReference->getDescription() !== '0') {
                            $linkedContent .= htmlspecialchars((string)$fileReference->getDescription()) . '<br />';
                        }
                    }

                    if ($linkedContent !== '' && $linkedContent !== '0') {
                        $itemContent .= $pageLayoutView->linkEditContent($linkedContent, $row);
                    }

                    unset($linkedContent);
                }
            }

            $drawItem = false;
        }
    }
}
