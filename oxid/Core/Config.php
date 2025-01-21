<?php

namespace Makaira\OxidConnect\Oxid\Core;

use function is_array;

class Config extends Config_parent
{
    public function setEcondaThemeParam(): void
    {
        if (!isset($this->_aThemeConfigParams['sEcondaRecommendationsAID'])) {
            $this->_aThemeConfigParams['sEcondaRecommendationsAID'] = 'makaira/connect';
        }
    }
}
