<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_update_delete_announcement(): void
    {
        $sa = User::create([
            'name' => 'Super Admin',
            'username' => 'admin',
            'email' => 'admin@nodera.id',
            'password' => bcrypt('secret'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $this->actingAs($sa);

        // store
        $store = $this->post('/superadmin/announcements/store', [
            'title' => 'Test', 'content' => 'Konten', 'type' => 'info',
        ]);
        $this->assertNotSame(404, $store->getStatusCode(), 'store returned 404');

        $ann = Announcement::where('title', 'Test')->first();
        $this->assertNotNull($ann, 'announcement not created');
        $this->assertTrue((bool) $ann->is_active, 'default is_active should be true');

        // update
        $update = $this->post("/superadmin/announcements/update/{$ann->id}", [
            'title' => 'Test2', 'content' => 'Konten2', 'type' => 'warning',
        ]);
        $this->assertNotSame(404, $update->getStatusCode(), 'update returned 404');
        $this->assertSame('Test2', $ann->fresh()->title);

        // delete
        $del = $this->post("/superadmin/announcements/delete/{$ann->id}");
        $this->assertNotSame(404, $del->getStatusCode(), 'delete returned 404');
        $this->assertNull(Announcement::find($ann->id));
    }
}
