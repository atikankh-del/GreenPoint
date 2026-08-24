<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RewardRedemption extends Model { protected $guarded=[]; public function reward(){return $this->belongsTo(Reward::class);} public function user(){return $this->belongsTo(User::class);} }
