<?php
/**
 * Public read-only REST route.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/one-navasan/v1/rate
 */
class One_Navasan_Rest {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the route.
	 */
	public static function register() {
		register_rest_route(
			'one-navasan/v1',
			'/rate',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'symbol' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'unit'   => array(
						'type'              => 'string',
						'default'           => 'toman',
						'enum'              => array( 'toman', 'rial' ),
					),
				),
			)
		);
	}

	/**
	 * Handle request. Exposes no settings or keys.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( $request ) {
		$out = One_Navasan_Rate_Service::symbol_output( (string) $request->get_param( 'symbol' ) );
		if ( ! $out ) {
			return new WP_Error( 'one_navasan_no_rate', __( 'نرخی موجود نیست.', ONE_NAVASAN_DOMAIN ), array( 'status' => 404 ) );
		}
		$unit   = 'rial' === $request->get_param( 'unit' ) ? 'rial' : 'toman';
		$factor = One_Navasan_Shortcode::unit_factor( $unit );
		$scale  = function ( $v ) use ( $factor ) {
			return null === $v ? null : round( $v * $factor, 4 );
		};
		$data = array(
			'symbol'     => $out['symbol'],
			'unit'       => $unit,
			'mode'       => $out['mode'],
			'source'     => $out['source'],
			'value'      => $scale( $out['value'] ),
			'change_val' => $scale( $out['change_val'] ),
			'change_pct' => $out['change_pct'],
			'high'       => $scale( $out['high'] ),
			'low'        => $scale( $out['low'] ),
			'updated'    => $out['updated'],
			'fetched_at' => $out['fetched_at'],
			'stale'      => One_Navasan_Rate_Service::is_stale(),
		);
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}
}
