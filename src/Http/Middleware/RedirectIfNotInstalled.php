<?php

namespace Taha20\LaravelInstaller\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class RedirectIfNotInstalled
{
    /**
     * اگر پروژه هنوز نصب نشده، کاربر به صفحه نصب‌کننده هدایت می‌شود.
     */
    public function handle(Request $request, Closure $next)
    {
        
        // خود مسیرهای نصب‌کننده را از این چک مستثنی می‌کنیم
        if ($request->is(config('installer.route_prefix') . '*')) {
            return $next($request);
        }

        if (! File::exists(config('installer.lock_file'))) {
            return redirect()->route('installer.welcome');
        }

        return $next($request);
    }
}
