<?php

namespace Tests\Feature;

use App\Models\{ActivityType, Badge, PointsTransaction, Post, User};
use App\Services\BadgeAwardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AutoBadgesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        DB::table('user_badges')->delete();
        Badge::query()->delete();
        PointsTransaction::query()->delete();
    }

    public function test_approval_awards_all_eligible_badges_and_correct_bonuses(): void
    {
        [$admin, $member] = $this->users();
        $type = ActivityType::where('counts_trees', true)->firstOrFail();
        $type->update(['name' => 'ชื่อที่ผู้ดูแลเปลี่ยน', 'points' => 10]);
        $tree = Badge::create(['name' => 'Three Trees', 'description' => 'three', 'icon' => '🌳', 'criteria_type' => 'trees', 'criteria_value' => 3, 'bonus_points' => 5]);
        $activity = Badge::create(['name' => 'First Activity', 'description' => 'first', 'icon' => '🌱', 'criteria_type' => 'activities', 'criteria_value' => 1, 'bonus_points' => 0]);
        $points = Badge::create(['name' => 'Thirty Points', 'description' => 'points', 'icon' => '⭐', 'criteria_type' => 'points', 'criteria_value' => 30, 'bonus_points' => 7]);
        Badge::create(['name' => 'Not Yet', 'description' => 'later', 'icon' => '🔒', 'criteria_type' => 'trees', 'criteria_value' => 4, 'bonus_points' => 100]);
        $post = $this->pendingPost($member, $type, 3);

        $response = $this->actingAs($admin)->post(route('admin.review', $post), ['status' => 'approved']);

        $response->assertSessionHas('success', fn ($message) => str_contains($message, 'Three Trees') && str_contains($message, 'First Activity') && str_contains($message, 'Thirty Points'));
        $this->assertSame(42, $member->fresh()->points);
        $this->assertEqualsCanonicalizing([$tree->id, $activity->id, $points->id], DB::table('user_badges')->where('user_id', $member->id)->pluck('badge_id')->all());
        $this->assertDatabaseHas('points_transactions', ['description' => 'ได้รับ Badge: Three Trees', 'amount' => 5, 'balance_after' => 35]);
        $this->assertDatabaseHas('points_transactions', ['description' => 'ได้รับ Badge: Thirty Points', 'amount' => 7, 'balance_after' => 42]);
        $this->assertDatabaseMissing('points_transactions', ['description' => 'ได้รับ Badge: First Activity']);
    }

    public function test_only_approved_point_requests_contribute_to_badge_metrics(): void
    {
        [$admin, $member] = $this->users();
        $treeType = ActivityType::where('counts_trees', true)->firstOrFail();
        $otherType = ActivityType::where('counts_trees', false)->firstOrFail();
        $badge = Badge::create(['name' => 'Filtered', 'description' => 'filtered', 'icon' => '🧪', 'criteria_type' => 'activities', 'criteria_value' => 2, 'bonus_points' => 0]);
        Post::create(['user_id' => $member->id, 'activity_type_id' => $treeType->id, 'content' => 'pending', 'tree_count' => 50, 'request_points' => true, 'status' => 'pending']);
        Post::create(['user_id' => $member->id, 'activity_type_id' => $treeType->id, 'content' => 'rejected', 'tree_count' => 50, 'request_points' => true, 'status' => 'rejected']);
        Post::create(['user_id' => $member->id, 'activity_type_id' => $treeType->id, 'content' => 'story', 'tree_count' => 50, 'request_points' => false, 'status' => 'approved', 'points_awarded' => 999]);
        $post = $this->pendingPost($member, $otherType);

        $this->actingAs($admin)->post(route('admin.review', $post), ['status' => 'approved']);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $member->id, 'badge_id' => $badge->id]);
    }

    public function test_repeat_or_conflicting_review_cannot_duplicate_or_reverse_approval(): void
    {
        [$admin, $member] = $this->users();
        $type = ActivityType::where('counts_trees', false)->firstOrFail();
        $badge = Badge::create(['name' => 'Once', 'description' => 'once', 'icon' => '1️⃣', 'criteria_type' => 'activities', 'criteria_value' => 1, 'bonus_points' => 9]);
        $post = $this->pendingPost($member, $type);

        $this->actingAs($admin)->post(route('admin.review', $post), ['status' => 'approved']);
        $points = $member->fresh()->points;
        $transactions = PointsTransaction::where('user_id', $member->id)->count();
        $this->actingAs($admin)->post(route('admin.review', $post), ['status' => 'approved'])->assertSessionHasErrors('review');
        $this->actingAs($admin)->post(route('admin.review', $post), ['status' => 'rejected'])->assertSessionHasErrors('review');

        $this->assertSame('approved', $post->fresh()->status);
        $this->assertSame($points, $member->fresh()->points);
        $this->assertSame($transactions, PointsTransaction::where('user_id', $member->id)->count());
        $this->assertSame(1, DB::table('user_badges')->where(['user_id' => $member->id, 'badge_id' => $badge->id])->count());
    }

    public function test_spending_points_does_not_remove_badges_or_reduce_activity_point_progress(): void
    {
        [$admin, $member] = $this->users();
        $type = ActivityType::where('counts_trees', false)->firstOrFail();
        $type->update(['points' => 25]);
        $first = Badge::create(['name' => 'Twenty Five', 'description' => '25', 'icon' => '🏅', 'criteria_type' => 'points', 'criteria_value' => 25, 'bonus_points' => 0]);
        $this->actingAs($admin)->post(route('admin.review', $this->pendingPost($member, $type)), ['status' => 'approved']);
        $member->update(['points' => 0]);
        $second = Badge::create(['name' => 'Fifty', 'description' => '50', 'icon' => '🏆', 'criteria_type' => 'points', 'criteria_value' => 50, 'bonus_points' => 0]);
        $this->actingAs($admin)->post(route('admin.review', $this->pendingPost($member, $type)), ['status' => 'approved']);

        $this->assertDatabaseHas('user_badges', ['user_id' => $member->id, 'badge_id' => $first->id]);
        $this->assertDatabaseHas('user_badges', ['user_id' => $member->id, 'badge_id' => $second->id]);
    }

    public function test_failure_during_badge_processing_rolls_back_entire_approval(): void
    {
        [$admin, $member] = $this->users();
        $post = $this->pendingPost($member, ActivityType::firstOrFail());
        $service = \Mockery::mock(BadgeAwardService::class);
        $service->shouldReceive('awardEligibleBadges')->once()->andThrow(new RuntimeException('test failure'));
        $this->app->instance(BadgeAwardService::class, $service);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('admin.review', $post), ['status' => 'approved']);
            $this->fail('Expected badge processing to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('test failure', $exception->getMessage());
        }

        $this->assertSame('pending', $post->fresh()->status);
        $this->assertSame(0, $member->fresh()->points);
        $this->assertDatabaseCount('points_transactions', 0);
    }

    private function users(): array
    {
        return [User::where('role', 'admin')->firstOrFail(), User::factory()->create(['points' => 0])];
    }

    private function pendingPost(User $member, ActivityType $type, int $trees = 0): Post
    {
        return Post::create(['user_id' => $member->id, 'activity_type_id' => $type->id, 'content' => 'activity', 'tree_count' => $trees, 'request_points' => true, 'status' => 'pending']);
    }
}
