<?php

namespace InnoGE\LaravelMsGraphMail\Tests;

use Illuminate\Support\Facades\View;
use InnoGE\LaravelMsGraphMail\MsGraphMailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation('tests/Resources/views');
    }

    protected function getPackageProviders($app)
    {
        return [
            MsGraphMailServiceProvider::class,
        ];
    }
}
