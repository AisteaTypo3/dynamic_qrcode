<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\DynamicQrcode\Service\QrCodeService;

final class QrPreviewElement extends AbstractFormElement
{
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();

        // --- Datensatzdaten ---
        $row   = $this->data['databaseRow'] ?? [];
        $uid   = (int)($row['uid'] ?? 0);
        $title = (string)($row['title'] ?? '');
        $targetUrl = $this->getTargetUrlFromRow($row);

        if ($uid <= 0) {
            $resultArray['html'] = '<div class="alert alert-warning">QR-Code Vorschau ist erst nach dem ersten Speichern verfügbar.</div>';
            return $resultArray;
        }

        if ($targetUrl === '') {
            $resultArray['html'] = '<div class="alert alert-warning">Bitte zuerst eine Target URL eingeben.</div>';
            return $resultArray;
        }

        // --- Frontend-URL ermitteln ---
        $resolverUrl = $this->buildResolverUrl($uid, $targetUrl);

        if (!$resolverUrl) {
            // Fallback URL generieren
            $resolverUrl = $this->buildFallbackUrl($uid);
            $resultArray['html'] = '<div class="alert alert-info">Verwende Fallback-URL: ' . htmlspecialchars($resolverUrl) . '</div>';
        }

        // SVG generieren
        try {
            $svg = $this->buildSvg($resolverUrl, $row);
        } catch (\Exception $e) {
            $resultArray['html'] = '<div class="alert alert-danger">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
            return $resultArray;
        }

        // HTML für die Vorschau generieren
        $html = $this->buildPreviewHtml($resolverUrl, $targetUrl, $svg, $title, $uid);
        $resultArray['html'] = $html;
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@vendor/dynamic-qrcode/live-preview.js')
            ->invoke('initialize', '#dynamic-qrcode-preview-image-' . $uid, (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('dynamic_qrcode_live_preview'), $uid);

        return $resultArray;
    }

    /**
     * Target URL aus dem Datensatz holen
     */
    private function getTargetUrlFromRow(array $row): string
    {
        return trim((string)($row['target_url'] ?? ''));
    }

    /**
     * Build resolver URL with proper error handling
     */
    private function buildResolverUrl(int $uid, string $targetUrl): ?string
    {
        error_log('=== QrPreviewElement::buildResolverUrl ===');
        error_log('QR UID: ' . $uid);
        error_log('Target URL: ' . $targetUrl);

        // Debug: Aktuelle Datensatzdaten prüfen
        $row = $this->data['databaseRow'] ?? [];
        error_log('Current row UID: ' . ($row['uid'] ?? 'NULL'));
        error_log('Current row title: ' . ($row['title'] ?? 'NULL'));

        if (empty($targetUrl)) {
            error_log('Target URL is empty!');
            return null;
        }

        try {
            $redirectService = GeneralUtility::makeInstance(\Vendor\DynamicQrcode\Service\RedirectService::class);
            $result = $redirectService->createRedirect($uid, $targetUrl);

            error_log('RedirectService returned: ' . $result);

            // Verifiziere dass die UID in der URL korrekt ist
            if (strpos($result, '/q/' . $uid . '/') === false) {
                error_log('ERROR: UID mismatch in generated URL!');
                error_log('Expected UID: ' . $uid);
                error_log('Generated URL: ' . $result);
            }

            return $result;

        } catch (\Exception $e) {
            error_log('QrPreviewElement Error: ' . $e->getMessage());
            return $this->buildFallbackUrl($uid);
        }
    }

