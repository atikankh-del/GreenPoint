<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\PointsTransaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChallengeAwardService
{
    public function progress(User $user, Challenge $challenge): int
    {
        $posts = Post::where('user_id', $user->id)->where('request_points', true)->where('status', 'approved')
            ->whereRaw('date(COALESCE(activity_date, created_at)) >= ?', [$challenge->starts_at->toDateString()])
            ->whereRaw('date(COALESCE(activity_date, created_at)) <= ?', [$challenge->ends_at->toDateString()])
            ->whereRaw('date(COALESCE(activity_date, created_at)) <= ?', [today()->toDateString()]);
        if ($challenge->activity_type_id) $posts->where('activity_type_id', $challenge->activity_type_id);
        return match ($challenge->metric) {
            'trees' => (int) $posts->whereHas('activityType', fn ($q) => $q->where('counts_trees', true))->sum('tree_count'),
            'days' => $posts->selectRaw('date(COALESCE(activity_date, created_at)) as day')->distinct()->get()->count(),
            default => $posts->count(),
        };
    }
    // Called inside approval transaction, after locking the member row.
    public function update(User $user): void
    {
        foreach (Challenge::where('active', true)->whereDate('starts_at', '<=', today())->lockForUpdate()->get() as $challenge) {
            $progress = $this->progress($user, $challenge);
            $row = DB::table('challenge_progress')->where('user_id', $user->id)->where('challenge_id', $challenge->id)->first();
            if ($row?->claimed_at) {
                continue;
            }
            $complete = $progress >= $challenge->target;
            DB::table('challenge_progress')->updateOrInsert(['user_id' => $user->id, 'challenge_id' => $challenge->id],
                ['progress' => $progress, 'completed_at' => $complete ? now() : null, 'claimed_at' => $complete ? now() : null]);
            if ($complete && $challenge->reward_points > 0) {
                $user->increment('points', $challenge->reward_points);
                PointsTransaction::create(['user_id' => $user->id, 'amount' => $challenge->reward_points, 'type' => 'earn',
                    'description' => 'สำเร็จภารกิจ: '.$challenge->title, 'balance_after' => $user->fresh()->points,
                    'reference_type' => Challenge::class, 'reference_id' => $challenge->id]);
            }
        }
    }
}
