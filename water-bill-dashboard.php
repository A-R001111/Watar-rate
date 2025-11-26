<?php
/**
 * Plugin Name:       Water Bill Dashboard
 * Description:       داشبورد محاسبه قبض آب با نمایش واکنش‌گرا و منوی سمت راست.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Water Manager
 * Text Domain:       water-bill-dashboard
 */

// جلوگیری از اجرا شدن مستقیم فایل افزونه در مرورگر
if (!defined('ABSPATH')) {
    // خروج امن در صورت دسترسی مستقیم
    exit;
}

class Water_Bill_Dashboard {
    // ثابت nonce برای ایمن‌سازی درخواست‌های AJAX
    private const NONCE_ACTION = 'water_bill_dashboard_nonce';

    // سازنده برای رجیستر کردن هوک‌ها و شورت‌کد
    public function __construct() {
        // ثبت شورت‌کد برای نمایش داشبورد
        add_shortcode('water_bill_dashboard', [$this, 'render_shortcode']);
        // ثبت اسکریپت و استایل در صف وردپرس
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        // هندلر AJAX برای کاربران وارد شده
        add_action('wp_ajax_water_bill_dashboard_get_view', [$this, 'handle_ajax_view']);
        // هندلر AJAX برای مهمان (در صورت نیاز به ریدایرکت یا پیام)
        add_action('wp_ajax_nopriv_water_bill_dashboard_get_view', [$this, 'handle_ajax_view']);
    }

    // ثبت و آماده‌سازی فایل‌های استایل و اسکریپت
    public function register_assets(): void {
        // رجیستر کردن استایل اصلی افزونه
        wp_register_style(
            'water-bill-dashboard',
            plugins_url('assets/water-bill-dashboard.css', __FILE__),
            [],
            '1.0.0'
        );

        // رجیستر کردن اسکریپت اصلی افزونه با وابستگی به wp-i18n
        wp_register_script(
            'water-bill-dashboard',
            plugins_url('assets/water-bill-dashboard.js', __FILE__),
            ['wp-i18n'],
            '1.0.0',
            true
        );
    }

    // رندر شورت‌کد و خروجی HTML اصلی داشبورد
    public function render_shortcode(): string {
        // بررسی دسترسی کاربر فعلی
        if (!$this->current_user_allowed()) {
            // نمایش پیام خطا در صورت نداشتن مجوز
            return '<div class="water-bill-dashboard__notice">' . esc_html__('دسترسی مجاز نیست.', 'water-bill-dashboard') . '</div>';
        }

        // افزودن استایل و اسکریپت به صفحه جاری
        wp_enqueue_style('water-bill-dashboard');
        wp_enqueue_script('water-bill-dashboard');

        // ارسال داده‌های لازم به اسکریپت جاوااسکریپت
        wp_localize_script(
            'water-bill-dashboard',
            'WaterBillDashboard',
            [
                // آدرس AJAX پیشفرض وردپرس
                'ajaxUrl' => admin_url('admin-ajax.php'),
                // نانس امنیتی برای کنترل درخواست
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                // دیتاهای نمایشی منوها
                'views' => $this->get_views_data(),
            ]
        );

        // شروع بافر خروجی برای بازگرداندن HTML
        ob_start();
        ?>
        <div class="water-bill-dashboard" dir="rtl">
            <div class="water-bill-dashboard__inner">
                <aside class="water-bill-dashboard__menu" aria-label="Water bill navigation">
                    <div class="water-bill-dashboard__brand">
                        <span class="water-bill-dashboard__brand-icon">💧</span>
                        <div>
                            <div class="water-bill-dashboard__brand-title">سامانه قبوض آب</div>
                            <div class="water-bill-dashboard__brand-subtitle">مدیریت آنلاین</div>
                        </div>
                    </div>
                    <ul class="water-bill-dashboard__menu-list" role="tablist">
                        <?php foreach ($this->get_views_data() as $view): ?>
                            <li>
                                <button
                                    type="button"
                                    class="water-bill-dashboard__menu-item"
                                    data-view="<?php echo esc_attr($view['id']); ?>"
                                    role="tab"
                                    aria-controls="water-bill-dashboard__content"
                                >
                                    <span class="water-bill-dashboard__menu-icon" aria-hidden="true"><?php echo esc_html($view['icon']); ?></span>
                                    <span class="water-bill-dashboard__menu-text"><?php echo esc_html($view['label']); ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
                <section class="water-bill-dashboard__content" id="water-bill-dashboard__content" role="tabpanel">
                    <div class="water-bill-dashboard__loader" hidden>
                        <span class="water-bill-dashboard__spinner"></span>
                        <p>در حال بارگذاری...</p>
                    </div>
                    <div class="water-bill-dashboard__content-body" aria-live="polite"></div>
                </section>
            </div>
        </div>
        <?php
        // بازگرداندن خروجی تولید شده به عنوان شورت‌کد
        return ob_get_clean();
    }

