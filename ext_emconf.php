<?php
$EM_CONF['magstyleimages'] = [
    'title' => 'Magazine Style Images',
    'description' => 'Implements a new content element "Magazine Images" with very nice looking automatic image layouts for up to eight images.',
    'category' => 'fe',
    'version' => '1.4.0',
    'state' => 'alpha',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => false,
    'author' => 'Julian Hofmann',
    'author_email' => 'julian.hofmann@webenergy.de',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.23-11.5.99',
            'fluid_styled_content' => '9.5.0-11.5.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => ['Webenergy\\Magstyleimages\\' => 'Classes']
    ]
];
