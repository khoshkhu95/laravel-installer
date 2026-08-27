<?php

namespace Taha20\LaravelInstaller\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallerResetCommand extends Command
{
    protected $signature = 'installer:reset';

    protected $description = 'حذف فایل قفل نصب تا ویزارد نصب دوباره فعال شود (فقط برای محیط توسعه)';

    public function handle()
    {
        $lockFile = config('installer.lock_file');

        if (File::exists($lockFile)) {
            File::delete($lockFile);
            $this->info('فایل قفل نصب حذف شد. نصب‌کننده دوباره فعال است.');
            return self::SUCCESS;
        }

        $this->comment('فایل قفل نصب یافت نشد؛ پروژه در حال حاضر نصب نشده است.');
        return self::SUCCESS;
    }
}
