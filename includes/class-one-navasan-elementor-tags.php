<?php
/**
 * Elementor dynamic tag classes. Loaded only after Elementor is available.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {

	/**
	 * Base: shared controls.
	 */
	abstract class One_Navasan_Elementor_Base_Tag extends \Elementor\Core\DynamicTags\Tag {

		/**
		 * Group.
		 *
		 * @return string
		 */
		public function get_group() {
			return 'site';
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'one_field',
				array(
					'label'   => __( 'مقدار', ONE_NAVASAN_DOMAIN ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'value',
					'options' => array(
						'value'      => __( 'نرخ', ONE_NAVASAN_DOMAIN ),
						'change_val' => __( 'تغییر (مبلغ)', ONE_NAVASAN_DOMAIN ),
						'change_pct' => __( 'تغییر (درصد)', ONE_NAVASAN_DOMAIN ),
						'high'       => __( 'بیشترین امروز', ONE_NAVASAN_DOMAIN ),
						'low'        => __( 'کمترین امروز', ONE_NAVASAN_DOMAIN ),
						'updated'    => __( 'زمان به‌روزرسانی', ONE_NAVASAN_DOMAIN ),
					),
				)
			);
			$this->add_control(
				'one_symbol',
				array(
					'label'       => __( 'نماد (خالی = نماد پایه)', ONE_NAVASAN_DOMAIN ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
				)
			);
		}

		/**
		 * Output array for current settings.
		 *
		 * @return array|null
		 */
		protected function one_output() {
			return One_Navasan_Rate_Service::symbol_output( sanitize_text_field( (string) $this->get_settings( 'one_symbol' ) ) );
		}
	}

	/**
	 * Text tag (formatted).
	 */
	class One_Navasan_Elementor_Text_Tag extends One_Navasan_Elementor_Base_Tag {

		public function get_name() {
			return 'one-navasan-text';
		}

		public function get_title() {
			return __( 'نرخ طلا (متن)', ONE_NAVASAN_DOMAIN );
		}

		public function get_categories() {
			return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
		}

		public function render() {
			$out = $this->one_output();
			if ( ! $out ) {
				return;
			}
			echo esc_html(
				One_Navasan_Shortcode::format_field(
					$out,
					(string) $this->get_settings( 'one_field' ),
					array(
						'unit'        => 'toman',
						'thousands'   => true,
						'digits'      => 'fa',
						'label'       => 'no',
						'decimals'    => '',
						'date_format' => 'Y/m/d H:i',
					)
				)
			);
		}
	}

	/**
	 * Number tag (raw number).
	 */
	class One_Navasan_Elementor_Number_Tag extends One_Navasan_Elementor_Base_Tag {

		public function get_name() {
			return 'one-navasan-number';
		}

		public function get_title() {
			return __( 'نرخ طلا (عدد)', ONE_NAVASAN_DOMAIN );
		}

		public function get_categories() {
			return array( \Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY );
		}

		public function render() {
			$out   = $this->one_output();
			$field = (string) $this->get_settings( 'one_field' );
			if ( ! $out || ! isset( $out[ $field ] ) || ! is_numeric( $out[ $field ] ) ) {
				return;
			}
			echo esc_html( (string) $out[ $field ] );
		}
	}
}
