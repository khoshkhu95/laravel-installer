<?php

namespace Taha20\LaravelInstaller;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class InstallerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/installer.php', 'installer');
    }

    public function boot()
    {
        // بارگذاری روت‌ها
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // بارگذاری ویوها با namespace اختصاصی installer::
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'installer');

        // بارگذاری ترجمه‌ها
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'installer');

        // ثبت میدلور global برای اجبار نصب (اختیاری - در پایین توضیح داده شده)
        $this->app['router']->aliasMiddleware(
            'installer.installed',
            \Taha20\LaravelInstaller\Http\Middleware\RedirectIfNotInstalled::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Taha20\LaravelInstaller\Console\InstallerResetCommand::class,
            ]);

            // انتشار کانفیگ
            $this->publishes([
                __DIR__ . '/../config/installer.php' => config_path('installer.php'),
            ], 'installer-config');

            // انتشار ویوها (برای سفارشی‌سازی ظاهر)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/installer'),
            ], 'installer-views');

            // انتشار ترجمه‌ها
            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/installer'),
            ], 'installer-lang');
        }
    }
}
