<?php
$EM_CONF['magstyleimages'] = [
    'title' => 'Magazine Style Images',
    'description' => 'Implements a new content element "Magazine Images" with very nice looking automatic image layouts for up to eight images.',
    'category' => 'fe',
    'shy' => 0,
    'version' => '0.1.1',
    'dependencies' => '',
    'conflicts' => '',
    'priority' => '',
    'loadOrder' => '',
    'module' => '',
    'state' => 'alpha',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => 'tt_content',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'Julian Hofmann',
    'author_email' => 'julian.hofmann@webenergy.de',
    'author_company' => '',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '8.6.0-8.7.99',
            'fluid_styled_content' => '8.6.0-8.7.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
            'psr-4' => ['Webenergy\\Magstyleimages\\' => 'Classes']
        ]
];