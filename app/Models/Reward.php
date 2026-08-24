<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Reward extends Model { protected $guarded=[]; public function rewardRedemptions(){return $this->hasMany(RewardRedemption::class);} }
