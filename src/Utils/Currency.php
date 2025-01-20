<?php

namespace Makaira\OxidConnect\Utils;

use Makaira\OxidConnect\Helper\OxidSettings;

use function round;

class Currency
{
    public function __construct(private OxidSettings $oxidSettings)
    {
    }

    public function toCurrency(float $value): float
    {
        $currency = $this->oxidSettings->getCurrentCurrency();

        return round($value * $currency->rate, 2);
    }

    public function fromCurrency(float $value): float
    {
        $currency = $this->oxidSettings->getCurrentCurrency();

        return round($value / $currency->rate, 2);
    }
}
