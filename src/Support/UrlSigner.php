<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Support;

use RuntimeException;

class UrlSigner
{
    public function __construct(private string $key) {}

    public function sign(array $params): string
    {
        if ($this->key === '') {
            throw new RuntimeException('APP_KEY is not set. Cannot sign URLs without a secret key.');
        }

        ksort($params);

        return hash_hmac('sha256', http_build_query($params), $this->key);
    }

    public function verify(array $params): bool
    {
        $sig = $params['sig'] ?? '';
        unset($params['sig']);

        if ($sig === '') {
            return false;
        }

        return hash_equals($this->sign($params), $sig);
    }
}
