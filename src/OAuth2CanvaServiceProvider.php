<?php

namespace Macoauth2canva\OAuth2Canva;

use Illuminate\Support\Facades\App;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Macoauth2canva\OAuth2Canva\Commands\OAuth2CanvaCommand;

class OAuth2CanvaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('oauth2canva')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_oauth2canva_table')
            ->hasCommand(OAuth2CanvaCommand::class);
    }

    public function packageRegistered(): void
    {
        // Đăng ký OAuth2Canva như một singleton để Facade hoạt động
        $this->app->singleton(OAuth2Canva::class, function ($app) {
            return new OAuth2Canva();
        });
    }
}
