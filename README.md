# Laravel Installer

یک نصب‌کننده وب‌بیس (Web-based Installer) به شکل ویزارد چند مرحله‌ای برای پروژه‌های لاراول.

## مراحل ویزارد

1. خوش‌آمدگویی
2. بررسی پیش‌نیازهای سرور (نسخه PHP و اکستنشن‌ها)
3. بررسی پرمیشن پوشه‌ها و فایل‌ها
4. دریافت و تست اتصال اطلاعات دیتابیس
5. نوشتن `.env`، ساخت `APP_KEY` (در صورت نیاز) و اجرای مایگریشن
6. ساخت حساب مدیر سیستم
7. قفل شدن نصب‌کننده و اتمام نصب

## نصب پکیج (برای استفاده به عنوان composer package محلی)

اگر این پکیج را در ریپازیتوری خودتان publish نکرده‌اید، آن را به صورت یک `path repository` در پروژه اصلی اضافه کنید:

```json
// composer.json پروژه اصلی
"repositories": [
    {
        "type": "path",
        "url": "packages/yourvendor/laravel-installer"
    }
],
"require": {
    "yourvendor/laravel-installer": "*"
}
```

سپس پوشه این پکیج را داخل `packages/yourvendor/laravel-installer` پروژه اصلی کپی کنید و دستور زیر را اجرا کنید:

```bash
composer require yourvendor/laravel-installer:*
```

اگر پکیج را روی Packagist یا یک ریپازیتوری گیت‌هاب خصوصی منتشر کردید، کافیست به شکل معمول نصب شود:

```bash
composer require yourvendor/laravel-installer
```

## انتشار فایل‌های قابل سفارشی‌سازی

```bash
php artisan vendor:publish --tag=installer-config
php artisan vendor:publish --tag=installer-views
php artisan vendor:publish --tag=installer-lang
```

## فعال‌سازی اجبار نصب در کل سایت (اختیاری)

