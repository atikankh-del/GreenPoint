<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', 'avatar', 'cover', 'bio', 'points', 'join_ranking', 'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'join_ranking' => 'boolean',
        ];
    }

    public function getLevelInfoAttribute(): array
    {
        $xp = (int) $this->posts()->where('request_points', true)->where('status', 'approved')->sum('points_awarded');
        $levels = [0 => 'Seed', 100 => 'Sprout', 300 => 'Plant', 700 => 'Tree', 1500 => 'Forest Guardian', 3000 => 'Green Hero'];
        $floor = 0;
        $name = 'Seed';
        $number = 1;
        $next = null;
        foreach ($levels as $threshold => $label) {
            if ($xp >= $threshold) {
                $floor = $threshold;
                $name = $label;
            } else {
                $next = $threshold;
                break;
            }
        }
        $number = array_search($floor, array_keys($levels)) + 1;

        return ['xp' => $xp, 'name' => $name, 'number' => $number, 'next' => $next, 'percent' => $next ? min(100, ($xp - $floor) / ($next - $floor) * 100) : 100];
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')->withPivot('earned_at');
    }
}
