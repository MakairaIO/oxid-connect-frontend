<?php
/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 * Version:    1.0
 * Author:     Jens Richter <richter@marmalade.de>
 * Author URI: http://www.marmalade.de
 */

namespace Makaira\OxidConnect\Utils;

use Makaira\AbstractQuery;
use Makaira\Constraints;
use Makaira\OxidConnect\Helper\Cookies;

class OperationalIntelligence
{
    private const COOKIE_NAME_ID       = 'oiID';
    private const COOKIE_NAME_TIMEZONE = 'oiLocalTimeZone';

    public function __construct(private Cookies $cookieUtils)
    {
    }

    public function apply(AbstractQuery $query): void
    {
        $query->constraints[Constraints::OI_USER_AGENT]    = $this->getUserAgentString();
        $query->constraints[Constraints::OI_USER_IP]       = $this->anonymizeIp($this->getUserIP());
        $query->constraints[Constraints::OI_USER_ID]       = $this->generateUserID();
        $query->constraints[Constraints::OI_USER_TIMEZONE] = $this->getUserTimeZone();
    }

    /**
     * Get User ID (set cookie "oiID")
     */
    private function generateUserID(): string
    {
        $userID = (string) ($_COOKIE[self::COOKIE_NAME_ID] ?? '');

        if (!$userID) {
            $userID = $this->getUserIP();
            $userID .= $this->getUserAgentString();

            $userID = md5($userID);

            $this->cookieUtils->setCookie(self::COOKIE_NAME_ID, $userID, time() + 86400);
        }

        return $userID;
    }

    /**
     * Get actual User IP
     * 1) from $_SERVER['X_FORWARDED_FOR']
     * 2) from $_SERVER['REMOTE_ADDR']
     */
    private function getUserIP(): string
    {
        return ($_SERVER['X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Replaces the last two digits of an IPv4 address with ".0.0".
     *
     * @param string $ip IPv4 address to anonymize.
     *
     * @return string
     */
    private function anonymizeIp(string $ip): string
    {
        return preg_replace('/\.\d+\.\d+$/', '.0.0', $ip);
    }

    /**
     * Get actual User Agent String (raw data)
     */
    private function getUserAgentString(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get User Time Zone from cookie "oiLocalTimeZone"
     */
    private function getUserTimeZone()
    {
        return $_COOKIE[self::COOKIE_NAME_TIMEZONE] ?? '';
    }
}
