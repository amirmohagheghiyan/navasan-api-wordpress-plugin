# Navasan-API plugin for WordPress

[فارسی](#فارسی) · [English](#english)

---

<div dir="rtl">

## فارسی

افزونهٔ وردپرس برای گرفتن نرخ لحظه‌ای طلا، نمایش آن در سایت (شورت‌کد، REST، اسکریپت سبک، المنتور) و قیمت‌گذاری خودکار محصولات ووکامرس بر اساس **وزن، عیار، اجرت، سود و مالیات**.

> ### منبع داده / Powered by priceto.day and Navasan-API
> نرخ از **یکی از دو منبع** (به انتخاب شما) یا به‌صورت دستی گرفته می‌شود:
>
> - **[priceto.day](https://priceto.day)** — پروژهٔ [margani/pricedb](https://github.com/margani/pricedb) ساختهٔ **Hossein Margani** (لایسنس MIT). داده را هر ۳۰ دقیقه در ساعات بازار از **[tgju.org](https://www.tgju.org)** می‌گیرد. API عمومی و بدون کلید دارد.
> - **[Navasan-API](https://github.com/HosseinOdd/Navasan-API)** — ساختهٔ **Hossein** ([HosseinOdd](https://github.com/HosseinOdd)). با GitHub Actions نرخ‌های **navasan.net** را در فایل `data/gold.json` می‌نویسد.
>
> این افزونه **فقط API و فایل JSON آن‌ها را می‌خواند**؛ هیچ کدی از آن‌ها کپی نشده است. جزئیات اعتبار و لایسنس هر دو: فایل [`NOTICE`](NOTICE).

### سلب مسئولیت
داده از دو پروژهٔ شخصی و رایگان و **بدون هیچ تضمینی** می‌آید که خودشان از tgju.org و navasan.net می‌گیرند. مسئولیت درستی قیمت‌ها و رعایت شرایط استفادهٔ منبع‌های اصلی با مدیر سایت است. این سلب مسئولیت دربارهٔ هر دو منبع صدق می‌کند.

### نیازمندی‌ها
وردپرس ۶٫۴+، PHP 7.4+. ووکامرس اختیاری است: بدون آن بخش نرخ، شورت‌کد و REST کار می‌کند و بخش قیمت‌گذاری محصول فعال نمی‌شود.

### نصب
1. فایل zip افزونه را از بخش [Releases](https://github.com/amirmohagheghiyan/navasan-api-wordpress-plugin/releases) بگیرید.
2. در وردپرس: «افزونه‌ها ← افزودن ← بارگذاری افزونه» و سپس فعال‌سازی. (برای ارتقا از نسخهٔ قبلی همین zip را روی آن نصب کنید؛ بخش «ارتقا» را ببینید.)
3. صفحهٔ تنظیمات: **ووکامرس ← نرخ طلا (Navasan-API)** (بدون ووکامرس: **تنظیمات ← نرخ طلا**).
4. «منبع نرخ» و «منبع پشتیبان» را انتخاب کنید. «تست اتصال» را بزنید (سرور بعضی هاست‌های ایران به گیت‌هاب یا Cloudflare دسترسی ندارد؛ نتیجهٔ هر مسیر از هر دو منبع جدا نشان داده می‌شود)، بعد «دریافت همین حالا».
5. برای قیمت‌گذاری محصولات: تنظیمات را کامل کنید، «پیش‌نمایش قیمت‌ها» را ببینید و بعد «اعمال قیمت‌ها» را بزنید. **تا این کار انجام نشود افزونه به قیمت هیچ محصولی دست نمی‌زند.**

### دو منبع، یک نگاه
| | priceto.day | Navasan-API |
|---|---|---|
| ساخته | Hossein Margani (`margani/pricedb`، MIT) | Hossein (`HosseinOdd/Navasan-API`) |
| داده از | tgju.org | navasan.net |
| واحد خودِ منبع | **ریال** (افزونه به تومان تبدیل می‌کند) | **تومان** |
| تازگی | هر ۳۰ دقیقه در ساعات بازار؛ جمعه‌ها و بعدازظهر پنجشنبه تغییر نمی‌کند | فایل ظاهراً هر ۳۰ دقیقه، ولی در عمل گاهی چند ساعت عقب می‌ماند |
| بیشترین/کمترین روز | **خودش می‌دهد** | نمی‌دهد؛ افزونه از دریافت‌های خودش نگه می‌دارد |
| مقدار/درصد تغییر روز | از مسیر `/v1/change` (یک درخواست اضافه، قابل خاموش شدن) | در فایل هست |
| نماد پایهٔ پیش‌فرض | `gold-18k` | `18ayar` |
| مسیرها | `https://api.priceto.day/v1/latest/irr/gold-18k` (+ `/v1/change/...`، `/v1/symbols` برای فهرست نمادها) | فایل `raw.githubusercontent.com` ← GitHub Contents API ← jsDelivr |
| کلید | ندارد | ندارد (توکن گیت‌هاب اختیاری) |

### منبع اصلی و پشتیبان
- **منبع نرخ**: `priceto.day`، `Navasan-API` یا `دستی`. در نصب تازه پیش‌فرض priceto.day است. «دستی» جای تنظیم قدیمی «حالت نرخ» را می‌گیرد و در آن هیچ دریافتی انجام نمی‌شود.
- **منبع پشتیبان**: هیچ‌کدام یا منبع خودکار دیگر (در نصب تازه Navasan-API). پشتیبان فقط وقتی استفاده می‌شود که منبع اصلی شکست بخورد **و** زمان دادهٔ پشتیبان از زمان دادهٔ آخرین نرخ سالم **جدیدتر** باشد. دلیل: Navasan-API گاهی چند ساعت عقب است و نباید یک نرخ کهنه جای نرخ تازه‌تر بنشیند. اگر پشتیبان نرخ را داد، صفحهٔ وضعیت با رنگ هشدار می‌گوید نرخ فعلی از پشتیبان آمده.
- نماد پایه برای هر منبع جدا تنظیم می‌شود (فهرست نمادهای priceto.day از `/v1/symbols` با یک دکمه تازه می‌شود)؛ عیار پایه برای هر دو یکی است. شورت‌کد یا اسکریپتی که با نماد پایهٔ هر دو منبع (`18ayar` یا `gold-18k`) نوشته شده باشد همیشه نرخ پایه را نشان می‌دهد.
- نمادهای دیگر (مثلاً `sekkeh`) فقط وقتی هست که نرخ فعلی از Navasan-API آمده باشد؛ priceto.day فقط نماد پایه را می‌گیرد (برای کم نگه داشتن درخواست‌ها).

### درخواست‌ها و شکست
- در هر دور فقط به منبع اصلی درخواست می‌رود؛ priceto.day دو درخواست (قیمت + تغییر روز، دومی اختیاری و هیچ‌وقت دریافت را خراب نمی‌کند) و Navasan-API یک درخواست. پشتیبان فقط بعد از شکست اصلی.
- مهلت هر درخواست **۶ ثانیه** است. مسیری که با خطای شبکه/تایم‌اوت یا ۵xx/۴۲۹ شکست بخورد **۳۰ دقیقه کنار گذاشته می‌شود** تا هر بار همان تأخیر تکرار نشود؛ یعنی `raw.githubusercontent.com` اگر تایم‌اوت داد، تا ۳۰ دقیقه مستقیم سراغ GitHub Contents API می‌رویم. اگر همهٔ مسیرهای یک منبع کنار گذاشته باشند، دوباره همه امتحان می‌شوند. «تست اتصال» و «دریافت همین حالا» همیشه امتحان می‌کنند.
- هر درخواست `User-Agent: One-Navasan-WordPress/<نسخه> (+آدرس مخزن)` می‌فرستد. این سرویس‌ها رایگان و شخصی‌اند؛ فاصلهٔ کمتر از ۳۰ دقیقه فایده‌ای ندارد.
- هیچ درخواستی به جز آدرس‌های همین دو منبع نمی‌رود (`api.priceto.day`؛ و برای Navasan-API آدرس تنظیم‌شده/`raw.githubusercontent.com`، `api.github.com`، `cdn.jsdelivr.net`). نه آمار، نه بررسی لایسنس.
- آدرس‌های پشتیبان Navasan-API (GitHub Contents API و jsDelivr) فقط وقتی استفاده می‌شوند که آدرس منبع همان پیش‌فرض باشد. jsDelivr شاخه را چند ساعت کش می‌کند، پس آخرین چاره است و اگر `date` آن از آخرین نرخ ذخیره‌شده قدیمی‌تر باشد نادیده گرفته می‌شود.

### محافظ‌ها
- **جهش**: نرخ جدیدی که بیش از حد مجاز (پیش‌فرض ۱۰٪، هر دو جهت) با نرخ قبلی فرق کند اعمال نمی‌شود، اعلان مدیر می‌آید و دکمهٔ «تأیید و اعمال» هست. **عوض کردن منبع هم می‌تواند جهش بسازد** (دو منبع چند درصد با هم فرق دارند)؛ همان روال اجرا می‌شود و پیام می‌گوید علت «تغییر منبع» است و نرخ از کدام منبع آمده.
- **دادهٔ کهنه**: پیش‌فرض ۶ ساعت. فقط **ساعت‌های باز بودن بازار** حساب می‌شود، تا شب‌ها، جمعه‌ها و عصر پنجشنبه هشدار بی‌دلیل روشن نشود. هشدار فقط اعلان است و قیمت‌ها دست نمی‌خورند.
- شکست دریافت یا پاسخ نامعتبر (JSON خراب، `success:false`، قیمت صفر یا منفی، زمان نامعتبر) نرخ سالم قبلی را نگه می‌دارد.

### تنظیمات
| بخش | تنظیم | توضیح |
|---|---|---|
| منبع | منبع نرخ / منبع پشتیبان | بخش بالا. فیلدهای بی‌ربط به منبع انتخاب‌شده کم‌رنگ می‌شوند |
| منبع | نماد پایه در priceto.day | فهرست انتخابی؛ پیش‌فرض `gold-18k` |
| منبع | تغییر روز از priceto.day | یک درخواست اضافه برای مقدار و درصد تغییر؛ خاموش = این دو فیلد خالی می‌ماند |
| منبع | آدرس منبع / کلید API / نماد پایه (Navasan-API) | پیش‌فرض آدرس `gold.json`؛ `{key}` در آدرس جایگزین کلید می‌شود. کلید فقط به مسیرهای Navasan-API می‌رود، پوشیده نمایش داده می‌شود و در لاگ نمی‌آید؛ توکن گیت‌هاب فقط به هاست‌های گیت‌هاب، هرگز به jsDelivr |
| منبع | عیار پایه و فاصلهٔ به‌روزرسانی | پیش‌فرض ۱۸ و ۳۰ دقیقه، حداقل ۵ |
| نرخ | قیمت دستی هر گرم | به واحد ارز فروشگاه؛ فقط وقتی «منبع نرخ» دستی است |
| نرخ | تعدیل | بدون / درصد / مبلغ ثابت (تومان)، مثبت یا منفی، روی نرخ خودکار (هر دو منبع) |
| محافظ | جهش مجاز و دادهٔ کهنه | ۱۰٪ و ۶ ساعت بازار |
| پول | واحد | خودکار (`IRT`=همان عدد، `IRR`=×۱۰) / تومان / ریال |
| گرد کردن | گام و جهت | بدون، ۵۰، ۱۰۰، ۵۰۰، ۱۰۰۰، ۵۰۰۰، ۱۰۰۰۰ یا دلخواه؛ نزدیک‌ترین / بالا / پایین؛ فقط روی قیمت نهایی |
| محصول | سود، مالیات (۹)، اجرت پیش‌فرض | درصد |
| محصول | منبع وزن و واحد | فیلد وزن ووکامرس یا ویژگی؛ واحد گرم/کیلوگرم **جدا از** واحد وزن ووکامرس |
| محصول | منبع اجرت / عیار | پیش‌فرض سراسری، ویژگی یا فیلد محصول |
| محصول | دامنهٔ اعمال | همهٔ محصولات، دسته‌های انتخابی، یا فقط محصولات تیک‌دار |

هر محصول (و هر متغیر) تب «قیمت‌گذاری با نرخ طلا» دارد: روشن/خاموش، وزن، عیار، اجرت (درصد یا ثابت)، سود، مبلغ اضافه. **فیلد خالی = از تنظیم سراسری یا ویژگی بخوان.** قیمت محاسبه‌شده و ریز آن کنار فیلدها نشان داده می‌شود.

### صفحهٔ وضعیت
نرخ فعلی و **منبعی که از آن آمده** (priceto.day، Navasan-API یا دستی؛ اگر از پشتیبان آمده با رنگ هشدار)، زمان آخرین دریافت، زمان خودِ داده و علامت کهنگی، اجرای بعدی، آخرین خطا، نرخ در انتظار تأیید (با علت)، وضعیت قیمت‌گذاری محصولات و ۲۰ خط آخر لاگ. «تست اتصال» جدولی با ستون **منبع**، مسیر، نتیجه، HTTP، زمان، نرخ (تومان) و زمان داده برای همهٔ مسیرهای هر دو منبع نشان می‌دهد.

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
بیشترین و کمترین امروز: اگر منبع فعال خودش بدهد (priceto.day) همان؛ وگرنه خود افزونه از دریافت‌هایش (به وقت تهران) نگه می‌دارد. شکل خروجی با نسخهٔ ۱٫۰ یکی است.

### REST
`GET /wp-json/one-navasan/v1/rate?symbol=gold-18k&unit=toman` (عمومی، فقط‌خواندنی، کش ۶۰ ثانیه، بدون هیچ تنظیم یا کلیدی). از نسخهٔ ۱٫۱ فقط فیلد `source` (`priceto`، `navasan` یا `manual`) اضافه شده است:
```json
{"symbol":"gold-18k","unit":"toman","mode":"auto","source":"priceto","value":25670800,"change_val":2281000,
 "change_pct":9.7236,"high":25821600,"low":24088100,"updated":1795523993,"fetched_at":1795524100,"stale":false}
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

### ارتقا از ۱٫۰٫۰
zip نسخهٔ ۱٫۱ را مثل هر افزونه روی نسخهٔ قبلی نصب کنید (slug، نام پوشه، text domain، کلید آپشن‌ها و متاها و نام شورت‌کد عوض نشده‌اند).
- همهٔ تنظیمات حفظ می‌شود. `حالت نرخ = خودکار` به «منبع: Navasan-API» و `دستی` به «منبع: دستی» تبدیل می‌شود، پشتیبان «هیچ‌کدام» است. **ارتقا خودش منبع را عوض نمی‌کند؛** منبع را خودتان در تنظیمات عوض کنید.
- وضعیت «پیش‌نمایش دیده و اعمال زده شده»، آخرین نرخ سالم، لاگ، بیشترین/کمترین روز و برنامهٔ زمان‌بندی حفظ می‌شود. **ارتقا به قیمت هیچ محصولی دست نمی‌زند** و بازمحاسبه‌ای شروع نمی‌کند.
- یک اعلان یک‌باره به مدیر می‌گوید منبع جدید اضافه شده و از کجا انتخاب می‌شود.
- پس از انتخاب منبع جدید، یک دریافت فوری انجام می‌شود. اگر نرخ تازه بیش از حد مجاز با نرخ فعلی فرق داشته باشد (مثلاً Navasan-API چند ساعت عقب بوده)، تا تأیید شما اعمال نمی‌شود؛ بعد از تأیید، بازمحاسبهٔ قیمت‌ها در پس‌زمینه انجام می‌شود.

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
- دقت و تازگی نرخ به منبع بستگی دارد (priceto.day حدود ۳۰ دقیقه در ساعات بازار؛ Navasan-API گاهی چند ساعت عقب).
- priceto.day فقط نماد پایه را می‌دهد؛ نمادهای دیگر شورت‌کد فقط با Navasan-API کار می‌کنند.
- فقط محصولات ساده و متغیر قیمت‌گذاری می‌شوند. قیمت حراج را افزونه عوض نمی‌کند؛ فقط اگر بعد از محاسبه از قیمت اصلی بیشتر یا مساوی شد پاک می‌شود.
- نوع محصول گروه‌بندی‌شده و خارجی نادیده گرفته می‌شود.
- تاریخ شمسی تولید نمی‌شود (از `wp_date` استفاده می‌شود؛ با افزونهٔ تاریخ شمسی سازگار با `wp_date` شمسی می‌شود).
- حذف افزونه گزینه‌ها، متاها و زمان‌بندی‌ها را پاک می‌کند ولی **قیمت محصولات را برنمی‌گرداند**.

### تصمیم‌ها
- تعدیل «مبلغ ثابت» نرخ خودکار به **تومان** است و قبل از تبدیل واحد اعمال می‌شود؛ قیمت دستی به واحد فروشگاه است. تبدیل ریال به تومان (priceto.day) فقط داخل کلاس provider همان منبع انجام می‌شود.
- شورت‌کد/REST/اسکریپت برای نماد پایه همان نرخ مؤثر (دستی یا تعدیل‌شده) را نشان می‌دهند؛ برای نمادهای دیگر نرخ خام منبع. بیشترین/کمترین پایه با همان تعدیل نمایش داده می‌شود؛ در حالت دستی برابر همان نرخ است.
- اولویت منبع هر مقدار: فیلد خود محصول (متغیر ← والد) ← ویژگی/فیلد وزن طبق تنظیم ← پیش‌فرض سراسری. وزن معتبر الزامی است؛ ویژگی ناخوانا (وزن، عیار، اجرت) یعنی محصول ردشود، نه مقدار پیش‌فرض.
- پیش‌فرض‌ها: سود ۰، اجرت ۰، مالیات ۹، عیار ۱۸ (عدد دیگری حدس زده نشد).
- جهش بیش از حد مجاز (پیش‌فرض ۱۰٪) در هر دو جهت نگه داشته می‌شود؛ مقدار جدید تا تأیید مدیر ذخیره نمی‌شود. تغییر منبع هم همین‌طور.
- **کهنگی با ساعات بازار**: به‌جای پرسیدن از `/v1/health` (یک درخواست اضافه که فقط سلامت خود سرویس را می‌گوید و برای Navasan-API بی‌معناست)، برای هر دو منبع یک قاعدهٔ ساده و قابل‌توضیح به کار می‌رود: فقط ساعت‌های بازار حساب می‌شود — شنبه تا چهارشنبه ۰۸:۰۰–۲۲:۰۰، پنجشنبه ۰۸:۰۰–۱۴:۰۰، جمعه تعطیل، به وقت تهران (UTC+۰۳:۳۰، بدون ساعت تابستانی). ساعت‌ها در `One_Navasan_Market_Hours::windows()` است. اگر بازار واقعی ساعت دیگری کار کند، همین یک تابع را عوض کنید.
- منبع پشتیبان فقط با داده‌ای جدیدتر از آخرین نرخ سالم پذیرفته می‌شود؛ پیش‌فرض نصب تازه priceto.day اصلی و Navasan-API پشتیبان است، ولی ارتقا پشتیبان را «هیچ‌کدام» می‌گذارد تا خودش رفتاری را عوض نکند.
- نرخ ذخیره‌شده با نماد پایهٔ **خودِ داده** خوانده می‌شود، نه تنظیم فعلی؛ پس عوض کردن منبع در تنظیمات تا دریافت بعدی نرخ را خالی نمی‌کند.
- بازمحاسبهٔ خودکار فقط وقتی انجام می‌شود که نرخ مؤثر یا تنظیمات قیمت‌گذاری عوض شده باشد و مدیر یک بار پیش‌نمایش و اعمال را انجام داده باشد. اگر وسط کار تنظیمات عوض شود، کار از اول با ورودی جدید شروع می‌شود.
- ۵۰ محصول در هر مرحله پردازش می‌شود. دکمه‌های صفحه از AJAX مرحله‌به‌مرحله استفاده می‌کنند؛ تغییر خودکار نرخ از زمان‌بندی پس‌زمینه.
- اسکریپت سبک به‌صورت پیش‌فرض فعال است ولی اگر عنصری با `data-one-gold` نباشد کاری نمی‌کند.
- بیشترین/کمترین روزی که خود افزونه از دریافت‌هایش حساب می‌کند (برای منبعی که high/low نمی‌دهد) در آپشن ذخیره می‌شود و با تغییر روز (به وقت تهران) از نو شروع می‌شود.

### توسعه و تست
```
composer install
vendor/bin/phpunit --testsuite unit      # تست واحد (بدون وردپرس)
vendor/bin/phpunit --testsuite live      # خواندن آدرس واقعی هر دو منبع (بدون شبکه رد می‌شود)
powershell -File tests/integration/run-integration.ps1   # وردپرس واقعی با WordPress Playground، بدون Docker
powershell -File tests/integration/run-integration.ps1 -Modes upgrade,upgrade-manual   # سناریوی ارتقا از تگ v1.0.0
```

</div>

---

## English

A WordPress plugin that fetches the live gold rate, shows it on the site (shortcode, REST, light script, Elementor) and automatically prices WooCommerce products from **weight, karat, wage, profit and tax**.

> ### Data sources / Powered by priceto.day and Navasan-API
> The rate comes from **one of two sources** (your choice), or is entered by hand:
>
> - **[priceto.day](https://priceto.day)**: the [margani/pricedb](https://github.com/margani/pricedb) project by **Hossein Margani** (MIT). It collects the data from **[tgju.org](https://www.tgju.org)** every 30 minutes during market hours and serves a public, keyless API.
> - **[Navasan-API](https://github.com/HosseinOdd/Navasan-API)** by **Hossein** ([HosseinOdd](https://github.com/HosseinOdd)). A GitHub Actions workflow writes the **navasan.net** rates to `data/gold.json`.
>
> This plugin **only reads their API and JSON files**; no code from either project is included. See [`NOTICE`](NOTICE) for credit and license details of both.

### Disclaimer
The data comes from two free personal projects, with **no warranty**, which themselves take it from tgju.org and navasan.net. The site administrator is responsible for the correctness of the prices and for complying with the terms of use of the original sources. This applies to both sources.

### Requirements
WordPress 6.4+, PHP 7.4+. WooCommerce is optional: without it the rate, shortcodes and REST work and product pricing stays off.

### Install
1. Download the plugin zip from [Releases](https://github.com/amirmohagheghiyan/navasan-api-wordpress-plugin/releases).
2. WordPress: Plugins → Add New → Upload Plugin, then activate. (To upgrade, upload the new zip over the old version; see "Upgrading".)
3. Settings page: **WooCommerce → Gold rate (Navasan-API)** (without WooCommerce: **Settings → Gold rate**).
4. Choose the **rate source** and the **backup source**. Click **Test connection** (some Iranian hosts cannot reach GitHub or Cloudflare; every path of both sources is reported separately), then **Fetch now**.
5. For product pricing: finish the settings, run **Preview**, then **Apply**. **Until you do, the plugin never touches any product price.**

### The two sources at a glance
| | priceto.day | Navasan-API |
|---|---|---|
| Built by | Hossein Margani (`margani/pricedb`, MIT) | Hossein (`HosseinOdd/Navasan-API`) |
| Data from | tgju.org | navasan.net |
| Unit of the source | **Rial** (the plugin converts to Toman) | **Toman** |
| Freshness | every 30 min during market hours; no change on Fridays and after Thursday afternoon | nominally every 30 min, in practice sometimes hours behind |
| Daily high/low | **provided** | not provided; the plugin tracks it from its own fetches |
| Day change (value/%) | from `/v1/change` (one extra request, can be switched off) | in the file |
| Default base symbol | `gold-18k` | `18ayar` |
| Paths | `https://api.priceto.day/v1/latest/irr/gold-18k` (+ `/v1/change/...`, `/v1/symbols` for the symbol list) | `raw.githubusercontent.com` file → GitHub Contents API → jsDelivr |
| Key | none | none (optional GitHub token) |

### Primary and backup source
- **Rate source**: `priceto.day`, `Navasan-API` or `manual`. A fresh install defaults to priceto.day. "Manual" replaces the old "rate mode" setting and performs no fetching at all.
- **Backup source**: none, or the other automatic source (Navasan-API on a fresh install). The backup is used only when the primary fails **and** its data time is **newer** than the data time of the last healthy rate. Reason: Navasan-API is sometimes hours behind and an old rate must never replace a fresher one. When the backup delivered the rate, the status page says so in a warning colour.
- The base symbol is set per source (the priceto.day list can be refreshed from `/v1/symbols` with a button); the base karat is shared. A shortcode or script written with the base symbol of either source (`18ayar` or `gold-18k`) always shows the base rate.
- Other symbols (for example `sekkeh`) exist only while the current rate came from Navasan-API; priceto.day fetches the base symbol only, to keep requests minimal.

### Requests and failures
- Each cycle only contacts the primary source: two requests for priceto.day (price + day change; the second is optional and never fails a fetch), one for Navasan-API. The backup is contacted only after the primary failed.
- Every request has a **6 second** timeout. A path that fails with a network error/timeout or a 5xx/429 is **skipped for 30 minutes** so the same delay does not repeat; for example, if `raw.githubusercontent.com` times out, the plugin goes straight to the GitHub Contents API for 30 minutes. If every path of a source is cooling down, all of them are tried again. "Test connection" and "Fetch now" always try.
- Every request sends `User-Agent: One-Navasan-WordPress/<version> (+repository URL)`. These are free personal services; an interval below 30 minutes does not help.
- No request goes anywhere except the two sources (`api.priceto.day`; for Navasan-API the configured URL / `raw.githubusercontent.com`, `api.github.com`, `cdn.jsdelivr.net`): no telemetry, no license checks.
- The Navasan-API fallbacks (GitHub Contents API and jsDelivr) are used only while the source URL is the default one. jsDelivr caches the branch for hours, so it is the last resort and is ignored when its `date` is older than the stored rate.

### Guards
- **Jump**: a new rate that differs from the previous one by more than the limit (default 10%, both directions) is not applied; the admin gets a notice and an "Approve and apply" button. **Switching the source can itself create a jump** (the two sources differ by a few percent); the same hold applies and the message says the cause is the source change and which source the rate came from.
- **Stale data**: default 6 hours, counting **market-open hours only**, so nights, Fridays and Thursday afternoons do not raise a pointless warning. It is only a notice; prices are untouched.
- A failed fetch or an invalid answer (broken JSON, `success:false`, zero or negative price, invalid time) keeps the last healthy rate.

### Settings
See the Persian table above. In short: rate source and backup, base symbol per source, optional day change from priceto.day, Navasan-API URL (supports `{key}`) and optional key (sent only to Navasan-API paths; masked, never logged; a GitHub token is only sent to GitHub hosts), base karat, interval (min 5 minutes), manual per-gram price, rate adjustment (percent/fixed Toman, both sources), jump guard (10%), stale warning (6 market hours), currency unit (auto: `IRT` = same number, `IRR` = ×10), rounding step/direction (final price only), default profit/tax(9)/wage, weight/wage/karat sources (WooCommerce field, product attribute or product field), weight unit (g/kg, independent from the WooCommerce unit) and scope (all / categories / flagged products). Fields that do not belong to the selected source are dimmed.

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
Parameters: `symbol`, `field` (`value`, `change_val`, `change_pct`, `high`, `low`, `updated`), `unit` (`toman`|`rial`), `thousands`, `digits` (`fa`|`en`), `label`, `decimals`, `date_format`. Daily high/low: the active source's own values when it provides them (priceto.day); otherwise the min/max of the plugin's own fetches per day (Tehran time). The output shape is the same as in 1.0.

### REST
`GET /wp-json/one-navasan/v1/rate?symbol=gold-18k&unit=toman`: public, read-only, cached 60 s, exposes no settings or keys. 1.1 only adds a `source` field (`priceto`, `navasan` or `manual`); an example response is in the Persian section.

### Light script
Any element with `data-one-gold="value|change_val|change_pct|high|low|updated"` is filled with the current rate; inputs get their value set and `input`/`change` events. Options: `data-one-symbol`, `data-one-unit`, `data-one-format="plain"`, `data-one-digits`. Can be turned off in settings.

### Elementor Pro
Two Dynamic Tags (text and number categories). Nothing happens, and no error is raised, if Elementor is missing.

### Hooks
Filters: `one_navasan_final_rate( $toman, $settings )`, `one_navasan_product_price( $price, $product, $breakdown )`. Actions: `one_navasan_rate_changed( $new, $old )`, `one_navasan_prices_applied( $counts )`.

### Upgrading from 1.0.0
Upload the 1.1 zip over the old version like any plugin (slug, folder name, text domain, option and meta keys and the shortcode name are unchanged).
- All settings are kept. `rate mode = auto` becomes "source: Navasan-API" and `manual` becomes "source: manual"; the backup is "none". **The upgrade never changes the source by itself**; change it yourself on the settings page.
- The "preview seen and applied" state, the last healthy rate, the log, the daily high/low and the schedule are kept. **The upgrade touches no product price** and starts no recalculation.
- A one-time admin notice says a new source was added and where to choose it.
- After you choose the new source, an immediate fetch runs. If the new rate differs from the current one by more than the jump limit (for example because Navasan-API was hours behind), it waits for your approval; after approval, prices are recalculated in the background.

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
- Freshness depends on the source (priceto.day ~30 minutes during market hours; Navasan-API sometimes hours behind).
- priceto.day supplies the base symbol only; other shortcode symbols work only with Navasan-API.
- Simple and variable products only. The plugin never sets sale prices; it only clears one that is ≥ the new regular price.
- Dates use `wp_date` (no built-in Jalali calendar).
- Uninstalling removes options, meta and schedules but **does not revert product prices**.

### Decisions
See the Persian "تصمیم‌ها" list. Highlights: Rial→Toman conversion lives only inside the priceto.day provider; fixed rate adjustment is in Toman and applied before unit conversion; the base symbol shows the effective (manual/adjusted) rate; value precedence is product field (variation → parent) → attribute/WC field → global default; unreadable weight/karat/wage skips the product instead of guessing; defaults are profit 0, wage 0, tax 9, karat 18; the jump guard holds both directions, including source switches; **staleness counts market-open hours only** (Sat–Wed 08:00–22:00, Thu 08:00–14:00, Fri closed, Tehran time; the table is `One_Navasan_Market_Hours::windows()`) instead of calling `/v1/health`, because one rule then works for both sources and costs no request; a backup rate is only accepted when newer than the last healthy rate; a fresh install uses priceto.day with a Navasan-API backup but an upgrade keeps the old source and no backup; the stored rate is read by the base symbol of the data itself so switching the source never blanks the rate before the next fetch; automatic recalculation runs only after the first preview + apply and restarts if settings change mid-run; 50 products per step.

### Development and testing
```
composer install
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite live      # reads both real sources (skipped without network)
powershell -File tests/integration/run-integration.ps1   # real WordPress via WordPress Playground, no Docker
powershell -File tests/integration/run-integration.ps1 -Modes upgrade,upgrade-manual   # upgrade scenario from the v1.0.0 tag
```

### License
GPL-2.0-or-later, see [`LICENSE`](LICENSE). priceto.day (margani/pricedb, MIT) and Navasan-API are credited in [`NOTICE`](NOTICE).
