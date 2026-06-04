<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Middleware;

use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\DynamicQrcode\Service\ScanTrackingService;

final class QrResolverMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = (string)$request->getUri()->getPath();
        if (!preg_match('#^/q/(\d+)(?:/([A-Fa-f0-9]{8}|[A-Fa-f0-9]{64}))?$#', $path, $matches)) {
            return $handler->handle($request);
        }

        $uid = (int)$matches[1];
        $hmac = (string)($matches[2] ?? '');
        if ($uid <= 0) {
            return $this->errorResponse(400, 'Bad QR URL: Missing UID');
        }

        if ($hmac !== '' && !$this->isValidHmac($uid, $hmac)) {
            return $this->errorResponse(403, 'Invalid HMAC');
        }

        $record = $this->fetchQrCodeRecord($uid);
        if ($record === null || (int)$record['deleted'] === 1 || (int)$record['hidden'] === 1) {
            return $this->errorResponse(404, 'QR not found');
        }

        $targetUrl = trim((string)($record['target_url'] ?? ''));
        if ($targetUrl === '') {
            return $this->errorResponse(410, 'No target URL configured');
        }

        try {
            GeneralUtility::makeInstance(ScanTrackingService::class)->trackResolverHit($uid, $targetUrl, $request);
        } catch (\Throwable $exception) {
            error_log('Dynamic QR scan tracking failed for UID ' . $uid . ': ' . $exception->getMessage());
        }

        return new RedirectResponse($targetUrl, 302);
    }

    private function isValidHmac(int $uid, string $hmac): bool
    {
        $fullHmac = hash_hmac('sha256', (string)$uid, $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        $expected = strlen($hmac) === 8 ? substr($fullHmac, 0, 8) : $fullHmac;

        return hash_equals($expected, $hmac);
    }

    private function fetchQrCodeRecord(int $uid): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dynamicqrcode_domain_model_qrcode');

        $record = $queryBuilder
            ->select('uid', 'target_url', 'hidden', 'deleted')
            ->from('tx_dynamicqrcode_domain_model_qrcode')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($record) ? $record : null;
    }

    private function errorResponse(int $statusCode, string $message): ResponseInterface
    {
        $response = new Response();
        $response = $response
            ->withStatus($statusCode, $message)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->getBody()->write($message);

        return $response;
    }
}
