<?php

namespace Makaira\OxidConnect\Http\Middleware;

use Exception;
use Makaira\OxidConnect\Http\Request;

use function bin2hex;
use function hash_final;
use function hash_update;
use function random_bytes;

use const HASH_HMAC;
use const PHP_INT_SIZE;

class Signature implements MiddlewareInterface
{
    public const NONCE_HEADER_NAME = 'X-Makaira-Nonce';

    public const HASE_HEADER_NAME = 'X-Makaira-Hash';

    public function __construct(private string $apiKey)
    {
    }

    /**
     * @throws Exception
     */
    public function apply(Request $request): Request
    {
        $nonce = bin2hex(random_bytes(PHP_INT_SIZE));

        $ctx = hash_init('sha256', HASH_HMAC, $this->apiKey);
        hash_update($ctx, $nonce);
        hash_update($ctx, ':');
        hash_update($ctx, $request->getBody());
        $hash = hash_final($ctx);

        return $request->withHeaders([self::NONCE_HEADER_NAME => $nonce, self::HASE_HEADER_NAME => $hash]);
    }
}