    /**
     * Fallback URL wenn keine Site konfiguriert ist
     */
    private function buildFallbackUrl(int $uid): string
    {
        // Basis-URL aus Server-Variablen
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;

        $hmac = hash_hmac('sha256', (string)$uid, $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        $shortHmac = substr($hmac, 0, 8);

        return $baseUrl . '/q/' . $uid . '/' . $shortHmac;
    }

    /**
     * Build preview HTML
     */
    private function buildPreviewHtml(string $resolverUrl, string $targetUrl, string $svg, string $title, int $uid): string
    {
        $row = $this->data['databaseRow'] ?? [];
        $scanCount = (int)($row['scan_count'] ?? 0);
        $firstScanAt = (int)($row['first_scan_at'] ?? 0);
        $lastScanAt = (int)($row['last_scan_at'] ?? 0);
        $previewImageId = 'dynamic-qrcode-preview-image-' . $uid;

        $html = [];
        $html[] = '<div class="card card--shadow" style="padding:12px;margin-top:8px">';
        $html[] = '<div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">';

        // QR Code Bild
        $html[] = '<div>';
        $html[] = '<img id="' . htmlspecialchars($previewImageId) . '" src="data:image/svg+xml;base64,' . base64_encode($svg) . '" alt="QR" style="width:180px;height:180px;object-fit:contain;border:1px solid #ddd;border-radius:8px;padding:6px;background:#fff"/>';
        $html[] = '</div>';

        // Informationen
        $html[] = '<div style="min-width:280px">';
        $html[] = '<div style="font-weight:600;margin-bottom:6px">Permalink (QR-Inhalt):</div>';
        $html[] = '<div style="word-break:break-all;margin-bottom:10px"><a href="' . htmlspecialchars($resolverUrl) . '" target="_blank" rel="noreferrer noopener">' . htmlspecialchars($resolverUrl) . '</a></div>';
        $html[] = '<div style="font-size:12px;color:#666;margin-bottom:8px">Ziel: <a href="' . htmlspecialchars($targetUrl) . '" target="_blank">' . htmlspecialchars($targetUrl) . '</a></div>';
        $html[] = '<div style="font-size:12px;color:#444;margin-bottom:10px">';
        $html[] = 'Scans: <strong>' . $scanCount . '</strong>';
        $html[] = ' | Erster Scan: ' . htmlspecialchars($this->formatTimestamp($firstScanAt));
        $html[] = ' | Letzter Scan: ' . htmlspecialchars($this->formatTimestamp($lastScanAt));
        $html[] = '</div>';
        $html[] = '<div style="font-size:12px;color:#666;margin-bottom:10px">Preview aktualisiert sich bei Style-Aenderungen ohne Speichern.</div>';
        $html[] = '<div style="display:flex;gap:8px;flex-wrap:wrap">';
        $html[] = '<a href="' . htmlspecialchars($resolverUrl) . '" target="_blank" class="btn btn-default">Link testen</a>';
        $html[] = '<a href="data:image/svg+xml;base64,' . base64_encode($svg) . '" download="qr-' . $uid . '.svg" class="btn btn-default">SVG herunterladen</a>';
        $html[] = '</div>';
        $html[] = '</div>';

        $html[] = '</div></div>';

        return implode('', $html);
    }

    private function buildSvg(string $data, array $row): string
    {
        if (class_exists(QrCodeService::class)) {
            try {
                $service = GeneralUtility::makeInstance(QrCodeService::class);

                if (method_exists($service, 'svgFromConfig')) {
                    $config = ['data' => $data] + $row;
                    $result = $service->svgFromConfig($config);

                    if (is_string($result) && !empty($result) && strpos($result, '<svg') !== false) {
                        return $result;
                    }
                }
            } catch (\Exception $e) {
                // Fallback to test QR
            }
        }

        return $this->createTestQrSvg($data);
    }

    private function createErrorSvg(string $message): string
    {
        $esc = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256">
  <rect width="256" height="256" fill="#ffebee"/>
  <rect x="8" y="8" width="240" height="240" fill="#f44336" stroke="#d32f2f" stroke-width="2"/>
  <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="14" fill="#ffffff" font-weight="bold">ERROR</text>
  <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="10" fill="#ffffff">{$esc}</text>
</svg>
SVG;
    }

    private function createTestQrSvg(string $data): string
    {
        $esc = htmlspecialchars(substr($data, 0, 30), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256">
  <rect width="256" height="256" fill="#ffffff"/>
  <rect x="16" y="16" width="224" height="224" fill="#000000"/>
  <rect x="32" y="32" width="192" height="192" fill="#ffffff"/>
  
  <!-- QR Pattern Simulation -->
  <rect x="48" y="48" width="32" height="32" fill="#000000"/>
  <rect x="96" y="48" width="16" height="16" fill="#000000"/>
  <rect x="128" y="48" width="16" height="16" fill="#000000"/>
  <rect x="48" y="96" width="16" height="16" fill="#000000"/>
  <rect x="128" y="96" width="16" height="16" fill="#000000"/>
  
  <text x="128" y="180" text-anchor="middle" font-family="Arial" font-size="10" fill="#666666">TEST QR</text>
  <text x="128" y="200" text-anchor="middle" font-family="Arial" font-size="8" fill="#999999">{$esc}...</text>
</svg>
SVG;
    }

    private function formatTimestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '-';
        }

        return date('Y-m-d H:i', $timestamp);
    }

    // Helper-Methoden für Dateispeicherung (falls benötigt)
    private function ensureFolder(\TYPO3\CMS\Core\Resource\ResourceStorage $storage, Folder $root, string $relative): Folder
    {
        $current = $root;
        foreach (array_filter(explode('/', trim($relative, '/'))) as $seg) {
            if (!$storage->hasFolderInFolder($seg, $current)) {
                $current = $storage->createFolder($seg, $current);
            } else {
                $current = $storage->getFolderInFolder($seg, $current);
            }
        }
        return $current;
    }

    private function slugify(string $s, int $maxLen = 60): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $t !== false ? $t : $s;
        $s = preg_replace('/[^a-z0-9._-]+/', '-', $s);
        $s = trim((string)$s, '-_.');
        if ($maxLen > 0 && strlen($s) > $maxLen) {
            $s = rtrim(substr($s, 0, $maxLen), '-_.');
        }
        return $s !== '' ? $s : 'qr';
    }

    private function uniqueName(\TYPO3\CMS\Core\Resource\ResourceStorage $storage, Folder $folder, string $base, string $ext): string
    {
        $i = 0;
        $candidate = $base . '.' . $ext;
        while ($storage->hasFileInFolder($candidate, $folder)) {
            $i++;
            $candidate = sprintf('%s_%02d.%s', $base, $i, $ext);
        }
        return $candidate;
    }
}
