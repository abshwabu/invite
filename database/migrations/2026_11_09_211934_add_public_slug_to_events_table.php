<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('public_slug', 64)->nullable()->unique()->after('status');
        });

        // Populate existing events with unique public_slug
        $events = DB::table('events')->whereNull('public_slug')->get();
        foreach ($events as $event) {
            $base = Str::slug($event->title ?: 'event');
            $slug = substr($base, 0, 40) . '-' . Str::lower(Str::random(8));
            DB::table('events')->where('id', $event->id)->update(['public_slug' => $slug]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('public_slug');
        });
    }
};
