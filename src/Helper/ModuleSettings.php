<?php

namespace Makaira\OxidConnect\Helper;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use Symfony\Component\String\UnicodeString;

class ModuleSettings implements ModuleSettingServiceInterface
{
    public function __construct(
        private string $moduleId,
        private ModuleSettingServiceInterface $moduleSettingService,
    ) {
    }

    public function getInteger(string $name, ?string $moduleId = null): int
    {
        return $this->moduleSettingService->getInteger($name, $moduleId ?? $this->moduleId);
    }

    public function getFloat(string $name, ?string $moduleId = null): float
    {
        return $this->moduleSettingService->getFloat($name, $moduleId ?? $this->moduleId);
    }

    public function getString(string $name, ?string $moduleId = null): UnicodeString
    {
        return $this->moduleSettingService->getString($name, $moduleId ?? $this->moduleId);
    }

    public function getBoolean(string $name, ?string $moduleId = null): bool
    {
        return $this->moduleSettingService->getBoolean($name, $moduleId ?? $this->moduleId);
    }

    public function getCollection(string $name, ?string $moduleId = null): array
    {
        return $this->moduleSettingService->getCollection($name, $moduleId ?? $this->moduleId);
    }

    public function saveInteger(string $name, int $value, ?string $moduleId = null): void
    {
        $this->moduleSettingService->saveInteger($name, $value, $moduleId ?? $this->moduleId);
    }

    public function saveFloat(string $name, float $value, ?string $moduleId = null): void
    {
        $this->moduleSettingService->saveFloat($name, $value, $moduleId ?? $this->moduleId);
    }

    public function saveString(string $name, string $value, ?string $moduleId = null): void
    {
        $this->moduleSettingService->saveString($name, $value, $moduleId ?? $this->moduleId);
    }

    public function saveBoolean(string $name, bool $value, ?string $moduleId = null): void
    {
        $this->moduleSettingService->saveBoolean($name, $value, $moduleId ?? $this->moduleId);
    }

    public function saveCollection(string $name, array $value, ?string $moduleId = null): void
    {
        $this->moduleSettingService->saveCollection($name, $value, $moduleId ?? $this->moduleId);
    }

    public function exists(string $name, ?string $moduleId = null): bool
    {
        return $this->moduleSettingService->exists($name, $moduleId ?? $this->moduleId);
    }
}
