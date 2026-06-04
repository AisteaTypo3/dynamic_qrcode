<?php

declare(strict_types=1);

namespace Vendor\DynamicQrcode\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class QrCodeService implements SingletonInterface
{
    private const DEFAULT_SIZE = 256;
    private const DEFAULT_MARGIN = 2;
    private const DEFAULT_FG_COLOR = '#000000';
    private const DEFAULT_BG_COLOR = '#FFFFFF';
    private const DEFAULT_ERROR_CORRECTION = 'M';
    private const MIN_SIZE = 64;
    private const MAX_SIZE = 2048;
    private const MIN_MARGIN = 0;
    private const MAX_MARGIN = 64;

    /**
     * Generate SVG QR code from configuration
     */
    public function svgFromConfig(array $config): string
    {
        try {
            // Check if library is available
            if (!class_exists(\Endroid\QrCode\QrCode::class)) {
                throw new \RuntimeException('Endroid QrCode library not found. Run: composer require endroid/qr-code');
            }

            $config = $this->sanitizeConfigForTca($config);
            $config = $this->applyStylePreset($config);
            $qrCode = $this->createQrCodeFromConfig($config);
            $logo = $this->createLogoFromConfig($config, $qrCode->getSize());

            $writer = $this->createSvgWriter();
            $options = [];
            if (defined(\Endroid\QrCode\Writer\SvgWriter::class . '::WRITER_OPTION_COMPACT')) {
                $options[\Endroid\QrCode\Writer\SvgWriter::WRITER_OPTION_COMPACT] = false;
            }
            $result = $writer->write($qrCode, $logo, null, $options);

            $svg = $result->getString();

            $svg = $this->applySvgStyling($svg, $config);

            return $svg;
        } catch (\Throwable $e) {
            return $this->createErrorSvg($e->getMessage(), $config);
        }
    }

    /**
     * Create QR code instance from configuration - FIXED for TYPO3 TCA Arrays
     */
    private function createQrCodeFromConfig(array $config): \Endroid\QrCode\QrCode
    {
        // FIX: TYPO3 TCA-Arrays in einzelne Werte umwandeln
        $config = $this->sanitizeConfigForTca($config);
        $config = $this->applyStylePreset($config);

        $uid = (int)($config['uid'] ?? 0);

        // URL generieren - kurze Version verwenden
        $url = $this->buildResolverUrl($uid, $config);

        $size = $this->validateSize($config['size']);
        $margin = $this->validateMargin($config['margin']);

        // Create QR Code instance (compatible with different versions)
        if (method_exists(\Endroid\QrCode\QrCode::class, 'create')) {
            // Version 4.x and newer
            $qrCode = \Endroid\QrCode\QrCode::create($url);
        } else {
            // Version 3.x and older
            $qrCode = new \Endroid\QrCode\QrCode($url);
        }

        // Set basic properties
        $qrCode->setSize($size);
        $qrCode->setMargin($margin);

        // Set encoding (version-compatible)
        $this->setEncoding($qrCode);

        // Set error correction level (version-compatible)
        $this->setErrorCorrectionLevel($qrCode, $config['error_correction']);

        // Set colors (version-compatible)
        $this->setForegroundColor($qrCode, $config['fg_color']);
        $this->setBackgroundColor($qrCode, $config['bg_color']);

        // Set additional properties if available
        $this->setRoundedModules($qrCode, $config['rounded_modules']);
        $this->setDotStyle($qrCode, $config['dot_style'], $config['dot_intensity']);
        $this->setEyeStyle($qrCode, $config['eye_style'], $config['eye_radius']);

        return $qrCode;
    }

    /**
     * Sanitize TCA array values to single values - FIXED for 'data' field
     */
    private function sanitizeConfigForTca(array $config): array
    {
        // Definierte Felder mit ihren Defaults
        $fields = [
            'data' => '', // ADDED: Handle data field properly
            'style_preset' => 'custom',
            'error_correction' => self::DEFAULT_ERROR_CORRECTION,
            'fg_color' => self::DEFAULT_FG_COLOR,
            'bg_color' => self::DEFAULT_BG_COLOR,
            'size' => self::DEFAULT_SIZE,
            'margin' => self::DEFAULT_MARGIN,
            'rounded_modules' => false,
            'logo_file' => '',
            'eye_radius' => 0,
            'fg_gradient_from' => '',
            'fg_gradient_to' => '',
            'fg_gradient_angle' => 0,
            'logo_scale' => 30,
            'drop_shadow' => false,
            'logo_bg' => false,
            'logo_bg_color' => '#FFFFFF',
            'logo_bg_radius' => 8,
            'logo_bg_padding' => 4,
            'dot_style' => 'square',
            'dot_intensity' => 5,
            'eye_style' => 'square'
        ];

        foreach ($fields as $field => $default) {
            if (isset($config[$field])) {
                // Array-Wert: ersten Wert nehmen
                if (is_array($config[$field])) {
                    $config[$field] = $config[$field][0] ?? $default;
                }

                // Typ-spezifische Konvertierung
                switch ($field) {
                    case 'rounded_modules':
                    case 'drop_shadow':
                    case 'logo_bg':
                        $config[$field] = (bool)($config[$field] ?? $default);
                        break;
                    case 'size':
                    case 'margin':
                    case 'eye_radius':
                    case 'dot_intensity':
                    case 'fg_gradient_angle':
                    case 'logo_scale':
                    case 'logo_bg_radius':
                    case 'logo_bg_padding':
                        $config[$field] = (int)($config[$field] ?? $default);
                        break;
                    default:
                        // FIXED: Ensure proper string conversion for all string fields including 'data'
                        $value = $config[$field] ?? $default;
                        if (is_array($value)) {
                            $value = $value[0] ?? $default;
                        }
                        $config[$field] = (string)$value;
                        break;
                }
            } else {
                $config[$field] = $default;
            }
        }

        return $config;
    }

