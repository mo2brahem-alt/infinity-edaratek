<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicStorageLinkStructureTest extends TestCase
{
    public function test_app_service_provider_bootstraps_public_storage_link_maintenance(): void
    {
        $content = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertIsString($content);
        $this->assertStringContainsString('use App\\Support\\PublicStorageLinkMaintainer;', $content);
        $this->assertStringContainsString("app(PublicStorageLinkMaintainer::class)->ensure();", $content);
        $this->assertStringContainsString('if (! App::runningUnitTests()) {', $content);
    }

    public function test_public_storage_link_maintainer_does_not_move_or_copy_existing_legacy_directories(): void
    {
        $files = \Mockery::mock(\Illuminate\Filesystem\Filesystem::class);
        $files->shouldReceive('exists')->once()->with(public_path('storage'))->andReturnTrue();
        $files->shouldNotReceive('copyDirectory'); $files->shouldNotReceive('deleteDirectory'); $files->shouldNotReceive('link');
        (new \App\Support\PublicStorageLinkMaintainer($files))->ensure();
    }
}
