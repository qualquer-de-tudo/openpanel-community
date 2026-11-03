<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('disk_mb')->default(10240);
            $t->unsignedInteger('memory_mb')->default(2048);
            $t->unsignedInteger('project_limit')->default(5);
            $t->unsignedInteger('database_limit')->default(5);
            $t->timestamps();
        });
        Schema::create('resources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind');
            $t->string('name');
            $t->string('engine');
            $t->string('domain')->nullable();
            $t->text('repository')->nullable();
            $t->unsignedInteger('disk_mb')->default(512);
            $t->string('status')->default('planned');
            $t->timestamps();
            $t->unique(['user_id', 'kind', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
        Schema::dropIfExists('accounts');
    }
};
