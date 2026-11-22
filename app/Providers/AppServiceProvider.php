<?php

namespace App\Providers;

use DB;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\MySqlSchemaGrammar;
use Modules\Core\Database\PostgresSchemaGrammar;
use Modules\Core\Utilities\QueryListener;
use Modules\Support\Console\RefreshSchemaCommand;
use Modules\User\Models\PersonalAccessToken;
use Modules\User\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        Connection::resolverFor('mysql', function ($pdo, $database, $prefix, $config) {
            $connection = new MySqlConnection($pdo, $database, $prefix, $config);
            $connection->setSchemaGrammar(new MySqlSchemaGrammar($connection));

            return $connection;
        });

        Connection::resolverFor('pgsql', function ($pdo, $database, $prefix, $config) {
            $connection = new PostgresConnection($pdo, $database, $prefix, $config);
            $connection->setSchemaGrammar(new PostgresSchemaGrammar($connection));

            return $connection;
        });

        Passkeys::ignoreRoutes();
        Passkeys::useUserModel(User::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->usePersonalAccessTokenModel();
        $this->strictModels();

        // The entry script is emitted immediately, so its preload is redundant when a service worker controls the page.
        Vite::usePreloadTagAttributes(fn (?string $src): array|false => $src === 'resources/js/app.ts' ? false : []);

        QueryListener::listen();

        Gate::before(fn ($user) => $user->isSuperAdmin());

        try {
            Schema::defaultStringLength(191);
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
            }
        } catch (Exception) {
        }

        if ($this->app->environment(['local', 'testing'])) {
            QueryListener::listen();
        } elseif ($this->app->isProduction()) {
            $this->prohibitDestructiveCommands();
        }
    }

    /**
     * Register the personal access tokens' model
     */
    private function usePersonalAccessTokenModel(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * The model should be strict to get optimal performance.
     */
    private function strictModels(): void
    {
        Model::shouldBeStrict();
    }

    /**
     * Prohibit Destructive Commands
     */
    private function prohibitDestructiveCommands(): void
    {
        if ($this->app->isProduction()) {
            // Disable refresh schema command in production environment.
            RefreshSchemaCommand::prohibit();
            // Prohibits: db:wipe, migrate:fresh, migrate:refresh, and migrate:reset.
            DB::prohibitDestructiveCommands();
        }
    }
}
