<?php

namespace InnoGE\LaravelMsGraphMail\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\View;
use InnoGE\LaravelMsGraphMail\LaravelMsGraphMailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-msgraph-mail_table.php.stub';
        $migration->up();
        */
    }

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'InnoGE\\LaravelMsGraphMail\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        View::addLocation('tests/Resources/views');
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelMsGraphMailServiceProvider::class,
        ];
    }
}
