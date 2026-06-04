<?php

return [
    'frontend' => [
        'vendor/dynamic-qrcode/qr-resolver' => [
            'target' => \Vendor\DynamicQrcode\Middleware\QrResolverMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
        'vendor/dynamic-qrcode/svg' => [
            'target' => \Vendor\DynamicQrcode\Middleware\SvgMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
