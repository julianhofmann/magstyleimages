<?php

declare(strict_types=1);
namespace Webenergy\Magstyleimages\DataProcessing;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2006 Harvey Kane (Original Script) <info@ragepank.com>
  * (c) 2017-2019 Julian Hofmann <julian.hofmann@webenergy.de>
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

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;

/**
 * Class MagazineStyleImagesProcessor
 */
class MagazineStyleImagesProcessor implements DataProcessorInterface
{
    /**
     * The content object renderer
     *
     * @var ContentObjectRenderer
     */
    protected ContentObjectRenderer $contentObjectRenderer;

    /**
     * The processor configuration
     *
     * @var array
     */
    protected array $processorConfiguration = [];

    /**
     * Matching the tt_content field towards the imageOrient option
     *
     * @var array
     */
    protected static array $availableImagesBlockPositions = [
        'horizontal' => [
            'center' => [0, 8],
            'right' => [1, 9, 17, 25],
            'left' => [2, 10, 18, 26],
        ],
        'vertical' => [
            'above' => [0, 1, 2],
            'intext' => [17, 18, 25, 26],
            'below' => [8, 9, 10],
        ],
    ];

    /**
     * Storage for processed data
     *
     * @var array
     */
    protected array $imagesBlockData = [
        'position' => [
            'horizontal' => '',
            'vertical' => '',
            'noWrap' => false,
        ],
        'width' => 0,
        'count' => [
            'files' => 0,
        ],
        'border' => [
            'enabled' => false,
            'width' => 0,
            'padding' => 0,
        ],
        'images' => [],
        'profile' => '',
    ];

    /**
     * @var int
     */
    protected int $mediaOrientation;

    /**
     * @var int
     */
    protected int $maxImagesBlockWidth;

    /**
     * @var int
     */
    protected int $maxImagesBlockWidthInText;

    /**
     * @var bool
     */
    protected bool $borderEnabled;

    /**
     * @var int
     */
    protected int $borderWidth;

    /**
     * @var int
     */
    protected int $borderPadding;

    /**
     * @var string
     */
    protected string $cropVariant = 'default';

    /**
     * The (filtered) image files to be used in the images block
     *
     * @var FileInterface[]
     */
    protected array $fileObjects = [];

    /**
     * The calculated dimensions for each image element
     *
     * @var array
     */
    protected array $mediaDimensions = [];

