<?php
namespace Tests\Feature;
use App\Models\{ActivityType,Post,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GreenPointTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); }

    public function test_guest_can_see_landing_and_login(): void
    {
        $this->get('/')->assertOk()->assertSee('GreenPoint');
        $this->post('/login',['email'=>'kan@greenpoint.test','password'=>'password'])->assertRedirect(route('feed'));
    }

    #[DataProvider('memberPageProvider')]
    public function test_member_pages_render(string $url): void
    {
        $user=User::where('email','kan@greenpoint.test')->firstOrFail();
        $this->actingAs($user)->get($url)->assertOk();
    }

    public static function memberPageProvider(): array { return array_map(fn($url)=>[$url],['/feed','/dashboard','/profile','/activities','/points','/rewards','/challenges','/leaderboard']); }

    public function test_member_can_submit_activity(): void
    {
        $user=User::where('email','kan@greenpoint.test')->firstOrFail();
        $this->actingAs($user)->post('/posts',['content'=>'ทดสอบปลูกต้นไม้','activity_type_id'=>1,'tree_count'=>2,'privacy'=>'public','request_points'=>1])->assertSessionHas('success');
        $this->assertDatabaseHas('posts',['content'=>'ทดสอบปลูกต้นไม้','status'=>'pending']);
    }

    public function test_admin_approval_awards_points(): void
    {
        $admin=User::where('role','admin')->firstOrFail(); $member=User::findOrFail(1); $before=$member->points;
        $post=Post::create(['user_id'=>$member->id,'activity_type_id'=>ActivityType::where('name','ปลูกต้นไม้')->value('id'),'content'=>'หลักฐาน','tree_count'=>2,'privacy'=>'public','request_points'=>true,'status'=>'pending']);
        $this->actingAs($admin)->post(route('admin.review',$post),['status'=>'approved'])->assertSessionHas('success');
        $this->assertEquals($before+40,$member->fresh()->points); $this->assertEquals('approved',$post->fresh()->status);
    }

    public function test_admin_management_pages_render_and_crud_works(): void
    {
        $admin=User::where('role','admin')->firstOrFail();
        foreach(['users','activities','rewards','redemptions','challenges','badges','reports','settings'] as $section) $this->actingAs($admin)->get(route('admin.manage',$section))->assertOk();
        $this->actingAs($admin)->post(route('admin.activities.store'),['name'=>'ประหยัดพลังงาน','icon'=>'💡','points'=>15,'color'=>'#226644','active'=>1])->assertSessionHas('success');
        $this->assertDatabaseHas('activity_types',['name'=>'ประหยัดพลังงาน','points'=>15]);
        $this->actingAs($admin)->put(route('admin.settings.save'),['site_name'=>'GreenPoint','welcome_message'=>'โลกดีขึ้นได้','support_email'=>'admin@greenpoint.test','ranking_enabled'=>1,'auto_approve_story'=>1])->assertSessionHas('success');
        $this->assertDatabaseHas('system_settings',['key'=>'welcome_message','value'=>'โลกดีขึ้นได้']);
    }

    public function test_normal_member_cannot_access_admin_management(): void
    {
        $member=User::where('role','user')->firstOrFail();
        $this->actingAs($member)->get(route('admin.manage','users'))->assertForbidden();
    }
}
