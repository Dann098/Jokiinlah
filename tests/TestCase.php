<?php

namespace Tests;

use App\Contracts\MalwareScannerInterface;
use App\Models\User;
use App\Services\Malware\FakeMalwareScanner;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MalwareScannerInterface::class, new FakeMalwareScanner);
    }

    public function actingAs(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User
            && ($user->isAdmin() || $user->isStaff())
            && $user->hasEnabledTwoFactorAuthentication()) {
            $this->withSession(['security.two_factor_passed_user' => $user->getKey()]);
        }

        return $this;
    }
}
