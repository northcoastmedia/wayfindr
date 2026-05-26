<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_issue_provider_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('name');
            $table->string('base_url', 2048)->nullable();
            $table->text('credentials')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'provider']);
            $table->index(['account_id', 'is_enabled']);
        });

        Schema::create('site_external_issue_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_issue_provider_connection_id')
                ->constrained('external_issue_provider_connections')
                ->cascadeOnDelete();
            $table->string('project_key');
            $table->string('project_name')->nullable();
            $table->string('web_url', 2048)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'external_issue_provider_connection_id', 'project_key'], 'site_external_issue_projects_unique');
            $table->index(['account_id', 'site_id']);
            $table->index('external_issue_provider_connection_id', 'site_external_issue_projects_connection_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_external_issue_projects');
        Schema::dropIfExists('external_issue_provider_connections');
    }
};
