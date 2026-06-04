<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\DynamicQrcode\Service\QrCodeService;

final class LivePreviewController
{
    public function renderAction(): ResponseInterface
    {
        $payload = is_array($_POST) ? $_POST : [];

        $config = [
            'uid' => (int)($payload['uid'] ?? 0),
            'target_url' => (string)($payload['target_url'] ?? ''),
            'data' => (string)($payload['data'] ?? ''),
            'style_preset' => (string)($payload['style_preset'] ?? 'custom'),
            'fg_color' => (string)($payload['fg_color'] ?? '#000000'),
            'bg_color' => (string)($payload['bg_color'] ?? '#FFFFFF'),
            'error_correction' => (string)($payload['error_correction'] ?? 'M'),
            'size' => (int)($payload['size'] ?? 256),
            'margin' => (int)($payload['margin'] ?? 2),
            'dot_style' => (string)($payload['dot_style'] ?? 'square'),
            'dot_intensity' => (int)($payload['dot_intensity'] ?? 5),
            'rounded_modules' => !empty($payload['rounded_modules']),
            'eye_style' => (string)($payload['eye_style'] ?? 'square'),
            'eye_radius' => (int)($payload['eye_radius'] ?? 0),
            'fg_gradient_from' => (string)($payload['fg_gradient_from'] ?? ''),
            'fg_gradient_to' => (string)($payload['fg_gradient_to'] ?? ''),
            'fg_gradient_angle' => (int)($payload['fg_gradient_angle'] ?? 0),
            'logo_file' => (string)($payload['logo_file'] ?? ''),
            'logo_scale' => (int)($payload['logo_scale'] ?? 30),
            'drop_shadow' => !empty($payload['drop_shadow']),
            'logo_bg' => !empty($payload['logo_bg']),
            'logo_bg_color' => (string)($payload['logo_bg_color'] ?? '#FFFFFF'),
            'logo_bg_radius' => (int)($payload['logo_bg_radius'] ?? 8),
            'logo_bg_padding' => (int)($payload['logo_bg_padding'] ?? 4),
        ];

        $svg = GeneralUtility::makeInstance(QrCodeService::class)->svgFromConfig($config);

        $response = new Response();
        $response = $response->withHeader('Content-Type', 'image/svg+xml; charset=utf-8');
        $response->getBody()->write($svg);

        return $response;
    }
}