    /**
     * Process data for a magazine style image block, for instance the CType "textmedia"
     *
     * @param ContentObjectRenderer $contentObjectRenderer The content object renderer, which contains data of the content element
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     * @throws ContentRenderingException
     */
    public function process(
        ContentObjectRenderer $contentObjectRenderer,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        if (isset($processorConfiguration['if.']) && !$contentObjectRenderer->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $this->contentObjectRenderer = $contentObjectRenderer;
        $this->processorConfiguration = $processorConfiguration;

        $filesProcessedDataKey = (string)$contentObjectRenderer->stdWrapValue(
            'filesProcessedDataKey',
            $processorConfiguration,
            'files'
        );
        if (isset($processedData[$filesProcessedDataKey]) && is_array($processedData[$filesProcessedDataKey])) {
            $this->fileObjects = $this->transpose($processedData[$filesProcessedDataKey]);
            $this->imagesBlockData['count']['files'] = count($this->fileObjects);
        } else {
            throw new ContentRenderingException('No files found for key ' . $filesProcessedDataKey . ' in $processedData.', 1487542549);
        }

        $this->mediaOrientation = (int)$this->getConfigurationValue('mediaOrientation', 'imageorient');
        $this->maxImagesBlockWidth = (int)$this->getConfigurationValue('maxImagesBlockWidth') ?: 600;
        $this->maxImagesBlockWidthInText = (int)$this->getConfigurationValue('maxImagesBlockWidthInText') ?: 300;
        $this->borderEnabled = (bool)$this->getConfigurationValue('borderEnabled', 'imageborder');
        $this->borderWidth = (int)$this->getConfigurationValue('borderWidth');
        $this->borderPadding = (int)$this->getConfigurationValue('borderPadding');

        $this->determineImagesBlockPosition();
        $this->determineMaximumImagesBlockWidth();
        $this->determineProfile();

        $this->arrangeImages();

        $this->prepareImagesBlockData();

        $targetFieldName = (string)$contentObjectRenderer->stdWrapValue(
            'as',
            $processorConfiguration,
            'imagesBlock'
        );

        $processedData[$targetFieldName] = $this->imagesBlockData;

        return $processedData;
    }

    /**
     * Get configuration value from processorConfiguration
     * with when $dataArrayKey fallback to value from cObj->data array
     *
     * @return string
     */
    protected function getConfigurationValue(string $key, ?string $dataArrayKey = null): string
    {
        $defaultValue = '';
        if ($dataArrayKey && isset($this->contentObjectRenderer->data[$dataArrayKey])) {
            $defaultValue = $this->contentObjectRenderer->data[$dataArrayKey];
        }

        return (string)$this->contentObjectRenderer->stdWrapValue(
            $key,
            $this->processorConfiguration,
            $defaultValue
        );
    }

    /**
     * Transposes the fileObjects into landscape and portrait images. Within each group, the sorting will be kept.
     *
     * @return array
     */
    private function transpose(array $fileObjects): array
    {
        $newarr = [];

        // Currently this extension supports only up to 8 images in a block
        if (\count($fileObjects) > 8) {
            $fileObjects = \array_slice($fileObjects, 0, 8);
        }

        foreach ($fileObjects as $i => $fileObject) {
            if ($fileObject->hasProperty('width') === false || $fileObject->hasProperty('height') === false) {
                throw new ContentRenderingException($fileObject->getIdentifier() . ' has either no height or no width', 1487544368);
            }

            if ($fileObject->getProperty('width') > $fileObject->getProperty('height')) {
                $i += 100;
            } else {
                $i += 200;
            }

            $newarr[$i] = $fileObject;
        }

        ksort($newarr);
        return array_values($newarr);
    }

    /**
     * Define the images block position
     *
     * Images block has a horizontal and a vertical position towards the text
     * and a possible wrapping of the text around the images block.
     */
    protected function determineImagesBlockPosition(): void
    {
        foreach (self::$availableImagesBlockPositions as $positionDirectionKey => $positionDirectionValue) {
            foreach ($positionDirectionValue as $positionKey => $positionArray) {
                if (\in_array($this->mediaOrientation, $positionArray, true)) {
                    $this->imagesBlockData['position'][$positionDirectionKey] = $positionKey;
                }
            }
        }

        if ($this->mediaOrientation === 25 || $this->mediaOrientation === 26) {
            $this->imagesBlockData['position']['noWrap'] = true;
        }
    }

    /**
     * Get the images block width based on vertical position
     */
    protected function determineMaximumImagesBlockWidth(): void
    {
        if ($this->imagesBlockData['position']['vertical'] === 'intext') {
            $this->imagesBlockData['width'] = $this->maxImagesBlockWidthInText;
        } else {
            $this->imagesBlockData['width'] = $this->maxImagesBlockWidth;
        }
    }

    /**
     * Profile explains the makeup of the images (landscape vs portrait) so we can use the best layout eg. LPPP or LLLP
     */
    protected function determineProfile(): void
    {
        $profile = '';
        foreach ($this->fileObjects as $fileObject) {
            $profile .= ($fileObject->getProperty('width') > $fileObject->getProperty('height')) ? 'L' : 'P';
        }

        $this->imagesBlockData['profile'] = $profile;
    }

    /**
     * When retrieving the height or width for a media file
     * a possible cropping needs to be taken into account.
     *
     * @param string $dimensionalProperty 'width' or 'height'
     * @return int
     */
    protected function getCroppedDimensionalProperty(FileInterface $file, string $dimensionalProperty): int
    {
        if (!$file->hasProperty('crop') || $file->getProperty('crop') === '') {
            return (int)$file->getProperty($dimensionalProperty);
        }

        $croppingConfiguration = $file->getProperty('crop');
        $cropVariantCollection = CropVariantCollection::create((string)$croppingConfiguration);
        return (int)$cropVariantCollection->getCropArea($this->cropVariant)->makeAbsoluteBasedOnFile($file)->asArray()[$dimensionalProperty];
    }

    /**
     * Prepare the gallery data
     *
     * Make an array for rows, columns and configuration
     */
    protected function prepareImagesBlockData(): void
    {
        foreach (array_keys($this->fileObjects) as $fileKey) {
            $this->imagesBlockData['images'][$fileKey] = [
                'media' => $this->fileObjects[$fileKey],
                'dimensions' => [
                    'width' => $this->mediaDimensions[$fileKey]['width'],
                    'height' => $this->mediaDimensions[$fileKey]['height'],
                ],
            ];
        }

        $this->imagesBlockData['border']['enabled'] = $this->borderEnabled;
        $this->imagesBlockData['border']['width'] = $this->borderWidth;
        $this->imagesBlockData['border']['padding'] = $this->borderPadding;
    }

    /**
     * Arrange up to 6 images
     */
    private function arrangeImages(): void
    {
        switch ($this->imagesBlockData['profile']) {
            case 'L':
            case 'P':
                $this->calculateImagesWidthsAndHeights1a(0);
                break;
            case 'LL':
            case 'LP':
            case 'PP':
                $this->calculateImagesWidthsAndHeights2a(0, 1);
                break;
            case 'LLL':
            case 'LLP':
            case 'LPP':
            case 'PPP':
                $this->calculateImagesWidthsAndHeights3b(0, 1, 2);
                break;
            case 'LLLP':
                $this->calculateImagesWidthsAndHeights4b(0, 1, 2, 3);
                break;
            case 'LPPP':
                $this->calculateImagesWidthsAndHeights3a(1, 2, 3);
                $this->calculateImagesWidthsAndHeights1a(0);
                break;
            case 'LLLL':
            case 'LLPP':
            case 'PPPP':
                $this->calculateImagesWidthsAndHeights2a(2, 0);
                $this->calculateImagesWidthsAndHeights2a(1, 3);
                break;
            case 'LLLLL':
                $this->calculateImagesWidthsAndHeights3a(0, 1, 2);
                $this->calculateImagesWidthsAndHeights2a(3, 4);
                break;
            case 'LLLLP':
            case 'LLLPP':
                $this->calculateImagesWidthsAndHeights3b(0, 1, 4);
                $this->calculateImagesWidthsAndHeights2a(2, 3);
                break;
            case 'LLPPP':
            case 'LPPPP':
                $this->calculateImagesWidthsAndHeights3b(2, 3, 4);
                $this->calculateImagesWidthsAndHeights2a(0, 1);
                break;
            case 'PPPPP':
                $this->calculateImagesWidthsAndHeights2a(4, 0);
                $this->calculateImagesWidthsAndHeights3a(1, 2, 3);
                break;
            case 'LLLLLL':
                $this->calculateImagesWidthsAndHeights2a(0, 1);
                $this->calculateImagesWidthsAndHeights2a(2, 3);
                $this->calculateImagesWidthsAndHeights2a(4, 5);
                break;
            case 'LLLLLP':
                $this->calculateImagesWidthsAndHeights4b(0, 1, 2, 5);
                $this->calculateImagesWidthsAndHeights2a(3, 4);
                break;
            case 'LLLLPP':
                $this->calculateImagesWidthsAndHeights3b(0, 1, 4);
                $this->calculateImagesWidthsAndHeights3b(2, 3, 5);
                break;
            case 'LLLPPP':
                $this->calculateImagesWidthsAndHeights3b(0, 1, 5);
                $this->calculateImagesWidthsAndHeights3c(2, 3, 4);
                break;
            case 'LLPPPP':
                $this->calculateImagesWidthsAndHeights3b(0, 2, 4);
                $this->calculateImagesWidthsAndHeights3b(1, 3, 5);
                break;
            case 'LPPPPP':
                $this->calculateImagesWidthsAndHeights3b(0, 1, 5);
                $this->calculateImagesWidthsAndHeights3a(2, 3, 4);
                break;
            case 'PPPPPP':
                $this->calculateImagesWidthsAndHeights3a(3, 4, 5);
                $this->calculateImagesWidthsAndHeights3a(0, 1, 2);
                break;

            case 'LLLLLLL':
                $this->calculateImagesWidthsAndHeights3a(0, 1, 2);
                $this->calculateImagesWidthsAndHeights2a(3, 4);
                $this->calculateImagesWidthsAndHeights2a(5, 6);
                break;
            case 'LLLLLLP':
                $this->calculateImagesWidthsAndHeights4b(0, 1, 2, 6);
                $this->calculateImagesWidthsAndHeights3a(3, 4, 5);
                break;
            case 'LLLLLPP':
                $this->calculateImagesWidthsAndHeights4b(0, 1, 2, 5);
                $this->calculateImagesWidthsAndHeights3b(3, 4, 6);
                break;
            case 'LLLLPPP':
            case 'LLLPPPP':
                $this->calculateImagesWidthsAndHeights3b(0, 1, 5);
                $this->calculateImagesWidthsAndHeights4b(2, 3, 4, 6);
                break;
            case 'LLPPPPP':
                $this->calculateImagesWidthsAndHeights3a(4, 5, 6);
                $this->calculateImagesWidthsAndHeights2a(0, 1);
                $this->calculateImagesWidthsAndHeights2a(2, 3);
                break;
            case 'LPPPPPP':
                $this->calculateImagesWidthsAndHeights3a(0, 1, 2);
                $this->calculateImagesWidthsAndHeights4b(3, 4, 5, 6);
                break;
            case 'PPPPPPP':
                $this->calculateImagesWidthsAndHeights4a(0, 1, 2, 3);
                $this->calculateImagesWidthsAndHeights3b(4, 5, 6);
                break;

            case 'LLLLLLLL':
                $this->calculateImagesWidthsAndHeights3a(0, 1, 2);
                $this->calculateImagesWidthsAndHeights2a(3, 4);
                $this->calculateImagesWidthsAndHeights3a(5, 6, 7);
                break;
            case 'LLLLLLLP':
                $this->calculateImagesWidthsAndHeights4b(0, 1, 2, 7);
                $this->calculateImagesWidthsAndHeights2a(3, 4);
                $this->calculateImagesWidthsAndHeights2a(5, 6);
                break;
            case 'LLLLLLPP':
            case 'LLLLLPPP':
            case 'LLLLPPPP':
                $this->calculateImagesWidthsAndHeights4b(0, 1, 2, 6);
                $this->calculateImagesWidthsAndHeights4c(3, 4, 5, 7);
                break;
            case 'LLLPPPPP':
                $this->calculateImagesWidthsAndHeights3a(4, 5, 6);
                $this->calculateImagesWidthsAndHeights2a(0, 1);
                $this->calculateImagesWidthsAndHeights3a(2, 3, 7);
                break;
            case 'LLPPPPPP':
            case 'LPPPPPPP':
                $this->calculateImagesWidthsAndHeights3b(5, 6, 7);
                $this->calculateImagesWidthsAndHeights2a(0, 1);
                $this->calculateImagesWidthsAndHeights3c(2, 3, 4);
                break;
            case 'PPPPPPPP':
                $this->calculateImagesWidthsAndHeights4a(0, 1, 2, 3);
                $this->calculateImagesWidthsAndHeights4a(4, 5, 6, 7);
                break;
            default:
        }
    }

    /**
     * IMAGE LAYOUTS
     * =============
     * These layouts are coded based on the number of images.
     * Some fairly heavy mathematics is used to calculate the image sizes and the excellent calculators at
     * http://www.quickmath.com/ were very useful. Each of these layouts outputs a small piece of HTML code with the images.
     * @param mixed $fileKey1
     */

    /**
     * Layout: 111 or 1
     *                1
     *
     * @param int $fileKey1 Index of the image
     */
    private function calculateImagesWidthsAndHeights1a(int $fileKey1): void
    {
        $s = (int)floor($this->imagesBlockData['width'] - ($this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0));

        $this->setMediaDimensions($fileKey1, $s, 0);
    }

    /**
     * Layout: 1122
     * Equation: t = 4p + ha + hb Variable: h
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     */
    private function calculateImagesWidthsAndHeights2a(int $fileKey1, int $fileKey2): void
    {
        $a = (int)$this->fileObjects[$fileKey1]->getProperty('width') / (int)$this->fileObjects[$fileKey1]->getProperty('height');
        $b =(int)$this->fileObjects[$fileKey2]->getProperty('width') / (int)$this->fileObjects[$fileKey2]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        $h1 = (int)floor((4 * $p - $t) / (-$a - $b));

        $this->setMediaDimensions($fileKey1, 0, $h1);
        $this->setMediaDimensions($fileKey2, 0, $h1);
    }

    /**
     * Layout: 1223
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     */
    private function calculateImagesWidthsAndHeights3a(int $fileKey1, int $fileKey2, int $fileKey3): void
    {
        $a =(int)$this->fileObjects[$fileKey3]->getProperty('width') / (int)$this->fileObjects[$fileKey3]->getProperty('height');
        $b = (int)$this->fileObjects[$fileKey1]->getProperty('width') / (int)$this->fileObjects[$fileKey1]->getProperty('height');
        $c = (int)$this->fileObjects[$fileKey2]->getProperty('width') / (int)$this->fileObjects[$fileKey2]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        /**
         * Enter the following data at http://www.hostsrv.com/webmab/app1/MSP/quickmath/02/pageGenerate?site=quickmath&s1=equations&s2=solve&s3=advanced#reply
         * EQUATIONS
         * t = 6p + ah + bh + ch
         * VARIABLES
         * h
         */
        $h1 = (int)floor(
            (6 * $p - $t)
            /
            (-$a - $b - $c)
        );

        $this->setMediaDimensions($fileKey1, 0, $h1);
        $this->setMediaDimensions($fileKey2, 0, $h1);
        $this->setMediaDimensions($fileKey3, 0, $h1);
    }

    /**
     * Layout: 1133
     *         2233
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     */
    private function calculateImagesWidthsAndHeights3b(int $fileKey1, int $fileKey2, int $fileKey3): void
    {
        $a =(int)$this->fileObjects[$fileKey3]->getProperty('width') / (int)$this->fileObjects[$fileKey3]->getProperty('height');
        $b = (int)$this->fileObjects[$fileKey1]->getProperty('width') / (int)$this->fileObjects[$fileKey1]->getProperty('height');
        $c = (int)$this->fileObjects[$fileKey2]->getProperty('width') / (int)$this->fileObjects[$fileKey2]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        /**
         *  Enter the following data at http://www.hostsrv.com/webmab/app1/MSP/quickmath/02/pageGenerate?site=quickmath&s1=equations&s2=solve&s3=advanced#reply
         *  EQUATIONS
         *  x/a = w/b + w/c + 2p
         *  w+x+4p = t
         *  VARIABLES
         *  w
         * x
         */

        /* column with 2 small images */
        $w1 = (int)floor(
            -(
                (2 * $a * $b * $c * $p + 4 * $b * $c * $p - $b * $c * $t)
                /
                ($a * $b + $c * $b + $a * $c)
            )
        );

        /* column with 1 large image */
        $w2 = (int)floor(
            ($a * (-4 * $b * $p + 2 * $b * $c * $p - 4 * $c * $p + $b * $t + $c * $t))
            /
            ($a * $b + $c * $b + $a * $c)
        );

        $this->setMediaDimensions($fileKey3, $w2, 0);
        $this->setMediaDimensions($fileKey1, $w1, 0);
        $this->setMediaDimensions($fileKey2, $w1, 0);
    }

    /**
     * Layout: 3311
     *         3322
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     */
    private function calculateImagesWidthsAndHeights3c(int $fileKey1, int $fileKey2, int $fileKey3): void
    {
        $this->calculateImagesWidthsAndHeights3b($fileKey1, $fileKey2, $fileKey3);
    }

    /**
     * Layout: 1234
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     * @param int $fileKey4 Index of the fourth image
     */
    public function calculateImagesWidthsAndHeights4a(int $fileKey1, int $fileKey2, int $fileKey3, int $fileKey4): void
    {
        $a = (int)$this->fileObjects[$fileKey1]->getProperty('width') / (int)$this->fileObjects[$fileKey1]->getProperty('height');
        $b = (int)$this->fileObjects[$fileKey2]->getProperty('width') / (int)$this->fileObjects[$fileKey2]->getProperty('height');
        $c =(int)$this->fileObjects[$fileKey3]->getProperty('width') / (int)$this->fileObjects[$fileKey3]->getProperty('height');
        $d =(int)$this->fileObjects[$fileKey4]->getProperty('width') / (int)$this->fileObjects[$fileKey4]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        /**
         * Enter the following data at http://www.hostsrv.com/webmab/app1/MSP/quickmath/02/pageGenerate?site=quickmath&s1=equations&s2=solve&s3=advanced#reply
         * EQUATIONS
         * t = 6p + ah + bh + ch + dh
         * VARIABLES
         * h
         */
        $h1 = (int)floor(
            (8 * $p - $t)
            /
            (-$a - $b - $c - $d)
        );

        $this->setMediaDimensions($fileKey1, 0, $h1);
        $this->setMediaDimensions($fileKey2, 0, $h1);
        $this->setMediaDimensions($fileKey3, 0, $h1);
        $this->setMediaDimensions($fileKey4, 0, $h1);
    }

    /**
     * Layout: 11444
     *         22444
     *         33444
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     * @param int $fileKey4 Index of the fourth image
     */
    private function calculateImagesWidthsAndHeights4b(int $fileKey1, int $fileKey2, int $fileKey3, int $fileKey4): void
    {
        $a = (int)$this->fileObjects[$fileKey4]->getProperty('width') / (int)$this->fileObjects[$fileKey4]->getProperty('height');
        $b =(int)$this->fileObjects[$fileKey1]->getProperty('width') / (int)$this->fileObjects[$fileKey1]->getProperty('height');
        $c =(int)$this->fileObjects[$fileKey2]->getProperty('width') / (int)$this->fileObjects[$fileKey2]->getProperty('height');
        $d = (int)$this->fileObjects[$fileKey3]->getProperty('width') / (int)$this->fileObjects[$fileKey3]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        /**
         * Enter the following data at http://www.hostsrv.com/webmab/app1/MSP/quickmath/02/pageGenerate?site=quickmath&s1=equations&s2=solve&s3=advanced#reply
         * EQUATIONS
         * x/a = w/b + w/c + 2p
         * w+x+4p = t
         * VARIABLES
         * w
         * x
         */

        /* column with 3 small images */
        $w1 = (int)floor(
            -(
                (4 * $a * $b * $c * $d * $p + 4 * $b * $c * $d * $p - $b * $c * $d * $t)
                /
                ($a * $b * $c + $a * $d * $c + $b * $d * $c + $a * $b * $d)
            )
        );

        /* column with 1 large image */
        $w2 = (int)floor(
            -(
                (-4 * $p - (-(1 / $c) - (1 / $d) - (1 / $b)) * (4 * $p - $t))
                /
                ((1 / $b) + (1 / $c) + (1 / $d) + (1 / $a))
            )
        );

        $this->setMediaDimensions($fileKey4, $w2, 0);
        $this->setMediaDimensions($fileKey1, $w1, 0);
        $this->setMediaDimensions($fileKey2, $w1, 0);
        $this->setMediaDimensions($fileKey3, $w1, 0);
    }

    /**
     * Layout: 44411
     *         44422
     *         44433
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     * @param int $fileKey4 Index of the fourth image
     */
    private function calculateImagesWidthsAndHeights4c(int $fileKey1, int $fileKey2, int $fileKey3, int $fileKey4): void
    {
        $this->calculateImagesWidthsAndHeights4b($fileKey1, $fileKey2, $fileKey3, $fileKey4);
    }

    /**
     * @throws ContentRenderingException
     */
    private function setMediaDimensions(int $fileKey, int $width = 0, int $height = 0): void
    {
        if ($width > 0) {
            $mediaHeight = floor(
                $this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'height') * ($width / max($this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'width'), 1))
            );
            $mediaWidth = $width . 'c';
        } elseif ($height > 0) {
            $mediaWidth = floor(
                $this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'width') * ($height / max($this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'height'), 1))
            );
            $mediaHeight = $height . 'c';
        } else {
            throw new ContentRenderingException('Neither a width nor a height are set for image with index ' . $fileKey . '.', 1487580086);
        }

        $this->mediaDimensions[$fileKey] = [
            'width' => $mediaWidth,
            'height' => $mediaHeight,
        ];
    }
}
