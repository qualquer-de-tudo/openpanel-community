<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qopenpanel_users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('qopenpanel_setup', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
        });
        DB::table('qopenpanel_setup')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('qopenpanel_setup');
        Schema::dropIfExists('qopenpanel_users');
    }
};
