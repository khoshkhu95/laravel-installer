<?php

namespace Taha20\LaravelInstaller\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class RedirectIfInstalled
{
    /**
     * اگر پروژه قبلاً نصب شده باشد، اجازه دسترسی به صفحات نصب‌کننده داده نمی‌شود.
     */
    public function handle(Request $request, Closure $next)
    {

        if (File::exists(config('installer.lock_file'))) {
            if (!$request->routeIs('installer.finish')) {
                abort(404);
            }
            // return redirect()->route('installer.finish');
        }

        return $next($request);
    }
}
