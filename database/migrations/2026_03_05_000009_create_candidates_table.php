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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_cycle_id')->constrained()->cascadeOnDelete();

            $table->string('party_name')->nullable();
            $table->json('achievements')->nullable();
            $table->string('photo')->nullable();

            $table->json('previous_position')->nullable();
            $table->json('previous_school_project')->nullable();
            $table->decimal('average_grade', 5, 2)->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'active'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->integer('votes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
