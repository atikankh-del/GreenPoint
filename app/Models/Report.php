<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Report extends Model { protected $guarded=[]; public function reporter(){return $this->belongsTo(User::class,'reporter_id');} public function post(){return $this->belongsTo(Post::class);} }
