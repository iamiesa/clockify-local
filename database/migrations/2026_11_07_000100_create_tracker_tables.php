<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('hourly_rate')->default(0)->comment('Default rate in cents');
            $table->boolean('billable_by_default')->default(true);
            $table->boolean('members_can_create_projects')->default(false);
            $table->boolean('members_can_create_tags')->default(true);
            $table->boolean('members_see_team_reports')->default(false);
            $table->boolean('force_project')->default(false);
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('hourly_rate')->nullable()->comment('Cents; overrides workspace rate');
            $table->string('invite_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('user_group_user', function (Blueprint $table) {
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['user_group_id', 'user_id']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('note')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#03a9f4');
            $table->boolean('billable')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('hourly_rate')->nullable()->comment('Cents; overrides member rate');
            $table->unsignedInteger('estimate_hours')->nullable();
            $table->text('note')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'client_id', 'name']);
            $table->index(['workspace_id', 'archived']);
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_manager')->default(false);
            $table->unsignedInteger('hourly_rate')->nullable()->comment('Cents; highest precedence');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_favorites', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('estimate_hours')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('archived')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 3000)->default('');
            $table->dateTime('start');
            $table->dateTime('end')->nullable()->comment('NULL while the timer is running');
            $table->unsignedInteger('duration')->default(0)->comment('Seconds; 0 while running');
            $table->boolean('billable')->default(false);
            $table->unsignedInteger('hourly_rate')->default(0)->comment('Cents snapshot at save time');
            $table->timestamps();

            $table->index(['workspace_id', 'start']);
            $table->index(['workspace_id', 'user_id', 'start']);
            $table->index(['workspace_id', 'project_id', 'start']);
            $table->index(['user_id', 'end']);
        });

        Schema::create('tag_time_entry', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();

            $table->primary(['time_entry_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        foreach (['tag_time_entry', 'time_entries', 'tags', 'tasks', 'project_favorites', 'project_user', 'projects', 'clients', 'user_group_user', 'user_groups', 'workspace_user', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
