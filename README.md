# Navasan-API plugin for WordPress

[فارسی](#فارسی) · [English](#english)

---

<div dir="rtl">

## فارسی

افزونهٔ وردپرس برای گرفتن نرخ لحظه‌ای طلا، نمایش آن در سایت (شورت‌کد، REST، اسکریپت سبک، المنتور) و قیمت‌گذاری خودکار محصولات ووکامرس بر اساس **وزن، عیار، اجرت، سود و مالیات**.

> ### منبع داده / Powered by Navasan-API
> نرخ‌ها از پروژهٔ متن‌باز **[Navasan-API](https://github.com/HosseinOdd/Navasan-API)** ساختهٔ **Hossein** ([HosseinOdd](https://github.com/HosseinOdd)) گرفته می‌شود. این پروژه با GitHub Actions حدوداً هر ۳۰ دقیقه نرخ‌های navasan.net را در فایل `data/gold.json` می‌نویسد.
> این افزونه **فقط فایل JSON همان مخزن را می‌خواند**؛ هیچ کدی از آن کپی نشده است. جزئیات اعتبار و لایسنس: فایل [`NOTICE`](NOTICE).

### سلب مسئولیت
داده از یک پروژهٔ شخصی و **بدون هیچ تضمینی** می‌آید که خودش از navasan.net می‌گیرد. مسئولیت درستی قیمت‌ها و رعایت شرایط استفادهٔ منبع با مدیر سایت است.

### نیازمندی‌ها
وردپرس ۶٫۴+، PHP 7.4+. ووکامرس اختیاری است: بدون آن بخش نرخ، شورت‌کد و REST کار می‌کند و بخش قیمت‌گذاری محصول فعال نمی‌شود.

### نصب
1. فایل zip افزونه را از بخش [Releases](https://github.com/amirmohagheghiyan/navasan-api-wordpress-plugin/releases) بگیرید.
2. در وردپرس: «افزونه‌ها ← افزودن ← بارگذاری افزونه» و سپس فعال‌سازی.
3. صفحهٔ تنظیمات: **ووکامرس ← نرخ طلا (Navasan-API)** (بدون ووکامرس: **تنظیمات ← نرخ طلا**).
4. «تست اتصال» را بزنید (سرور بعضی هاست‌های ایران به گیت‌هاب دسترسی ندارد؛ نتیجهٔ هر سه آدرس جدا نشان داده می‌شود)، بعد «دریافت همین حالا».
5. برای قیمت‌گذاری محصولات: تنظیمات را کامل کنید، «پیش‌نمایش قیمت‌ها» را ببینید و بعد «اعمال قیمت‌ها» را بزنید. **تا این کار انجام نشود افزونه به قیمت هیچ محصولی دست نمی‌زند.**

### منبع داده و آدرس‌های پشتیبان
1. `https://raw.githubusercontent.com/HosseinOdd/Navasan-API/main/data/gold.json` (اصلی)
2. GitHub Contents API همان فایل (`Accept: application/vnd.github.raw+json`)
3. jsDelivr همان فایل. شاخه را تا چند ساعت کش می‌کند، پس فقط آخرین چاره است و اگر `date` آن از آخرین نرخ ذخیره‌شده قدیمی‌تر باشد نادیده گرفته می‌شود.

آدرس‌های پشتیبان فقط وقتی استفاده می‌شوند که آدرس منبع همان پیش‌فرض باشد. هیچ درخواستی به جز این آدرس‌ها نمی‌رود (نه آمار، نه بررسی لایسنس).

### تنظیمات
| بخش | تنظیم | توضیح |
|---|---|---|
| منبع | آدرس منبع | پیش‌فرض آدرس بالا؛ `{key}` در آدرس جایگزین کلید می‌شود |
| منبع | کلید API (اختیاری) | Navasan-API کلید نمی‌خواهد. برای توکن گیت‌هاب یا منبع دیگر. روش: هدر `Authorization: Bearer` یا `{key}` در آدرس. کلید پوشیده نمایش داده می‌شود و در لاگ نمی‌آید. توکن فقط به هاست‌های گیت‌هاب فرستاده می‌شود، هرگز به jsDelivr |
| منبع | نماد و عیار پایه | پیش‌فرض `18ayar` و ۱۸ |
| منبع | فاصلهٔ به‌روزرسانی | دقیقه، پیش‌فرض ۳۰، حداقل ۵ (منبع خودش حدوداً هر ۳۰ دقیقه تازه می‌شود) |
| نرخ | حالت نرخ | خودکار یا دستی؛ در حالت دستی «قیمت دستی هر گرم» (به واحد ارز فروشگاه) ملاک است و منبع نادیده گرفته می‌شود |
| نرخ | تعدیل | بدون / درصد / مبلغ ثابت (تومان)، مثبت یا منفی، روی نرخ خودکار |
| محافظ | جهش مجاز | پیش‌فرض ۱۰٪؛ بیشتر از آن اعمال نمی‌شود، اعلان مدیر می‌آید و دکمهٔ «تأیید و اعمال» هست |
| محافظ | دادهٔ کهنه | پیش‌فرض ۶ ساعت؛ هشدار در صفحه و اعلان مدیر، قیمت‌ها دست نمی‌خورند |
| پول | واحد | خودکار (`IRT`=همان عدد، `IRR`=×۱۰) / تومان / ریال |
| گرد کردن | گام و جهت | بدون، ۵۰، ۱۰۰، ۵۰۰، ۱۰۰۰، ۵۰۰۰، ۱۰۰۰۰ یا دلخواه؛ نزدیک‌ترین / بالا / پایین؛ فقط روی قیمت نهایی |
| محصول | سود، مالیات (۹)، اجرت پیش‌فرض | درصد |
| محصول | منبع وزن و واحد | فیلد وزن ووکامرس یا ویژگی؛ واحد گرم/کیلوگرم **جدا از** واحد وزن ووکامرس |
| محصول | منبع اجرت / عیار | پیش‌فرض سراسری، ویژگی یا فیلد محصول |
| محصول | دامنهٔ اعمال | همهٔ محصولات، دسته‌های انتخابی، یا فقط محصولات تیک‌دار |

هر محصول (و هر متغیر) تب «قیمت‌گذاری با نرخ طلا» دارد: روشن/خاموش، وزن، عیار، اجرت (درصد یا ثابت)، سود، مبلغ اضافه. **فیلد خالی = از تنظیم سراسری یا ویژگی بخوان.** قیمت محاسبه‌شده و ریز آن کنار فیلدها نشان داده می‌شود.

### فرمول
```
نرخ عیار محصول = نرخ پایه × (عیار محصول ÷ عیار نرخ پایه)
طلا     = وزن (گرم) × نرخ عیار محصول
اجرت    = طلا × درصد اجرت          (یا مبلغ ثابت)
سود     = (طلا + اجرت) × درصد سود
مالیات  = (اجرت + سود) × درصد مالیات
قیمت    = گرد(طلا + اجرت + سود + مالیات + مبلغ اضافه)
```
نمونه: نرخ ۲۵٬۰۰۰٬۰۰۰، وزن ۲ گرم، اجرت ۱۰٪، سود ۷٪، مالیات ۹٪ ← طلا ۵۰٬۰۰۰٬۰۰۰ + اجرت ۵٬۰۰۰٬۰۰۰ + سود ۳٬۸۵۰٬۰۰۰ + مالیات ۷۹۶٬۵۰۰ = **۵۹٬۶۴۶٬۵۰۰**.

خواندن عدد از متن: `1.22 گرم`، `6%`، `10.5%`، `.5`، `8,69`، ارقام فارسی (`۱٫۲۲`) پشتیبانی می‌شود؛ مقدار نامفهوم یعنی «ردشده»، نه صفر. (`1,234` هزارگان خوانده می‌شود، `8,69` اعشار.)

### شورت‌کد
```
[one_gold_price]
[one_gold_price field="high" unit="rial" digits="en" thousands="no" label="no"]
[one_gold_price symbol="sekkeh" field="value"]
[one_gold_price field="updated" date_format="Y/m/d H:i"]
```
پارامترها: `symbol` (پیش‌فرض نماد پایه)، `field` (`value`, `change_val`, `change_pct`, `high`, `low`, `updated`)، `unit` (`toman`|`rial`)، `thousands` (`yes`|`no`)، `digits` (`fa`|`en`)، `label` (`yes`|`no`)، `decimals`، `date_format`.
بیشترین و کمترین امروز را منبع نمی‌دهد؛ خود افزونه از دریافت‌هایش (به وقت تهران) نگه می‌دارد.

### REST
`GET /wp-json/one-navasan/v1/rate?symbol=18ayar&unit=toman` (عمومی، فقط‌خواندنی، کش ۶۰ ثانیه، بدون هیچ تنظیم یا کلیدی)
```json
{"symbol":"18ayar","unit":"toman","mode":"auto","value":25035780,"change_val":0,"change_pct":0,
 "high":25035780,"low":25035780,"updated":1795498220,"fetched_at":1795498500,"stale":false}
```

### اسکریپت سبک
```html
<span data-one-gold="value" data-one-digits="fa"></span>
<input type="text" data-one-gold="value" data-one-unit="toman">
```
مقدارهای `value, change_val, change_pct, high, low, updated`؛ اختیاری: `data-one-symbol`, `data-one-unit`, `data-one-format="plain"`, `data-one-digits="en|fa"`. برای `input` مقدار پر و رویداد `input`/`change` فرستاده می‌شود. از تنظیمات قابل خاموش شدن است.

### المنتور پرو
دو Dynamic Tag («نرخ طلا (متن)» و «نرخ طلا (عدد)») در دسته‌های متن و عدد ثبت می‌شود. بدون المنتور هیچ خطایی نمی‌دهد.

### هوک‌ها
| نوع | نام | آرگومان‌ها |
|---|---|---|
| فیلتر | `one_navasan_final_rate` | نرخ نهایی پایه (تومان)، تنظیمات |
| فیلتر | `one_navasan_product_price` | قیمت محاسبه‌شده، محصول، ریز محاسبه |
| اکشن | `one_navasan_rate_changed` | نرخ جدید، نرخ قبلی (تومان) |
| اکشن | `one_navasan_prices_applied` | شمارش‌های اجرا |

### کرون واقعی
اگر Action Scheduler (مثلاً با ووکامرس) باشد از آن استفاده می‌شود وگرنه WP-Cron. **WP-Cron به بازدید سایت وابسته است.** برای دقت بیشتر:
```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```
```
*/5 * * * * wget -q -O /dev/null "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
# یا با WP-CLI
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now >/dev/null 2>&1
```

### محدودیت‌ها
- دقت و تازگی نرخ به منبع بستگی دارد (حدود ۳۰ دقیقه، گاهی چند ساعت عقب).
- فقط محصولات ساده و متغیر قیمت‌گذاری می‌شوند. قیمت حراج را افزونه عوض نمی‌کند؛ فقط اگر بعد از محاسبه از قیمت اصلی بیشتر یا مساوی شد پاک می‌شود.
- نوع محصول گروه‌بندی‌شده و خارجی نادیده گرفته می‌شود.
- تاریخ شمسی تولید نمی‌شود (از `wp_date` استفاده می‌شود؛ با افزونهٔ تاریخ شمسی سازگار با `wp_date` شمسی می‌شود).
- حذف افزونه گزینه‌ها، متاها و زمان‌بندی‌ها را پاک می‌کند ولی **قیمت محصولات را برنمی‌گرداند**.

### تصمیم‌ها
- تعدیل «مبلغ ثابت» نرخ خودکار به **تومان** (واحد منبع) است و قبل از تبدیل واحد اعمال می‌شود؛ قیمت دستی به واحد فروشگاه است.
- شورت‌کد/REST/اسکریپت برای نماد پایه همان نرخ مؤثر (دستی یا تعدیل‌شده) را نشان می‌دهند؛ برای نمادهای دیگر نرخ خام منبع. در حالت دستی بیشترین/کمترین برابر همان نرخ است.
- اولویت منبع هر مقدار: فیلد خود محصول (متغیر ← والد) ← ویژگی/فیلد وزن طبق تنظیم ← پیش‌فرض سراسری. وزن معتبر الزامی است؛ ویژگی ناخوانا (وزن، عیار، اجرت) یعنی محصول ردشود، نه مقدار پیش‌فرض.
- پیش‌فرض‌ها: سود ۰، اجرت ۰، مالیات ۹، عیار ۱۸ (عدد دیگری حدس زده نشد).
- جهش بیش از حد مجاز (پیش‌فرض ۱۰٪) در هر دو جهت نگه داشته می‌شود؛ مقدار جدید تا تأیید مدیر ذخیره نمی‌شود.
- بازمحاسبهٔ خودکار فقط وقتی انجام می‌شود که نرخ مؤثر یا تنظیمات قیمت‌گذاری عوض شده باشد و مدیر یک بار پیش‌نمایش و اعمال را انجام داده باشد. اگر وسط کار تنظیمات عوض شود، کار از اول با ورودی جدید شروع می‌شود.
- ۵۰ محصول در هر مرحله پردازش می‌شود. دکمه‌های صفحه از AJAX مرحله‌به‌مرحله استفاده می‌کنند؛ تغییر خودکار نرخ از زمان‌بندی پس‌زمینه.
- اسکریپت سبک به‌صورت پیش‌فرض فعال است ولی اگر عنصری با `data-one-gold` نباشد کاری نمی‌کند.
- بیشترین/کمترین روز در آپشن ذخیره می‌شود و با تغییر روز (به وقت تهران) از نو شروع می‌شود.

### توسعه و تست
```
composer install
vendor/bin/phpunit --testsuite unit      # تست واحد (بدون وردپرس)
vendor/bin/phpunit --testsuite live      # خواندن آدرس واقعی (بدون شبکه رد می‌شود)
powershell -File tests/integration/run-integration.ps1   # وردپرس واقعی با WordPress Playground، بدون Docker
```

</div>

---

## English

A WordPress plugin that fetches the live gold rate, shows it on the site (shortcode, REST, light script, Elementor) and automatically prices WooCommerce products from **weight, karat, wage, profit and tax**.

> ### Data source / Powered by Navasan-API
> Rates come from the open-source project **[Navasan-API](https://github.com/HosseinOdd/Navasan-API)** by **Hossein** ([HosseinOdd](https://github.com/HosseinOdd)). Its GitHub Actions workflow writes the navasan.net rates to `data/gold.json` about every 30 minutes.
> This plugin **only reads the JSON file of that repository**; no code from it is included. See [`NOTICE`](NOTICE) for credit and license details.

### Disclaimer
The data comes from a personal project, with **no warranty**, which itself takes it from navasan.net. The site administrator is responsible for the correctness of the prices and for complying with the terms of use of the original source.

### Requirements
WordPress 6.4+, PHP 7.4+. WooCommerce is optional: without it the rate, shortcodes and REST work and product pricing stays off.

### Install
1. Download the plugin zip from [Releases](https://github.com/amirmohagheghiyan/navasan-api-wordpress-plugin/releases).
2. WordPress: Plugins → Add New → Upload Plugin, then activate.
3. Settings page: **WooCommerce → Gold rate (Navasan-API)** (without WooCommerce: **Settings → Gold rate**).
4. Click **Test connection** (some Iranian hosts cannot reach GitHub; each of the three URLs is reported separately), then **Fetch now**.
5. For product pricing: finish the settings, run **Preview**, then **Apply**. **Until you do, the plugin never touches any product price.**

### Source and fallbacks
1. `https://raw.githubusercontent.com/HosseinOdd/Navasan-API/main/data/gold.json` (primary)
2. GitHub Contents API for the same file (`Accept: application/vnd.github.raw+json`)
3. jsDelivr for the same file. It caches the branch for hours, so it is the last resort and is ignored when its `date` is older than the stored rate.

Fallbacks are only used while the source URL is the default one. No request goes anywhere else (no telemetry, no license checks).

### Settings
See the Persian table above; the options are: source URL (supports `{key}`), optional API key (Bearer header or `{key}` in URL; masked, never logged, a GitHub token is only sent to GitHub hosts), base symbol/karat, interval (min 5 minutes), auto/manual mode with manual per-gram price, rate adjustment (percent/fixed Toman), jump guard (default 10%), stale warning (default 6 h), currency unit (auto: `IRT` = same number, `IRR` = ×10), rounding step/direction (final price only), default profit/tax(9)/wage, weight/wage/karat sources (WooCommerce field, product attribute or product field), weight unit (g/kg, independent from the WooCommerce unit) and scope (all / categories / flagged products).

Every product and variation has a "Gold pricing" tab: on/off, weight, karat, wage (percent or fixed), profit, extra amount. **An empty field means "use the global setting or attribute".**

### Formula
```
karat_rate = base_rate × (product_karat ÷ base_karat)
gold       = weight_g × karat_rate
wage       = gold × wage%            (or a fixed amount)
profit     = (gold + wage) × profit%
tax        = (wage + profit) × tax%
price      = round(gold + wage + profit + tax + extra)
```
Example: rate 25,000,000, 2 g, wage 10%, profit 7%, tax 9% → 50,000,000 + 5,000,000 + 3,850,000 + 796,500 = **59,646,500**.

Number parsing handles `1.22 گرم`, `6%`, `10.5%`, `.5`, `8,69`, Persian digits; unreadable input yields `null` (product skipped), never zero. `1,234` is read as thousands, `8,69` as a decimal comma.

### Shortcode
```
[one_gold_price]
[one_gold_price field="high" unit="rial" digits="en" thousands="no" label="no"]
[one_gold_price symbol="sekkeh" field="value"]
[one_gold_price field="updated" date_format="Y/m/d H:i"]
```
Parameters: `symbol`, `field` (`value`, `change_val`, `change_pct`, `high`, `low`, `updated`), `unit` (`toman`|`rial`), `thousands`, `digits` (`fa`|`en`), `label`, `decimals`, `date_format`. The source has no daily high/low, so the plugin records the min/max of its own fetches per day (Tehran time).

### REST
`GET /wp-json/one-navasan/v1/rate?symbol=18ayar&unit=toman` — public, read-only, cached 60 s, exposes no settings or keys. Example response is in the Persian section.

### Light script
Any element with `data-one-gold="value|change_val|change_pct|high|low|updated"` is filled with the current rate; inputs get their value set and `input`/`change` events. Options: `data-one-symbol`, `data-one-unit`, `data-one-format="plain"`, `data-one-digits`. Can be turned off in settings.

### Elementor Pro
Two Dynamic Tags (text and number categories). Nothing happens, and no error is raised, if Elementor is missing.

### Hooks
Filters: `one_navasan_final_rate( $toman, $settings )`, `one_navasan_product_price( $price, $product, $breakdown )`. Actions: `one_navasan_rate_changed( $new, $old )`, `one_navasan_prices_applied( $counts )`.

### Real cron
Action Scheduler is used when available, otherwise WP-Cron, **which depends on site visits**. For reliable timing:
```php
define( 'DISABLE_WP_CRON', true ); // wp-config.php
```
```
*/5 * * * * wget -q -O /dev/null "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
# or with WP-CLI
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now >/dev/null 2>&1
```

### Limitations
- Freshness depends on the source (~30 minutes, sometimes hours behind).
- Simple and variable products only. The plugin never sets sale prices; it only clears one that is ≥ the new regular price.
- Dates use `wp_date` (no built-in Jalali calendar).
- Uninstalling removes options, meta and schedules but **does not revert product prices**.

### Decisions
See the Persian "تصمیم‌ها" list: fixed rate adjustment is in Toman and applied before unit conversion; the base symbol shows the effective (manual/adjusted) rate; value precedence is product field (variation → parent) → attribute/WC field → global default; unreadable weight/karat/wage skips the product instead of guessing; defaults are profit 0, wage 0, tax 9, karat 18; the jump guard holds both directions and needs admin approval; automatic recalculation runs only after the first preview + apply and restarts if settings change mid-run; 50 products per step.

### Development and testing
```
composer install
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite live
powershell -File tests/integration/run-integration.ps1   # real WordPress via WordPress Playground, no Docker
```

### License
GPL-2.0-or-later, see [`LICENSE`](LICENSE). Navasan-API is credited in [`NOTICE`](NOTICE).
