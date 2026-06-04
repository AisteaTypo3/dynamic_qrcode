<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\DynamicQrcode\Service\ScanAnalyticsService;

final class QrAnalyticsElement extends AbstractFormElement
{
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $row = $this->data['databaseRow'] ?? [];
        $uid = (int)($row['uid'] ?? 0);
        $range = $this->resolveRange();

        if ($uid <= 0) {
            $resultArray['html'] = '<div class="alert alert-warning">Analytics sind erst nach dem ersten Speichern verfuegbar.</div>';
            return $resultArray;
        }

        $analyticsService = GeneralUtility::makeInstance(ScanAnalyticsService::class);
        $analytics = $analyticsService->buildForQrCode($uid, $range);
        $exportUrl = (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute(
            'dynamic_qrcode_export_scans',
            ['qrUid' => $uid, 'range' => $analytics['range']]
        );

        $cards = [
            'Total scans' => (string)$analytics['scanCount'],
            'Unique scans (24h)' => (string)$analytics['uniqueScanCount'],
            'Human scans' => (string)$analytics['humanScans'],
            'Bot scans' => (string)$analytics['botScans'],
            'First scan' => $this->formatTimestamp((int)($row['first_scan_at'] ?? 0)),
            'Last scan' => $this->formatTimestamp((int)($row['last_scan_at'] ?? 0)),
        ];

        $html = [];
        $html[] = '<div class="card card--shadow" style="padding:16px;margin-top:8px">';
        $html[] = '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">';
        $html[] = '<div><strong>Analytics fuer QR UID ' . $uid . '</strong><div style="font-size:12px;color:#666;margin-top:4px">Range: ' . htmlspecialchars($analytics['rangeLabel']) . '</div></div>';
        $html[] = '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
        foreach (['7d' => '7d', '30d' => '30d', '90d' => '90d', 'all' => 'All'] as $rangeKey => $rangeLabel) {
            $className = $analytics['range'] === $rangeKey ? 'btn btn-primary' : 'btn btn-default';
            $html[] = '<a href="' . htmlspecialchars($this->buildFilterUrl($rangeKey)) . '" class="' . $className . '">' . htmlspecialchars($rangeLabel) . '</a>';
        }
        $html[] = '<a href="' . htmlspecialchars($exportUrl) . '" class="btn btn-default">CSV exportieren</a>';
        $html[] = '</div>';
        $html[] = '</div>';

        $html[] = '<div style="font-size:12px;color:#666;margin-bottom:12px">Unique scans werden als 24h-Heuristik auf Basis von gehashter IP und User-Agent berechnet.</div>';

        $html[] = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:18px">';
        foreach ($cards as $label => $value) {
            $html[] = '<div style="border:1px solid #d8d8d8;border-radius:8px;padding:10px;background:#fafafa">';
            $html[] = '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.04em">' . htmlspecialchars($label) . '</div>';
            $html[] = '<div style="font-size:20px;font-weight:700;margin-top:4px">' . htmlspecialchars($value) . '</div>';
            $html[] = '</div>';
        }
        $html[] = '</div>';

        $html[] = '<div style="display:grid;grid-template-columns:minmax(280px,2fr) minmax(220px,1fr);gap:16px;align-items:start">';
        $html[] = '<div>';
        $html[] = '<h3 style="margin:0 0 10px 0;font-size:14px">Daily scans (' . htmlspecialchars($analytics['rangeLabel']) . ')</h3>';
        $html[] = '<table class="table table-striped table-hover"><thead><tr><th>Day</th><th>Count</th></tr></thead><tbody>';
        foreach ($analytics['dailySeries'] as $point) {
            $html[] = '<tr><td>' . htmlspecialchars($point['label']) . '</td><td>' . (int)$point['count'] . '</td></tr>';
        }
        $html[] = '</tbody></table>';
        $html[] = '</div>';

        $html[] = '<div>';
        $html[] = '<h3 style="margin:0 0 10px 0;font-size:14px">Top referers</h3>';
        if ($analytics['topReferers'] === []) {
            $html[] = '<div class="alert alert-info">Noch keine Referer-Daten vorhanden.</div>';
        } else {
            $html[] = '<table class="table table-striped table-hover"><thead><tr><th>Host</th><th>Count</th></tr></thead><tbody>';
            foreach ($analytics['topReferers'] as $host => $count) {
                $html[] = '<tr><td style="word-break:break-word">' . htmlspecialchars((string)$host) . '</td><td>' . (int)$count . '</td></tr>';
            }
            $html[] = '</tbody></table>';
        }
        $html[] = '</div>';
        $html[] = '</div>';

        $html[] = '<h3 style="margin:18px 0 10px 0;font-size:14px">Latest scans</h3>';
        if ($analytics['recentScans'] === []) {
            $html[] = '<div class="alert alert-info">Noch keine Scans vorhanden.</div>';
        } else {
            $html[] = '<table class="table table-striped table-hover"><thead><tr><th>When</th><th>Host</th><th>Referer</th><th>Bot</th><th>Target</th></tr></thead><tbody>';
            foreach ($analytics['recentScans'] as $scan) {
                $html[] = '<tr>';
                $html[] = '<td>' . htmlspecialchars($this->formatTimestamp((int)$scan['timestamp'])) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string)$scan['site_host']) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string)$scan['referer_host']) . '</td>';
                $html[] = '<td>' . ((bool)$scan['is_bot'] ? 'yes' : 'no') . '</td>';
                $html[] = '<td style="word-break:break-word">' . htmlspecialchars((string)$scan['target_url']) . '</td>';
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table>';
        }

        $html[] = '</div>';
        $resultArray['html'] = implode('', $html);

        return $resultArray;
    }

    private function formatTimestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '-';
        }

        return date('Y-m-d H:i', $timestamp);
    }

    private function resolveRange(): string
    {
        $range = (string)($_GET['dqAnalyticsRange'] ?? '30d');
        return GeneralUtility::makeInstance(ScanAnalyticsService::class)->normalizeRange($range);
    }

    private function buildFilterUrl(string $range): string
    {
        $requestUri = (string)GeneralUtility::getIndpEnv('REQUEST_URI');
        $parts = parse_url($requestUri);
        $path = $parts['path'] ?? '';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['dqAnalyticsRange'] = $range;

        return $path . '?' . http_build_query($query);
    }
}
