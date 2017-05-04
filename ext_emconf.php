<?php
$EM_CONF['magstyleimages'] = [
    'title' => 'Magazine Style Images',
    'description' => 'Implements a new content element "Magazine Images" with very nice looking automatic image layouts for up to eight images.',
    'category' => 'fe',
    'version' => '0.1.2',
    'module' => '',
    'state' => 'alpha',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'Julian Hofmann',
    'author_email' => 'julian.hofmann@webenergy.de',
    'author_company' => '',
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