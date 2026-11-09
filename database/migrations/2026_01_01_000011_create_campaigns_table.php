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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // invitation, reminder, thank_you, custom
            $table->string('channel'); // sms, email, both
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('link_mode')->default('guest_invitation'); // guest_invitation, event_page, custom_url, none
            $table->text('custom_url')->nullable();
            $table->json('audience')->nullable(); // filter definition
            $table->integer('audience_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, sending, sent, cancelled, failed
            $table->integer('estimated_cards')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
