<?php

namespace Tests\Feature;

use App\Models\ActivityType;
use App\Models\Challenge;
use App\Models\PointsTransaction;
use App\Models\Post;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin()
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function member()
    {
        return User::factory()->create(['points' => 0]);
    }

    private function postFor($user, $type, $date = null, $trees = 1)
    {
        return Post::create(['user_id' => $user->id, 'activity_type_id' => $type->id, 'content' => 'proof', 'request_points' => true, 'privacy' => 'public', 'status' => 'pending', 'tree_count' => $trees, 'activity_date' => $date ?? today()]);
    }

    private function challenge($type, $metric, $target = 2)
    {
        return Challenge::create(['title' => 'Test mission', 'description' => 'Test', 'icon' => '🌱', 'activity_type_id' => $type->id, 'metric' => $metric, 'target' => $target, 'reward_points' => 75, 'starts_at' => today()->subDays(5), 'ends_at' => today()->addDay(), 'active' => true]);
    }

    private function approve($post)
    {
        return $this->actingAs($this->admin())->post(route('admin.review', $post), ['status' => 'approved']);
    }

    public function test_tree_challenge_pays_once_and_ignores_out_of_range_posts(): void
    {
        $user = $this->member();
        $type = ActivityType::where('counts_trees', true)->first();
        $challenge = $this->challenge($type, 'trees', 3);
        $this->approve($this->postFor($user, $type, today()->subDays(10), 20));
        $this->assertDatabaseHas('challenge_progress', ['user_id' => $user->id, 'challenge_id' => $challenge->id, 'progress' => 0, 'claimed_at' => null]);
        $post = $this->postFor($user, $type, today(), 3);
        $this->approve($post)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('challenge_progress', ['user_id' => $user->id, 'challenge_id' => $challenge->id, 'progress' => 3]);
        $this->approve($post)->assertSessionHasErrors('review');
        $this->approve($this->postFor($user, $type));
        $this->assertSame(1, PointsTransaction::where('reference_type', Challenge::class)->where('reference_id', $challenge->id)->count());
        $this->assertDatabaseHas('points_transactions', ['reference_id' => $challenge->id, 'reference_type' => Challenge::class, 'amount' => 75]);
    }

    public function test_day_challenge_counts_distinct_days_and_late_review(): void
    {
        $user = $this->member();
        $type = ActivityType::where('counts_trees', false)->first();
        $challenge = $this->challenge($type, 'days');
        $challenge->update(['ends_at' => today()->subDay()]);
        $this->approve($this->postFor($user, $type, today()->subDays(2)));
        $this->approve($this->postFor($user, $type, today()->subDays(2)));
        $this->assertDatabaseHas('challenge_progress', ['user_id' => $user->id, 'challenge_id' => $challenge->id, 'progress' => 1, 'claimed_at' => null]);
        $this->approve($this->postFor($user, $type, today()->subDay()));
        $this->assertNotNull(DB::table('challenge_progress')->where('challenge_id', $challenge->id)->where('user_id', $user->id)->value('claimed_at'));
    }

    public function test_rejection_requires_reason_and_owner_can_resubmit_existing_image(): void
    {
        Storage::fake('evidence');
        Storage::disk('evidence')->put('posts/proof.png', 'fixture');
        $user = $this->member();
        $post = $this->postFor($user, ActivityType::first());
        $post->update(['image' => 'posts/proof.png']);
        $this->actingAs($this->admin())->post(route('admin.review', $post), ['status' => 'rejected'])->assertSessionHasErrors('review_note');
        $this->post(route('admin.review', $post), ['status' => 'rejected', 'review_note' => 'ขอรายละเอียดสถานที่'])->assertSessionHas('success');
        $this->actingAs($user)->get(route('posts.edit', $post))->assertOk()->assertSee('ขอรายละเอียดสถานที่');
        $this->actingAs($this->member())->put(route('posts.update', $post), ['content' => 'hack', 'privacy' => 'public'])->assertForbidden();
        $payload = ['content' => 'รายละเอียดใหม่', 'privacy' => 'private', 'activity_type_id' => $post->activity_type_id, 'tree_count' => 2];
        $this->actingAs($user)->put(route('posts.update', $post), $payload)->assertRedirect(route('activities'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'pending', 'review_note' => null, 'image' => 'posts/proof.png', 'content' => 'รายละเอียดใหม่']);
        $this->put(route('posts.update', $post), $payload)->assertStatus(409);
    }

    public function test_suspended_session_cannot_read_or_mutate(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get('/feed')->assertOk();
        $user->update(['status' => 'suspended']);
        $this->get('/feed')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($user)->post('/posts', ['content' => 'blocked', 'privacy' => 'public'])->assertRedirect(route('login'));
        $this->assertDatabaseMissing('posts', ['content' => 'blocked']);
    }

    public function test_reward_checks_fresh_stock_and_active_status(): void
    {
        $user = $this->member();
        $user->update(['points' => 100]);
        $reward = Reward::first();
        $reward->update(['stock' => 1, 'points_required' => 60]);
        $this->actingAs($user)->post(route('rewards.redeem', $reward))->assertSessionHasNoErrors();
        $this->post(route('rewards.redeem', $reward))->assertSessionHasErrors('reward');
        $this->assertEquals(40, $user->fresh()->points);
        $this->assertEquals(0, $reward->fresh()->stock);
        $reward->update(['stock' => 5, 'points_required' => 1, 'status' => 'inactive']);
        $this->post(route('rewards.redeem', $reward))->assertSessionHasErrors('reward');
        $this->assertDatabaseCount('reward_redemptions', 1);
    }

    public function test_share_route_respects_privacy_and_levels_use_earned_activity_points(): void
    {
        $owner = $this->member();
        $post = $this->postFor($owner, ActivityType::first());
        $post->update(['status' => 'approved', 'points_awarded' => 700]);
        $other = $this->member();
        $this->actingAs($other)->get(route('posts.show', $post))->assertOk()->assertSee('proof');
        $post->update(['privacy' => 'private']);
        $this->get(route('posts.show', $post))->assertForbidden();
        $this->actingAs($owner)->get(route('posts.show', $post))->assertOk();
        $this->assertSame('Tree', $owner->level_info['name']);
        $owner->update(['points' => 0]);
        $this->assertSame('Tree',$owner->fresh()->level_info['name']);
        $this->get('/profile')->assertOk()->assertSee('700 XP');
    }
}
