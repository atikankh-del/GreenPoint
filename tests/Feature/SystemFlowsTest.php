<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Post,User,Reward,RewardRedemption,SystemSetting};
class SystemFlowsTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed();}
 public function test_owner_deletes_and_other_members_cannot():void{
  $post=Post::first();$owner=$post->user;$xp=$owner->level_info['xp'];$points=$owner->points;
  $this->actingAs(User::find(2))->delete(route('posts.destroy',$post))->assertForbidden();
  $this->actingAs($owner)->delete(route('posts.destroy',$post))->assertRedirect(route('profile'));
  $this->assertSoftDeleted('posts',['id'=>$post->id]);
  $this->get(route('posts.show',$post))->assertNotFound();$this->get(route('posts.image',$post))->assertNotFound();
  $this->get('/feed')->assertDontSee($post->content);$this->get('/profile')->assertDontSee($post->content);
  $this->assertEquals($points,$owner->fresh()->points);$this->assertEquals($xp,$owner->fresh()->level_info['xp']);
 }
 public function test_report_reaches_admin_and_private_post_cannot_be_reported():void{
  $post=Post::first();$this->actingAs(User::find(2))->post(route('posts.report',$post),['reason'=>'ตรวจสอบรูป'])->assertSessionHas('success');
  $this->assertDatabaseHas('reports',['post_id'=>$post->id,'reason'=>'ตรวจสอบรูป']);
  $this->actingAs(User::where('role','admin')->first())->get(route('admin.manage','reports'))->assertOk()->assertSee('ตรวจสอบรูป');
  $post->update(['privacy'=>'private']);$this->actingAs(User::find(2))->post(route('posts.report',$post),['reason'=>'test'])->assertForbidden();
 }
 public function test_cancel_refunds_once_and_completed_cannot_be_reversed():void{
  $user=User::find(1);$reward=Reward::first();$points=$user->points;$stock=$reward->stock;
  $this->actingAs($user)->post(route('rewards.redeem',$reward));$row=RewardRedemption::latest()->first();
  $this->actingAs(User::where('role','admin')->first())->patch(route('admin.redemptions.status',$row),['status'=>'cancelled'])->assertSessionHasNoErrors();
  $this->patch(route('admin.redemptions.status',$row),['status'=>'cancelled']);
  $this->assertEquals($points,$user->fresh()->points);$this->assertEquals($stock,$reward->fresh()->stock);
  $this->patch(route('admin.redemptions.status',$row),['status'=>'approved'])->assertSessionHasErrors('status');
  $this->actingAs($user)->post(route('rewards.redeem',$reward));$row=RewardRedemption::latest('id')->first();
  $this->actingAs(User::where('role','admin')->first())->patch(route('admin.redemptions.status',$row),['status'=>'completed'])->assertSessionHasErrors('status');
  $this->patch(route('admin.redemptions.status',$row),['status'=>'approved']);$this->patch(route('admin.redemptions.status',$row),['status'=>'completed']);
  $this->patch(route('admin.redemptions.status',$row),['status'=>'cancelled'])->assertSessionHasErrors('status');
 }
 public function test_maintenance_blocks_member_but_not_admin():void{
  SystemSetting::updateOrCreate(['key'=>'maintenance_mode'],['value'=>'1']);
  $this->actingAs(User::find(1))->get('/feed')->assertStatus(503);
  $this->actingAs(User::where('role','admin')->first())->get('/admin')->assertOk();
 }
}
