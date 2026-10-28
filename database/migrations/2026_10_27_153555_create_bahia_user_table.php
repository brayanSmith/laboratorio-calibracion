<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bahías asignadas a cada usuario: una bahía puede tener varios usuarios y un usuario
     * varias bahías.
     */
    public function up(): void
    {
        Schema::create('bahia_user', function (Blueprint $table) {
            $table->foreignId('bahia_id')->constrained('bahias')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->primary(['bahia_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahia_user');
    }
};
