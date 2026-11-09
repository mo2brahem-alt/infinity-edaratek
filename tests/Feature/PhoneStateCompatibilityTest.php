<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WhatsApp\PhoneNumberNormalizer; // Existing generic phone utility, no transport.
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneStateCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_change_invalidates_phone_verification(): void
    {
        $user = User::factory()->create([
            'mobile' => '0500000114',
            'phone_verified_at' => now(),
        ]);

        $user->update(['mobile' => '0500000115']);

        $this->assertNull($user->refresh()->phone_verified_at);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phoneNumberCases')]
    public function test_phone_number_normalization_is_strict_for_supported_saudi_and_egypt_numbers(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, app(PhoneNumberNormalizer::class)->normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function phoneNumberCases(): array
    {
        return [
            'saudi local' => ['0552470119', '966552470119'],
            'saudi plus' => ['+966552470119', '966552470119'],
            'saudi international' => ['966552470119', '966552470119'],
            'egypt local' => ['01022827443', '201022827443'],
            'egypt plus' => ['+201022827443', '201022827443'],
            'egypt international' => ['201022827443', '201022827443'],
            'duplicate country code' => ['+966966552470119', null],
            'malformed short' => ['12345', null],
            'ambiguous generic' => ['1234567890', null],
        ];
    }
}
