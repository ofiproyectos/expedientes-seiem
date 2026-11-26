<?php

namespace Tests\Feature;

use App\Services\Identity\IdentityProfile;
use Tests\TestCase;

class IdentityProfileTest extends TestCase
{
    public function test_local_configuration_is_explicit_and_has_no_institutional_secrets(): void
    {
        app(IdentityProfile::class)->validate();
        $this->assertTrue(app(IdentityProfile::class)->canManageLocalAccounts());
        config(['identity.access_token' => 'contradictory']);
        $this->expectException(\LogicException::class);
        app(IdentityProfile::class)->validate();
    }

    public function test_missing_and_unknown_values_are_rejected(): void
    {
        foreach ([null, 'true', 'unknown', 1] as $value) {
            config(['identity.enabled' => $value]);
            try {
                app(IdentityProfile::class)->validate();
                $this->fail('Invalid profile accepted');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('OIDC_ENABLED', $exception->getMessage());
            }
        }
    }

    public function test_production_cannot_enable_local_credentials(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(\LogicException::class);
        app(IdentityProfile::class)->validate();
    }

    public function test_institutional_configuration_requires_https_and_secrets(): void
    {
        config(['identity.enabled' => true, 'identity.issuer' => 'http://insecure.test']);
        $this->expectException(\LogicException::class);
        app(IdentityProfile::class)->validate();
    }

    public function test_demo_command_rejects_non_isolated_database(): void
    {
        $this->artisan('expedientes:demo')->assertExitCode(1);
    }

    public function test_institutional_profile_blocks_local_admin_command_before_database_access(): void
    {
        config(['identity.enabled' => true]);
        $this->artisan('expedientes:admin local.admin')->assertExitCode(1);
    }
}
