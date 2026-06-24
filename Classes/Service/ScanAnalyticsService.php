<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ScanAnalyticsService
{
    public function buildForQrCode(int $qrCodeUid, string $range = '30d'): array
    {
        $range = $this->normalizeRange($range);
        $rows = $this->fetchScanRows($qrCodeUid, $range, false);
        $today = strtotime('today');
        $dailyCounts = [];
        $refererHosts = [];
        $latestScans = [];
        $scanCount = count($rows);
        $botScans = 0;
        $humanScans = 0;
        $uniqueKeys = [];
        $excludedScanCount = $this->countExcludedScanRows($qrCodeUid, $range);

        foreach ($rows as $index => $row) {
            $timestamp = (int)$row['crdate'];
            $isBot = (int)$row['is_bot'] === 1;
            $dayKey = date('Y-m-d', $timestamp);
            $refererHost = $this->extractHost((string)$row['referer']);

            $dailyCounts[$dayKey] = ($dailyCounts[$dayKey] ?? 0) + 1;
            if ($refererHost !== '-') {
                $refererHosts[$refererHost] = ($refererHosts[$refererHost] ?? 0) + 1;
            }

            if ($isBot) {
                $botScans++;
            } else {
                $humanScans++;
                $uniqueKey = $this->buildUniqueKey(
                    (string)($row['ip_hash'] ?? ''),
                    (string)($row['user_agent'] ?? ''),
                    $timestamp
                );
                if ($uniqueKey !== null) {
                    $uniqueKeys[$uniqueKey] = true;
                }
            }

            if ($index < 10) {
                $latestScans[] = [
                    'timestamp' => $timestamp,
                    'site_host' => (string)$row['site_host'],
                    'referer' => (string)$row['referer'],
                    'referer_host' => $refererHost,
                    'is_bot' => $isBot,
                    'target_url' => (string)$row['target_url'],
                ];
            }
        }

        krsort($dailyCounts);
        arsort($refererHosts);

        $dailySeries = [];
        $dayRange = $this->resolveDayRange($range, count($dailyCounts));
        for ($offset = $dayRange - 1; $offset >= 0; $offset--) {
            $dayTimestamp = strtotime('-' . $offset . ' days', $today);
            $dayKey = date('Y-m-d', $dayTimestamp);
            $dailySeries[] = [
                'label' => date('d.m', $dayTimestamp),
                'count' => $dailyCounts[$dayKey] ?? 0,
            ];
        }

        return [
            'scanCount' => $scanCount,
            'uniqueScanCount' => count($uniqueKeys),
            'botScans' => $botScans,
            'humanScans' => $humanScans,
            'excludedScanCount' => $excludedScanCount,
            'recentScans' => $latestScans,
            'dailySeries' => $dailySeries,
            'topReferers' => array_slice($refererHosts, 0, 5, true),
            'range' => $range,
            'rangeLabel' => $this->resolveRangeLabel($range),
        ];
    }

    public function fetchExportRows(int $qrCodeUid, string $range = '30d'): array
    {
        $range = $this->normalizeRange($range);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dynamicqrcode_domain_model_scan');

        $queryBuilder
            ->select(
                'scan.qr_code AS qr_uid',
                'qr.title AS qr_title',
                'scan.crdate AS scanned_at',
                'scan.target_url',
                'scan.resolved_path',
                'scan.site_host',
                'scan.referer',
                'scan.user_agent',
                'scan.ip_hash',
                'scan.is_bot',
                'scan.is_excluded',
                'scan.excluded_reason'
            )
            ->from('tx_dynamicqrcode_domain_model_scan', 'scan')
            ->leftJoin(
                'scan',
                'tx_dynamicqrcode_domain_model_qrcode',
                'qr',
                $queryBuilder->expr()->eq('scan.qr_code', 'qr.uid')
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'scan.qr_code',
                    $queryBuilder->createNamedParameter($qrCodeUid, ParameterType::INTEGER)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'scan.is_excluded',
                    $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)
                )
            )
            ->orderBy('scan.crdate', 'DESC');

        $threshold = $this->resolveThreshold($range);
        if ($threshold !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte(
                    'scan.crdate',
                    $queryBuilder->createNamedParameter($threshold, ParameterType::INTEGER)
                )
            );
        }

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        foreach ($rows as &$row) {
            $row['scanned_at'] = $this->formatTimestamp((int)$row['scanned_at']);
            $row['is_bot'] = (int)$row['is_bot'] === 1 ? '1' : '0';
        }
        unset($row);

        return $rows;
    }

    public function normalizeRange(string $range): string
    {
        return in_array($range, ['7d', '30d', '90d', 'all'], true) ? $range : '30d';
    }

    private function fetchScanRows(int $qrCodeUid, string $range, bool $includeExcluded): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dynamicqrcode_domain_model_scan');

        $queryBuilder
            ->select('crdate', 'site_host', 'referer', 'is_bot', 'target_url', 'ip_hash', 'user_agent')
            ->from('tx_dynamicqrcode_domain_model_scan')
            ->where(
                $queryBuilder->expr()->eq(
                    'qr_code',
                    $queryBuilder->createNamedParameter($qrCodeUid, ParameterType::INTEGER)
                )
            )
            ->orderBy('crdate', 'DESC');

        if (!$includeExcluded) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'is_excluded',
                    $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)
                )
            );
        }

        $threshold = $this->resolveThreshold($range);
        if ($threshold !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte(
                    'crdate',
                    $queryBuilder->createNamedParameter($threshold, ParameterType::INTEGER)
                )
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    private function countExcludedScanRows(int $qrCodeUid, string $range): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dynamicqrcode_domain_model_scan');

        $queryBuilder
            ->count('uid')
            ->from('tx_dynamicqrcode_domain_model_scan')
            ->where(
                $queryBuilder->expr()->eq(
                    'qr_code',
                    $queryBuilder->createNamedParameter($qrCodeUid, ParameterType::INTEGER)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'is_excluded',
                    $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)
                )
            );

        $threshold = $this->resolveThreshold($range);
        if ($threshold !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte(
                    'crdate',
                    $queryBuilder->createNamedParameter($threshold, ParameterType::INTEGER)
                )
            );
        }

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    private function resolveThreshold(string $range): ?int
    {
        return match ($range) {
            '7d' => strtotime('-7 days'),
            '30d' => strtotime('-30 days'),
            '90d' => strtotime('-90 days'),
            default => null,
        };
    }

    private function resolveDayRange(string $range, int $availableDays): int
    {
        return match ($range) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => max(14, min(365, max(1, $availableDays))),
        };
    }

    private function resolveRangeLabel(string $range): string
    {
        return match ($range) {
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
            default => 'All time',
        };
    }

    private function buildUniqueKey(string $ipHash, string $userAgent, int $timestamp): ?string
    {
        if ($ipHash === '' || $userAgent === '' || $timestamp <= 0) {
            return null;
        }

        return $ipHash . '|' . sha1($userAgent) . '|' . date('Y-m-d', $timestamp);
    }

    private function extractHost(string $url): string
    {
        if ($url === '') {
            return '-';
        }

        return (string)(parse_url($url, PHP_URL_HOST) ?: $url);
    }

    private function formatTimestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
