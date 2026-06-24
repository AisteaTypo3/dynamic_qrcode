<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ScanTrackingService
{
    private const EXTENSION_KEY = 'dynamic_qrcode';

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
        $clientIp = $this->resolveClientIp($request);
        $ipHash = $this->hashIpAddress($clientIp);
        $isBot = $this->isLikelyBot($userAgent) ? 1 : 0;
        $excludedReason = $this->resolveExcludedReason($clientIp);
        $isExcluded = $excludedReason !== '' ? 1 : 0;

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
            'is_excluded' => $isExcluded,
            'excluded_reason' => mb_substr($excludedReason, 0, 100),
        ]);

        if ($isExcluded === 0) {
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
    }

    private function hashIpAddress(string $ipAddress): string
    {
        if ($ipAddress === '') {
            return '';
        }

        return hash_hmac(
            'sha256',
            $ipAddress,
            (string)$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
        );
    }

    private function resolveClientIp(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if ($normalizedParams instanceof NormalizedParams) {
            $remoteAddress = trim($normalizedParams->getRemoteAddress());
            if (filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false) {
                return $remoteAddress;
            }
        }

        foreach (['X-Forwarded-For', 'CF-Connecting-IP', 'X-Real-IP'] as $headerName) {
            $headerValue = trim($request->getHeaderLine($headerName));
            if ($headerValue === '') {
                continue;
            }

            if ($headerName === 'X-Forwarded-For') {
                $parts = array_map('trim', explode(',', $headerValue));
                foreach ($parts as $part) {
                    if (filter_var($part, FILTER_VALIDATE_IP) !== false) {
                        return $part;
                    }
                }
                continue;
            }

            if (filter_var($headerValue, FILTER_VALIDATE_IP) !== false) {
                return $headerValue;
            }
        }

        $serverParams = $request->getServerParams();
        $remoteAddress = (string)($serverParams['REMOTE_ADDR'] ?? '');

        return filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false ? $remoteAddress : '';
    }

    public function resolveClientIpForDebug(ServerRequestInterface $request): string
    {
        return $this->resolveClientIp($request);
    }

    public function resolveExcludedReasonForIp(string $clientIp): string
    {
        return $this->resolveExcludedReason($clientIp);
    }

    /**
     * @return string[]
     */
    public function getConfiguredExcludedIpRanges(): array
    {
        return array_map(
            static fn (array $rule): string => $rule['display'],
            $this->resolveExcludedIpRules()
        );
    }

    private function resolveExcludedReason(string $clientIp): string
    {
        if ($clientIp === '') {
            return '';
        }

        foreach ($this->resolveExcludedIpRules() as $rule) {
            if ($this->ipMatchesRange($clientIp, $rule['value'])) {
                return $rule['reason'];
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function resolveExcludedIpRules(): array
    {
        try {
            $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(self::EXTENSION_KEY);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($configuration)) {
            return [];
        }

        $rawRanges = trim((string)($configuration['excludeIpRanges'] ?? ''));
        if ($rawRanges === '') {
            return [];
        }

        $lines = preg_split('/[\r\n,;]+/', $rawRanges) ?: [];
        $rules = [];

        foreach ($lines as $line) {
            $rule = $this->parseExcludedIpRule($line);
            if ($rule === null) {
                continue;
            }

            $ruleKey = $rule['label'] . '|' . $rule['value'];
            $rules[$ruleKey] = $rule;
        }

        return array_values($rules);
    }

    /**
     * @return array{label:string,value:string,reason:string,display:string}|null
     */
    private function parseExcludedIpRule(string $rawLine): ?array
    {
        $line = trim($rawLine);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        $line = trim((string)preg_replace('/\s+#.*$/', '', $line));
        if ($line === '') {
            return null;
        }

        $label = '';
        $value = $line;
        if (preg_match('/^([A-Za-z0-9_-][A-Za-z0-9 _-]*):\s*(.+)$/', $line, $matches) === 1) {
            $label = trim($matches[1]);
            $value = trim($matches[2]);
        }

        $normalizedValue = $this->normalizeIpRuleValue($value);
        if ($normalizedValue === null) {
            return null;
        }

        $reason = $label !== '' ? 'ip_group:' . $label . ':' . $normalizedValue : 'ip_range:' . $normalizedValue;
        $display = $label !== '' ? $label . ': ' . $normalizedValue : $normalizedValue;

        return [
            'label' => $label,
            'value' => $normalizedValue,
            'reason' => $reason,
            'display' => $display,
        ];
    }

    private function normalizeIpRuleValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (strpos($value, '/') === false) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
        }

        [$ipPart, $prefixPart] = array_map('trim', explode('/', $value, 2));
        if (filter_var($ipPart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if (!ctype_digit($prefixPart)) {
                return null;
            }
            $prefix = (int)$prefixPart;
            if ($prefix < 0 || $prefix > 32) {
                return null;
            }

            return $prefix === 32 ? $ipPart : $ipPart . '/' . $prefix;
        }

        if (filter_var($ipPart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            if (!ctype_digit($prefixPart)) {
                return null;
            }
            $prefix = (int)$prefixPart;
            if ($prefix < 0 || $prefix > 128) {
                return null;
            }

            return $ipPart . '/' . $prefix;
        }

        return null;
    }

    private function ipMatchesRange(string $ipAddress, string $range): bool
    {
        return GeneralUtility::cmpIP($ipAddress, $range);
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
