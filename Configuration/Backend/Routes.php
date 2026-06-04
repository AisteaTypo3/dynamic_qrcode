<?php

return [
    'dynamic_qrcode_export_scans' => [
        'path' => '/dynamic-qrcode/export-scans',
        'target' => \Vendor\DynamicQrcode\Controller\ScanExportController::class . '::exportAction',
    ],
    'dynamic_qrcode_live_preview' => [
        'path' => '/dynamic-qrcode/live-preview',
        'target' => \Vendor\DynamicQrcode\Controller\LivePreviewController::class . '::renderAction',
    ],
];
