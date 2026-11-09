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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('preset'); // 3_days_before, 1_day_before, morning_of, custom
            $table->string('channel')->default('sms'); // sms, email, both
            $table->string('target_audience')->default('rsvp_pending'); // rsvp_pending, all, rsvp_yes
            $table->dateTime('scheduled_at');
            $table->string('status')->default('pending'); // pending, sending, sent, failed, cancelled
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->text('message_body')->nullable();
            $table->string('subject')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
