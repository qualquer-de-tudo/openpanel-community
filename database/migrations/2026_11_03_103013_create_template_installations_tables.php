<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_installations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('resource_id')->unique()->constrained('resources')->cascadeOnDelete();
            $t->string('template_id');
            $t->string('version');
            $t->string('digest', 64);
            $t->json('manifest');
            $t->timestamps();
        });
        Schema::create('template_installation_history', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('installation_id')->constrained('template_installations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('sequence');
            $t->string('action');
            $t->string('version');
            $t->string('digest', 64);
            $t->json('manifest');
            $t->uuid('project_version_id');
            $t->timestamp('created_at');
            $t->unique(['installation_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_installation_history');
        Schema::dropIfExists('template_installations');
    }
};
