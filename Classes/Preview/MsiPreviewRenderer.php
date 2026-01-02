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
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Contains a preview rendering for the page module of CType="magstyle_images"
 */
class MsiPreviewRenderer extends StandardContentPreviewRenderer
{
    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    #[\Override]
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $itemContent = '';
        $record = $item->getRecord();
        $recordObj = GeneralUtility::makeInstance(RecordFactory::class)->createResolvedRecordFromDatabaseRow('tt_content', $record);

        if ($record['bodytext']) {
            $bodytext = $this->renderText($record['bodytext']);
            $maxLength = 250;
            $itemContent .= $this->linkEditContent(substr((string)$bodytext, 0, $maxLength), $record) . (\strlen((string)$bodytext) > $maxLength ? '...' : '') . '<br />';
        }

        if ($recordObj->has('image') && ($image = $recordObj->get('image'))) {
            $itemContent .= $this->linkEditContent($this->getThumbCodeUnlinked($image), $record) . '<br />';

            $fileReferences = BackendUtility::resolveFileReferences('tt_content', 'image', $record);

            if ($fileReferences !== null && $fileReferences !== []) {
                $linkedContent = '';

                foreach ($fileReferences as $fileReference) {
                    if (!in_array($fileReference->getDescription(), ['', '0'])) {
                        $linkedContent .= htmlspecialchars((string)$fileReference->getDescription(), ENT_QUOTES | ENT_HTML5) . '<br />';
                    }
                }

                if (!in_array($linkedContent, ['', '0'])) {
                    $itemContent .= $this->linkEditContent($linkedContent, $record);
                }

                unset($linkedContent);
            }
        }

        return $itemContent;
    }
}
