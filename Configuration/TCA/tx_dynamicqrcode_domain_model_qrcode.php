<?php

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:dynamic_qrcode/Resources/Private/Language/locallang_db.xlf:tx_dynamicqrcode_domain_model_qrcode',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:dynamic_qrcode/Resources/Public/Icons/module-qr.svg',
        'searchFields' => 'title,target_url',
        'security' => [
            'ignorePageTypeRestriction' => true
        ]
    ],
        'types' => [
            '1' => [
                'showitem' => '
                    --div--;General,
                        title, target_url, style_preset,
                --div--;Design & Preview,
                    preview_field,
                    fg_color, bg_color, error_correction, size, margin,
                    logo_file, logo_scale, drop_shadow, logo_bg, logo_bg_color, logo_bg_radius, logo_bg_padding,
                    fg_gradient_from, fg_gradient_to, fg_gradient_angle,
                    dot_style, dot_intensity, rounded_modules, eye_style, eye_radius,
                --div--;Analytics,
                    analytics_field, scan_count, first_scan_at, last_scan_at
            '
            ],
        ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    ['label' => '', 'invertStateDisplay' => true]
                ],
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => ['type' => 'datetime'],
        ],
        'endtime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => ['type' => 'datetime'],
        ],
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:dynamic_qrcode/Resources/Private/Language/locallang_db.xlf:tx_dynamicqrcode_domain_model_qrcode.title',
            'config' => ['type' => 'input', 'eval' => 'trim,required']
        ],
        'target_url' => [
            'exclude' => false,
            'label' => 'Target URL',
            'config' => ['type' => 'input', 'eval' => 'trim,required']
        ],
        'style_preset' => [
            'exclude' => false,
            'label' => 'Style preset',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['Custom', 'custom'],
                    ['Dotted Modern', 'dotted_modern'],
                    ['Soft Medical', 'soft_medical'],
                    ['Dark Tech', 'dark_tech'],
                    ['Sunrise Gradient', 'sunrise_gradient'],
                ],
                'default' => 'custom',
            ],
        ],
        'scan_count' => [
            'exclude' => true,
            'label' => 'Scan count',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'int',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'first_scan_at' => [
            'exclude' => true,
            'label' => 'First scan',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'last_scan_at' => [
            'exclude' => true,
            'label' => 'Last scan',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'fg_color' => [
            'exclude' => false,
            'label' => 'Foreground color',
            'config' => ['type' => 'color', 'default' => '#000000']
        ],
        'bg_color' => [
            'exclude' => false,
            'label' => 'Background color',
            'config' => ['type' => 'color', 'default' => '#FFFFFF']
        ],
        'error_correction' => [
            'exclude' => false,
            'label' => 'Error correction (L/M/Q/H)',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['L (7%)', 'L'],
                    ['M (15%)', 'M'],
                    ['Q (25%)', 'Q'],
                    ['H (30%)', 'H'],
                ],
                'default' => 'M'
            ]
        ],
        'size' => [
            'exclude' => false,
            'label' => 'Size (px)',
            'config' => ['type' => 'number', 'range' => ['lower' => 64, 'upper' => 2048], 'default' => 256]
        ],
        'margin' => [
            'exclude' => false,
            'label' => 'Margin',
            'config' => ['type' => 'number', 'range' => ['lower' => 0, 'upper' => 64], 'default' => 2]
        ],
        'logo_file' => [
            'exclude' => false,
            'label' => 'Logo (absolute/relative path or URL)',
            'config' => ['type' => 'input', 'eval' => 'trim']
        ],
        'dot_style' => [
            'exclude' => false,
            'label' => 'Dot style',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['Square', 'square'],
                    ['Rounded', 'rounded'],
                    ['Dots', 'dots'],
                    ['Circles', 'circles'],
                    ['Bubble', 'bubble'],
                    ['Diamond', 'diamond'],
                    ['Soft Square', 'softsquare'],
                ],
                'default' => 'square'
            ]
        ],
        'dot_intensity' => [
            'exclude' => false,
            'label' => 'Rounding intensity (1-10)',
            'config' => [
                'type' => 'number',
                'range' => ['lower' => 1, 'upper' => 10],
                'default' => 5,
                'slider' => [
                    'step' => 1,
                    'width' => 200
                ]
            ]
        ],
        'rounded_modules' => [
            'exclude' => false,
            'label' => 'Rounded modules',
            'config' => [
                'type' => 'check',
                'items' => [['label' => '']],
                'default' => 0
            ]
        ],
        'eye_radius' => [
            'exclude' => false,
            'label' => 'Eye corner radius (0-50)',
            'config' => [
                'type' => 'number',
                'range' => ['lower' => 0, 'upper' => 50],
                'default' => 0,
                'slider' => [
                    'step' => 1,
                    'width' => 200
                ]
            ]
        ],
        'eye_style' => [
            'exclude' => false,
            'label' => 'Eye style',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['Square', 'square'],
                    ['Rounded', 'rounded'],
                    ['Circular', 'circular'],
                    ['Soft Square', 'softsquare'],
                    ['Diamond', 'diamond'],
                ],
                'default' => 'square'
            ]
        ],
        'fg_gradient_from' => [
            'exclude' => false,
            'label' => 'FG Gradient: From',
            'config' => ['type' => 'color', 'default' => '']
        ],
        'fg_gradient_to' => [
            'exclude' => false,
            'label' => 'FG Gradient: To',
            'config' => ['type' => 'color', 'default' => '']
        ],
        'fg_gradient_angle' => [
            'exclude' => false,
            'label' => 'FG Gradient: Angle (°)',
            'config' => ['type' => 'number', 'range' => ['lower' => 0, 'upper' => 360], 'default' => 0]
        ],
        'logo_scale' => [
            'exclude' => false,
            'label' => 'Logo scale (%)',
            'config' => ['type' => 'number', 'range' => ['lower' => 5, 'upper' => 50], 'default' => 30]
        ],
        'logo_bg' => [
            'exclude'=>false,
            'label'=>'Logo background',
            'config'=>['type'=>'check', 'items'=>[['label'=>'']]]
        ],
        'logo_bg_color' => [
            'exclude'=>false,
            'label'=>'Logo bg color',
            'config'=>['type'=>'color', 'default'=>'#FFFFFF']
        ],
        'logo_bg_radius' => [
            'exclude'=>false,
            'label'=>'Logo bg radius (px)',
            'config'=>['type'=>'number', 'range'=>['lower'=>0, 'upper'=>50], 'default'=>8]
        ],
        'logo_bg_padding' => [
            'exclude'=>false,
            'label'=>'Logo padding (px)',
            'config'=>['type'=>'number', 'range'=>['lower'=>0, 'upper'=>20], 'default'=>4]
        ],
        'drop_shadow' => [
            'exclude' => false,
            'label' => 'Drop shadow',
            'config' => ['type' => 'check', 'items' => [['label' => '']]]
        ],
        'preview_field' => [
            'label' => 'Live Preview',
            'config' => [
                'type' => 'user',
                'renderType' => 'dynamicQrcodePreview'
            ],
            'displayCond' => 'FIELD:uid:>:0'
        ],
        'analytics_field' => [
            'label' => 'Analytics',
            'config' => [
                'type' => 'user',
                'renderType' => 'dynamicQrcodeAnalytics'
            ],
            'displayCond' => 'FIELD:uid:>:0'
        ]
    ]
];
