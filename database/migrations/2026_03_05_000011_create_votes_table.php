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
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained(); // No cascade to keep audit trail
            $table->foreignId('candidate_id')->constrained();
            $table->foreignId('position_id')->constrained();
            $table->foreignId('election_cycle_id')->constrained();
            $table->timestamp('voted_at')->useCurrent();
            $table->unique(['student_id', 'position_id', 'election_cycle_id'], 'unique_student_vote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
