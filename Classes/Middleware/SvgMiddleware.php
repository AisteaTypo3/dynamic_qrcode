<?php

namespace Vendor\DynamicQrcode\Middleware;

use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\DynamicQrcode\Service\QrCodeService;

class SvgMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = (string)$request->getUri()->getPath();
        $matchesPath = ($path === '/dynamic-qrcode/svg')
            || (str_ends_with($path, '/dynamic-qrcode/svg'))
            || (strpos($path, '/index.php/dynamic-qrcode/svg') !== false);
        if (!$matchesPath) {
            return $handler->handle($request);
        }

        $params = $request->getQueryParams();
        $uid = (int)($params['uid'] ?? 0);
        $h = (string)($params['h'] ?? '');
        $download = (int)($params['download'] ?? 0) === 1;

        if ($uid <= 0 || $h === '') {
            return $this->badRequest('Missing parameters');
        }
        $expected = hash_hmac('sha256', (string)$uid, $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        if (!hash_equals($expected, $h)) {
            return $this->badRequest('Invalid HMAC');
        }

        // Fetch record
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_dynamicqrcode_domain_model_qrcode');
        $row = $qb->select('*')
            ->from('tx_dynamicqrcode_domain_model_qrcode')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()->fetchAssociative();

        if (!$row) {
            return $this->badRequest('Not found');
        }

        $service = GeneralUtility::makeInstance(QrCodeService::class);
        $svg = $service->svgFromConfig($row);

        $response = new Response();
        $response = $response->withHeader('Content-Type', 'image/svg+xml; charset=utf-8');
        if ($download) {
            $filename = 'qr-' . $uid . '.svg';
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }
        $response->getBody()->write($svg);
        return $response;
    }

    private function badRequest(string $msg): ResponseInterface
    {
        $r = new Response();
        $r = $r->withStatus(400, $msg)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        $r->getBody()->write($msg);
        return $r;
    }
}
