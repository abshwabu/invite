<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
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
        $this->app->bind(SmsGateway::class, function () {
            return new LogSmsGateway();
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
    }
}
