<?php

namespace Onuraycicek\DatabaseUpdater;

use Illuminate\Support\Facades\Blade;
use Onuraycicek\DatabaseUpdater\Commands\DatabaseUpdaterCommand;
use Onuraycicek\DatabaseUpdater\Components\DatabaseUpdaterComponent;
use Onuraycicek\DatabaseUpdater\Components\DatabaseUpdaterResponseComponent;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DatabaseUpdaterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('databaseupdater')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(DatabaseUpdaterCommand::class)
            ->hasRoute('web');
    }

    public function packageBooted()
    {
        Blade::component('database-updater', DatabaseUpdaterComponent::class);
        Blade::component('database-updater-response', DatabaseUpdaterResponseComponent::class);
    }
}
