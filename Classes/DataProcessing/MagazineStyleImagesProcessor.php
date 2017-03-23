<?php
namespace Webenergy\Magstyleimages\DataProcessing;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2006 Harvey Kane (Original Script) <info@ragepank.com>
  * (c) 2017 Julian Hofmann <julian.hofmann@webenergy.de>
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
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Class MagazineStyleImagesProcessor
 * @package Webenergy\Magstyleimges\DataProcessing
 */
class MagazineStyleImagesProcessor implements DataProcessorInterface
{
    /**
     * The content object renderer
     *
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    /**
     * The processor configuration
     *
     * @var array
     */
    protected $processorConfiguration;

    /**
     * Matching the tt_content field towards the imageOrient option
     *
     * @var array
     */
    protected $availableImagesBlockPositions = [
        'horizontal' => [
            'center' => [0, 8],
            'right' => [1, 9, 17, 25],
            'left' => [2, 10, 18, 26]
        ],
        'vertical' => [
            'above' => [0, 1, 2],
            'intext' => [17, 18, 25, 26],
            'below' => [8, 9, 10]
        ]
    ];

    /**
     * Storage for processed data
     *
     * @var array
     */
    protected $imagesBlockData = [
        'position' => [
            'horizontal' => '',
            'vertical' => '',
            'noWrap' => false
        ],
        'width' => 0,
        'count' => [
            'files' => 0
        ],
        'border' => [
            'enabled' => false,
            'width' => 0,
            'padding' => 0,
        ],
        'images' => [],
        'profile' => ''
    ];

    /**
     * @var int
     */
    protected $mediaOrientation;

    /**
     * @var int
     */
    protected $maxImagesBlockWidth;

    /**
     * @var int
     */
    protected $maxImagesBlockWidthInText;

    /**
     * @var bool
     */
    protected $borderEnabled;

    /**
     * @var int
     */
    protected $borderWidth;

    /**
     * @var int
     */
    protected $borderPadding;

    /**
     * @var string
     */
    protected $cropVariant = 'default';

    /**
     * The (filtered) image files to be used in the images block
     *
     * @var FileInterface[]
     */
    protected $fileObjects = [];

    /**
     * The calculated dimensions for each image element
     *
     * @var array
     */
    protected $mediaDimensions = [];

    /**
     * Process data for a magazine style image block, for instance the CType "textmedia"
     *
     * @param ContentObjectRenderer $cObj The content object renderer, which contains data of the content element
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     * @throws ContentRenderingException
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ) {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $this->contentObjectRenderer = $cObj;
        $this->processorConfiguration = $processorConfiguration;

        $filesProcessedDataKey = (string)$cObj->stdWrapValue(
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

        $targetFieldName = (string)$cObj->stdWrapValue(
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
     * @param string $key
     * @param string|NULL $dataArrayKey
     * @return string
     */
    protected function getConfigurationValue($key, $dataArrayKey = null)
    {
        $defaultValue = '';
        if ($dataArrayKey && isset($this->contentObjectRenderer->data[$dataArrayKey])) {
            $defaultValue = $this->contentObjectRenderer->data[$dataArrayKey];
        }
        return $this->contentObjectRenderer->stdWrapValue(
            $key,
            $this->processorConfiguration,
            $defaultValue
        );
    }

