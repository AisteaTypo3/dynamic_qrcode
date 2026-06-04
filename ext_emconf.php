<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Dynamic QR Code',
    'description' => 'Create, manage and render dynamic SVG QR codes with backend preview and frontend plugin.',
    'category' => 'fe',
    'author' => 'Yannick Aister',
    'author_email' => 'yannick.aister@medartis.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
