<?php

namespace Tests\Feature\Mobile\Phase5;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAssetCompatibilityTest extends TestCase
{
    public function test_branding_remains_public_but_sensitive_and_unclassified_paths_do_not(): void
    {
        Storage::fake('public');
        foreach (['schools/logos/logo.png', 'settings/banner.png', 'uploads/logo.svg', 'profile-photos/avatar.jpg'] as $path) {
            Storage::disk('public')->put($path, 'public-branding');
            foreach (['storage', 'media-files'] as $alias) { $this->get('/'.$alias.'/'.$path)->assertOk(); }
        }
        foreach (['ticketing/attachments/proof.txt', 'schools/1/student-leaves/proof.pdf', 'unclassified/id.pdf'] as $path) {
            Storage::disk('public')->put($path, 'sensitive-synthetic');
            foreach (['storage', 'media-files'] as $alias) { $this->get('/'.$alias.'/'.$path)->assertNotFound(); }
        }
        foreach (['../.env', '%2e%2e/.env', 'uploads/%2e%2e/ticketing/proof', 'uploads\\..\\proof', '/etc/passwd'] as $path) {
            $this->get('/media-files/'.$path)->assertNotFound();
        }
    }
}
