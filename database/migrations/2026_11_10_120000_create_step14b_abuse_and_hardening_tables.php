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
        // Add warnings_sent tracking to subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('warnings_sent')->nullable()->after('note');
        });

        // Create abuse_reports table for reporting public invitations
        Schema::create('abuse_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reporter_ip', 45);
            $table->string('reporter_email')->nullable();
            $table->string('reason', 50);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending'); // pending, dismissed, action_taken
            $table->string('action_taken', 50)->nullable(); // dismissed, event_suspended, user_suspended
            $table->foreignId('reviewed_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abuse_reports');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('warnings_sent');
        });
    }
};
