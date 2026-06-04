<?php

defined('TYPO3') or die();

(function () {
    // Register plugin
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'DynamicQrcode',
        'Qrcode',
        'Dynamic QR Code'
    );

    // Add FlexForm
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['dynamicqrcode_qrcode'] = 'pi_flexform';
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        'dynamicqrcode_qrcode',
        'FILE:EXT:dynamic_qrcode/Configuration/FlexForms/Qrcode.xml'
    );
})();
