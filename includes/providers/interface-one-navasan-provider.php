<?php
/**
 * Rate provider contract.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * A rate provider turns one upstream service into the plugin's internal shape.
 *
 * Internal entry: {value (Toman), high, low, change_val, change_pct, date (unix)}; high, low and
 * the change fields are null when the service does not provide them.
 */
interface One_Navasan_Provider {

	/**
	 * Short id stored in state and returned by REST: priceto|navasan.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Base symbol configured for this provider.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	public function base_symbol( array $settings );

	/**
	 * Ordered endpoints (paths) to try. Each: id, label, url, headers, cdn.
	 *
	 * @param array $settings Plugin settings.
	 * @return array[]
	 */
	public function endpoints( array $settings );

	/**
	 * Validate and convert one endpoint's body.
	 *
	 * @param array  $endpoint Endpoint definition.
	 * @param string $body     Raw body.
	 * @param array  $settings Plugin settings.
	 * @return array {ok:bool, error:string, symbols:array<string,array>} Always contains the base symbol when ok.
	 */
	public function parse( array $endpoint, $body, array $settings );

	/**
	 * Optional extra data after a good parse (for example the day change). Must never fail the fetch.
	 *
	 * @param array $symbols  Parsed symbols.
	 * @param array $settings Plugin settings.
	 * @return array Symbols.
	 */
	public function supplement( array $symbols, array $settings );
}
