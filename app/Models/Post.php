<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Post extends Model { protected $guarded=[]; protected $casts=['activity_date'=>'date','request_points'=>'boolean']; public function user(){return $this->belongsTo(User::class);} public function activityType(){return $this->belongsTo(ActivityType::class);} public function likes(){return $this->hasMany(Like::class);} public function comments(){return $this->hasMany(Comment::class);} }
