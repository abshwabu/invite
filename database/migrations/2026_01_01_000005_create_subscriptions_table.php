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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained()->cascadeOnDelete();
            $table->integer('cards_total');
            $table->integer('cards_used')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active, exhausted, expired, cancelled
            $table->string('source')->default('free'); // free, purchase, admin_grant
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
