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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->default('Africa/Addis_Ababa');
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->text('map_url')->nullable();
            $table->text('host_names')->nullable();
            $table->boolean('is_invite_only')->default(true);
            $table->boolean('rsvp_enabled')->default(true);
            $table->boolean('plus_ones_allowed')->default(false);
            $table->json('design_json')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->json('thankyou_design_json')->nullable();
            $table->foreignId('thankyou_template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('status')->default('draft'); // draft, sending, sent, closed, suspended
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
