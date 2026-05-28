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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('block_id')->nullable()->constrained()->onDelete('set null');
            $table->string('student_id', 50)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 10)->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->date('birthday')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('vote_reference', 20)->nullable()->unique();
            $table->boolean('has_voted')->default(false);
            $table->timestamp('voted_at')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index(['course_id', 'block_id']);
            $table->index('status');
            $table->index('has_voted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
