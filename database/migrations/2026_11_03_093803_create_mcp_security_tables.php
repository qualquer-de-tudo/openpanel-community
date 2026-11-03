<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(1);
        });
        Schema::create('mcp_grants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->json('scopes');
            $t->json('resource_ids')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('vault_secrets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id')->index();
            $t->string('purpose');
            $t->string('repository')->nullable();
            $t->text('ciphertext');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('secret_bindings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id')->index();
            $t->string('variable');
            $t->foreignUuid('secret_id')->constrained('vault_secrets')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['resource_id', 'variable']);
        });
        Schema::create('project_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id')->index();
            $t->unsignedInteger('revision');
            $t->json('manifest');
            $t->string('digest', 64);
            $t->string('source_sha', 40)->nullable();
            $t->timestamp('created_at');
            $t->unique(['resource_id', 'revision']);
        });
        Schema::create('mcp_audit', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->uuid('grant_id')->nullable()->index();
            $t->string('action');
            $t->string('outcome');
            $t->timestamp('created_at');
        });
        Schema::create('deployment_plans', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id');
            $t->foreignUuid('version_id')->constrained('project_versions')->restrictOnDelete();
            $t->string('status')->default('blocked_agent');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['deployment_plans', 'mcp_audit', 'project_versions', 'secret_bindings', 'vault_secrets', 'mcp_grants'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('resources', fn (Blueprint $t) => $t->dropColumn('revision'));
    }
};
