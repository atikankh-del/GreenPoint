<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_types', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('icon')->default('🌱');
            $t->unsignedInteger('points'); $t->string('color')->default('#25795a'); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('activity_type_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title')->nullable(); $t->text('content'); $t->string('image')->nullable();
            $t->string('location')->nullable(); $t->date('activity_date')->nullable();
            $t->unsignedInteger('tree_count')->default(0); $t->string('privacy')->default('public');
            $t->boolean('request_points')->default(false); $t->string('status')->default('published');
            $t->unsignedInteger('points_awarded')->default(0); $t->text('review_note')->nullable(); $t->timestamps();
        });
        Schema::create('likes', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('post_id')->constrained()->cascadeOnDelete(); $t->timestamps(); $t->unique(['user_id','post_id']); });
        Schema::create('comments', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('post_id')->constrained()->cascadeOnDelete(); $t->text('content'); $t->timestamps(); });
        Schema::create('points_transactions', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->integer('amount'); $t->string('type'); $t->string('description'); $t->unsignedInteger('balance_after'); $t->nullableMorphs('reference'); $t->timestamps(); });
        Schema::create('rewards', function (Blueprint $t) { $t->id(); $t->string('name'); $t->text('description'); $t->string('icon')->default('🎁'); $t->unsignedInteger('points_required'); $t->unsignedInteger('stock')->default(0); $t->string('status')->default('active'); $t->timestamps(); });
        Schema::create('reward_redemptions', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('reward_id')->constrained()->cascadeOnDelete(); $t->unsignedInteger('points_spent'); $t->string('status')->default('pending'); $t->string('redemption_code')->unique(); $t->timestamps(); });
        Schema::create('badges', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('description'); $t->string('icon'); $t->string('criteria_type'); $t->unsignedInteger('criteria_value'); $t->unsignedInteger('bonus_points')->default(0); $t->timestamps(); });
        Schema::create('user_badges', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('badge_id')->constrained()->cascadeOnDelete(); $t->timestamp('earned_at'); $t->unique(['user_id','badge_id']); });
        Schema::create('challenges', function (Blueprint $t) { $t->id(); $t->string('title'); $t->text('description'); $t->string('icon')->default('🎯'); $t->foreignId('activity_type_id')->nullable()->constrained()->nullOnDelete(); $t->unsignedInteger('target'); $t->unsignedInteger('reward_points'); $t->date('starts_at'); $t->date('ends_at'); $t->boolean('active')->default(true); $t->timestamps(); });
        Schema::create('challenge_progress', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('challenge_id')->constrained()->cascadeOnDelete(); $t->unsignedInteger('progress')->default(0); $t->timestamp('completed_at')->nullable(); $t->timestamp('claimed_at')->nullable(); $t->unique(['user_id','challenge_id']); });
        Schema::create('notifications', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('title'); $t->text('message'); $t->string('type')->default('info'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
        Schema::create('reports', function (Blueprint $t) { $t->id(); $t->foreignId('reporter_id')->constrained('users')->cascadeOnDelete(); $t->foreignId('post_id')->constrained()->cascadeOnDelete(); $t->text('reason'); $t->string('status')->default('pending'); $t->timestamps(); });
    }
    public function down(): void
    {
        foreach (['reports','notifications','challenge_progress','challenges','user_badges','badges','reward_redemptions','rewards','points_transactions','comments','likes','posts','activity_types'] as $table) Schema::dropIfExists($table);
    }
};
