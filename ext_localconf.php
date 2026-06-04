<?php

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Vendor\DynamicQrcode\Controller\QrCodeController;

call_user_func(function () {
    // Register plugin
    ExtensionUtility::configurePlugin(
        'DynamicQrcode',
        'Qrcode',
        [QrCodeController::class => 'redirect'],
        [QrCodeController::class => 'redirect'] // non-cacheable
    );

    // Redirects automatisch aktualisieren
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        \Vendor\DynamicQrcode\Hooks\DataHandlerHook::class;

    // Register custom form element
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1684747380] = [
        'nodeName' => 'dynamicQrcodePreview',
        'priority' => 40,
        'class' => \Vendor\DynamicQrcode\Form\Element\QrPreviewElement::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1684747381] = [
        'nodeName' => 'dynamicQrcodeAnalytics',
        'priority' => 40,
        'class' => \Vendor\DynamicQrcode\Form\Element\QrAnalyticsElement::class,
    ];

    // Backend route for file download (reuses same middleware URL)
});
