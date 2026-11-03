<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qopenpanel_resources', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(1);
        });
        Schema::create('qopenpanel_mcp_grants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('qopenpanel_users')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->json('scopes');
            $t->json('resource_ids')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('qopenpanel_vault_secrets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('qopenpanel_users')->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id')->index();
            $t->string('purpose');
            $t->string('repository')->nullable();
            $t->text('ciphertext');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('qopenpanel_secret_bindings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('qopenpanel_users')->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id')->index();
            $t->string('variable');
            $t->foreignUuid('secret_id')->constrained('qopenpanel_vault_secrets')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['resource_id', 'variable']);
        });
        Schema::create('qopenpanel_project_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('qopenpanel_users')->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id')->index();
            $t->unsignedInteger('revision');
            $t->json('manifest');
            $t->string('digest', 64);
            $t->string('source_sha', 40)->nullable();
            $t->timestamp('created_at');
            $t->unique(['resource_id', 'revision']);
        });
        Schema::create('qopenpanel_mcp_audit', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('qopenpanel_users')->cascadeOnDelete();
            $t->uuid('grant_id')->nullable()->index();
            $t->string('action');
            $t->string('outcome');
            $t->timestamp('created_at');
        });
        Schema::create('qopenpanel_deployment_plans', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('qopenpanel_users')->cascadeOnDelete();
            $t->unsignedBigInteger('resource_id');
            $t->foreignUuid('version_id')->constrained('qopenpanel_project_versions')->restrictOnDelete();
            $t->string('status')->default('blocked_agent');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['qopenpanel_deployment_plans', 'qopenpanel_mcp_audit', 'qopenpanel_project_versions', 'qopenpanel_secret_bindings', 'qopenpanel_vault_secrets', 'qopenpanel_mcp_grants'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('qopenpanel_resources', fn (Blueprint $t) => $t->dropColumn('revision'));
    }
};
