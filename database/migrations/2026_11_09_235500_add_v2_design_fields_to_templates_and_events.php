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
        Schema::table('templates', function (Blueprint $table) {
            $table->json('palette')->nullable()->after('design_json');
            $table->json('tags')->nullable()->after('palette');
            $table->string('recipe')->nullable()->after('tags');
            $table->json('ornament_sets')->nullable()->after('recipe');
            $table->json('spec')->nullable()->after('ornament_sets');
            $table->string('suite_key')->nullable()->after('spec')->index();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('card_language')->default('en')->after('rsvp_enabled'); // en, am, both
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['palette', 'tags', 'recipe', 'ornament_sets', 'spec', 'suite_key']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('card_language');
        });
    }
};
