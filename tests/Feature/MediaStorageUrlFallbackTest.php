<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaStorageUrlFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storage_fallback_serves_public_disk_files_when_symlink_is_unavailable(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/sample.txt', 'edaratek media file');

        $response = $this->get('/media-files/uploads/sample.txt');

        $response
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=604800, public');
    }

    public function test_public_htaccess_routes_storage_requests_through_laravel(): void
    {
        $content = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($content);
        $this->assertStringContainsString('RewriteRule ^storage/(.*)$ index.php [L]', $content);
    }

    public function test_project_root_htaccess_routes_storage_requests_to_public_index(): void
    {
        $content = file_get_contents(base_path('.htaccess'));

        $this->assertIsString($content);
        $this->assertStringContainsString('RewriteRule ^storage/(.*)$ public/index.php [L]', $content);
    }

    public function test_public_storage_fallback_rejects_path_traversal(): void
    {
        Storage::fake('public');

        $this->get('/media-files/%2E%2E/.env')->assertNotFound();
    }

    public function test_admin_media_index_returns_authenticated_preview_urls(): void
    {
        config()->set('filesystems.disks.public.url', 'https://wrong-production-url.example/storage');

        $admin = $this->createSuperAdmin();

        $media = Media::query()->create([
            'file_name' => 'logo.png',
            'file_path' => 'uploads/logo.png',
            'file_type' => 'image',
            'mime_type' => 'image/png',
            'file_size' => 1000,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.media.index'));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'url' => route('admin.media.preview', $media, false),
            ]);
    }

    public function test_admin_media_preview_serves_public_disk_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/logo.png', 'preview-image');

        $admin = $this->createSuperAdmin();

        $media = Media::query()->create([
            'file_name' => 'logo.png',
            'file_path' => 'uploads/logo.png',
            'file_type' => 'image',
            'mime_type' => 'image/png',
            'file_size' => 1000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.media.preview', $media))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=300, public');
    }

    private function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
