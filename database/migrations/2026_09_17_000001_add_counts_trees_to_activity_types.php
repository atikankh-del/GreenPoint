<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->boolean('counts_trees')->default(false)->after('points');
        });
        DB::table('activity_types')->whereIn('name', ['ปลูกต้นไม้', 'ปลูกป่า'])->update(['counts_trees' => true]);
    }

    public function down(): void
    {
        Schema::table('activity_types', fn (Blueprint $table) => $table->dropColumn('counts_trees'));
    }
};
