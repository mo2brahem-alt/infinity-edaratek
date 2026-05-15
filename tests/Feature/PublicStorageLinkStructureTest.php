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

    public function test_public_storage_link_maintainer_repairs_legacy_project_storage_directories(): void
    {
        $content = file_get_contents(app_path('Support/PublicStorageLinkMaintainer.php'));

        $this->assertIsString($content);
        $this->assertStringContainsString('$this->files->copyDirectory($publicStoragePath, $targetPath);', $content);
        $this->assertStringContainsString('$this->files->deleteDirectory($publicStoragePath);', $content);
        $this->assertStringContainsString('$this->files->link($targetPath, $publicStoragePath);', $content);
        $this->assertStringContainsString('return is_link($path) && ! $this->files->exists($path);', $content);
    }
}
