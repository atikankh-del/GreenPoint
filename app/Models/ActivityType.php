<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityType extends Model { protected $guarded=[]; protected $casts=['active'=>'boolean','counts_trees'=>'boolean']; public function posts(){return $this->hasMany(Post::class);} }
