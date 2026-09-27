<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Post};
class PostActionRenderingTest extends TestCase {
 use RefreshDatabase;
 public function test_delete_actions_render_as_forms_on_all_member_pages():void {
  $this->seed();$owner=User::findOrFail(1);$post=Post::where('user_id',$owner->id)->firstOrFail();
  $this->actingAs($owner);
  foreach(['/profile','/feed','/activities',route('posts.show',$post)] as $url){
   $this->get($url)->assertOk()->assertSee('ลบโพสต์ของฉัน')->assertSee(route('posts.destroy',$post),false)->assertDontSee('@include',false);
  }
  $this->actingAs(User::findOrFail(2))->get(route('profile',$owner))->assertOk()->assertDontSee('ลบโพสต์ของฉัน')->assertSee('รายงานโพสต์')->assertDontSee('@include',false);
 }
}