    private function applyStylePreset(array $config): array
    {
        $preset = strtolower((string)($config['style_preset'] ?? 'custom'));
        $presets = [
            'dotted_modern' => [
                'fg_color' => '#0f172a',
                'bg_color' => '#ffffff',
                'error_correction' => 'H',
                'margin' => 3,
                'rounded_modules' => true,
                'dot_style' => 'bubble',
                'dot_intensity' => 8,
                'eye_style' => 'softsquare',
                'eye_radius' => 18,
                'fg_gradient_from' => '#0f172a',
                'fg_gradient_to' => '#14b8a6',
                'fg_gradient_angle' => 45,
                'drop_shadow' => true,
                'logo_bg' => true,
            ],
            'soft_medical' => [
                'fg_color' => '#0f766e',
                'bg_color' => '#ffffff',
                'error_correction' => 'Q',
                'margin' => 3,
                'rounded_modules' => true,
                'dot_style' => 'softsquare',
                'dot_intensity' => 7,
                'eye_style' => 'rounded',
                'eye_radius' => 22,
                'fg_gradient_from' => '#0f766e',
                'fg_gradient_to' => '#38bdf8',
                'fg_gradient_angle' => 25,
                'drop_shadow' => false,
                'logo_bg' => true,
                'logo_bg_color' => '#f8fafc',
            ],
            'dark_tech' => [
                'fg_color' => '#e2e8f0',
                'bg_color' => '#020617',
                'error_correction' => 'H',
                'margin' => 4,
                'rounded_modules' => false,
                'dot_style' => 'diamond',
                'dot_intensity' => 7,
                'eye_style' => 'diamond',
                'eye_radius' => 0,
                'fg_gradient_from' => '#38bdf8',
                'fg_gradient_to' => '#a855f7',
                'fg_gradient_angle' => 135,
                'drop_shadow' => true,
                'logo_bg' => false,
            ],
            'sunrise_gradient' => [
                'fg_color' => '#7c2d12',
                'bg_color' => '#fff7ed',
                'error_correction' => 'Q',
                'margin' => 3,
                'rounded_modules' => true,
                'dot_style' => 'rounded',
                'dot_intensity' => 9,
                'eye_style' => 'softsquare',
                'eye_radius' => 16,
                'fg_gradient_from' => '#f97316',
                'fg_gradient_to' => '#ec4899',
                'fg_gradient_angle' => 35,
                'drop_shadow' => true,
                'logo_bg' => true,
                'logo_bg_color' => '#ffffff',
            ],
        ];

        if ($preset === 'custom' || !isset($presets[$preset])) {
            return $config;
        }

        return array_replace($config, $presets[$preset]);
    }

