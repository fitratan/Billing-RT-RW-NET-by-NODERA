<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MikhmonOverlayTest extends TestCase
{
    public function test_ros6_template_headhtml_contains_loading_overlay_and_controls(): void
    {
        $headhtml = file_get_contents(resource_path('mikhmon-template/include/headhtml.php'));

        $this->assertStringContainsString('id="mikhmon-loading-overlay"', $headhtml);
        $this->assertStringContainsString('mikhmon-overlay-box', $headhtml);
        $this->assertStringContainsString('mikhmon-overlay-spinner', $headhtml);
        $this->assertStringContainsString('window.showMikhmonOverlay', $headhtml);
        $this->assertStringContainsString('window.hideMikhmonOverlay', $headhtml);
        $this->assertStringContainsString('Loading...', $headhtml);
    }

    public function test_ros7_template_headhtml_contains_loading_overlay_and_controls(): void
    {
        $headhtml = file_get_contents(resource_path('mikhmon-template-v7/include/headhtml.php'));

        $this->assertStringContainsString('id="mikhmon-loading-overlay"', $headhtml);
        $this->assertStringContainsString('mikhmon-overlay-box', $headhtml);
        $this->assertStringContainsString('mikhmon-overlay-spinner', $headhtml);
        $this->assertStringContainsString('window.showMikhmonOverlay', $headhtml);
        $this->assertStringContainsString('window.hideMikhmonOverlay', $headhtml);
        $this->assertStringContainsString('Loading...', $headhtml);
    }

    public function test_mikhmon_js_files_contain_overlay_invocation_on_connect_and_switch(): void
    {
        $paths = [
            resource_path('mikhmon-template/js/mikhmon.js'),
            resource_path('mikhmon-template-v7/js/mikhmon.js'),
            public_path('js/mikhmon.js'),
        ];

        foreach ($paths as $path) {
            $content = file_get_contents($path);
            $this->assertStringContainsString('showMikhmonOverlay', $content, "Missing showMikhmonOverlay in {$path}");
            $this->assertStringContainsString('hideMikhmonOverlay', $content, "Missing hideMikhmonOverlay in {$path}");
            $this->assertStringContainsString('.connect', $content, "Missing .connect handler in {$path}");
        }
    }

    public function test_menu_templates_contain_overlay_triggers(): void
    {
        $menus = [
            resource_path('mikhmon-template/include/menu.php'),
            resource_path('mikhmon-template-v7/include/menu.php'),
        ];

        foreach ($menus as $menuPath) {
            $content = file_get_contents($menuPath);
            $this->assertStringContainsString('showMikhmonOverlay', $content, "Missing showMikhmonOverlay in {$menuPath}");
        }
    }

    public function test_mikhmon_sync_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('mikhmon:sync');
        $this->assertSame(0, $exitCode);
    }
}
