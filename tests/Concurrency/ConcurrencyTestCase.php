<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Tests\Concurrency;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Honeypot\HoneypotServiceProvider;
use Spatie\OneTimePasswords\OneTimePasswordsServiceProvider;
use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Cms\CmsServiceProvider;
use Sunrice\Commerce\CommerceServiceProvider;
use Sunrice\CommerceRental\RentalServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\AdminPanelProvider;

/**
 * Real commits on MySQL, so a second connection sees them (RefreshDatabase
 * would keep everything in one uncommitted transaction). Runs only when
 * RENTAL_MYSQL_DATABASE is set:
 *
 *   RENTAL_MYSQL_DATABASE=rental_test RENTAL_MYSQL_USERNAME=… RENTAL_MYSQL_PASSWORD=… vendor/bin/pest tests/Concurrency
 */
abstract class ConcurrencyTestCase extends Orchestra
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (! getenv('RENTAL_MYSQL_DATABASE')) {
            $this->markTestSkipped('Set RENTAL_MYSQL_DATABASE (and _USERNAME, _PASSWORD, _HOST) to run the MySQL locking tests.');
        }
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AdminServiceProvider::class,
            CmsServiceProvider::class,
            HoneypotServiceProvider::class,
            OneTimePasswordsServiceProvider::class,
            SocialiteServiceProvider::class,
            CommerceServiceProvider::class,
            RentalServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $mysql = [
            'driver' => 'mysql',
            'host' => getenv('RENTAL_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('RENTAL_MYSQL_PORT') ?: '3306',
            'database' => getenv('RENTAL_MYSQL_DATABASE'),
            'username' => getenv('RENTAL_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('RENTAL_MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', $mysql);
        // The "other customer": the same database through a second connection.
        $app['config']->set('database.connections.mysql_other', $mysql);
        $app['config']->set('sunrice-cms.locales', ['id', 'en']);
        $app['config']->set('sunrice-cms.locale_names', ['id' => 'Bahasa Indonesia', 'en' => 'English']);
        $app['config']->set('sunrice-admin.auth.password_uncompromised', false);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fve4Qp/sF3rJf2fDR2lHo4SY9mZhJb+s=');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->app->make('migrator')->path(__DIR__.'/../../workbench/database/migrations');
    }
}
