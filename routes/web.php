<?php

use Illuminate\Support\Facades\Route;
use Taha20\LaravelInstaller\Http\Controllers\InstallerController;

Route::group([
    'prefix' => config('installer.route_prefix'),
    'middleware' => array_merge(
        config('installer.route_middleware'),
        [\Taha20\LaravelInstaller\Http\Middleware\RedirectIfInstalled::class]
    ),
    'as' => 'installer.',
], function () {

    Route::get('/', [InstallerController::class, 'welcome'])->name('welcome');

    Route::get('/requirements', [InstallerController::class, 'requirements'])->name('requirements');

    Route::get('/permissions', [InstallerController::class, 'permissions'])->name('permissions');

    Route::get('/database', [InstallerController::class, 'databaseForm'])->name('database');
    Route::post('/database', [InstallerController::class, 'databaseStore'])->name('database.store');

    Route::get('/migrate', [InstallerController::class, 'migrateForm'])->name('migrate');
    Route::post('/migrate/prepare', [InstallerController::class, 'migratePrepare'])->name('migrate.prepare');
    Route::post('/migrate/step', [InstallerController::class, 'migrateStep'])->name('migrate.step');
    Route::post('/migrate/seed', [InstallerController::class, 'migrateSeed'])->name('migrate.seed');

    Route::get('/admin', [InstallerController::class, 'adminForm'])->name('admin');
    Route::post('/admin', [InstallerController::class, 'adminStore'])->name('admin.store');

    Route::get('/finish', [InstallerController::class, 'finish'])->name('finish');
});
