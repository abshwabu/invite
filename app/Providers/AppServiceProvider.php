<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Services\Sms\AfroMessageGateway;
use App\Services\Sms\GeezSmsGateway;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, function ($app) {
            $driver = config('invite.sms.default_provider', env('SMS_GATEWAY', 'log'));

            return match ($driver) {
                'afromessage' => $app->make(AfroMessageGateway::class),
                'geezsms' => $app->make(GeezSmsGateway::class),
                default => $app->make(LogSmsGateway::class),
            };
        });

        $this->app->bind(\App\Contracts\PaymentProvider::class, function ($app) {
            $driver = config('invite.payments.provider', env('PAYMENT_PROVIDER', 'manual'));

            return match ($driver) {
                'chapa' => $app->make(\App\Services\Payment\ChapaProvider::class),
                default => $app->make(\App\Services\Payment\ManualPaymentProvider::class),
            };
        });

        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            return new class($connection, $database, $prefix, $config) extends SQLiteConnection {
                public function getSchemaBuilder()
                {
                    if (is_null($this->schemaGrammar)) {
                        $this->useDefaultSchemaGrammar();
                    }

                    return new class($this) extends SQLiteBuilder {
                        public function dropAllTables()
                        {
                            $database = $this->connection->getDatabaseName();

                            if ($database !== ':memory:' && ! str_contains($database, 'mode=memory')) {
                                $this->connection->disconnect();
                                file_put_contents($database, '');
                                if (file_exists("{$database}-wal")) {
                                    @unlink("{$database}-wal");
                                }
                                if (file_exists("{$database}-shm")) {
                                    @unlink("{$database}-shm");
                                }

                                return;
                            }

                            parent::dropAllTables();
                        }
                    };
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if ($this->app->environment('production') && \Illuminate\Support\Facades\Schema::hasTable('payment_methods')) {
            try {
                $hasPlaceholders = \App\Models\PaymentMethod::where('is_active', true)
                    ->where(function ($query) {
                        $query->where('account_number', 'like', '%XXXX%')
                            ->orWhere('phone_number', 'like', '%XXXX%');
                    })
                    ->exists();

                if ($hasPlaceholders) {
                    \Illuminate\Support\Facades\Log::warning(
                        'SECURITY ALERT: Placeholder payment method receiving accounts (containing XXXX) are active in production!'
                    );
                }
            } catch (\Throwable) {
                // Table might not be ready during migrations
            }
        }
    }
}
