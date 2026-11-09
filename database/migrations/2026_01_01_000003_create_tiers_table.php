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
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_en');
            $table->string('name_am');
            $table->text('description_en')->nullable();
            $table->text('description_am')->nullable();
            $table->decimal('price_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('ETB');
            $table->integer('card_quota');
            $table->integer('validity_days')->nullable(); // null = never expires
            $table->integer('max_events')->nullable(); // null = unlimited
            $table->integer('max_guests_per_event')->default(500);
            $table->integer('max_scanners_per_event')->default(3);
            $table->integer('sms_weight')->default(1);
            $table->integer('email_weight')->default(1);
            $table->boolean('thank_you_consumes_quota')->default(false);
            $table->json('features')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiers');
    }
};