    // هندل کردن درخواست‌های AJAX برای تغییر نما
    public function handle_ajax_view(): void {
        // اعتبارسنجی nonce امنیتی
        if (!wp_verify_nonce($_POST['nonce'] ?? '', self::NONCE_ACTION)) {
            // ارسال پاسخ خطا در صورت نامعتبر بودن نانس
            wp_send_json_error(['message' => __('درخواست ناامن است.', 'water-bill-dashboard')], 403);
        }

        // بررسی دسترسی کاربر
        if (!$this->current_user_allowed()) {
            // ارسال پاسخ خطا برای کاربران غیرمجاز
            wp_send_json_error(['message' => __('دسترسی مجاز نیست.', 'water-bill-dashboard')], 403);
        }

        // دریافت شناسه نمای درخواستی از POST و ایمن‌سازی آن
        $view = sanitize_key($_POST['view'] ?? '');
        // دریافت داده‌های نمای موجود
        $views = $this->get_views_data();

        // اعتبارسنجی وجود نمای درخواستی
        if (!isset($views[$view])) {
            // ارسال پیام خطا در صورت نبود نمای معتبر
            wp_send_json_error(['message' => __('نمای معتبر نیست.', 'water-bill-dashboard')], 400);
        }

        // بازگشت خروجی HTML نما و عنوان آن به صورت JSON موفق
        wp_send_json_success([
            'html' => $this->render_view($view),
            'title' => $views[$view]['label'],
        ]);
    }

    // بررسی مجوز نقش‌های کاربر جاری
    private function current_user_allowed(): bool {
        // دریافت اطلاعات کاربر فعلی
        $user = wp_get_current_user();
        // نقش‌های مجاز با قابلیت فیلتر برای توسعه‌دهندگان
        $allowed_roles = apply_filters('water_bill_dashboard_allowed_roles', ['administrator', 'water-manager']);
        // بررسی اشتراک نقش‌های مجاز با نقش‌های کاربر
        return (bool) array_intersect($allowed_roles, (array) $user->roles);
    }

    // تامین داده‌های منو و فرم‌های مرتبط
    private function get_views_data(): array {
        // آرایه پیشفرض نماها با شناسه، برچسب، آیکون و آیدی فرم گرavity Forms
        $views = [
            'register-bill' => [
                'id' => 'register-bill',
                'label' => __('ثبت قبض', 'water-bill-dashboard'),
                'icon' => '🧾',
                'form_id' => 1,
            ],
            'bill-history' => [
                'id' => 'bill-history',
                'label' => __('تاریخچه قبض', 'water-bill-dashboard'),
                'icon' => '📜',
                'form_id' => 2,
            ],
            'new-subscriber' => [
                'id' => 'new-subscriber',
                'label' => __('ثبت مشترک جدید', 'water-bill-dashboard'),
                'icon' => '🧑‍🤝‍🧑',
                'form_id' => 3,
            ],
            'subscriber-info' => [
                'id' => 'subscriber-info',
                'label' => __('اطلاعات مشترکین', 'water-bill-dashboard'),
                'icon' => '📇',
                'form_id' => 4,
            ],
            'send-notice' => [
                'id' => 'send-notice',
                'label' => __('ارسال اطلاعیه', 'water-bill-dashboard'),
                'icon' => '📢',
                'form_id' => 5,
            ],
        ];

        // اجازه ویرایش لیست نماها توسط توسعه‌دهندگان دیگر
        return apply_filters('water_bill_dashboard_views', $views);
    }

    // رندر کردن بدنه هر نمای انتخاب شده
    private function render_view(string $view): string {
        // دریافت داده‌های نما برای دسترسی به برچسب و فرم مربوطه
        $views = $this->get_views_data();
        // استخراج آیدی فرم برای ارسال به شورت‌کد گرویتی فرم
        $form_id = $views[$view]['form_id'] ?? null;
        // توضیحات کمکی برای هر نما جهت نمایش در هدر
        $description = [
            'register-bill' => __('فرم ثبت قبض جدید را تکمیل کنید.', 'water-bill-dashboard'),
            'bill-history' => __('مشاهده سوابق و وضعیت پرداخت قبض‌ها.', 'water-bill-dashboard'),
            'new-subscriber' => __('افزودن مشترک جدید به سامانه.', 'water-bill-dashboard'),
            'subscriber-info' => __('جستجو و مشاهده اطلاعات مشترکین.', 'water-bill-dashboard'),
            'send-notice' => __('ارسال پیام و اطلاعیه به مشترکین انتخاب‌شده.', 'water-bill-dashboard'),
        ];

        // شروع بافر خروجی برای رندر HTML نما
        ob_start();
        ?>
        <div class="water-bill-dashboard__panel">
            <header class="water-bill-dashboard__panel-header">
                <div>
                    <p class="water-bill-dashboard__eyebrow">سامانه آب</p>
                    <h2 class="water-bill-dashboard__panel-title"><?php echo esc_html($views[$view]['label']); ?></h2>
                    <p class="water-bill-dashboard__panel-desc"><?php echo esc_html($description[$view] ?? ''); ?></p>
                </div>
                <div class="water-bill-dashboard__badge">Ajax</div>
            </header>
            <div class="water-bill-dashboard__panel-body">
                <?php if ($form_id) : ?>
                    <div class="water-bill-dashboard__form">
                        <?php echo do_shortcode('[gravityform id="' . intval($form_id) . '" title="false" description="false" ajax="true"]'); ?>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e('فرم برای این بخش پیکربندی نشده است.', 'water-bill-dashboard'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        // بازگردانی خروجی کامل شده
        return ob_get_clean();
    }
}

// نمونه‌سازی کلاس برای فعال شدن هوک‌ها
new Water_Bill_Dashboard();
