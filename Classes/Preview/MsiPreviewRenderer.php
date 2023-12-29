<?php

declare(strict_types=1);
namespace Webenergy\Magstyleimages\Preview;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2017-2023 Julian Hofmann <julian.hofmann@webenergy.de>
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

use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;

/**
 * Contains a preview rendering for the page module of CType="magstyle_images"
 */
class MsiPreviewRenderer extends StandardContentPreviewRenderer
{
    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function renderPageModulePreviewContent(GridColumnItem $gridColumnItem): string
    {
        $itemContent = '';
        $row = $gridColumnItem->getRecord();

        if ($row['bodytext']) {
            $bodytext = $this->renderText($row['bodytext']);
            $maxLength = 250;
            $itemContent .= $this->linkEditContent(substr((string)$bodytext, 0, $maxLength), $row) . (\strlen((string)$bodytext) > $maxLength ? '...' : '') . '<br />';
        }

        if ($row['image']) {
            $itemContent .= $this->linkEditContent($this->getThumbCodeUnlinked($row, 'tt_content', 'image'), $row) . '<br />';

            $fileReferences = BackendUtility::resolveFileReferences('tt_content', 'image', $row);

            if ($fileReferences !== null && $fileReferences !== []) {
                $linkedContent = '';

                foreach ($fileReferences as $fileReference) {
                    if (!in_array($fileReference->getDescription(), ['', '0'])) {
                        $linkedContent .= htmlspecialchars((string)$fileReference->getDescription(), ENT_QUOTES | ENT_HTML5) . '<br />';
                    }
                }

                if (!in_array($linkedContent, ['', '0'])) {
                    $itemContent .= $this->linkEditContent($linkedContent, $row);
                }

                unset($linkedContent);
            }
        }

        return $itemContent;
    }
}
