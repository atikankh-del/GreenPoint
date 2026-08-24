<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityType extends Model { protected $guarded=[]; public function posts(){return $this->hasMany(Post::class);} }
