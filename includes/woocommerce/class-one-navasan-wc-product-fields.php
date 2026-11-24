<?php
/**
 * Product edit screen: gold pricing tab and variation fields.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product meta UI.
 */
class One_Navasan_WC_Product_Fields {

	const TEXT_KEYS = array( 'weight', 'karat', 'wage', 'profit', 'extra' );

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_variation' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );
	}

	/**
	 * Add the tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		if ( ! current_user_can( One_Navasan_Settings::capability() ) ) {
			return $tabs;
		}
		$tabs['one_navasan'] = array(
			'label'    => __( 'قیمت‌گذاری با نرخ طلا', ONE_NAVASAN_DOMAIN ),
			'target'   => 'one_navasan_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Render fields (shared by product and variation).
	 *
	 * @param string $prefix  Field name prefix.
	 * @param array  $values  Current values.
	 * @param bool   $variation Whether it is a variation.
	 */
	private static function fields( $prefix, array $values, $variation ) {
		$enabled_options = $variation
			? array( '' => __( 'ارث‌بری از محصول', ONE_NAVASAN_DOMAIN ), 'yes' => __( 'روشن', ONE_NAVASAN_DOMAIN ), 'no' => __( 'خاموش', ONE_NAVASAN_DOMAIN ) )
			: array( '' => __( 'طبق دامنهٔ اعمال در تنظیمات', ONE_NAVASAN_DOMAIN ), 'yes' => __( 'روشن', ONE_NAVASAN_DOMAIN ), 'no' => __( 'خاموش', ONE_NAVASAN_DOMAIN ) );
		$wrap = $variation ? 'form-row form-row-first one-field' : 'form-field one-field';
		$text = function ( $key, $label, $desc = '' ) use ( $prefix, $values, $wrap ) {
			printf(
				'<p class="%1$s"><label for="%2$s">%3$s</label><input type="text" id="%2$s" name="%4$s" value="%5$s" class="short" />%6$s</p>',
				esc_attr( $wrap ),
				esc_attr( $prefix . '_' . $key ),
				esc_html( $label ),
				esc_attr( $prefix . '[' . $key . ']' ),
				esc_attr( isset( $values[ $key ] ) ? $values[ $key ] : '' ),
				$desc ? '<span class="description">' . esc_html( $desc ) . '</span>' : ''
			);
		};
		printf( '<p class="%s"><label>%s</label><select name="%s">', esc_attr( $wrap ), esc_html__( 'قیمت‌گذاری خودکار', ONE_NAVASAN_DOMAIN ), esc_attr( $prefix . '[enabled]' ) );
		foreach ( $enabled_options as $val => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( isset( $values['enabled'] ) ? $values['enabled'] : '', $val, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		$text( 'weight', __( 'وزن (گرم/کیلوگرم طبق تنظیمات)', ONE_NAVASAN_DOMAIN ), __( 'خالی = از تنظیم سراسری یا ویژگی', ONE_NAVASAN_DOMAIN ) );
		$text( 'karat', __( 'عیار', ONE_NAVASAN_DOMAIN ) );
		$text( 'wage', __( 'اجرت', ONE_NAVASAN_DOMAIN ), __( 'خالی = پیش‌فرض یا ویژگی', ONE_NAVASAN_DOMAIN ) );
		printf( '<p class="%s"><label>%s</label><select name="%s">', esc_attr( $wrap ), esc_html__( 'نوع اجرت', ONE_NAVASAN_DOMAIN ), esc_attr( $prefix . '[wage_mode]' ) );
		foreach ( array( 'percent' => __( 'درصد', ONE_NAVASAN_DOMAIN ), 'fixed' => __( 'مبلغ ثابت', ONE_NAVASAN_DOMAIN ) ) as $val => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( isset( $values['wage_mode'] ) ? $values['wage_mode'] : 'percent', $val, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		$text( 'profit', __( 'درصد سود', ONE_NAVASAN_DOMAIN ) );
		$text( 'extra', __( 'مبلغ اضافهٔ ثابت (مثلاً قیمت سنگ)', ONE_NAVASAN_DOMAIN ) );
	}

	/**
	 * Load current values.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private static function values( $product ) {
		$values = array( 'enabled' => One_Navasan_WC_Pricing::meta( $product, 'enabled' ), 'wage_mode' => One_Navasan_WC_Pricing::meta( $product, 'wage_mode' ) );
		foreach ( self::TEXT_KEYS as $k ) {
			$values[ $k ] = One_Navasan_WC_Pricing::meta( $product, $k );
		}
		return $values;
	}

	/**
	 * Computed price + breakdown notice.
	 *
	 * @param WC_Product $product Product.
	 */
	private static function computed_box( $product ) {
		$calc = One_Navasan_WC_Pricing::compute( $product );
		echo '<div class="one-computed" style="margin:10px 12px;padding:8px 12px;background:#f6f7f7;border-inline-start:3px solid #2271b1">';
		if ( ! $calc['ok'] ) {
			echo esc_html( 'out_of_scope' === $calc['reason'] ? __( 'این محصول در دامنهٔ قیمت‌گذاری خودکار نیست.', ONE_NAVASAN_DOMAIN ) : sprintf( /* translators: reason. */ __( 'قیمت قابل محاسبه نیست: %s', ONE_NAVASAN_DOMAIN ), One_Navasan_WC_Pricing::reason_label( $calc['reason'] ) ) );
		} else {
			$f = function ( $n ) {
				return One_Navasan_Number_Formatter::format( $n, true, 'en' );
			};
			echo esc_html(
				sprintf(
					/* translators: price breakdown. */
					__( 'قیمت محاسبه‌شده: %1$s — طلا %2$s + اجرت %3$s + سود %4$s + مالیات %5$s + اضافه %6$s', ONE_NAVASAN_DOMAIN ),
					$f( $calc['total'] ),
					$f( $calc['gold'] ),
					$f( $calc['wage'] ),
					$f( $calc['profit'] ),
					$f( $calc['tax'] ),
					$f( $calc['extra'] )
				)
			);
		}
		echo '</div>';
	}

	/**
	 * Render the product data panel.
	 */
	public static function render_panel() {
		global $post;
		$product = $post ? wc_get_product( $post->ID ) : null;
		if ( ! $product || ! current_user_can( One_Navasan_Settings::capability() ) ) {
			return;
		}
		wp_nonce_field( 'one_navasan_product', 'one_navasan_product_nonce' );
		echo '<div id="one_navasan_product_data" class="panel woocommerce_options_panel" style="display:none">';
		echo '<div class="options_group">';
		self::fields( 'one_navasan', self::values( $product ), false );
		echo '</div>';
		if ( $product->is_type( 'simple' ) ) {
			self::computed_box( $product );
		}
		echo '</div>';
	}

	/**
	 * Save product fields.
	 *
	 * @param int $post_id Product ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['one_navasan_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['one_navasan_product_nonce'] ) ), 'one_navasan_product' ) ) {
			return;
		}
		if ( ! current_user_can( One_Navasan_Settings::capability() ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}
		$in = isset( $_POST['one_navasan'] ) && is_array( $_POST['one_navasan'] ) ? wp_unslash( $_POST['one_navasan'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in store().
		self::store( $product, $in );
		$product->save_meta_data();
	}

	/**
	 * Render variation fields.
	 *
	 * @param int     $loop           Index.
	 * @param array   $variation_data Data.
	 * @param WP_Post $variation      Variation post.
	 */
	public static function render_variation( $loop, $variation_data, $variation ) {
		if ( ! current_user_can( One_Navasan_Settings::capability() ) ) {
			return;
		}
		$product = wc_get_product( $variation->ID );
		if ( ! $product ) {
			return;
		}
		echo '<div class="one-navasan-variation"><strong>' . esc_html__( 'قیمت‌گذاری با نرخ طلا', ONE_NAVASAN_DOMAIN ) . '</strong>';
		self::fields( 'one_navasan_var_' . (int) $loop, self::values( $product ), true );
		self::computed_box( $product );
		echo '</div>';
	}

	/**
	 * Save variation fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Index.
	 */
	public static function save_variation( $variation_id, $loop ) {
		if ( ! current_user_can( One_Navasan_Settings::capability() ) || ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}
		$name = 'one_navasan_var_' . (int) $loop;
		// Nonce is verified by WooCommerce's variation save (woocommerce_save_variations).
		if ( ! isset( $_POST[ $name ] ) || ! is_array( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$product = wc_get_product( $variation_id );
		if ( ! $product ) {
			return;
		}
		self::store( $product, wp_unslash( $_POST[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$product->save_meta_data();
	}

	/**
	 * Sanitize and store values on a product object.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $in      Raw values.
	 */
	private static function store( $product, array $in ) {
		$enabled = isset( $in['enabled'] ) ? sanitize_text_field( (string) $in['enabled'] ) : '';
		$product->update_meta_data( 'one_navasan_enabled', in_array( $enabled, array( 'yes', 'no' ), true ) ? $enabled : '' );
		$mode = isset( $in['wage_mode'] ) ? sanitize_text_field( (string) $in['wage_mode'] ) : 'percent';
		$product->update_meta_data( 'one_navasan_wage_mode', 'fixed' === $mode ? 'fixed' : 'percent' );
		foreach ( self::TEXT_KEYS as $key ) {
			$value = isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? sanitize_text_field( (string) $in[ $key ] ) : '';
			$product->update_meta_data( 'one_navasan_' . $key, $value );
		}
	}
}
