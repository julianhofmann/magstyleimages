<?php

$EM_CONF['magstyleimages'] = [
    'title' => 'Magazine Style Images',
    'description' => 'Implements a new content element "Magazine Images" with very nice looking automatic image layouts for up to eight images.',
    'category' => 'fe',
    'version' => '2.0.0',
    'state' => 'alpha',
    'author' => 'Julian Hofmann',
    'author_email' => 'julian.hofmann@webenergy.de',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
            'fluid_styled_content' => '13.4.99-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => ['Webenergy\\Magstyleimages\\' => 'Classes'],
    ],
];
