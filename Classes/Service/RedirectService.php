<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RedirectService
{
    public function createRedirect(int $qrCodeUid, string $targetUrl): string
    {
        error_log('=== RedirectService::createRedirect ===');
        error_log('QR UID: ' . $qrCodeUid);
        error_log('Target URL: ' . $targetUrl);

        $baseUrl = rtrim((string)GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/');
        error_log('Base URL: ' . $baseUrl);

        // URL-Struktur
        $hmac = hash_hmac('sha256', (string)$qrCodeUid, $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        $shortHmac = substr($hmac, 0, 8);
        $sourcePath = '/q/' . $qrCodeUid . '/' . $shortHmac;

        $sourceHost = parse_url($baseUrl, PHP_URL_HOST) ?: '*';

        error_log('Source Path: ' . $sourcePath);
        error_log('Source Host: ' . $sourceHost);

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect');

        try {
            // Prüfen ob Redirect bereits existiert
            $existing = $connection->select(
                ['uid'],
                'sys_redirect',
                [
                    'source_path' => $sourcePath,
                    'source_host' => $sourceHost,
                    'deleted' => 0
                ]
            )->fetchAssociative();

            error_log('Existing redirect: ' . ($existing ? 'YES' : 'NO'));

            $currentTime = time();

            if ($existing) {
                // Redirect aktualisieren (TYPO3 v13 Spaltennamen)
                $result = $connection->update(
                    'sys_redirect',
                    [
                        'target' => $targetUrl,
                        'updatedon' => $currentTime,
                        'disabled' => 0,
                        'target_statuscode' => 302,
                    ],
                    ['uid' => $existing['uid']]
                );
                error_log('Update result: ' . $result);
            } else {
                // Neuen Redirect anlegen (TYPO3 v13 Spaltennamen)
                $data = [
                    'pid' => 0,
                    'createdon' => $currentTime,    // TYPO3 v13: createdon statt crdate
                    'updatedon' => $currentTime,    // TYPO3 v13: updatedon statt tstamp
                    'source_host' => $sourceHost,
                    'source_path' => $sourcePath,
                    'target' => $targetUrl,
                    'target_statuscode' => 302,
                    'disabled' => 0,
                    'is_regexp' => 0,
                    'keep_query_parameters' => 0,
                    'respect_query_parameters' => 0,
                    'hitcount' => 0,                // TYPO3 v13: hitcount statt hit_count
                    'deleted' => 0,
                ];

                error_log('Insert data: ' . print_r($data, true));

                $result = $connection->insert('sys_redirect', $data);
                error_log('Insert result: ' . $result);
                error_log('Last insert ID: ' . $connection->lastInsertId());
            }

            // Redirect-Cache invalidieren
            $this->flushRedirectCache();

        } catch (\Exception $e) {
            error_log('RedirectService Exception: ' . $e->getMessage());
            error_log('Exception Trace: ' . $e->getTraceAsString());
        }

        $finalUrl = $baseUrl . $sourcePath;
        error_log('Final URL: ' . $finalUrl);

        return $finalUrl;
    }

    public function updateRedirect(int $qrCodeUid, string $targetUrl): void
    {
        error_log('=== RedirectService::updateRedirect ===');
        $this->createRedirect($qrCodeUid, $targetUrl);
    }

    private function flushRedirectCache(): void
    {
        try {
            if (class_exists(\TYPO3\CMS\Redirects\Service\RedirectCacheService::class)) {
                $cacheService = GeneralUtility::makeInstance(\TYPO3\CMS\Redirects\Service\RedirectCacheService::class);
                $cacheService->rebuild();
                error_log('Redirect cache flushed');
            }
        } catch (\Throwable $e) {
            error_log('Cache flush error: ' . $e->getMessage());
        }
    }
}
