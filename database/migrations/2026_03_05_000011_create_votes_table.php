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
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->foreignId('position_id')->constrained()->onDelete('cascade');
            $table->foreignId('election_cycle_id')->constrained()->onDelete('cascade');
            $table->string('reference_number', 20)->nullable();
            $table->timestamp('voted_at')->useCurrent();
            $table->timestamps();
            $table->index('reference_number');
            $table->index('candidate_id');
            $table->index('election_cycle_id');
            $table->index('student_id');
            $table->unique(['student_id', 'position_id', 'election_cycle_id'], 'unique_student_vote');
            $table->index(['candidate_id', 'election_cycle_id']);
            $table->index('voted_at');
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
