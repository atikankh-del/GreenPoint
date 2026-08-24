<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Challenge extends Model { protected $guarded=[]; protected $casts=['starts_at'=>'date','ends_at'=>'date']; public function activityType(){return $this->belongsTo(ActivityType::class);} }
