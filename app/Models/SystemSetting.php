<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class SystemSetting extends Model { protected $guarded=[]; public static function valueOf(string $key,$default=null){return static::where('key',$key)->value('value')??$default;} }
