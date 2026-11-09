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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('token', 32)->unique();
            $table->string('preferred_channel')->default('sms'); // sms, email, both
            $table->integer('max_party_size')->default(1);
            $table->string('rsvp_status')->default('pending'); // pending, yes, no, maybe
            $table->integer('rsvp_party_size')->nullable();
            $table->text('rsvp_note')->nullable();
            $table->boolean('opted_out')->default(false);
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('checked_in_by')->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
