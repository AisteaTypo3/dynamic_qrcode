<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Service;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SvgStorageService
{
    private ResourceFactory $resourceFactory;

    public function __construct(ResourceFactory $resourceFactory = null)
    {
        $this->resourceFactory = $resourceFactory ?? GeneralUtility::makeInstance(ResourceFactory::class);
    }

    /**
     * Speichert SVG im fileadmin (z. B. user_upload/dynamic_qr) und
     * erstellt bei Kollision fortlaufend qr-123_01.svg, qr-123_02.svg, …
     */
    public function saveSvg(
        string $svg,
        string $fileBaseName,
        string $relativeFolder = 'user_upload/dynamic_qr'
    ): FileInterface {
        $storage = $this->resourceFactory->getDefaultStorage();
        if ($storage === null) {
            throw new \RuntimeException('No default storage (fileadmin) configured.');
        }

        // Zielordner sicherstellen (rekursiv)
        $folder = $this->ensureFolder($storage->getRootLevelFolder(), $relativeFolder);

        // Dateinamen vorbereiten
        $safeBase = $this->sanitizeBaseName($fileBaseName);
        $uniqueName = $this->generateUniqueName($storage, $folder, $safeBase, 'svg');

        // Datei anlegen und Inhalt schreiben
        $file = $storage->createFile($uniqueName, $folder);
        $storage->setFileContents($file, $svg);

        return $file; // $file->getPublicUrl() liefert den Direktlink
    }

    /** Nummerierung: name.svg → name.svg / name_01.svg / name_02.svg … */
    private function generateUniqueName(\TYPO3\CMS\Core\Resource\ResourceStorage $storage, Folder $folder, string $base, string $ext): string
    {
        $i = 0;
        $candidate = $base . '.' . $ext;
        while ($storage->hasFileInFolder($candidate, $folder)) {
            $i++;
            $candidate = sprintf('%s_%02d.%s', $base, $i, $ext);
        }
        return $candidate;
    }

    /** sehr simpler Sanitizer für Basenamen */
    private function sanitizeBaseName(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = $trans !== false ? $trans : $name;
        $name = preg_replace('/[^a-z0-9._-]+/', '-', $name);
        $name = trim((string)$name, '-_.');
        return $name !== '' ? $name : 'file';
    }

    /** rekursiv Unterordner anlegen (z. B. "user_upload/dynamic_qr") */
    private function ensureFolder(Folder $baseFolder, string $relativePath): Folder
    {
        $storage = $baseFolder->getStorage();
        $current = $baseFolder;

        foreach (array_filter(explode('/', trim($relativePath, '/'))) as $segment) {
            if (!$storage->hasFolderInFolder($segment, $current)) {
                $current = $storage->createFolder($segment, $current);
            } else {
                $current = $storage->getFolderInFolder($segment, $current);
            }
        }
        return $current;
    }
}
