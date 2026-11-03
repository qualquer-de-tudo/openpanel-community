<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qopenpanel_users', fn (Blueprint $table) => $table->boolean('is_operator')->default(false));
        Schema::create('qopenpanel_maintenance_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('qopenpanel_users');
            $table->string('action');
            $table->string('target')->nullable();
            $table->string('expected_sha', 40)->nullable();
            $table->boolean('dispatch_failed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qopenpanel_maintenance_jobs');
        Schema::table('qopenpanel_users', fn (Blueprint $table) => $table->dropColumn('is_operator'));
    }
};
