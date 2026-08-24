<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Drop the auth and JWT singletons so the next request in this test starts
     * from a clean container, the way a real request does.
     *
     * Feature tests run every request through one PHP process, so jwt-auth's
     * singletons survive between them: the guard keeps the token it last saw,
     * and the payload factory keeps the custom claims it last built. That state
     * makes a stale token look valid and hides blacklist hits. Production never
     * sees it, since each HTTP request boots its own process.
     *
     * Call this between requests whenever a test depends on the identity or
     * validity of a specific token.
     */
    protected function isolateNextRequest(): void
    {
        $this->app['auth']->forgetGuards();

        foreach ([
            'tymon.jwt',
            'tymon.jwt.auth',
            'tymon.jwt.manager',
            'tymon.jwt.blacklist',
            'tymon.jwt.parser',
            'tymon.jwt.payload.factory',
            'tymon.jwt.claim.factory',
            'tymon.jwt.provider.jwt',
            'tymon.jwt.validators.payload',
        ] as $binding) {
            $this->app->forgetInstance($binding);
        }
    }
}
