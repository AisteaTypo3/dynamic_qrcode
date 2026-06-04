<?php

declare(strict_types=1);
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'dynamic-qrcode-record' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:dynamic_qrcode/Resources/Public/Icons/Extension.svg',
    ],
];
