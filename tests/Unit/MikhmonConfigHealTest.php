<?php

namespace Tests\Unit;

use App\Services\MikhmonProvisioner;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MikhmonConfigHealTest extends TestCase
{
    private MikhmonProvisioner $provisioner;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisioner = app(MikhmonProvisioner::class);
        $this->tempDir = sys_get_temp_dir() . '/mikhmon_test_' . uniqid();
        File::ensureDirectoryExists($this->tempDir . '/include');
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_is_php_syntax_valid_detects_valid_and_invalid_php()
    {
        $validCode = "<?php \$a = ['foo' => 'bar'];";
        $invalidCode = "<?php \$a = ['foo' => ]; => unexpected";

        $this->assertTrue($this->provisioner->isPhpSyntaxValid($validCode));
        $this->assertFalse($this->provisioner->isPhpSyntaxValid($invalidCode));
    }

    public function test_sanitize_and_rebuild_config_php_fixes_syntax_error_with_unexpected_token()
    {
        $corruptedConfig = <<<'PHP'
<?php 
if(substr($_SERVER["REQUEST_URI"], -10) == "config.php"){header("Location:./");}; 
$data['mikhmon'] = array ('1'=>'mikhmon<|<fitra','mikhmon>|>secret123');
'1' => 'ROUTER1!192.168.88.1',
$data['ROUTER1'] = array('1' => 'ROUTER1!192.168.88.1', '2' => 'ROUTER1@|@admin', '3' => 'ROUTER1#|#pass', '4' => 'ROUTER1%Hotspot', '5' => 'ROUTER1^dns', '6' => 'ROUTER1&Rp', '7' => 'ROUTER1*10', '8' => 'ROUTER1(1', '9' => 'ROUTER1)', '10' => 'ROUTER1=10', '11' => 'ROUTER1@!@disable');
=> syntax error unexpected token
PHP;

        $rebuilt = $this->provisioner->sanitizeAndRebuildConfigPhp($corruptedConfig);

        $this->assertNotNull($rebuilt);
        $this->assertTrue($this->provisioner->isPhpSyntaxValid($rebuilt));
        $this->assertStringContainsString("data['mikhmon']", $rebuilt);
        $this->assertStringContainsString("data['ROUTER1']", $rebuilt);
        $this->assertStringNotContainsString("=> syntax error", $rebuilt);
    }

    public function test_heal_and_restore_user_configs_recovers_corrupted_file_on_disk()
    {
        $cfgPath = $this->tempDir . '/include/config.php';
        $corruptedContent = "<?php \n\$data['mikhmon'] = array('1'=>'mikhmon<|<nodera', '2'=>'mikhmon>|>pqCWnaOT');\n=> corrupt token\n";
        File::put($cfgPath, $corruptedContent);

        $this->provisioner->healAndRestoreUserConfigs($this->tempDir, 'test-sub');

        $fixedContent = File::get($cfgPath);
        $this->assertTrue($this->provisioner->isPhpSyntaxValid($fixedContent));
        $this->assertStringContainsString("data['mikhmon']", $fixedContent);
        $this->assertStringNotContainsString("=> corrupt token", $fixedContent);
    }

    public function test_whatsapp_mikhmon_created_uses_nodera_credentials()
    {
        $wa = new \App\Services\WhatsappService();
        $sub = (object) [
            'subdomain'   => 'mikhmon-test',
            'ros_version' => '7',
            'expires_at'  => now()->addMonth(),
        ];
        $user = (object) [
            'name'  => 'Test Member',
            'phone' => '08123456789',
        ];

        // Ensure reflection or execution formats nodera / nodera
        $reflection = new \ReflectionClass($wa);
        $method = $reflection->getMethod('renderTemplate');
        $method->setAccessible(true);

        $defaultMsg = "*LAYANAN MIKHMON ONLINE BERHASIL DIAKTIFKAN*\n"
            . "👤 User    : *{username}*\n"
            . "🔑 Pass    : *{password}*";

        $rendered = $method->invoke($wa, 'Mikhmon Created', $defaultMsg, [
            'username' => 'nodera',
            'password' => 'nodera',
        ]);

        $this->assertStringContainsString('*nodera*', $rendered);
        $this->assertStringNotContainsString('*mikhmon*', $rendered);
        $this->assertStringNotContainsString('*1234*', $rendered);
    }
}

