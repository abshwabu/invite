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
        Schema::create('checkin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('scanner_id')->nullable()->constrained('scanners')->nullOnDelete();
            $table->string('result'); // success, already_used, invalid, wrong_event, revoked
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();

            $table->index('event_id');
            $table->index(['event_id', 'result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin_logs');
    }
};
