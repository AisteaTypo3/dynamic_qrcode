<?php

defined('TYPO3') or die();

call_user_func(function () {

    // Register plugin in BE
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'DynamicQrcode',
        'Qrcode',
        'Dynamic QR Code'
    );
});
