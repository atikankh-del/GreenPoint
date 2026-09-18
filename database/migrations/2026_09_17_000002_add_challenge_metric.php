<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', fn (Blueprint $t) => $t->string('metric')->default('activities'));
        DB::table('challenges')->whereIn('activity_type_id', DB::table('activity_types')->where('counts_trees', true)->select('id'))->update(['metric' => 'trees']);
        DB::table('challenges')->where('title', 'รดน้ำต่อเนื่อง')->update(['metric' => 'days', 'description' => 'ดูแลต้นไม้ให้ครบ 5 วันภายในช่วงภารกิจ (ไม่จำเป็นต้องติดต่อกัน)']);
    }

    public function down(): void
    {
        Schema::table('challenges', fn (Blueprint $t) => $t->dropColumn('metric'));
    }
};
