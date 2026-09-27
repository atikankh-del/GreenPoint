<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Post,User};
class PostEditingTest extends TestCase {
 use RefreshDatabase;
 public function test_owner_edits_approved_post_without_changing_awarded_points():void {
  $this->seed();$post=Post::where('status','approved')->firstOrFail();$owner=$post->user;$points=$owner->points;$earned=$post->points_awarded;$trees=$post->tree_count;
  $this->actingAs(User::find(3))->get(route('posts.edit',$post))->assertForbidden();
  $this->put(route('posts.update',$post),['content'=>'wrong','privacy'=>'public'])->assertForbidden();
  $this->actingAs($owner)->get(route('posts.edit',$post))->assertOk()->assertSee('บันทึกการแก้ไข');
  $this->put(route('posts.update',$post),['title'=>'แก้ไขแล้ว','content'=>'ข้อความใหม่','privacy'=>'private','tree_count'=>999,'points_awarded'=>999])->assertRedirect(route('posts.show',$post));
  $post->refresh();$this->assertEquals('ข้อความใหม่',$post->content);$this->assertEquals('approved',$post->status);$this->assertEquals($earned,$post->points_awarded);$this->assertEquals($trees,$post->tree_count);$this->assertEquals($points,$owner->fresh()->points);
  $this->get('/profile')->assertSee('เมนูโพสต์')->assertSee('แก้ไข')->assertDontSee('@include',false);
 }
}