    /**
     * Build resolver URL - FIXED to handle array values properly
     */
    private function buildResolverUrl(int $uid, array $config): string
    {
        // FIXED: Handle data field properly (already sanitized by sanitizeConfigForTca)
        $data = $config['data'] ?? '';

        // Additional safety check in case sanitization missed something
        if (is_array($data)) {
            $data = $data[0] ?? '';
        }

        // Direkte URL aus config verwenden falls vorhanden
        if (!empty($data)) {
            return (string)$data;
        }

        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $sites = $siteFinder->getAllSites();

            if (!empty($sites)) {
                $site = array_values($sites)[0];
                $baseUrl = rtrim((string)$site->getBase(), '/');
            } else {
                $baseUrl = rtrim((string)GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/');
            }

            // Kurze URL mit 8-Zeichen HMAC
            $hmac = hash_hmac('sha256', (string)$uid, $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
            $shortHmac = substr($hmac, 0, 8);

            return $baseUrl . '/q/' . $uid . '/' . $shortHmac;

        } catch (\Exception $e) {
            // Fallback
            return 'https://example.com/q/' . $uid;
        }
    }

    /**
     * Set encoding (version-compatible)
     */
    private function setEncoding(\Endroid\QrCode\QrCode $qrCode): void
    {
        try {
            if (class_exists(\Endroid\QrCode\Encoding\Encoding::class)) {
                // Version 4.x+
                $qrCode->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'));
            } elseif (method_exists($qrCode, 'setEncoding')) {
                // Version 3.x
                $qrCode->setEncoding('UTF-8');
            }
        } catch (\Throwable $e) {
            // Continue without encoding setting
        }
    }

    /**
     * Set error correction level (version-compatible) - FIXED
     */
    private function setErrorCorrectionLevel(\Endroid\QrCode\QrCode $qrCode, string $level): void
    {
        // Level ist bereits durch sanitizeConfigForTca() bereinigt
        $level = strtoupper(trim($level));

        // Fallback bei ungültigem Level
        if (!in_array($level, ['L', 'M', 'Q', 'H'], true)) {
            $level = 'M';
        }

        try {
            // Try Version 4.x/5.x approach (separate classes)
            if ($this->trySetErrorCorrectionV4($qrCode, $level)) {
                return;
            }

            // Try Version 3.x approach (constants)
            if ($this->trySetErrorCorrectionV3($qrCode, $level)) {
                return;
            }

            // Try older versions
            $this->trySetErrorCorrectionOld($qrCode, $level);

        } catch (\Throwable $e) {
            // Continue without error correction setting
            error_log('QrCodeService: Error correction setting failed: ' . $e->getMessage());
        }
    }

    /**
     * Try to set error correction for v4+ (separate classes)
     */
    private function trySetErrorCorrectionV4(\Endroid\QrCode\QrCode $qrCode, string $level): bool
    {
        $classMap = [
            'L' => \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow::class,
            'M' => \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium::class,
            'Q' => \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelQuartile::class,
            'H' => \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh::class,
        ];

        if (!isset($classMap[$level]) || !class_exists($classMap[$level])) {
            return false;
        }

        try {
            $qrCode->setErrorCorrectionLevel(new $classMap[$level]());
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Try to set error correction for v3 (constants)
     */
    private function trySetErrorCorrectionV3(\Endroid\QrCode\QrCode $qrCode, string $level): bool
    {
        if (!class_exists(\Endroid\QrCode\ErrorCorrectionLevel::class)) {
            return false;
        }

        $constantMap = [
            'L' => 'LOW',
            'M' => 'MEDIUM',
            'Q' => 'QUARTILE',
            'H' => 'HIGH',
        ];

        if (!isset($constantMap[$level])) {
            return false;
        }

        try {
            $constantName = \Endroid\QrCode\ErrorCorrectionLevel::class . '::' . $constantMap[$level];
            if (defined($constantName)) {
                $qrCode->setErrorCorrectionLevel(constant($constantName));
                return true;
            }
        } catch (\Throwable $e) {
            // Continue
        }

        return false;
    }

    /**
     * Try to set error correction for older versions
     */
    private function trySetErrorCorrectionOld(\Endroid\QrCode\QrCode $qrCode, string $level): void
    {
        // For very old versions, try direct string values
        if (method_exists($qrCode, 'setErrorCorrection')) {
            $qrCode->setErrorCorrection($level);
        } elseif (method_exists($qrCode, 'setErrorCorrectionLevel')) {
            $qrCode->setErrorCorrectionLevel($level);
        }
    }

    /**
     * Set foreground color (version-compatible) - FIXED
     */
    private function setForegroundColor(\Endroid\QrCode\QrCode $qrCode, string $color): void
    {
        try {
            if (class_exists(\Endroid\QrCode\Color\Color::class)) {
                // Version 4.x+
                $colorObj = $this->createColorObject($color);
                $qrCode->setForegroundColor($colorObj);
            } else {
                // Version 3.x and older
                $rgb = $this->hexToRgb($color);
                $qrCode->setForegroundColor($rgb);
            }
        } catch (\Throwable $e) {
            // Continue without color setting
            error_log('QrCodeService: Foreground color setting failed: ' . $e->getMessage());
        }
    }

    /**
     * Set background color (version-compatible) - FIXED
     */
    private function setBackgroundColor(\Endroid\QrCode\QrCode $qrCode, string $color): void
    {
        try {
            if (class_exists(\Endroid\QrCode\Color\Color::class)) {
                // Version 4.x+
                $colorObj = $this->createColorObject($color);
                $qrCode->setBackgroundColor($colorObj);
            } else {
                // Version 3.x and older
                $rgb = $this->hexToRgb($color);
                $qrCode->setBackgroundColor($rgb);
            }
        } catch (\Throwable $e) {
            // Continue without color setting
            error_log('QrCodeService: Background color setting failed: ' . $e->getMessage());
        }
    }

    /**
     * Create color object (version 4.x+)
     */
    private function createColorObject(string $hex): \Endroid\QrCode\Color\Color
    {
        $rgb = $this->hexToRgb($hex);
        return new \Endroid\QrCode\Color\Color($rgb['r'], $rgb['g'], $rgb['b']);
    }

    /**
     * Set rounded modules if supported - FIXED
     */
    private function setRoundedModules(\Endroid\QrCode\QrCode $qrCode, bool $rounded): void
    {
        try {
            if ($rounded && class_exists(\Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin::class)) {
                $qrCode->setRoundBlockSizeMode(new \Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin());
            } elseif (!$rounded && class_exists(\Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone::class)) {
                $qrCode->setRoundBlockSizeMode(new \Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone());
            }
        } catch (\Throwable $e) {
            // Continue without rounded modules
            error_log('QrCodeService: Rounded modules setting failed: ' . $e->getMessage());
        }
    }

    /**
     * Set dot style and intensity (version-compatible)
     */
    private function setDotStyle(\Endroid\QrCode\QrCode $qrCode, string $style, int $intensity): void
    {
        try {
            $intensity = max(1, min(10, $intensity));

            // Für Endroid QR Code v4.x+ mit Custom Shape Interface
            if (interface_exists(\Endroid\QrCode\Shape\ShapeInterface::class)) {
                $this->setDotStyleV4($qrCode, $style, $intensity);
            }
            // Für ältere Versionen oder falls keine Shape-Interface verfügbar
            else {
                $this->setDotStyleLegacy($qrCode, $style, $intensity);
            }
        } catch (\Throwable $e) {
            error_log('QrCodeService: Dot style setting failed: ' . $e->getMessage());
        }
    }

    /**
     * Set dot style for v4.x+ with ShapeInterface
     */
    private function setDotStyleV4(\Endroid\QrCode\QrCode $qrCode, string $style, int $intensity): void
    {
        // Basis-Rounding basierend auf Intensity (1-10 → 0.1-0.9)
        $rounding = $intensity * 0.1;

        switch ($style) {
            case 'rounded':
                if (class_exists(\Endroid\QrCode\Shape\RoundedShape::class)) {
                    $qrCode->setShape(new \Endroid\QrCode\Shape\RoundedShape($rounding));
                }
                break;
            case 'dots':
                if (class_exists(\Endroid\QrCode\Shape\DotShape::class)) {
                    $qrCode->setShape(new \Endroid\QrCode\Shape\DotShape($rounding));
                }
                break;
            case 'circles':
                if (class_exists(\Endroid\QrCode\Shape\CircleShape::class)) {
                    $qrCode->setShape(new \Endroid\QrCode\Shape\CircleShape($rounding));
                }
                break;
            case 'square':
            default:
                // Square ist der Default - keine Änderung nötig
                break;
        }
    }

    /**
     * Set dot style for legacy versions or as Fallback
     */
    private function setDotStyleLegacy(\Endroid\QrCode\QrCode $qrCode, string $style, int $intensity): void
    {
        // Für ältere Versionen: Rounded Modules als Fallback
        if ($style === 'rounded' && method_exists($qrCode, 'setRoundBlockSizeMode')) {
            if (class_exists(\Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin::class)) {
                $qrCode->setRoundBlockSizeMode(new \Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin());
            }
        }
    }

    /**
     * Set eye style and radius (version-compatible)
     */
    private function setEyeStyle(\Endroid\QrCode\QrCode $qrCode, string $style, int $radius): void
    {
        try {
            $radius = max(0, min(50, $radius));

            // Für Endroid QR Code v4.x+ mit Eye Shape Interface
            if (interface_exists(\Endroid\QrCode\Shape\EyeShapeInterface::class)) {
                $this->setEyeStyleV4($qrCode, $style, $radius);
            }
        } catch (\Throwable $e) {
            error_log('QrCodeService: Eye style setting failed: ' . $e->getMessage());
        }
    }

    /**
     * Set eye style for v4.x+ with EyeShapeInterface
     */
    private function setEyeStyleV4(\Endroid\QrCode\QrCode $qrCode, string $style, int $radius): void
    {
        // Radius in relative Einheiten umrechnen (0-50 → 0.0-0.5)
        $relativeRadius = $radius * 0.01;

        switch ($style) {
            case 'rounded':
                if (class_exists(\Endroid\QrCode\Shape\Eye\RoundedEyeShape::class)) {
                    $qrCode->setEyeShape(new \Endroid\QrCode\Shape\Eye\RoundedEyeShape($relativeRadius));
                }
                break;
            case 'circular':
                if (class_exists(\Endroid\QrCode\Shape\Eye\CircleEyeShape::class)) {
                    $qrCode->setEyeShape(new \Endroid\QrCode\Shape\Eye\CircleEyeShape());
                }
                break;
            case 'square':
            default:
                if (class_exists(\Endroid\QrCode\Shape\Eye\SquareEyeShape::class)) {
                    $qrCode->setEyeShape(new \Endroid\QrCode\Shape\Eye\SquareEyeShape());
                }
                break;
        }
    }

    /**
     * Create SVG writer (version-compatible)
     */
    private function createSvgWriter()
    {
        if (class_exists(\Endroid\QrCode\Writer\SvgWriter::class)) {
            return new \Endroid\QrCode\Writer\SvgWriter();
        }

        throw new \RuntimeException('SVG Writer not available in this version of Endroid QrCode');
    }

    /**
     * Create logo from configuration (version-compatible) - FIXED
     */
    private function createLogoFromConfig(array $config, int $qrCodeSize): ?object
    {
        $logoPath = trim($config['logo_file'] ?? '');
        if (empty($logoPath) || !class_exists(\Endroid\QrCode\Logo\Logo::class)) {
            return null;
        }

        try {
            $resolvedPath = $this->resolveLogoPath($logoPath);
            if (!$resolvedPath || !file_exists($resolvedPath)) {
                return null;
            }

            $scale = (int)($config['logo_scale'] ?? 30);
            $scale = max(5, min(50, $scale));
            $maxLogoSize = (int)($qrCodeSize * ($scale / 100));

            if (method_exists(\Endroid\QrCode\Logo\Logo::class, 'create')) {
                // Version 4.x+
                return \Endroid\QrCode\Logo\Logo::create($resolvedPath)
                    ->setResizeToWidth($maxLogoSize);
            }
            // Older versions
            $logo = new \Endroid\QrCode\Logo\Logo($resolvedPath);
            if (method_exists($logo, 'setResizeToWidth')) {
                $logo->setResizeToWidth($maxLogoSize);
            }
            return $logo;

        } catch (\Throwable $e) {
            error_log('QrCodeService: Logo creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve logo path
     */
    private function resolveLogoPath(string $logoPath): ?string
    {
        // Handle URL
        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }

        // Handle relative path
        if (!str_starts_with($logoPath, '/')) {
            $logoPath = GeneralUtility::getFileAbsFileName($logoPath);
        }

        return $logoPath && file_exists($logoPath) ? $logoPath : null;
    }

    /**
     * Convert hex color to RGB array
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000000'; // Fallback to black
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Validate size - FIXED
     */
    private function validateSize(int $size): int
    {
        return max(self::MIN_SIZE, min(self::MAX_SIZE, $size));
    }

    /**
     * Validate margin - FIXED
     */
    private function validateMargin(int $margin): int
    {
        return max(self::MIN_MARGIN, min(self::MAX_MARGIN, $margin));
    }

    /**
     * Create error SVG
     */
    private function createErrorSvg(string $message, array $config): string
    {
        $size = $this->validateSize((int)($config['size'] ?? self::DEFAULT_SIZE));
        $encodedMessage = htmlspecialchars($message);

        return "<svg width=\"{$size}\" height=\"{$size}\" xmlns=\"http://www.w3.org/2000/svg\">
            <rect width=\"{$size}\" height=\"{$size}\" fill=\"#fee2e2\" stroke=\"#dc2626\" stroke-width=\"2\" rx=\"8\"/>
            <text x=\"" . ($size/2) . '" y="' . ($size/2 - 20) . '" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#dc2626">
                QR Code Error
            </text>
            <foreignObject x="10" y="' . ($size/2) . '" width="' . ($size-20) . '" height="' . ($size/2-20) . "\">
                <div xmlns=\"http://www.w3.org/1999/xhtml\" style=\"font-size:12px; word-wrap:break-word; padding:5px; color:#7f1d1d; text-align:center;\">
                    {$encodedMessage}
                </div>
            </foreignObject>
        </svg>";
    }

    /**
     * Apply non-destructive SVG styling: foreground gradient, optional drop shadow,
     * and optional logo background tile with padding. Does not alter QR matrix data.
     */
    private function applySvgStyling(string $svg, array $config): string
    {
        // Helper closures to normalize configuration values, avoiding array-to-string warnings.
        $normalizeColor = function ($value, $default) {
            // Convert array-based colors to a hex string. Accepts [r,g,b] or ['r'=>..]
            if (is_array($value)) {
                // Detect associative or numeric array
                if (isset($value['r']) && isset($value['g']) && isset($value['b'])) {
                    $r = (int)$value['r'];
                    $g = (int)$value['g'];
                    $b = (int)$value['b'];
                } elseif (isset($value[0]) && isset($value[1]) && isset($value[2])) {
                    $r = (int)$value[0];
                    $g = (int)$value[1];
                    $b = (int)$value[2];
                } else {
                    // Fallback: join values as comma-separated string
                    return implode(',', $value);
                }
                // Clamp values and convert to hex
                $r = max(0, min(255, $r));
                $g = max(0, min(255, $g));
                $b = max(0, min(255, $b));
                return sprintf('#%02x%02x%02x', $r, $g, $b);
            }
            // If not array, cast to string and trim
            $val = trim((string)($value ?? ''));
            return $val !== '' ? $val : $default;
        };

        $normalizeString = function ($value, $default) {
            if (is_array($value)) {
                // Join array values into a single string
                return trim(implode(',', $value));
            }
            $val = trim((string)($value ?? ''));
            return $val !== '' ? $val : $default;
        };

        $normalizeInt = function ($value, $default) {
            if (is_array($value)) {
                return $default;
            }
            return (int)($value ?? $default);
        };

        // Normalize gradient colors
        $from = $normalizeColor($config['fg_gradient_from'] ?? '', '');
        $to   = $normalizeColor($config['fg_gradient_to'] ?? '', '');
        // Normalize numeric angle and drop shadow flag
        $angle = $normalizeInt($config['fg_gradient_angle'] ?? 0, 0);
        $shadow = !empty($config['drop_shadow']);
        // Normalize foreground color
        $fg = strtolower($normalizeColor($config['fg_color'] ?? self::DEFAULT_FG_COLOR, self::DEFAULT_FG_COLOR));
        // Normalize dot style and intensity
        $dotStyle = strtolower($normalizeString($config['dot_style'] ?? 'square', 'square'));
        $dotIntensity = max(1, min(10, $normalizeInt($config['dot_intensity'] ?? 5, 5)));
        // Normalize eye style and radius
        $eyeStyle = strtolower($normalizeString($config['eye_style'] ?? 'square', 'square'));
        $eyeRadius = $normalizeInt($config['eye_radius'] ?? 0, 0);

        $needsGradient = ($from !== '' && $to !== '');
        $needsLogoBg = !empty($config['logo_bg']);
        $needsDotStyling = ($dotStyle !== 'square');
        $needsEyeStyling = ($eyeStyle !== 'square' || $eyeRadius > 0);

        if (!$needsGradient && !$shadow && !$needsLogoBg && !$needsDotStyling && !$needsEyeStyling) {
            return $svg;
        }

        // Insert <defs> right after <svg ...>
        if (!preg_match('/<svg[^>]*>/i', $svg, $m, PREG_OFFSET_CAPTURE)) {
            return $svg;
        }
        $insertPos = $m[0][1] + strlen($m[0][0]);

        $defs = '<defs>';

        if ($needsGradient) {
            $rad = deg2rad($angle % 360);
            $x1 = 50 - 50 * cos($rad);
            $y1 = 50 - 50 * sin($rad);
            $x2 = 50 + 50 * cos($rad);
            $y2 = 50 + 50 * sin($rad);

            $defs .= sprintf(
                '<linearGradient id="fgGrad" gradientUnits="userSpaceOnUse" x1="%1$.2f%%" y1="%2$.2f%%" x2="%3$.2f%%" y2="%4$.2f%%">
                <stop offset="0%%" stop-color="%5$s"/>
                <stop offset="100%%" stop-color="%6$s"/>
             </linearGradient>',
                $x1,
                $y1,
                $x2,
                $y2,
                htmlspecialchars($from),
                htmlspecialchars($to)
            );
        }

        if ($shadow) {
            $defs .= '<filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity="0.25"/></filter>';
        }

        $defs .= '</defs>';

        $svg = substr($svg, 0, $insertPos) . $defs . substr($svg, $insertPos);

        // Foreground gradient: replace only fills that match the configured fg_color (3 or 6 hex)
        if ($needsGradient) {
            $fgNorm = strtolower($fg);
            if ($fgNorm[0] !== '#') {
                $fgNorm = '#' . $fgNorm;
            }

            // Beide Farbformate vorbereiten (#rrggbb und #rgb)
            $colorVariants = [$fgNorm];

            // Wenn es eine lange Form ist, erstelle auch die kurze Variante
            if (strlen($fgNorm) === 7 && $fgNorm[1] === $fgNorm[2] && $fgNorm[3] === $fgNorm[4] && $fgNorm[5] === $fgNorm[6]) {
                $short = '#' . $fgNorm[1] . $fgNorm[3] . $fgNorm[5];
                $colorVariants[] = $short;
            }
            // Wenn es eine kurze Form ist, erstelle auch die lange Variante
            elseif (strlen($fgNorm) === 4) {
                $long = '#' . $fgNorm[1] . $fgNorm[1] . $fgNorm[2] . $fgNorm[2] . $fgNorm[3] . $fgNorm[3];
                $colorVariants[] = $long;
            }

            // Escape alle Farbvarianten für Regex
            $escapedColors = [];
            foreach ($colorVariants as $color) {
                $escapedColors[] = preg_quote($color, '/');
            }

            // Regex Pattern für alle Farbvarianten erstellen
            $pattern = '/fill="(' . implode('|', $escapedColors) . ')"/i';

            // Ersetzung durchführen
            $svg = preg_replace($pattern, 'fill="url(#fgGrad)"', $svg);
        }

        // Optional shadow -> wrap entire content into group with filter
        if ($shadow) {
            // Korrekte Regex für Shadow-Ersetzung
            if (preg_match('/(<svg[^>]*>)(.*)(<\/svg>)/is', $svg, $matches)) {
                $svg = $matches[1] . '<g filter="url(#softShadow)">' . $matches[2] . '</g>' . $matches[3];
            }
        }

        // Optional logo background tile with padding/radius under the <image>
        if ($needsLogoBg) {
            if (preg_match('/<image[^>]*\bx="([\d.]+)".*?\by="([\d.]+)".*?\bwidth="([\d.]+)".*?\bheight="([\d.]+)"/is', $svg, $m, PREG_OFFSET_CAPTURE)) {
                $x = (float)$m[1][0];
                $y = (float)$m[2][0];
                $w = (float)$m[3][0];
                $h = (float)$m[4][0];

                $pad = max(0, (int)($config['logo_bg_padding'] ?? 4));
                $rx  = max(0, (int)($config['logo_bg_radius'] ?? 8));
                // Normalize logo background color to avoid array-to-string issues
                $logoBgColor = $config['logo_bg_color'] ?? '#FFFFFF';
                $bg  = htmlspecialchars($normalizeColor($logoBgColor, '#FFFFFF'));

                $rect = sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="%s"/>',
                    $x - $pad,
                    $y - $pad,
                    $w + 2*$pad,
                    $h + 2*$pad,
                    $rx,
                    $rx,
                    $bg
                );

                // KORREKTUR: Richtigen Offset aus dem Array nehmen
                $pos = (int)$m[0][1];
                $svg = substr($svg, 0, $pos) . $rect . substr($svg, $pos);
            }
        }

        // Dot styling anwenden (SVG-basierter Fallback)
        if ($needsDotStyling) {
            $svg = $this->applyDotStyling($svg, $dotStyle, $dotIntensity);
        }

        $eyeFill = $needsGradient ? 'url(#fgGrad)' : $fg;
        $eyeBg = $normalizeColor($config['bg_color'] ?? self::DEFAULT_BG_COLOR, self::DEFAULT_BG_COLOR);

        // Eye styling anwenden (SVG-basierter Fallback)
        if ($needsEyeStyling) {
            $svg = $this->applyEyeStyling($svg, $eyeStyle, $eyeRadius, $eyeFill, $eyeBg);
        }

        return $svg;
    }

    /**
     * Enhanced SVG manipulation for dot styles (Fallback für ältere Versionen)
     */
    private function applyDotStyling(string $svg, string $dotStyle, int $intensity): string
    {
        if ($dotStyle === 'square') {
            return $svg;
        }

        $rounding = min(0.9, max(0.1, $intensity * 0.1));
        $svg = $this->applyDotStylingToBlockDefinition($svg, $dotStyle, $rounding);

        // Regex für alle schwarzen Rechtecke (QR-Module)
        $pattern = '/<rect x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="([\d.]+)"(?:\s+rx="[\d.]+")?(?:\s+ry="[\d.]+")?\s+fill="([^"]+)"\/>/';

        return preg_replace_callback($pattern, function ($matches) use ($dotStyle, $rounding) {
            $x = floatval($matches[1]);
            $y = floatval($matches[2]);
            $width = floatval($matches[3]);
            $height = floatval($matches[4]);
            $fill = (string)$matches[5];

            // Nur kleine Quadrate (QR-Module) verändern, nicht die Finder Patterns
            if ($width === $height && $width <= 3) {
                return $this->createDotShape($x, $y, $width, $dotStyle, $rounding, $fill);
            }

            return $matches[0];
        }, $svg);
    }

    private function applyDotStylingToBlockDefinition(string $svg, string $dotStyle, float $rounding): string
    {
        $pattern = '/<rect id="block" width="([\d.]+)" height="([\d.]+)" fill="([^"]+)"(?:\s+fill-opacity="([^"]+)")?\/>/';

        return preg_replace_callback($pattern, function ($matches) use ($dotStyle, $rounding) {
            $width = (float)$matches[1];
            $height = (float)$matches[2];
            $fill = htmlspecialchars((string)$matches[3], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $fillOpacity = isset($matches[4]) && $matches[4] !== ''
                ? ' fill-opacity="' . htmlspecialchars((string)$matches[4], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                : '';
            $size = min($width, $height);
            $inset = max(0.0, $size * 0.12);
            $softSize = max(0.0, $size - ($inset * 2));
            $softRadius = max(0.2, $softSize * min(0.45, $rounding * 0.55));

            return match ($dotStyle) {
                'rounded' => sprintf(
                    '<rect id="block" width="%s" height="%s" rx="%s" ry="%s" fill="%s"%s/>',
                    $width,
                    $height,
                    $size * $rounding / 2,
                    $size * $rounding / 2,
                    $fill,
                    $fillOpacity
                ),
                'dots' => sprintf(
                    '<circle id="block" cx="%s" cy="%s" r="%s" fill="%s"%s/>',
                    $width / 2,
                    $height / 2,
                    $size * $rounding / 2,
                    $fill,
                    $fillOpacity
                ),
                'circles' => sprintf(
                    '<circle id="block" cx="%s" cy="%s" r="%s" fill="%s"%s/>',
                    $width / 2,
                    $height / 2,
                    $size / 2,
                    $fill,
                    $fillOpacity
                ),
                'bubble' => sprintf(
                    '<g id="block"><circle cx="%s" cy="%s" r="%s" fill="%s"%s/><circle cx="%s" cy="%s" r="%s" fill="#ffffff" fill-opacity="0.16"/></g>',
                    $width / 2,
                    $height / 2,
                    $size * 0.42,
                    $fill,
                    $fillOpacity,
                    $width * 0.36,
                    $height * 0.36,
                    $size * 0.11
                ),
                'diamond' => sprintf(
                    '<path id="block" d="M %1$s 0 L %2$s %3$s L %1$s %4$s L 0 %3$s Z" fill="%5$s"%6$s/>',
                    $width / 2,
                    $width,
                    $height / 2,
                    $height,
                    $fill,
                    $fillOpacity
                ),
                'softsquare' => sprintf(
                    '<rect id="block" x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="%s"%s/>',
                    $inset,
                    $inset,
                    $softSize,
                    $softSize,
                    $softRadius,
                    $softRadius,
                    $fill,
                    $fillOpacity
                ),
                default => $matches[0],
            };
        }, $svg) ?? $svg;
    }

    /**
     * Create dot shape for SVG
     */
    private function createDotShape(float $x, float $y, float $size, string $style, float $rounding, string $fill): string
    {
        $centerX = $x + $size / 2;
        $centerY = $y + $size / 2;
        $fill = htmlspecialchars($fill, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $inset = max(0.0, $size * 0.12);

        switch ($style) {
            case 'rounded':
                $rx = $ry = $size * $rounding / 2;
                return sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="%s"/>',
                    $x,
                    $y,
                    $size,
                    $size,
                    $rx,
                    $ry,
                    $fill
                );

            case 'dots':
                $r = $size * $rounding / 2;
                return sprintf(
                    '<circle cx="%s" cy="%s" r="%s" fill="%s"/>',
                    $centerX,
                    $centerY,
                    $r,
                    $fill
                );

            case 'circles':
                return sprintf(
                    '<circle cx="%s" cy="%s" r="%s" fill="%s"/>',
                    $centerX,
                    $centerY,
                    $size / 2,
                    $fill
                );

            case 'bubble':
                return sprintf(
                    '<circle cx="%s" cy="%s" r="%s" fill="%s"/><circle cx="%s" cy="%s" r="%s" fill="#ffffff" fill-opacity="0.16"/>',
                    $centerX,
                    $centerY,
                    $size * 0.42,
                    $fill,
                    $centerX - $size * 0.14,
                    $centerY - $size * 0.14,
                    $size * 0.11
                );

            case 'diamond':
                return sprintf(
                    '<path d="M %1$s %2$s L %3$s %4$s L %5$s %6$s L %7$s %8$s Z" fill="%9$s"/>',
                    $centerX,
                    $y,
                    $x + $size,
                    $centerY,
                    $centerX,
                    $y + $size,
                    $x,
                    $centerY,
                    $fill
                );

            case 'softsquare':
                $softSize = max(0.0, $size - ($inset * 2));
                $radius = max(0.2, $softSize * min(0.45, $rounding * 0.55));
                return sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="%s"/>',
                    $x + $inset,
                    $y + $inset,
                    $softSize,
                    $softSize,
                    $radius,
                    $radius,
                    $fill
                );

            default:
                return sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>',
                    $x,
                    $y,
                    $size,
                    $size,
                    $fill
                );
        }
    }

    /**
     * Apply eye styling to SVG (wird in applySvgStyling aufgerufen)
     */
    private function applyEyeStyling(string $svg, string $style, int $radius, string $fill, string $backgroundFill): string
    {
        if ($style === 'square' && $radius === 0) {
            return $svg; // Keine Änderung nötig
        }

        $usePattern = '/<use\b[^>]*\bx="([\d.]+)"\s+\by="([\d.]+)"[^>]*xlink:href="#block"[^>]*\/>/i';
        if (!preg_match_all($usePattern, $svg, $matches, PREG_SET_ORDER)) {
            return $svg;
        }

        $xValues = [];
        $yValues = [];
        foreach ($matches as $match) {
            $xValues[] = (float)$match[1];
            $yValues[] = (float)$match[2];
        }

        $uniqueX = array_values(array_unique(array_map(static fn (float $value): string => sprintf('%.4F', $value), $xValues)));
        $uniqueY = array_values(array_unique(array_map(static fn (float $value): string => sprintf('%.4F', $value), $yValues)));
        sort($uniqueX, SORT_NATURAL);
        sort($uniqueY, SORT_NATURAL);
        if (count($uniqueX) < 7 || count($uniqueY) < 7) {
            return $svg;
        }

        $toFloat = static fn (string $value): float => (float)$value;
        $moduleX = $toFloat($uniqueX[0]);
        $moduleY = $toFloat($uniqueY[0]);
        $moduleStepX = isset($uniqueX[1]) ? max(0.1, $toFloat($uniqueX[1]) - $moduleX) : 10.0;
        $moduleStepY = isset($uniqueY[1]) ? max(0.1, $toFloat($uniqueY[1]) - $moduleY) : 10.0;
        $moduleSize = min($moduleStepX, $moduleStepY);

        $left = $moduleX;
        $top = $moduleY;
        $right = $toFloat($uniqueX[count($uniqueX) - 7]);
        $bottom = $toFloat($uniqueY[count($uniqueY) - 7]);

        $eyeZones = [
            [$left, $top],
            [$right, $top],
            [$left, $bottom],
        ];

        $filteredSvg = preg_replace_callback($usePattern, static function (array $match) use ($eyeZones, $moduleStepX, $moduleStepY): string {
            $x = (float)$match[1];
            $y = (float)$match[2];

            foreach ($eyeZones as [$eyeX, $eyeY]) {
                if ($x >= $eyeX && $x < ($eyeX + (7 * $moduleStepX)) && $y >= $eyeY && $y < ($eyeY + (7 * $moduleStepY))) {
                    return '';
                }
            }

            return $match[0];
        }, $svg);

        if (!is_string($filteredSvg)) {
            return $svg;
        }

        $eyeMarkup = '';
        foreach ($eyeZones as [$eyeX, $eyeY]) {
            $eyeMarkup .= $this->createFinderPatternShape($eyeX, $eyeY, $moduleSize, $style, $radius, $fill, $backgroundFill);
        }

        return preg_replace('/<\/svg>\s*$/i', $eyeMarkup . '</svg>', $filteredSvg) ?? $filteredSvg;
    }

    /**
     * Create full finder pattern eye based on style
     */
    private function createFinderPatternShape(float $x, float $y, float $moduleSize, string $style, int $radius, string $fill, string $backgroundFill): string
    {
        $outer = $this->createEyeShape($x, $y, 7 * $moduleSize, $style, $radius, $fill);
        $middle = $this->createEyeShape($x + $moduleSize, $y + $moduleSize, 5 * $moduleSize, $style, $radius, $backgroundFill);
        $inner = $this->createEyeShape($x + (2 * $moduleSize), $y + (2 * $moduleSize), 3 * $moduleSize, $style, $radius, $fill);

        return '<g class="dynamic-qrcode-eye">' . $outer . $middle . $inner . '</g>';
    }

    /**
     * Create SVG shape for eye based on style
     */
    private function createEyeShape(float $x, float $y, float $size, string $style, int $radius, string $fill): string
    {
        $centerX = $x + $size / 2;
        $centerY = $y + $size / 2;
        $fill = htmlspecialchars($fill, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        switch ($style) {
            case 'rounded':
                $rx = $ry = min((float)$radius, $size / 2);
                return sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="%s"/>',
                    $x,
                    $y,
                    $size,
                    $size,
                    $rx,
                    $ry,
                    $fill
                );

            case 'circular':
                $r = $size / 2;
                return sprintf(
                    '<circle cx="%s" cy="%s" r="%s" fill="%s"/>',
                    $centerX,
                    $centerY,
                    $r,
                    $fill
                );

            case 'softsquare':
                $corner = max(1.0, $size * 0.22);
                return sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" fill="%s"/>',
                    $x,
                    $y,
                    $size,
                    $size,
                    $corner,
                    $corner,
                    $fill
                );

            case 'diamond':
                return sprintf(
                    '<path d="M %1$s %2$s L %3$s %4$s L %5$s %6$s L %7$s %8$s Z" fill="%9$s"/>',
                    $centerX,
                    $y,
                    $x + $size,
                    $centerY,
                    $centerX,
                    $y + $size,
                    $x,
                    $centerY,
                    $fill
                );

            case 'square':
            default:
                return sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>',
                    $x,
                    $y,
                    $size,
                    $size,
                    $fill
                );
        }
    }

    /**
     * Get library version info for debugging
     */
    public function getLibraryInfo(): array
    {
        $info = ['available' => false];

        if (!class_exists(\Endroid\QrCode\QrCode::class)) {
            $info['error'] = 'Endroid QrCode library not found';
            return $info;
        }

        $info['available'] = true;
        $info['classes'] = [];

        // Check available classes
        $classes = [
            'QrCode' => \Endroid\QrCode\QrCode::class,
            'SvgWriter' => \Endroid\QrCode\Writer\SvgWriter::class,
            'Color' => \Endroid\QrCode\Color\Color::class ?? null,
            'Encoding' => \Endroid\QrCode\Encoding\Encoding::class ?? null,
            'Logo' => \Endroid\QrCode\Logo\Logo::class ?? null,
            'ErrorCorrectionLevelMedium' => \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium::class ?? null,
            'ShapeInterface' => \Endroid\QrCode\Shape\ShapeInterface::class ?? null,
            'EyeShapeInterface' => \Endroid\QrCode\Shape\EyeShapeInterface::class ?? null,
            'RoundedShape' => \Endroid\QrCode\Shape\RoundedShape::class ?? null,
            'DotShape' => \Endroid\QrCode\Shape\DotShape::class ?? null,
            'CircleShape' => \Endroid\QrCode\Shape\CircleShape::class ?? null,
            'RoundedEyeShape' => \Endroid\QrCode\Shape\Eye\RoundedEyeShape::class ?? null,
            'CircleEyeShape' => \Endroid\QrCode\Shape\Eye\CircleEyeShape::class ?? null,
            'SquareEyeShape' => \Endroid\QrCode\Shape\Eye\SquareEyeShape::class ?? null,
        ];

        foreach ($classes as $name => $class) {
            $info['classes'][$name] = $class && class_exists($class);
        }

        return $info;
    }
}
