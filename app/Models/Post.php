<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Post extends Model { public function scopeVisibleTo($query, ?User $viewer)
 {
  return $query->where(function ($visible) use ($viewer) {
   $visible->where(function ($public) {
    $public->where('privacy','public')->whereIn('status',['published','approved']);
   });
   if($viewer?->role==='admin') $visible->orWhereRaw('1 = 1');
   elseif($viewer) $visible->orWhere('user_id',$viewer->id);
  });
 }
 protected $guarded=[]; protected $casts=['activity_date'=>'date','request_points'=>'boolean']; public function user(){return $this->belongsTo(User::class);} public function activityType(){return $this->belongsTo(ActivityType::class);} public function likes(){return $this->hasMany(Like::class);} public function comments(){return $this->hasMany(Comment::class);} }
