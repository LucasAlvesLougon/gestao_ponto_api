<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['CLOCK_IN', 'BREAK_START', 'BREAK_END', 'CLOCK_OUT']);
            $table->timestamp('registered_at');
            $table->boolean('is_edited')->default(false);
            $table->text('edit_reason')->nullable();
            $table->timestamp('original_registered_at')->nullable();
            $table->timestamps();

            // Índice composto para otimizar busca de histórico por usuário e data
            $table->index(['user_id', 'registered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
