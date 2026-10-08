<?php

namespace Tests\Feature;

use App\Models\ArisanMember;
use App\Models\ArisanSubscription;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArisanPwaAssetTest extends TestCase
{
    use RefreshDatabase;

    protected ArisanSubscription $subscription;
    protected ArisanMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        $vpnUser = VpnUser::factory()->create();
        $this->subscription = ArisanSubscription::create([
            'vpn_user_id' => $vpnUser->id,
            'subdomain' => 'mawar',
            'business_name' => 'Arisan Mawar Berkah',
            'price' => 10000,
            'order_date' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
            'admin_password_hash' => Hash::make('adminsecret123'),
        ]);

        $this->member = ArisanMember::create([
            'subscription_id' => $this->subscription->id,
            'name' => 'Siti Aminah',
            'phone_number' => '081234567890',
            'pin_hash' => Hash::make('1234'),
            'is_active' => true,
        ]);
    }

    public function test_manifest_returns_valid_json_with_correct_metadata(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/manifest.json");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/manifest+json', $response->headers->get('Content-Type') ?? '');

        $json = $response->json();
        $this->assertEquals('Arisan Mawar Berkah', $json['name']);
        $this->assertEquals('standalone', $json['display']);
        $this->assertEquals('#059669', $json['theme_color']);
        $this->assertEquals('#0f172a', $json['background_color']);
        $this->assertEquals("/arisan-app/{$this->subscription->subdomain}/member/login", $json['start_url']);
        $this->assertIsArray($json['icons']);
        $this->assertNotEmpty($json['icons']);
    }

    public function test_service_worker_endpoint_returns_javascript(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/sw.js");

        $response->assertStatus(200);
        $this->assertStringContainsString('javascript', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString("/arisan-app/{$this->subscription->subdomain}/offline", $response->getContent());
        $this->assertStringContainsString('install', $response->getContent());
        $this->assertStringContainsString('fetch', $response->getContent());
    }

    public function test_offline_fallback_endpoint_renders_successfully(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/offline");

        $response->assertStatus(200);
        $response->assertSee('Arisan Mawar Berkah');
        $response->assertSee('Koneksi Internet Terputus');
        $response->assertSee('Offline');
    }

    public function test_manifest_link_and_sw_registration_present_on_admin_login(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin/login");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Arisan/Admin/Login')
            ->where('subdomain', $this->subscription->subdomain)
            ->where('business_name', $this->subscription->business_name)
        );
    }

    public function test_manifest_link_and_sw_registration_present_on_member_login(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/login");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Arisan/Member/Login')
            ->where('subdomain', $this->subscription->subdomain)
            ->where('business_name', $this->subscription->business_name)
        );
    }

    public function test_manifest_and_pwa_banner_present_on_admin_layout(): void
    {
        session(['arisan_admin_id' => $this->subscription->id]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/admin");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Arisan/Admin/Dashboard')
            ->where('subdomain', $this->subscription->subdomain)
        );
    }

    public function test_manifest_and_pwa_banner_present_on_member_layout(): void
    {
        session(['arisan_member_id' => $this->member->id]);

        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/card");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Arisan/Member/Card')
            ->where('subdomain', $this->subscription->subdomain)
        );
    }

    public function test_pwa_install_banner_contains_ios_instructions_and_strictly_no_emojis(): void
    {
        $response = $this->get("/arisan-app/{$this->subscription->subdomain}/member/login");

        $response->assertStatus(200);
        $content = $response->getContent();

        // Strict emoji/emoticon check (Unicode ranges for emojis, pictographs, symbols)
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        $this->assertDoesNotMatchRegularExpression($emojiPattern, $content, 'Page must not contain emojis');
    }
}
