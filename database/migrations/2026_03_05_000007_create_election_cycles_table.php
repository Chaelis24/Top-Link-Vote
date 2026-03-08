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
        Schema::create('election_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('academic_year');
            $table->enum('semester', ['1st Semester', '2nd Semester', 'Summer']);
            $table->date('filing_start')->nullable();
            $table->date('filing_end')->nullable();
            $table->date('campaign_start');
            $table->date('campaign_end');
            $table->dateTime('voting_start');
            $table->dateTime('voting_end');
            $table->dateTime('results_date')->nullable();
            $table->enum('status', ['draft', 'upcoming', 'ongoing', 'completed', 'archived'])->default('draft');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('election_cycles');
    }
};
