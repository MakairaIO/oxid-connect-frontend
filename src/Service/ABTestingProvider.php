<?php

namespace Makaira\OxidConnect\Service;

use Makaira\OxidConnect\Helper\Cookies;

class ABTestingProvider
{
    private array $experiments = [];

    public function __construct(private Cookies $cookies)
    {
    }

    public function getExperiments(): array
    {
        if (empty($this->experiments)) {
            $this->experiments = $this->cookies->getExperiments();
        }
        return $this->experiments;
    }

    public function setExperiments(array $experiments): void
    {
        $this->cookies->setExperiments($experiments);
        $this->experiments = $experiments;
    }
}