    /**
     * Transposes the fileObjects into landscape and portrait images. Within each group, the sorting will be kept.
     *
     * @param array $fileObjects
     * @return array
     */
    private function transpose($fileObjects)
    {
        $newarr = [];
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
     *
     * @return void
     */
    protected function determineImagesBlockPosition()
    {
        foreach ($this->availableImagesBlockPositions as $positionDirectionKey => $positionDirectionValue) {
            foreach ($positionDirectionValue as $positionKey => $positionArray) {
                if (in_array($this->mediaOrientation, $positionArray, true)) {
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
     *
     * @return void
     */
    protected function determineMaximumImagesBlockWidth()
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
    protected function determineProfile()
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
     * @param FileInterface $fileObject
     * @param string $dimensionalProperty 'width' or 'height'
     *
     * @return int
     */
    protected function getCroppedDimensionalProperty(FileInterface $fileObject, $dimensionalProperty)
    {
        if (!$fileObject->hasProperty('crop') || empty($fileObject->getProperty('crop'))) {
            return $fileObject->getProperty($dimensionalProperty);
        }

        $croppingConfiguration = $fileObject->getProperty('crop');
        $cropVariantCollection = CropVariantCollection::create((string)$croppingConfiguration);
        return (int)$cropVariantCollection->getCropArea($this->cropVariant)->makeAbsoluteBasedOnFile($fileObject)->asArray()[$dimensionalProperty];
    }

    /**
     * Prepare the gallery data
     *
     * Make an array for rows, columns and configuration
     *
     * @return void
     */
    protected function prepareImagesBlockData()
    {
        foreach ($this->fileObjects as $fileKey => $fileObject) {
            $this->imagesBlockData['images'][$fileKey] = [
                'media' => $this->fileObjects[$fileKey],
                'dimensions' => [
                    'width' => $this->mediaDimensions[$fileKey]['width'],
                    'height' => $this->mediaDimensions[$fileKey]['height']
                ]
            ];
        }

        $this->imagesBlockData['border']['enabled'] = $this->borderEnabled;
        $this->imagesBlockData['border']['width'] = $this->borderWidth;
        $this->imagesBlockData['border']['padding'] = $this->borderPadding;
    }


    /**
     * for 6 images
     */
    private function arrangeImages()
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
        }
    }

    /**
     * IMAGE LAYOUTS
     * =============
     * These layouts are coded based on the number of images.
     * Some fairly heavy mathematics is used to calculate the image sizes and the excellent calculators at
     * http://www.quickmath.com/ were very useful. Each of these layouts outputs a small piece of HTML code with the images.
     */

    /**
     * Layout: 111 or 1
     *                1
     *
     * @param int $fileKey1 Index of the image
     */
    private function calculateImagesWidthsAndHeights1a($fileKey1)
    {
        $s = floor($this->imagesBlockData['width'] - ($this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0));

        $this->setMediaDimensions($fileKey1, $s, null);
    }

    /**
     * Layout: 1122
     * Equation: t = 4p + ha + hb Variable: h
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     */
    private function calculateImagesWidthsAndHeights2a($fileKey1, $fileKey2)
    {
        $a = $this->fileObjects[$fileKey1]->getProperty('width') / $this->fileObjects[$fileKey1]->getProperty('height');
        $b = $this->fileObjects[$fileKey2]->getProperty('width') / $this->fileObjects[$fileKey2]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        $h1 = floor((4 * $p - $t) / (-$a - $b));

        $this->setMediaDimensions($fileKey1, null, $h1);
        $this->setMediaDimensions($fileKey2, null, $h1);
    }

    /**
     * Layout: 1223
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     */
    private function calculateImagesWidthsAndHeights3a($fileKey1, $fileKey2, $fileKey3)
    {
        $a = $this->fileObjects[$fileKey3]->getProperty('width') / $this->fileObjects[$fileKey3]->getProperty('height');
        $b = $this->fileObjects[$fileKey1]->getProperty('width') / $this->fileObjects[$fileKey1]->getProperty('height');
        $c = $this->fileObjects[$fileKey2]->getProperty('width') / $this->fileObjects[$fileKey2]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        /**
         * Enter the following data at http://www.hostsrv.com/webmab/app1/MSP/quickmath/02/pageGenerate?site=quickmath&s1=equations&s2=solve&s3=advanced#reply
         * EQUATIONS
         * t = 6p + ah + bh + ch
         * VARIABLES
         * h
         */

        $h1 = floor(
            (6 * $p - $t)
            /
            (-$a - $b - $c)
        );

        $this->setMediaDimensions($fileKey1, null, $h1);
        $this->setMediaDimensions($fileKey2, null, $h1);
        $this->setMediaDimensions($fileKey3, null, $h1);
    }

    /**
     * Layout: 1133
     *         2233
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     */
    private function calculateImagesWidthsAndHeights3b($fileKey1, $fileKey2, $fileKey3)
    {
        $a = $this->fileObjects[$fileKey3]->getProperty('width') / $this->fileObjects[$fileKey3]->getProperty('height');
        $b = $this->fileObjects[$fileKey1]->getProperty('width') / $this->fileObjects[$fileKey1]->getProperty('height');
        $c = $this->fileObjects[$fileKey2]->getProperty('width') / $this->fileObjects[$fileKey2]->getProperty('height');
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
        $w1 = floor(
            -(
                (2 * $a * $b * $c * $p + 4 * $b * $c * $p - $b * $c * $t)
                /
                ($a * $b + $c * $b + $a * $c)
            )
        );

        /* column with 1 large image */
        $w2 = floor(
            ($a * (-4 * $b * $p + 2 * $b * $c * $p - 4 * $c * $p + $b * $t + $c * $t))
            /
            ($a * $b + $c * $b + $a * $c)
        );

        $this->setMediaDimensions($fileKey3, $w2, null);
        $this->setMediaDimensions($fileKey1, $w1, null);
        $this->setMediaDimensions($fileKey2, $w1, null);
    }

    /**
     * Layout: 3311
     *         3322
     *
     * @param int $fileKey1 Index of the first image
     * @param int $fileKey2 Index of the second image
     * @param int $fileKey3 Index of the third image
     */
    private function calculateImagesWidthsAndHeights3c($fileKey1, $fileKey2, $fileKey3)
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
    function calculateImagesWidthsAndHeights4a($fileKey1, $fileKey2, $fileKey3, $fileKey4)
    {
        $a = $this->fileObjects[$fileKey1]->getProperty('width') / $this->fileObjects[$fileKey1]->getProperty('height');
        $b = $this->fileObjects[$fileKey2]->getProperty('width') / $this->fileObjects[$fileKey2]->getProperty('height');
        $c = $this->fileObjects[$fileKey3]->getProperty('width') / $this->fileObjects[$fileKey3]->getProperty('height');
        $d = $this->fileObjects[$fileKey4]->getProperty('width') / $this->fileObjects[$fileKey4]->getProperty('height');
        $t = $this->imagesBlockData['width'];
        $p = $this->borderEnabled ? ($this->borderPadding + $this->borderWidth) : 0;

        /**
         * Enter the following data at http://www.hostsrv.com/webmab/app1/MSP/quickmath/02/pageGenerate?site=quickmath&s1=equations&s2=solve&s3=advanced#reply
         * EQUATIONS
         * t = 6p + ah + bh + ch + dh
         * VARIABLES
         * h
         */

        $h1 = floor(
            (8 * $p - $t)
            /
            (-$a - $b - $c - $d)
        );

        $this->setMediaDimensions($fileKey1, null, $h1);
        $this->setMediaDimensions($fileKey2, null, $h1);
        $this->setMediaDimensions($fileKey3, null, $h1);
        $this->setMediaDimensions($fileKey4, null, $h1);
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
    private function calculateImagesWidthsAndHeights4b($fileKey1, $fileKey2, $fileKey3, $fileKey4)
    {
        $a = $this->fileObjects[$fileKey4]->getProperty('width') / $this->fileObjects[$fileKey4]->getProperty('height');
        $b = $this->fileObjects[$fileKey1]->getProperty('width') / $this->fileObjects[$fileKey1]->getProperty('height');
        $c = $this->fileObjects[$fileKey2]->getProperty('width') / $this->fileObjects[$fileKey2]->getProperty('height');
        $d = $this->fileObjects[$fileKey3]->getProperty('width') / $this->fileObjects[$fileKey3]->getProperty('height');
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
        $w1 = floor(
            -(
                (4 * $a * $b * $c * $d * $p + 4 * $b * $c * $d * $p - $b * $c * $d * $t)
                /
                ($a * $b * $c + $a * $d * $c + $b * $d * $c + $a * $b * $d)
            )
        );

        /* column with 1 large image */
        $w2 = floor(
            -(
                (-4 * $p - (-(1 / $c) - (1 / $d) - (1 / $b)) * (4 * $p - $t))
                /
                ((1 / $b) + (1 / $c) + (1 / $d) + (1 / $a))
            )
        );

        $this->setMediaDimensions($fileKey4, $w2, null);
        $this->setMediaDimensions($fileKey1, $w1, null);
        $this->setMediaDimensions($fileKey2, $w1, null);
        $this->setMediaDimensions($fileKey3, $w1, null);
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
    private function calculateImagesWidthsAndHeights4c($fileKey1, $fileKey2, $fileKey3, $fileKey4)
    {
        $this->calculateImagesWidthsAndHeights4b($fileKey1, $fileKey2, $fileKey3, $fileKey4);
    }

    /**
     * @param int $fileKey
     * @param int $width
     * @param int $height
     * @throws ContentRenderingException
     */
    private function setMediaDimensions($fileKey, $width = 0, $height = 0)
    {
        if ($width) {
            $mediaWidth = $width . 'c';
            $mediaHeight = floor(
                $this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'height') * ($mediaWidth / max($this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'width'), 1))
            );
        } elseif ($height) {
            $mediaHeight = $height . 'c';
            $mediaWidth = floor(
                $this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'width') * ($mediaHeight / max($this->getCroppedDimensionalProperty($this->fileObjects[$fileKey], 'height'), 1))
            );
        } else {
            throw new ContentRenderingException('Neither a width nor a height are set for image with index ' . $fileKey . '.', 1487580086);
        }

        $this->mediaDimensions[$fileKey] = [
            'width' => $mediaWidth,
            'height' => $mediaHeight
        ];
    }
}