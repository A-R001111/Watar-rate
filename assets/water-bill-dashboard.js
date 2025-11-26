(function () {
    // جمع‌آوری تمام نمونه‌های داشبورد روی صفحه
    const containers = document.querySelectorAll('.water-bill-dashboard');
    // در صورت نبود داشبورد یا داده محلی، خروج سریع
    if (!containers.length || typeof WaterBillDashboard === 'undefined') return;

    // پیمایش هر داشبورد مستقل
    containers.forEach((container) => {
        // آیتم‌های منو برای کنترل نماها
        const menuItems = container.querySelectorAll('.water-bill-dashboard__menu-item');
        // بخش محتوای مرکزی برای تزریق HTML
        const contentBody = container.querySelector('.water-bill-dashboard__content-body');
        // لودر تصویری هنگام فراخوانی AJAX
        const loader = container.querySelector('.water-bill-dashboard__loader');

        // فعال‌سازی آیتم انتخاب‌شده و غیرفعال‌سازی سایرین
        const setActive = (target) => {
            // حذف کلاس فعال از همه آیتم‌ها
            menuItems.forEach((item) => item.classList.remove('is-active'));
            // افزودن کلاس فعال به آیتم هدف
            target.classList.add('is-active');
        };

        // کنترل نمایش یا مخفی‌سازی لودر بر اساس وضعیت
        const toggleLoader = (visible) => {
            // اگر المان لودر موجود نبود تابع متوقف شود
            if (!loader) return;
            // hidden معکوس می‌شود تا بر اساس پارامتر دیده شود
            loader.hidden = !visible;
        };

        // رندر کردن محتوای هر نما با فراخوانی AJAX
        const renderView = (view) => {
            // نمایش لودر قبل از ارسال درخواست
            toggleLoader(true);
            // ارسال درخواست POST به AJAX وردپرس
            fetch(WaterBillDashboard.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({
                    // نام اکشن ثبت‌شده در PHP
                    action: 'water_bill_dashboard_get_view',
                    // شناسه نمای انتخاب‌شده
                    view,
                    // ارسال نانس جهت ایمنی درخواست
                    nonce: WaterBillDashboard.nonce,
                }),
                // استفاده از session کوکی فعلی
                credentials: 'same-origin',
            })
                // تبدیل پاسخ به JSON پس از دریافت
                .then((res) => res.json())
                .then((response) => {
                    // در صورت خطا، استثنا پرتاب می‌شود تا بخش catch اجرا شود
                    if (!response.success) {
                        throw new Error(response.data?.message || 'خطا در بارگذاری محتوا');
                    }
                    // در صورت موفقیت، HTML بازگشتی در محتوا تزریق می‌شود
                    contentBody.innerHTML = response.data.html;
                })
                .catch((error) => {
                    // نمایش پیام خطا برای کاربر به‌صورت بصری
                    contentBody.innerHTML = `<div class="water-bill-dashboard__notice">${error.message}</div>`;
                })
                .finally(() => toggleLoader(false));
        };

        // افزودن رویداد کلیک به هر آیتم منو
        menuItems.forEach((item, index) => {
            // واکنش به کلیک برای تعویض نما
            item.addEventListener('click', () => {
                // تنظیم آیتم فعال
                setActive(item);
                // استخراج شناسه نما از ویژگی داده
                const view = item.getAttribute('data-view');
                // رندر نمای انتخاب‌شده
                renderView(view);
            });

            // بارگذاری خودکار اولین آیتم برای نمایش اولیه
            if (index === 0) {
                setActive(item);
                renderView(item.getAttribute('data-view'));
            }
        });
    });
})();