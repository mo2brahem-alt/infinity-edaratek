<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class NativeDeviceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_lists_only_active_native_devices_without_secret_fields(): void
    {
        $user = User::factory()->create();
        $current = $this->issue($user, 'Current device');
        $other = $this->issue($user, 'Other device');
        $expired = $user->createToken('native:'.Str::uuid().':Expired device', [NativeTokenService::ABILITY], now()->subSecond());
        $nonNative = $user->createToken('integration-client', ['*'], now()->addDay());

        $response = $this->native($current)->getJson('/api/v1/auth/devices?per_page=25');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['device_name' => 'Current device', 'is_current' => true])
            ->assertJsonFragment(['device_name' => 'Other device', 'is_current' => false])
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('data.0.abilities');
    }

    public function test_owner_can_revoke_current_or_all_native_devices_without_touching_other_pat_classes(): void
    {
        $user = User::factory()->create();
        $current = $this->issue($user, 'Current device');
        $other = $this->issue($user, 'Other device');
        $otherId = (int) explode('|', $other, 2)[0];
        $nonNative = $user->createToken('integration-client', ['*'], now()->addDay());

        $this->native($current)->deleteJson('/api/v1/auth/devices/'.$otherId)->assertNoContent();
        $this->assertNull(PersonalAccessToken::find($otherId));
        $this->assertNotNull(PersonalAccessToken::find($nonNative->accessToken->id));

        $this->native($current)->deleteJson('/api/v1/auth/devices')->assertNoContent();
        $this->assertNull(PersonalAccessToken::find((int) explode('|', $current, 2)[0]));
        $this->assertNotNull(PersonalAccessToken::find($nonNative->accessToken->id));
    }

    public function test_foreign_or_invalid_device_identifiers_are_not_disclosed(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownerToken = $this->issue($owner, 'Owner device');
        $foreignToken = $this->issue($otherUser, 'Foreign device');
        $foreignId = (int) explode('|', $foreignToken, 2)[0];

        $this->native($ownerToken)->deleteJson('/api/v1/auth/devices/'.$foreignId)
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->native($ownerToken)->deleteJson('/api/v1/auth/devices/not-an-id')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    private function issue(User $user, string $name): string
    {
        return app(NativeTokenService::class)->issue($user, (string) Str::uuid(), $name)->plainTextToken;
    }

    private function native(string $plainTextToken): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$plainTextToken);
    }
}
