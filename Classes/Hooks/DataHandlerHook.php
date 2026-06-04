<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Hooks;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\DynamicQrcode\Service\RedirectService;

class DataHandlerHook
{
    public function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, DataHandler $dataHandler)
    {
        if ($table !== 'tx_dynamicqrcode_domain_model_qrcode') {
            return;
        }

        // Bei neuem Datensatz: UID aus $dataHandler holen
        if (is_array($id)) {
            $id = $dataHandler->substNEWwithIDs[$id['NEW']] ?? 0;
        }

        if ($id <= 0) {
            return;
        }

        // Bei Update der target_url oder neuem Datensatz
        if (($status === 'update' && isset($fieldArray['target_url'])) || $status === 'new') {
            $redirectService = GeneralUtility::makeInstance(RedirectService::class);

            // Target URL aus Datenbank holen falls nicht in fieldArray
            $targetUrl = $fieldArray['target_url'] ?? $this->getTargetUrlFromDatabase($id);

            if (!empty($targetUrl)) {
                $redirectService->updateRedirect((int)$id, $targetUrl);
            }
        }
    }

    private function getTargetUrlFromDatabase(int $uid): string
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('tx_dynamicqrcode_domain_model_qrcode');

        $row = $queryBuilder
            ->select('target_url')
            ->from('tx_dynamicqrcode_domain_model_qrcode')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->executeQuery()
            ->fetchAssociative();

        return $row['target_url'] ?? '';
    }
}
