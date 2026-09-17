<?php

namespace App\Services;

use App\Models\{Badge, PointsTransaction, Post, User};
use Illuminate\Support\Facades\DB;

class BadgeAwardService
{
    /** @return list<Badge> */
    public function awardEligibleBadges(User $user): array
    {
        $metrics = [
            'trees' => (int) Post::query()->where('user_id', $user->id)->where('request_points', true)->where('status', 'approved')
                ->whereHas('activityType', fn ($query) => $query->where('counts_trees', true))->sum('tree_count'),
            'activities' => Post::query()->where('user_id', $user->id)->where('request_points', true)->where('status', 'approved')->count(),
            'points' => (int) Post::query()->where('user_id', $user->id)->where('request_points', true)->where('status', 'approved')->sum('points_awarded'),
        ];

        $awarded = [];
        $badges = Badge::query()->whereIn('criteria_type', array_keys($metrics))
            ->whereNotExists(function ($query) use ($user) {
                $query->selectRaw('1')->from('user_badges')->whereColumn('user_badges.badge_id', 'badges.id')->where('user_badges.user_id', $user->id);
            })->orderBy('id')->get();

        foreach ($badges as $badge) {
            if ($metrics[$badge->criteria_type] < $badge->criteria_value) continue;

            $inserted = DB::table('user_badges')->insertOrIgnore(['user_id' => $user->id, 'badge_id' => $badge->id, 'earned_at' => now()]);
            if (! $inserted) continue;

            if ($badge->bonus_points > 0) {
                $user->increment('points', $badge->bonus_points);
                $user->refresh();
                PointsTransaction::create([
                    'user_id' => $user->id, 'amount' => $badge->bonus_points, 'type' => 'earn',
                    'description' => 'ได้รับ Badge: '.$badge->name, 'balance_after' => $user->points,
                    'reference_type' => Badge::class, 'reference_id' => $badge->id,
                ]);
            }
            $awarded[] = $badge;
        }

        return $awarded;
    }
}
