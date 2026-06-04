<?php

namespace Vendor\DynamicQrcode\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class QrCode extends AbstractEntity
{
    protected string $title = '';
    protected string $targetUrl = '';
    protected string $stylePreset = 'custom';
    protected string $fgColor = '#000000';
    protected string $bgColor = '#FFFFFF';
    protected string $errorCorrection = 'M';
    protected int $size = 256;
    protected int $margin = 2;
    protected string $logoFile = '';
    protected string $fgGradientFrom = '';
    protected string $fgGradientTo   = '';
    protected int $fgGradientAngle = 0;
    protected int $logoScale = 30;
    protected bool $dropShadow = false;
    protected bool $logoBg = false;
    protected string $logoBgColor = '#FFFFFF';
    protected int $logoBgRadius = 8;
    protected int $logoBgPadding = 4;
    protected string $dotStyle = 'square';
    protected int $dotIntensity = 5;
    protected bool $roundedModules = false;
    protected string $eyeStyle = 'square';
    protected int $eyeRadius = 0;

    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $v): void
    {
        $this->title = $v;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }
    public function setTargetUrl(string $v): void
    {
        $this->targetUrl = $v;
    }

    public function getStylePreset(): string
    {
        return $this->stylePreset;
    }
    public function setStylePreset(string $v): void
    {
        $this->stylePreset = $v;
    }

    public function getFgColor(): string
    {
        return $this->fgColor;
    }
    public function setFgColor(string $v): void
    {
        $this->fgColor = $v;
    }

    public function getBgColor(): string
    {
        return $this->bgColor;
    }
    public function setBgColor(string $v): void
    {
        $this->bgColor = $v;
    }

    public function getErrorCorrection(): string
    {
        return $this->errorCorrection;
    }
    public function setErrorCorrection(string $v): void
    {
        $this->errorCorrection = $v;
    }

    public function getSize(): int
    {
        return $this->size;
    }
    public function setSize(int $v): void
    {
        $this->size = $v;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }
    public function setMargin(int $v): void
    {
        $this->margin = $v;
    }

    public function getLogoFile(): string
    {
        return $this->logoFile;
    }
    public function setLogoFile(string $v): void
    {
        $this->logoFile = $v;
    }

    public function isRoundedModules(): bool
    {
        return $this->roundedModules;
    }
    public function setRoundedModules(bool $v): void
    {
        $this->roundedModules = $v;
    }

    public function getEyeRadius(): int
    {
        return $this->eyeRadius;
    }
    public function setEyeRadius(int $v): void
    {
        $this->eyeRadius = $v;
    }
    public function getFgGradientFrom(): string
    {
        return $this->fgGradientFrom;
    }
    public function setFgGradientFrom(string $v): void
    {
        $this->fgGradientFrom = $v;
    }

    public function getFgGradientTo(): string
    {
        return $this->fgGradientTo;
    }
    public function setFgGradientTo(string $v): void
    {
        $this->fgGradientTo = $v;
    }

    public function getFgGradientAngle(): int
    {
        return $this->fgGradientAngle;
    }
    public function setFgGradientAngle(int $v): void
    {
        $this->fgGradientAngle = $v;
    }

    public function getLogoScale(): int
    {
        return $this->logoScale;
    }
    public function setLogoScale(int $v): void
    {
        $this->logoScale = $v;
    }

    public function isDropShadow(): bool
    {
        return $this->dropShadow;
    }
    public function setDropShadow(bool $v): void
    {
        $this->dropShadow = $v;
    }
    public function isLogoBg(): bool
    {
        return $this->logoBg;
    }        public function setLogoBg(bool $v): void
    {
        $this->logoBg = $v;
    }
    public function getLogoBgColor(): string
    {
        return $this->logoBgColor;
    } public function setLogoBgColor(string $v): void
    {
        $this->logoBgColor = $v;
    }
    public function getLogoBgRadius(): int
    {
        return $this->logoBgRadius;
    }  public function setLogoBgRadius(int $v): void
    {
        $this->logoBgRadius = $v;
    }
    public function getLogoBgPadding(): int
    {
        return $this->logoBgPadding;
    }public function setLogoBgPadding(int $v): void
    {
        $this->logoBgPadding = $v;
    }
    public function getDotStyle(): string
    {
        return $this->dotStyle;
    }
    public function setDotStyle(string $v): void
    {
        $this->dotStyle = $v;
    }

    public function getDotIntensity(): int
    {
        return $this->dotIntensity;
    }
    public function setDotIntensity(int $v): void
    {
        $this->dotIntensity = $v;
    }

    public function getEyeStyle(): string
    {
        return $this->eyeStyle;
    }
    public function setEyeStyle(string $v): void
    {
        $this->eyeStyle = $v;
    }

}
