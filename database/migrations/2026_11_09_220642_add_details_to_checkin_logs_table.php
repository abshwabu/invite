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
        Schema::table('checkin_logs', function (Blueprint $table) {
            $table->text('raw_payload')->nullable()->after('result');
            $table->string('ip_address', 45)->nullable()->after('raw_payload');
            $table->string('device_info')->nullable()->after('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkin_logs', function (Blueprint $table) {
            $table->dropColumn(['raw_payload', 'ip_address', 'device_info']);
        });
    }
};
