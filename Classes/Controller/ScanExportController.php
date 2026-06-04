<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\DynamicQrcode\Service\ScanAnalyticsService;

final class ScanExportController
{
    public function exportAction(): ResponseInterface
    {
        $qrUid = (int)($_GET['qrUid'] ?? 0);
        $range = (string)($_GET['range'] ?? '30d');
        if ($qrUid <= 0) {
            $response = new Response();
            $response->getBody()->write('Missing qrUid');
            return $response->withStatus(400);
        }

        $analyticsService = GeneralUtility::makeInstance(ScanAnalyticsService::class);
        $range = $analyticsService->normalizeRange($range);
        $rows = $analyticsService->fetchExportRows($qrUid, $range);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open temporary CSV stream.');
        }

        fputcsv($handle, [
            'qr_uid',
            'qr_title',
            'scanned_at',
            'target_url',
            'resolved_path',
            'site_host',
            'referer',
            'user_agent',
            'ip_hash',
            'is_bot',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['qr_uid'],
                $row['qr_title'],
                $row['scanned_at'],
                $row['target_url'],
                $row['resolved_path'],
                $row['site_host'],
                $row['referer'],
                $row['user_agent'],
                $row['ip_hash'],
                $row['is_bot'],
            ]);
        }

        rewind($handle);
        $csv = (string)stream_get_contents($handle);
        fclose($handle);

        $response = new Response();
        $response = $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="dynamic-qr-scans-' . $qrUid . '-' . $range . '.csv"');
        $response->getBody()->write($csv);

        return $response;
    }
}
