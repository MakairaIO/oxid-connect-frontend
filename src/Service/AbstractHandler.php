<?php
/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * @version 0.1
 * @author  Stefan Krenz <krenz@marmalade.de>
 * @link    http://www.marmalade.de
 */

namespace Makaira\OxidConnect\Service;

use Makaira\HttpClient;

abstract class AbstractHandler
{
    /**
     * AbstractHandler constructor.
     *
     * @param HttpClient $httpClient
     */
    public function __construct(protected HttpClient $httpClient)
    {
    }
}
