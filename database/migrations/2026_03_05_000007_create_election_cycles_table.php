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
            $table->date('filing_start')->nullable();
            $table->date('filing_end')->nullable();
            $table->date('campaign_start');
            $table->date('campaign_end');
            $table->dateTime('voting_start');
            $table->dateTime('voting_end');
            $table->dateTime('results_date')->nullable();
            $table->boolean('notifications_sent')->default(false);
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
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
