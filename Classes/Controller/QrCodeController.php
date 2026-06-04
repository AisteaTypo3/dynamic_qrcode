<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Controller;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class QrCodeController extends ActionController
{
    public function redirectAction(int $uid = 0, string $hmac = ''): void
    {
        // Kurz-/Lang unterscheiden (8 vs 64 hex). Ohne HMAC bei /q/{uid} erlauben.
        $isShort = ($hmac === '' || preg_match('/^[A-Fa-f0-9]{8}$/', $hmac));
        if ($uid <= 0) {
            $this->throwStatus(400, 'Bad QR URL: Missing UID');
        }

        if ($hmac !== '') {
            $full = hash_hmac('sha256', (string)$uid, $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
            $expected = $isShort ? substr($full, 0, 8) : $full;
            if (!hash_equals($expected, $hmac)) {
                $this->throwStatus(403, 'Invalid HMAC');
            }
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dynamicqrcode_domain_model_qrcode');

        $row = $qb->select('uid', 'target_url', 'hidden', 'deleted', 'starttime', 'endtime')
            ->from('tx_dynamicqrcode_domain_model_qrcode')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        if (!$row || (int)$row['deleted'] === 1 || (int)$row['hidden'] === 1) {
            $this->throwStatus(404, 'QR not found');
        }

        $target = trim((string)($row['target_url'] ?? ''));
        if ($target === '') {
            $this->throwStatus(410, 'No target URL configured');
        }

        $this->redirectToUri($target, 0, 302);
    }
}