اگر می‌خواهید تا وقتی نصب کامل نشده، هیچ صفحه‌ای از سایت باز نشود، میدلور را در
`bootstrap/app.php` (لاراول ۱۱ به بعد) یا `app/Http/Kernel.php` (لاراول ۱۰) به گروه `web` اضافه کنید:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \YourVendor\LaravelInstaller\Http\Middleware\RedirectIfNotInstalled::class,
    ]);
})
```

## استفاده

بعد از نصب پکیج، آدرس زیر را در مرورگر باز کنید:

```
https://your-domain.com/installer
```

## ریست کردن نصب (فقط محیط توسعه)

```bash
php artisan installer:reset
```

این دستور فایل `storage/installed.lock` را حذف می‌کند تا بتوانید ویزارد نصب را دوباره اجرا کنید.
هرگز این دستور را روی سرور تولید (production) در دسترس کاربر نگذارید.

## اجرای تکه‌تکه (Chunked) مایگریشن‌ها برای جلوگیری از Timeout

روی سرورهای اشتراکی معمولاً `max_execution_time` بین ۳۰ تا ۶۰ ثانیه است. اگه پروژه مایگریشن‌های
زیادی داشته باشه، اجرای یکجای `php artisan migrate` ممکنه باعث خطای ۵۰۴ یا سفید شدن صفحه بشه.

برای حل این مشکل، مرحله مایگریشن به ۳ درخواست AJAX جدا تقسیم شده:

1. **`/installer/migrate/prepare`** — یک بار صدا زده می‌شود: فایل `.env` را می‌نویسد،
   `config:clear` را اجرا می‌کند و لیست مایگریشن‌های اجرا‌نشده را در Session ذخیره کرده و تعداد کل را برمی‌گرداند.
2. **`/installer/migrate/step`** — به صورت حلقه‌ای (یکی پس از دیگری) توسط جاوااسکریپت صدا زده می‌شود.
   هر بار **فقط یک فایل مایگریشن** را اجرا و از صف Session حذف می‌کند. چون هر درخواست HTTP فقط یک فایل
   را اجرا می‌کند، مدت زمان هر درخواست کوتاه می‌ماند و ریسک تایم‌اوت عملاً از بین می‌رود.
3. **`/installer/migrate/seed`** — بعد از پایان همه مایگریشن‌ها یک بار صدا زده می‌شود و seeder ها را اجرا می‌کند.

نکته فنی: تابع `Migrator::run($paths)` در لاراول اگر مسیر داده‌شده به `.php` ختم شود (به‌جای مسیر یک پوشه)،
فقط همان فایل را (در صورتی که قبلاً اجرا نشده باشد) اجرا می‌کند. از همین ویژگی برای اجرای تک‌فایلی استفاده شده.

### محدودیت این روش

اگر **داخل یک فایل مایگریشن** عملیات بسیار سنگینی انجام شود (مثلاً میلیون‌ها رکورد insert در یک حلقه)،
همان یک درخواست AJAX مربوط به آن فایل هنوز می‌تواند تایم‌اوت بدهد، چون تقسیم‌بندی در سطح «فایل» است نه
داخل خود فایل. راه‌حل‌های تکمیلی برای این حالت:

- مایگریشن سنگین را به چند فایل مایگریشن کوچک‌تر بشکنید.
- منطق پرکردن داده‌های حجیم را به یک Seeder جدا منتقل کنید و آن Seeder را هم به همین روش (چند درخواست AJAX،
  هر بار یک بخش از داده) تقسیم کنید.
- برای پروژه‌هایی که واقعاً حجم داده بالا دارند، به‌جای اجرای همزمان در درخواست HTTP، از یک صف (queue) با
  درایور `database` یا `redis` استفاده کنید و مرحله مایگریشن را به یک Job پس‌زمینه بسپارید؛ صفحه نصب‌کننده
  فقط وضعیت Job را هر چند ثانیه یک‌بار poll می‌کند. این حالت پیچیده‌تر است و در این نسخه پیاده‌سازی نشده.
- در `php.ini` یا تنظیمات Nginx/PHP-FPM مقدار `max_execution_time` و `fastcgi_read_timeout` را برای مسیر
  `/installer/*` کمی بالاتر ببرید (مثلاً ۱۲۰ ثانیه) تا حاشیه امنیت بیشتری برای فایل‌های نسبتاً سنگین وجود داشته باشد.
  در کد کنترلر هم `@set_time_limit(120)` قبل از اجرای هر مایگریشن صدا زده می‌شود (در صورتی که هاست اجازه دهد).

## نکات امنیتی مهم

- بعد از اتمام نصب، حتماً دسترسی به مسیر `/installer` از طریق وب‌سرور (Nginx/Apache) هم مسدود شود؛
  میدلور `RedirectIfInstalled` این کار را در سطح لاراول انجام می‌دهد اما یک لایه امنیتی اضافه در وب‌سرور توصیه می‌شود.
- فایل `storage/installed.lock` را در `.gitignore` قرار ندهید اگر می‌خواهید بعد از هر دیپلوی نصب مجدد لازم نباشد؛
  در غیر این صورت هر بار که پروژه را کلون می‌کنید، نصب‌کننده دوباره فعال می‌شود (رفتار مطلوب برای فروش پروژه به مشتری جدید).
- مقادیر حساس مثل رمز عبور دیتابیس هرگز نباید در session قرار بمانند؛ در این پیاده‌سازی بلافاصله
  بعد از نوشتن در `.env` می‌توانید `Session::forget('installer.database')` را نیز اضافه کنید.

## ساختار پوشه‌ها

```
laravel-installer/
├── composer.json
├── config/installer.php
├── routes/web.php
├── src/
│   ├── InstallerServiceProvider.php
│   ├── Console/InstallerResetCommand.php
│   └── Http/
│       ├── Controllers/InstallerController.php
│       └── Middleware/
│           ├── RedirectIfInstalled.php
│           └── RedirectIfNotInstalled.php
└── resources/
    ├── views/installer/*.blade.php
    └── lang/{fa,en}/installer.php
```
