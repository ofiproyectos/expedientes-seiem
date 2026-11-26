<?php

namespace Tests\Feature;

use App\Models\Expediente;
use App\Models\User;
use App\Services\Identity\LaravelOidcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class InstitutionalIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['identity.enabled' => true, 'identity.issuer' => 'https://identity.test/realms/seiem',
            'identity.client_id' => 'expedientes-seiem', 'identity.client_secret' => 'test-only',
            'identity.redirect_uri' => 'http://localhost/auth/callback', 'identity.portal_url' => 'https://portal.test',
            'identity.access_url' => 'https://portal.test/api/integraciones/expedientes/acceso', 'identity.access_token' => str_repeat('t', 48),
            'identity.bootstrap_admin_sub' => 'bootstrap-subject']);
        Http::preventStrayRequests();
    }

    private function account(string $role = 'READER'): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->identity_issuer = config('identity.issuer');
        $user->identity_subject = fake()->uuid();
        $user->save();

        return $user;
    }

    private function completeCallback(string $subject, string $username)
    {
        $client = Mockery::mock(LaravelOidcClient::class);
        $client->shouldReceive('authenticate')->once()->andReturn(true);
        $client->shouldReceive('getVerifiedClaims')->once()->andReturn((object) ['sub' => $subject, 'preferred_username' => $username, 'name' => 'Cuenta Institucional', 'sid' => 'provider-session']);
        $client->shouldReceive('getIdToken')->zeroOrMoreTimes()->andReturn('id-token');
        $this->app->instance(LaravelOidcClient::class, $client);

        return $this->withSession(['oidc.handshake.openid_connect_state' => 'expected', 'oidc.started_at' => time()])->get('/auth/callback?state=expected&code=test');
    }

    public function test_local_password_login_is_disabled_and_forged_callbacks_never_reach_provider(): void
    {
        $client = Mockery::mock(LaravelOidcClient::class);
        $client->shouldNotReceive('authenticate');
        $this->app->instance(LaravelOidcClient::class, $client);
        $this->post('/login', ['username' => 'admin', 'password' => 'guess'])->assertNotFound();
        $this->get('/auth/callback?code=forged&state=forged')->assertStatus(400);
        Http::assertNothingSent();
    }

    public function test_approved_new_identity_enters_as_reader_without_adopting_existing_username(): void
    {
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => true])]);
        $existing = User::factory()->create(['username' => 'administrador', 'role' => 'ADMIN']);
        $this->completeCallback('new-subject', 'administrador')->assertRedirect(route('expedientes.index'));
        $current = auth()->user();
        $this->assertNotSame($existing->id, $current->id);
        $this->assertSame('READER', $current->role);
        $this->assertSame('new-subject', $current->identity_subject);
        $this->assertDatabaseHas('identity_sessions', ['user_id' => $current->id, 'provider_sid' => 'provider-session']);
    }

    public function test_only_explicit_bootstrap_subject_gets_first_administrator_role(): void
    {
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => true])]);
        $this->completeCallback('bootstrap-subject', 'admin.portal')->assertRedirect(route('expedientes.index'));
        $this->assertSame('ADMIN', auth()->user()->role);
    }

    public function test_unassigned_identity_and_expired_local_access_are_not_restored_by_sso(): void
    {
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => false], 403)]);
        $this->completeCallback('denied-subject', 'sin.acceso')->assertForbidden();
        $this->assertDatabaseMissing('users', ['identity_subject' => 'denied-subject']);
    }

    public function test_revocation_blocks_every_protected_request_including_private_files(): void
    {
        $user = $this->account();
        $expediente = Expediente::create(['name' => 'Privado', 'responsible_id' => $user->id]);
        $document = $expediente->documents()->create(['name' => 'Privado', 'type' => 'INE']);
        $version = $document->versions()->create(['version' => 1, 'path' => 'private.pdf', 'original_name' => 'private.pdf',
            'mime' => 'application/pdf', 'size' => 10, 'hash' => str_repeat('a', 64), 'uploaded_by' => $user->id]);
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => false], 403)]);
        $this->actingAs($user)->get('/files/'.$version->id)->assertForbidden();
        $this->assertGuest();
    }

    public function test_verification_outage_fails_closed_instead_of_exposing_documents(): void
    {
        Http::fake(['https://portal.test/*' => Http::response('', 503)]);
        $this->actingAs($this->account('ADMIN'))->get('/expedientes')->assertStatus(503);
    }

    public function test_project_admin_changes_roles_but_cannot_set_an_independent_password(): void
    {
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => true])]);
        $admin = $this->account('ADMIN');
        $reader = $this->account();
        $this->actingAs($admin)->get('/users/'.$reader->id.'/edit')->assertOk()->assertDontSee('name="password"', false)->assertSee('readonly', false);
        $this->put('/users/'.$reader->id, ['role' => 'LOADER', 'is_active' => 1])->assertRedirect(route('users.index'));
        $this->assertSame('LOADER', $reader->fresh()->role);
        $this->put('/users/'.$reader->id, ['role' => 'ADMIN', 'is_active' => 1, 'password' => 'Forbidden1234567'])->assertSessionHasErrors('password');
        $this->post('/users', ['name' => 'Local', 'username' => 'local', 'role' => 'ADMIN', 'is_active' => 1])->assertNotFound();
        $this->get('/expedientes')->assertDontSee('Cambiar mi contraseña')->assertSee('Plataforma Institucional SEIEM');
    }

    public function test_suspended_identity_is_not_reactivated_and_has_no_local_password(): void
    {
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => true])]);
        $user = $this->account();
        $user->update(['is_active' => false]);
        $this->completeCallback($user->identity_subject, $user->username)->assertForbidden();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertGuest();
    }

    public function test_new_identity_has_no_local_credential_and_reader_cannot_manage_roles(): void
    {
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => true])]);
        $this->completeCallback('no-local-password', 'new.reader')->assertRedirect();
        $this->assertNull(auth()->user()->password);
        $this->get('/users')->assertForbidden();
    }

    public function test_local_account_creation_form_is_rejected_in_institutional_profile(): void
    {
        Http::fake(['https://portal.test/*' => Http::response(['allowed' => true])]);
        $this->actingAs($this->account('ADMIN'))->get('/users/create')->assertNotFound();
    }

    public function test_logout_remains_available_during_platform_outage(): void
    {
        Http::fake(['https://portal.test/*' => Http::response('', 503)]);
        $this->actingAs($this->account())->post('/logout')->assertRedirect('https://portal.test');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_expired_handshake_does_not_reach_the_provider(): void
    {
        $client = Mockery::mock(LaravelOidcClient::class);
        $client->shouldNotReceive('authenticate');
        $this->app->instance(LaravelOidcClient::class, $client);
        $this->withSession(['oidc.handshake.openid_connect_state' => 'expected', 'oidc.started_at' => time() - 601])
            ->get('/auth/callback?state=expected&code=test')->assertStatus(400);
        $this->assertGuest();
    }

    public function test_verified_backchannel_logout_deletes_only_the_matching_provider_session(): void
    {
        $first = $this->account();
        $other = $this->account();
        foreach ([$first, $other] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
            DB::table('identity_sessions')->insert(['session_id' => 'session-'.$user->id, 'user_id' => $user->id, 'provider_sid' => 'sid-'.$user->id, 'created_at' => now()]);
        }
        $client = Mockery::mock(LaravelOidcClient::class);
        $client->shouldReceive('verifyLogoutToken')->once()->andReturn(true);
        $client->shouldReceive('getVerifiedClaims')->once()->andReturn((object) ['sub' => $first->identity_subject, 'sid' => 'sid-'.$first->id, 'jti' => 'logout-jti', 'iat' => time(), 'events' => (object) ['http://schemas.openid.net/event/backchannel-logout' => (object) []]]);
        $this->app->instance(LaravelOidcClient::class, $client);
        $this->post('/auth/backchannel-logout', ['logout_token' => 'signed-token'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['user_id' => $first->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
    }
}
