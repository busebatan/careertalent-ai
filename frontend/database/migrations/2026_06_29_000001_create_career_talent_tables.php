<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bootcamp')->default('YZTA');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->after('password');
            $table->foreignId('cohort_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('career_roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('required_skills');
            $table->unsignedTinyInteger('weeks_template')->default(12);
            $table->timestamps();
        });

        Schema::create('cv_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('status')->default('pending'); // pending, processing, ready, failed
            $table->text('raw_text')->nullable();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('profile_data');
            $table->timestamps();
        });

        Schema::create('user_career_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_role_id')->constrained();
            $table->text('aspiration_note')->nullable();
            $table->timestamps();
        });

        Schema::create('skill_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_role_id')->constrained();
            $table->json('gap_data');
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_gaps');
        Schema::dropIfExists('user_career_goals');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('cv_documents');
        Schema::dropIfExists('career_roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cohort_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('cohorts');
    }
};
