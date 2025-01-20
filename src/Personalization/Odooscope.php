<?php

namespace Makaira\OxidConnect\Personalization;

use function sprintf;

class Odooscope extends AbstractPersonalization
{
    private string $cookieName;

    public function __construct(string $token, array $data = [])
    {
        $this->cookieName = sprintf('osc-%s', $token);

        $data['token']     = $token;
        $data['osccookie'] = $this->cookieName;

        parent::__construct($data);
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    public function getType(): string
    {
        return 'odoscope';
    }
}
