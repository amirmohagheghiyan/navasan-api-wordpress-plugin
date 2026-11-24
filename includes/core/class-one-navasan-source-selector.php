<?php
/**
 * Primary / backup source selection rules (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Source_Selector' ) ) {

	/**
	 * Decides which providers are tried and when a backup result may be used.
	 */
	class One_Navasan_Source_Selector {

		const PROVIDERS = array( 'priceto', 'navasan' );

		/**
		 * Providers to try, in order.
		 *
		 * @param string $rate_source   priceto|navasan|manual.
		 * @param string $backup_source none|priceto|navasan.
		 * @return string[] Provider ids. Empty for manual mode.
		 */
		public static function plan( $rate_source, $backup_source ) {
			if ( ! in_array( $rate_source, self::PROVIDERS, true ) ) {
				return array();
			}
			$plan = array( $rate_source );
			if ( in_array( $backup_source, self::PROVIDERS, true ) && $backup_source !== $rate_source ) {
				$plan[] = $backup_source;
			}
			return $plan;
		}

		/**
		 * A backup result may replace the stored rate only when its data is newer.
		 *
		 * Navasan-API is often hours behind; an older rate must never replace a fresher one.
		 *
		 * @param int|null $backup_ts Data time of the backup result.
		 * @param int|null $stored_ts Data time of the last healthy rate (null = nothing stored).
		 * @return bool
		 */
		public static function backup_is_newer( $backup_ts, $stored_ts ) {
			if ( null === $stored_ts ) {
				return true;
			}
			if ( null === $backup_ts ) {
				return false;
			}
			return (int) $backup_ts > (int) $stored_ts;
		}

		/**
		 * Did the provider change compared with the stored rate?
		 *
		 * @param string|null $stored_provider Provider of the stored rate (null = none stored).
		 * @param string      $new_provider    Provider of the new rate.
		 * @return bool
		 */
		public static function is_source_change( $stored_provider, $new_provider ) {
			return null !== $stored_provider && '' !== $stored_provider && $stored_provider !== $new_provider;
		}

		/**
		 * Reason code for a held rate.
		 *
		 * @param bool $source_changed Whether the provider changed.
		 * @return string source_change|jump
		 */
		public static function hold_reason( $source_changed ) {
			return $source_changed ? 'source_change' : 'jump';
		}
	}
}
