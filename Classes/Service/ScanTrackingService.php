<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ScanTrackingService
{
    public function trackResolverHit(int $qrCodeUid, string $targetUrl, ServerRequestInterface $request): void
    {
        $timestamp = time();
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $qrConnection = $connectionPool->getConnectionForTable('tx_dynamicqrcode_domain_model_qrcode');
        $scanConnection = $connectionPool->getConnectionForTable('tx_dynamicqrcode_domain_model_scan');

        $path = $request->getUri()->getPath();
        $host = $request->getUri()->getHost();
        $referer = $request->getHeaderLine('Referer');
        $userAgent = $request->getHeaderLine('User-Agent');
        $ipHash = $this->hashIpAddress($request);
        $isBot = $this->isLikelyBot($userAgent) ? 1 : 0;

        $scanConnection->insert('tx_dynamicqrcode_domain_model_scan', [
            'pid' => 0,
            'tstamp' => $timestamp,
            'crdate' => $timestamp,
            'qr_code' => $qrCodeUid,
            'target_url' => $targetUrl,
            'resolved_path' => mb_substr($path, 0, 255),
            'site_host' => mb_substr($host, 0, 255),
            'referer' => mb_substr($referer, 0, 2048),
            'user_agent' => mb_substr($userAgent, 0, 1024),
            'ip_hash' => $ipHash,
            'is_bot' => $isBot,
        ]);

        $qrConnection->executeStatement(
            'UPDATE tx_dynamicqrcode_domain_model_qrcode
             SET scan_count = scan_count + 1,
                 first_scan_at = CASE WHEN first_scan_at = 0 THEN :timestamp ELSE first_scan_at END,
                 last_scan_at = :timestamp,
                 tstamp = :timestamp
             WHERE uid = :uid',
            [
                'timestamp' => $timestamp,
                'uid' => $qrCodeUid,
            ]
        );
    }

    private function hashIpAddress(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $ipAddress = (string)($serverParams['REMOTE_ADDR'] ?? '');
        if ($ipAddress === '') {
            return '';
        }

        return hash_hmac(
            'sha256',
            $ipAddress,
            (string)$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
        );
    }

    private function isLikelyBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return false;
        }

        return (bool)preg_match(
            '/bot|crawler|spider|preview|facebookexternalhit|slurp|wget|curl|headless|scanner/i',
            $userAgent
        );
    }
}
