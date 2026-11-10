<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Payment Methods (Receiving accounts for the business)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // bank, mobile_money
            $table->string('label');
            $table->string('bank_name')->nullable();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('phone_number')->nullable();
            $table->text('instructions_en')->nullable();
            $table->text('instructions_am')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        // 2. Extend Payments Table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_code', 32)->nullable()->unique()->after('id');
            $table->foreignId('method_id')->nullable()->after('provider_ref')->constrained('payment_methods')->nullOnDelete();
            $table->string('reference_number')->nullable()->after('method_id');
            $table->string('receipt_path')->nullable()->after('reference_number');
            $table->string('receipt_hash', 64)->nullable()->after('receipt_path');
            $table->string('receipt_phash', 64)->nullable()->after('receipt_hash');
            $table->decimal('amount_expected', 10, 2)->nullable()->after('amount');
            $table->decimal('amount_declared', 10, 2)->nullable()->after('amount_expected');
            $table->decimal('amount_verified', 10, 2)->nullable()->after('amount_declared');
            $table->dateTime('paid_at_declared')->nullable()->after('amount_verified');
            $table->string('paid_from')->nullable()->after('paid_at_declared');
            $table->text('user_note')->nullable()->after('paid_from');
            $table->text('admin_note')->nullable()->after('user_note');
            $table->text('rejection_reason')->nullable()->after('admin_note');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_reason')->constrained('super_admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->timestamp('submitted_at')->nullable()->after('reviewed_at');
            $table->json('flags')->nullable()->after('submitted_at');
        });

        // Conditional partial unique index on reference_number for active/processed payments
        // Prevents one reference from being reused across multiple approved/submitted payments
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS payments_reference_number_active_unique ON payments (reference_number) WHERE reference_number IS NOT NULL AND status IN ('submitted', 'needs_info', 'approved')");

        // 3. Payment Events (Immutable audit timeline of payment lifecycle)
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('actor_type'); // user, super_admin, system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event'); // created, submitted, info_requested, resubmitted, approved, rejected, refunded, abandoned
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');

        if (Schema::hasTable('payments')) {
            DB::statement("DROP INDEX IF EXISTS payments_reference_number_active_unique");

            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['method_id']);
                $table->dropForeign(['reviewed_by']);
                $table->dropColumn([
                    'payment_code',
                    'method_id',
                    'reference_number',
                    'receipt_path',
                    'receipt_hash',
                    'receipt_phash',
                    'amount_expected',
                    'amount_declared',
                    'amount_verified',
                    'paid_at_declared',
                    'paid_from',
                    'user_note',
                    'admin_note',
                    'rejection_reason',
                    'reviewed_by',
                    'reviewed_at',
                    'submitted_at',
                    'flags',
                ]);
            });
        }

        Schema::dropIfExists('payment_methods');
    }
};
