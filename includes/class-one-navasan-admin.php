<?php
/**
 * Admin settings page, AJAX tools and notices.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
class One_Navasan_Admin {

	const SLUG  = 'one-navasan';
	const GROUP = 'one_navasan_group';
	const NONCE = 'one_navasan_admin';
	const RUN   = 'one_navasan_ui_run';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( 'One_Navasan_Settings', 'capability' ) );
		foreach ( array( 'fetch', 'test', 'chunk', 'approve' ) as $action ) {
			add_action( 'wp_ajax_one_navasan_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
	}

	/**
	 * Menu entry: under WooCommerce when active, otherwise under Settings.
	 */
	public static function menu() {
		$cap   = One_Navasan_Settings::capability();
		$title = __( 'نرخ طلا (Navasan-API)', ONE_NAVASAN_DOMAIN );
		if ( One_Navasan_Settings::wc_active() ) {
			add_submenu_page( 'woocommerce', $title, $title, $cap, self::SLUG, array( __CLASS__, 'render' ) );
		} else {
			add_options_page( $title, $title, $cap, self::SLUG, array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Register the setting.
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			One_Navasan_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'One_Navasan_Settings', 'sanitize' ),
				'default'           => One_Navasan_Settings::defaults(),
			)
		);
	}

	/**
	 * Is the current screen our page?
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	private static function is_our_page( $hook ) {
		return is_string( $hook ) && false !== strpos( $hook, self::SLUG );
	}

	/**
	 * Enqueue assets on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( ! self::is_our_page( $hook ) ) {
			return;
		}
		wp_enqueue_style( 'one-navasan-admin', ONE_NAVASAN_URL . 'assets/css/one-navasan-admin.css', array(), ONE_NAVASAN_VERSION );
		wp_enqueue_script( 'one-navasan-admin', ONE_NAVASAN_URL . 'assets/js/one-navasan-admin.js', array(), ONE_NAVASAN_VERSION, true );
		wp_localize_script(
			'one-navasan-admin',
			'oneNavasanAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => array(
					'working'   => __( 'در حال انجام…', ONE_NAVASAN_DOMAIN ),
					'error'     => __( 'خطا در ارتباط با سرور.', ONE_NAVASAN_DOMAIN ),
					'progress'  => __( 'محصولات بررسی‌شده:', ONE_NAVASAN_DOMAIN ),
					'previewOk' => __( 'پیش‌نمایش کامل شد. اگر قیمت‌ها درست است «اعمال قیمت‌ها» را بزنید.', ONE_NAVASAN_DOMAIN ),
					'applyOk'   => __( 'قیمت‌ها اعمال شد.', ONE_NAVASAN_DOMAIN ),
					'updated'   => __( 'قیمت‌های تغییرکرده:', ONE_NAVASAN_DOMAIN ),
					'unchanged' => __( 'بدون تغییر:', ONE_NAVASAN_DOMAIN ),
					'skipped'   => __( 'ردشده:', ONE_NAVASAN_DOMAIN ),
					'confirm'   => __( 'قیمت ذخیره‌شدهٔ محصولات تغییر می‌کند. ادامه می‌دهید؟', ONE_NAVASAN_DOMAIN ),
					'colName'   => __( 'محصول', ONE_NAVASAN_DOMAIN ),
					'colOld'    => __( 'قیمت فعلی', ONE_NAVASAN_DOMAIN ),
					'colNew'    => __( 'قیمت جدید', ONE_NAVASAN_DOMAIN ),
					'colDetail' => __( 'ریز محاسبه', ONE_NAVASAN_DOMAIN ),
					'sample'    => __( 'نمونه‌های ردشده:', ONE_NAVASAN_DOMAIN ),
				),
			)
		);
	}

	/**
	 * Admin notices (pending rate / stale data).
	 */
	public static function notices() {
		if ( ! current_user_can( One_Navasan_Settings::capability() ) ) {
			return;
		}
		$url = esc_url( self::page_url() );
		$pending = One_Navasan_Rate_Service::pending();
		if ( $pending ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html(
					sprintf(
						/* translators: %s: percent. */
						__( 'نرخ تازهٔ طلا %s درصد با نرخ قبلی فرق دارد و اعمال نشد.', ONE_NAVASAN_DOMAIN ),
						number_format_i18n( (float) $pending['pct'], 2 )
					)
				),
				$url,
				esc_html__( 'بررسی و تأیید', ONE_NAVASAN_DOMAIN )
			);
		}
		if ( One_Navasan_Rate_Service::is_stale() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html(
					sprintf(
						/* translators: %s: hours. */
						__( 'دادهٔ نرخ طلا بیش از %s ساعت قدیمی است. قیمت‌ها تغییری نکرده‌اند.', ONE_NAVASAN_DOMAIN ),
						number_format_i18n( (float) One_Navasan_Settings::get( 'stale_hours' ) )
					)
				),
				$url,
				esc_html__( 'وضعیت', ONE_NAVASAN_DOMAIN )
			);
		}
	}

	/**
	 * URL of our page.
	 *
	 * @return string
	 */
	public static function page_url() {
		$base = One_Navasan_Settings::wc_active() ? 'admin.php' : 'options-general.php';
		return admin_url( $base . '?page=' . self::SLUG );
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Common AJAX guard.
	 */
	private static function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( One_Navasan_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', ONE_NAVASAN_DOMAIN ) ), 403 );
		}
	}

	/**
	 * Fetch now.
	 */
	public static function ajax_fetch() {
		self::guard();
		$r = One_Navasan_Rate_Service::refresh();
		wp_send_json_success( array( 'ok' => $r['ok'], 'message' => $r['message'] ) );
	}

	/**
	 * Connection test (each source separately).
	 */
	public static function ajax_test() {
		self::guard();
		$html = '<table class="widefat striped one-table"><thead><tr><th>' . esc_html__( 'منبع', ONE_NAVASAN_DOMAIN ) . '</th><th>' . esc_html__( 'نتیجه', ONE_NAVASAN_DOMAIN ) . '</th><th>HTTP</th><th>' . esc_html__( 'زمان', ONE_NAVASAN_DOMAIN ) . '</th><th>' . esc_html__( 'نرخ نماد پایه', ONE_NAVASAN_DOMAIN ) . '</th><th>' . esc_html__( 'زمان داده', ONE_NAVASAN_DOMAIN ) . '</th></tr></thead><tbody>';
		foreach ( One_Navasan_Fetcher::test_all() as $row ) {
			$html .= '<tr><td>' . esc_html( $row['label'] ) . '<br><code class="one-url">' . esc_html( $row['url'] ) . '</code></td>'
				. '<td class="' . ( $row['ok'] ? 'one-ok' : 'one-bad' ) . '">' . ( $row['ok'] ? esc_html__( 'موفق', ONE_NAVASAN_DOMAIN ) : esc_html__( 'ناموفق', ONE_NAVASAN_DOMAIN ) . ': ' . esc_html( $row['error'] ) ) . '</td>'
				. '<td>' . esc_html( $row['http'] ? $row['http'] : '—' ) . '</td>'
				. '<td>' . esc_html( $row['ms'] ) . ' ms</td>'
				. '<td>' . esc_html( null === $row['value'] ? '—' : One_Navasan_Number_Formatter::format( $row['value'], true, 'en' ) ) . '</td>'
				. '<td>' . esc_html( $row['date'] ? wp_date( 'Y/m/d H:i:s', (int) $row['date'] ) : '—' ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( One_Navasan_Settings::DEFAULT_URL !== One_Navasan_Settings::get( 'source_url' ) ) {
			$html .= '<p class="description">' . esc_html__( 'برای منبع سفارشی، آدرس‌های پشتیبان GitHub/jsDelivr استفاده نمی‌شوند.', ONE_NAVASAN_DOMAIN ) . '</p>';
		}
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Approve the pending rate.
	 */
	public static function ajax_approve() {
		self::guard();
		wp_send_json_success( array( 'ok' => One_Navasan_Rate_Service::approve_pending() ) );
	}

	/**
	 * One chunk of preview/apply.
	 */
	public static function ajax_chunk() {
		self::guard();
		if ( ! One_Navasan_Settings::wc_active() ) {
			wp_send_json_error( array( 'message' => __( 'ووکامرس فعال نیست.', ONE_NAVASAN_DOMAIN ) ) );
		}
		$mode   = ( isset( $_POST['mode'] ) && 'apply' === $_POST['mode'] ) ? 'apply' : 'preview';
		$cursor = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
		$shown  = isset( $_POST['shown'] ) ? absint( $_POST['shown'] ) : 0;
		$first  = ( 0 === $cursor );

		if ( 'apply' === $mode ) {
			if ( ! get_option( One_Navasan_WC_Pricing::PREVIEW_DONE ) ) {
				wp_send_json_error( array( 'message' => __( 'ابتدا باید یک بار «پیش‌نمایش قیمت‌ها» را کامل ببینید.', ONE_NAVASAN_DOMAIN ) ) );
			}
			if ( $first ) {
				update_option( One_Navasan_WC_Pricing::ENABLED, 1, false );
				set_transient(
					self::RUN,
					array(
						'hash'   => One_Navasan_WC_Pricing::pricing_hash(),
						'counts' => array( 'products' => 0, 'items' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => array(), 'examples' => array() ),
					),
					HOUR_IN_SECONDS
				);
			}
		}

		$chunk = One_Navasan_WC_Pricing::process_chunk( $mode, $cursor, $shown );
		if ( is_wp_error( $chunk ) ) {
			wp_send_json_error( array( 'message' => $chunk->get_error_message() ) );
		}
		if ( 'apply' === $mode ) {
			$run = get_transient( self::RUN );
			if ( is_array( $run ) ) {
				$run['counts'] = One_Navasan_WC_Pricing::merge_counts( $run['counts'], $chunk['counts'] );
				if ( $chunk['done'] ) {
					One_Navasan_WC_Pricing::finish( $run['counts'], $run['hash'] );
					delete_transient( self::RUN );
				} else {
					set_transient( self::RUN, $run, HOUR_IN_SECONDS );
				}
			}
		} elseif ( $chunk['done'] ) {
			update_option( One_Navasan_WC_Pricing::PREVIEW_DONE, time(), false );
		}
		wp_send_json_success( $chunk );
	}

	/* ------------------------------------------------------------------ */
	/* Page rendering                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Render a field row.
	 *
	 * @param array $f Field definition.
	 */
	private static function field( array $f ) {
		$s     = One_Navasan_Settings::all();
		$name  = One_Navasan_Settings::OPTION . '[' . $f['key'] . ']';
		$id    = 'one-f-' . $f['key'];
		$value = isset( $s[ $f['key'] ] ) ? $s[ $f['key'] ] : '';
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label></th><td>';
		switch ( $f['type'] ) {
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $f['options'] as $ov => $ol ) {
					echo '<option value="' . esc_attr( $ov ) . '"' . selected( (string) $value, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</select>';
				break;
			case 'checkbox':
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" /><label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . ' /> ' . esc_html( isset( $f['check_label'] ) ? $f['check_label'] : '' ) . '</label>';
				break;
			case 'password':
				echo '<input type="password" autocomplete="new-password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( '' !== (string) $value ? One_Navasan_Settings::KEY_MASK : '' ) . '" class="regular-text" dir="ltr" />';
				if ( '' !== (string) $value ) {
					echo ' <label><input type="checkbox" name="' . esc_attr( One_Navasan_Settings::OPTION ) . '[api_key_clear]" value="1" /> ' . esc_html__( 'حذف کلید', ONE_NAVASAN_DOMAIN ) . '</label>';
				}
				break;
			case 'categories':
				$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" multiple size="6" class="one-multi">';
				if ( ! is_wp_error( $cats ) ) {
					foreach ( $cats as $cat ) {
						echo '<option value="' . esc_attr( $cat->term_id ) . '"' . selected( in_array( (int) $cat->term_id, array_map( 'intval', (array) $value ), true ), true, false ) . '>' . esc_html( $cat->name ) . '</option>';
					}
				}
				echo '</select>';
				break;
			case 'attribute':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">—</option>';
				foreach ( wc_get_attribute_taxonomies() as $tax ) {
					$slug = wc_attribute_taxonomy_name( $tax->attribute_name );
					echo '<option value="' . esc_attr( $slug ) . '"' . selected( (string) $value, $slug, false ) . '>' . esc_html( $tax->attribute_label ) . '</option>';
				}
				echo '</select>';
				break;
			default:
				$attrs = '';
				foreach ( array( 'min', 'max', 'step', 'list' ) as $a ) {
					if ( isset( $f[ $a ] ) ) {
						$attrs .= ' ' . $a . '="' . esc_attr( $f[ $a ] ) . '"';
					}
				}
				$type = 'number' === $f['type'] ? 'number' : 'text';
				echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_array( $value ) ? '' : (string) $value ) . '"' . $attrs . ' class="regular-text one-ltr" dir="ltr" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs escaped above.
		}
		if ( ! empty( $f['desc'] ) ) {
			echo '<p class="description">' . esc_html( $f['desc'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Field definitions grouped in sections.
	 *
	 * @return array
	 */
	private static function sections() {
		$d = ONE_NAVASAN_DOMAIN;
		$sections = array(
			array(
				'title'  => __( 'منبع و به‌روزرسانی', $d ),
				'fields' => array(
					array( 'key' => 'source_url', 'type' => 'text', 'label' => __( 'آدرس منبع', $d ), 'desc' => __( 'پیش‌فرض: فایل gold.json پروژهٔ Navasan-API. منبع سازگار دیگری هم می‌تواند باشد؛ در آدرس می‌توانید {key} بگذارید.', $d ) ),
					array( 'key' => 'api_key', 'type' => 'password', 'label' => __( 'کلید API (اختیاری)', $d ), 'desc' => __( 'Navasan-API کلید نمی‌خواهد. این فیلد برای توکن گیت‌هاب (بالا بردن سقف درخواست Contents API) یا منبعی است که کلید می‌خواهد. کلید در لاگ نوشته نمی‌شود.', $d ) ),
					array( 'key' => 'key_method', 'type' => 'select', 'label' => __( 'روش ارسال کلید', $d ), 'options' => array( 'bearer' => __( 'هدر Authorization: Bearer', $d ), 'url' => __( 'جایگزینی {key} در آدرس', $d ) ) ),
					array( 'key' => 'base_symbol', 'type' => 'text', 'label' => __( 'نماد نرخ پایه', $d ), 'desc' => __( 'پیش‌فرض 18ayar (قیمت هر گرم طلای ۱۸ عیار به تومان).', $d ) ),
					array( 'key' => 'base_karat', 'type' => 'number', 'label' => __( 'عیار نرخ پایه', $d ), 'min' => 1, 'max' => 24, 'step' => 'any' ),
					array( 'key' => 'interval', 'type' => 'number', 'label' => __( 'فاصلهٔ به‌روزرسانی (دقیقه)', $d ), 'min' => 5, 'max' => 1440, 'desc' => __( 'حداقل ۵ دقیقه. منبع خودش حدوداً هر ۳۰ دقیقه تازه می‌شود.', $d ) ),
				),
			),
			array(
				'title'  => __( 'نرخ دستی و تعدیل', $d ),
				'fields' => array(
					array( 'key' => 'rate_mode', 'type' => 'select', 'label' => __( 'حالت نرخ', $d ), 'options' => array( 'auto' => __( 'خودکار از منبع', $d ), 'manual' => __( 'دستی', $d ) ) ),
					array( 'key' => 'manual_rate', 'type' => 'text', 'label' => __( 'قیمت دستی هر گرم', $d ), 'desc' => __( 'به واحد ارز فروشگاه. در حالت دستی منبع نادیده گرفته می‌شود.', $d ) ),
					array( 'key' => 'adjust_mode', 'type' => 'select', 'label' => __( 'تعدیل نرخ خودکار', $d ), 'options' => array( 'none' => __( 'بدون تعدیل', $d ), 'percent' => __( 'درصد', $d ), 'fixed' => __( 'مبلغ ثابت (تومان)', $d ) ) ),
					array( 'key' => 'adjust_value', 'type' => 'text', 'label' => __( 'مقدار تعدیل', $d ), 'desc' => __( 'مثبت یا منفی. درصد، یا مبلغ تومان که به نرخ منبع اضافه/کم می‌شود.', $d ) ),
				),
			),
			array(
				'title'  => __( 'محافظ‌ها', $d ),
				'fields' => array(
					array( 'key' => 'max_jump_pct', 'type' => 'number', 'label' => __( 'حداکثر جهش مجاز (درصد)', $d ), 'min' => 0, 'step' => 'any', 'desc' => __( 'نرخ جدیدی که بیش از این با نرخ قبلی فرق کند اعمال نمی‌شود و منتظر تأیید می‌ماند. ۰ = غیرفعال.', $d ) ),
					array( 'key' => 'stale_hours', 'type' => 'number', 'label' => __( 'هشدار دادهٔ کهنه (ساعت)', $d ), 'min' => 0, 'step' => 'any' ),
				),
			),
			array(
				'title'  => __( 'واحد پول و گرد کردن', $d ),
				'fields' => array(
					array( 'key' => 'currency_unit', 'type' => 'select', 'label' => __( 'واحد قیمت فروشگاه', $d ), 'options' => array( 'auto' => __( 'خودکار (از ارز ووکامرس)', $d ), 'toman' => __( 'تومان', $d ), 'rial' => __( 'ریال', $d ) ), 'desc' => sprintf( /* translators: %s: currency code. */ __( 'ارز فعلی ووکامرس: %s', ONE_NAVASAN_DOMAIN ), One_Navasan_Settings::store_currency() ) ),
					array( 'key' => 'round_step', 'type' => 'number', 'label' => __( 'گام گرد کردن قیمت محصول', $d ), 'min' => 0, 'step' => 'any', 'list' => 'one-round-steps', 'desc' => __( '۰ = بدون گرد کردن. نمونه: ۵۰، ۱۰۰، ۵۰۰، ۱۰۰۰، ۵۰۰۰، ۱۰۰۰۰ یا عدد دلخواه. فقط روی قیمت نهایی اعمال می‌شود.', $d ) ),
					array( 'key' => 'round_dir', 'type' => 'select', 'label' => __( 'جهت گرد کردن', $d ), 'options' => array( 'nearest' => __( 'نزدیک‌ترین', $d ), 'up' => __( 'رو به بالا', $d ), 'down' => __( 'رو به پایین', $d ) ) ),
				),
			),
		);

		if ( One_Navasan_Settings::wc_active() ) {
			$sections[] = array(
				'title'  => __( 'قیمت‌گذاری محصولات ووکامرس', $d ),
				'fields' => array(
					array( 'key' => 'profit_pct', 'type' => 'text', 'label' => __( 'درصد سود پیش‌فرض', $d ) ),
					array( 'key' => 'tax_pct', 'type' => 'text', 'label' => __( 'درصد مالیات پیش‌فرض', $d ) ),
					array( 'key' => 'wage_pct', 'type' => 'text', 'label' => __( 'درصد اجرت پیش‌فرض', $d ) ),
					array( 'key' => 'weight_source', 'type' => 'select', 'label' => __( 'منبع وزن', $d ), 'options' => array( 'wc' => __( 'فیلد وزن ووکامرس', $d ), 'attribute' => __( 'ویژگی محصول', $d ) ) ),
					array( 'key' => 'weight_attr', 'type' => 'attribute', 'label' => __( 'ویژگی وزن', $d ) ),
					array( 'key' => 'weight_unit', 'type' => 'select', 'label' => __( 'واحد وزن', $d ), 'options' => array( 'g' => __( 'گرم', $d ), 'kg' => __( 'کیلوگرم', $d ) ), 'desc' => __( 'واحد عددهای وزن؛ جدا از واحد وزن ووکامرس.', $d ) ),
					array( 'key' => 'wage_source', 'type' => 'select', 'label' => __( 'منبع اجرت', $d ), 'options' => array( 'default' => __( 'پیش‌فرض سراسری', $d ), 'attribute' => __( 'ویژگی محصول', $d ), 'product' => __( 'فیلد خود محصول', $d ) ), 'desc' => __( 'اگر فیلد اجرت محصول پر باشد همیشه همان ملاک است.', $d ) ),
					array( 'key' => 'wage_attr', 'type' => 'attribute', 'label' => __( 'ویژگی اجرت', $d ) ),
					array( 'key' => 'karat_source', 'type' => 'select', 'label' => __( 'منبع عیار', $d ), 'options' => array( 'default' => __( 'پیش‌فرض سراسری', $d ), 'attribute' => __( 'ویژگی محصول', $d ) ) ),
					array( 'key' => 'karat_attr', 'type' => 'attribute', 'label' => __( 'ویژگی عیار', $d ) ),
					array( 'key' => 'default_karat', 'type' => 'number', 'label' => __( 'عیار پیش‌فرض محصولات', $d ), 'min' => 1, 'max' => 24, 'step' => 'any' ),
					array( 'key' => 'scope', 'type' => 'select', 'label' => __( 'دامنهٔ اعمال', $d ), 'options' => array( 'all' => __( 'همهٔ محصولات', $d ), 'categories' => __( 'فقط دسته‌های انتخابی', $d ), 'flagged' => __( 'فقط محصولات دارای تیک «قیمت‌گذاری با نرخ طلا»', $d ) ) ),
					array( 'key' => 'scope_cats', 'type' => 'categories', 'label' => __( 'دسته‌ها', $d ) ),
				),
			);
		}
		$sections[] = array(
			'title'  => __( 'نمایش در سایت', $d ),
			'fields' => array(
				array( 'key' => 'frontend_script', 'type' => 'checkbox', 'label' => __( 'اسکریپت سبک data-one-gold', $d ), 'check_label' => __( 'فعال', $d ), 'desc' => __( 'عنصرهایی با data-one-gold="value" با نرخ فعلی پر می‌شوند.', $d ) ),
			),
		);
		return $sections;
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		if ( ! current_user_can( One_Navasan_Settings::capability() ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', ONE_NAVASAN_DOMAIN ) );
		}
		echo '<div class="wrap one-wrap" dir="rtl"><h1>' . esc_html__( 'Navasan-API plugin for WordPress', ONE_NAVASAN_DOMAIN ) . '</h1>';
		echo '<p class="one-credit">' . self::credit_html() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_*.
		settings_errors();
		self::render_status();
		self::render_tools();
		echo '<form method="post" action="options.php" class="one-form">';
		settings_fields( self::GROUP );
		echo '<datalist id="one-round-steps"><option value="0"><option value="50"><option value="100"><option value="500"><option value="1000"><option value="5000"><option value="10000"></datalist>';
		foreach ( self::sections() as $section ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2><table class="form-table" role="presentation"><tbody>';
			foreach ( $section['fields'] as $f ) {
				self::field( $f );
			}
			echo '</tbody></table>';
		}
		submit_button( __( 'ذخیرهٔ تنظیمات', ONE_NAVASAN_DOMAIN ) );
		echo '</form>';
		echo '<hr><p class="one-credit one-footer">' . self::credit_html() . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Attribution line.
	 *
	 * @return string
	 */
	private static function credit_html() {
		return esc_html__( 'منبع داده / Powered by', ONE_NAVASAN_DOMAIN ) . ' <a href="https://github.com/HosseinOdd/Navasan-API" target="_blank" rel="noopener noreferrer">Navasan-API</a> '
			. esc_html__( 'ساختهٔ Hossein (HosseinOdd). افزونه فقط فایل JSON همان مخزن را می‌خواند؛ داده بدون تضمین است و مسئولیت درستی قیمت‌ها با مدیر سایت است.', ONE_NAVASAN_DOMAIN );
	}

	/**
	 * Status block.
	 */
	private static function render_status() {
		$s       = One_Navasan_Settings::all();
		$out     = One_Navasan_Rate_Service::symbol_output();
		$state   = One_Navasan_Rate_Service::state();
		$pending = One_Navasan_Rate_Service::pending();
		$error   = (string) get_option( One_Navasan_Rate_Service::ERROR, '' );
		$mult    = One_Navasan_Settings::multiplier();
		$row     = function ( $label, $value ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by callers.
		};
		echo '<h2>' . esc_html__( 'وضعیت', ONE_NAVASAN_DOMAIN ) . '</h2><table class="widefat striped one-table"><tbody>';
		if ( $out ) {
			$row( __( 'نرخ فعلی', ONE_NAVASAN_DOMAIN ), esc_html( One_Navasan_Number_Formatter::format( $out['value'], true, 'en' ) . ' ' . __( 'تومان', ONE_NAVASAN_DOMAIN ) . ' — ' . ( 'manual' === $out['mode'] ? __( 'دستی', ONE_NAVASAN_DOMAIN ) : __( 'خودکار', ONE_NAVASAN_DOMAIN ) ) . ( 1.0 !== (float) $mult ? ' — ' . sprintf( /* translators: %s: number. */ __( 'قیمت فروشگاه: %s', ONE_NAVASAN_DOMAIN ), One_Navasan_Number_Formatter::format( $out['value'] * $mult, true, 'en' ) ) : '' ) ) );
		} else {
			$row( __( 'نرخ فعلی', ONE_NAVASAN_DOMAIN ), esc_html__( 'هنوز نرخی دریافت نشده است.', ONE_NAVASAN_DOMAIN ) );
		}
		$row( __( 'زمان آخرین دریافت', ONE_NAVASAN_DOMAIN ), esc_html( ! empty( $state['fetched_at'] ) ? wp_date( 'Y/m/d H:i:s', (int) $state['fetched_at'] ) : '—' ) );
		$data_ts = One_Navasan_Rate_Service::data_ts();
		$row( __( 'زمان خودِ داده', ONE_NAVASAN_DOMAIN ), esc_html( $data_ts ? wp_date( 'Y/m/d H:i:s', $data_ts ) : '—' ) . ( One_Navasan_Rate_Service::is_stale() ? ' <strong class="one-bad">' . esc_html__( '⚠ کهنه', ONE_NAVASAN_DOMAIN ) . '</strong>' : '' ) );
		$next = One_Navasan_Scheduler::next_run();
		$row( __( 'اجرای بعدی', ONE_NAVASAN_DOMAIN ), esc_html( $next ? wp_date( 'Y/m/d H:i:s', $next ) : '—' ) . ' (' . esc_html( One_Navasan_Scheduler::uses_action_scheduler() ? 'Action Scheduler' : 'WP-Cron' ) . ')' );
		$row( __( 'آخرین خطا', ONE_NAVASAN_DOMAIN ), esc_html( '' !== $error ? One_Navasan_Logger::redact( $error ) : '—' ) );
		if ( $pending ) {
			$row(
				__( 'نرخ در انتظار تأیید', ONE_NAVASAN_DOMAIN ),
				esc_html( sprintf( /* translators: 1: new value, 2: old value, 3: percent. */ __( 'نرخ جدید %1$s (قبلی %2$s، تغییر %3$s٪)', ONE_NAVASAN_DOMAIN ), One_Navasan_Number_Formatter::format( $pending['new_value'] ), One_Navasan_Number_Formatter::format( $pending['old_value'] ), $pending['pct'] ) )
				. ' <button type="button" class="button button-primary" data-one-action="approve">' . esc_html__( 'تأیید و اعمال', ONE_NAVASAN_DOMAIN ) . '</button>'
			);
		}
		if ( One_Navasan_Settings::wc_active() ) {
			$report = get_option( One_Navasan_WC_Pricing::REPORT );
			$row(
				__( 'قیمت‌گذاری محصولات', ONE_NAVASAN_DOMAIN ),
				esc_html( get_option( One_Navasan_WC_Pricing::ENABLED ) ? __( 'فعال', ONE_NAVASAN_DOMAIN ) : __( 'غیرفعال تا اولین پیش‌نمایش و اعمال', ONE_NAVASAN_DOMAIN ) )
				. ( is_array( $report ) ? ' — ' . esc_html( sprintf( /* translators: 1: time, 2: updated, 3: skipped. */ __( 'آخرین اجرا %1$s: %2$d به‌روز، %3$d ردشده', ONE_NAVASAN_DOMAIN ), wp_date( 'Y/m/d H:i', (int) $report['time'] ), (int) $report['counts']['updated'], array_sum( $report['counts']['skipped'] ) ) ) : '' )
			);
		}
		echo '</tbody></table>';
		unset( $s );

		$log = array_reverse( One_Navasan_Logger::get() );
		echo '<h3>' . esc_html__( 'لاگ (۲۰ خط آخر)', ONE_NAVASAN_DOMAIN ) . '</h3><pre class="one-log" dir="ltr">';
		if ( ! $log ) {
			echo esc_html( '—' );
		}
		foreach ( $log as $line ) {
			echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $line['time'] ) . ' [' . $line['level'] . '] ' . $line['message'] ) . "\n";
		}
		echo '</pre>';
	}

	/**
	 * Tool buttons and result area.
	 */
	private static function render_tools() {
		$wc = One_Navasan_Settings::wc_active();
		echo '<h2>' . esc_html__( 'ابزارها', ONE_NAVASAN_DOMAIN ) . '</h2><p class="one-tools">';
		echo '<button type="button" class="button" data-one-action="fetch">' . esc_html__( 'دریافت همین حالا', ONE_NAVASAN_DOMAIN ) . '</button> ';
		echo '<button type="button" class="button" data-one-action="test">' . esc_html__( 'تست اتصال', ONE_NAVASAN_DOMAIN ) . '</button> ';
		if ( $wc ) {
			echo '<button type="button" class="button" data-one-action="preview">' . esc_html__( 'پیش‌نمایش قیمت‌ها', ONE_NAVASAN_DOMAIN ) . '</button> ';
			echo '<button type="button" class="button button-primary" data-one-action="apply"' . disabled( (bool) get_option( One_Navasan_WC_Pricing::PREVIEW_DONE ), false, false ) . '>' . esc_html__( 'اعمال قیمت‌ها', ONE_NAVASAN_DOMAIN ) . '</button> ';
			echo '<button type="button" class="button" data-one-action="recalc"' . disabled( (bool) get_option( One_Navasan_WC_Pricing::ENABLED ), false, false ) . '>' . esc_html__( 'محاسبهٔ دوبارهٔ همهٔ قیمت‌ها', ONE_NAVASAN_DOMAIN ) . '</button>';
			echo '</p><p class="description">' . esc_html__( 'تا یک بار «پیش‌نمایش» را کامل نبینید و «اعمال» را نزنید، به قیمت هیچ محصولی دست زده نمی‌شود.', ONE_NAVASAN_DOMAIN ) . '</p>';
		} else {
			echo '</p><p class="description">' . esc_html__( 'ووکامرس فعال نیست؛ فقط بخش نرخ، شورت‌کد و REST کار می‌کند.', ONE_NAVASAN_DOMAIN ) . '</p>';
		}
		echo '<div id="one-navasan-result" class="one-result" aria-live="polite"></div>';
	}
}
