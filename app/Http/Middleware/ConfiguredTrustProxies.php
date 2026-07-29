<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

class ConfiguredTrustProxies extends TrustProxies
{
    protected function proxies()
    {
        $configured = config('security.trusted_proxies', []);

        return $configured === [] ? parent::proxies() : $configured;
    }
}
