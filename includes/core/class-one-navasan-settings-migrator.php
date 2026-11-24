<?php
/**
 * Conversion of 1.0.0 settings to the 1.1.0 layout (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Settings_Migrator' ) ) {

	/**
	 * 1.0.0 had "rate_mode" (auto|manual); 1.1.0 has "rate_source" (priceto|navasan|manual)
	 * and "backup_source" (none|priceto|navasan).
	 */
	class One_Navasan_Settings_Migrator {

		/**
		 * Does this stored array use the 1.0.0 layout?
		 *
		 * @param mixed $saved Stored settings.
		 * @return bool
		 */
		public static function needs_migration( $saved ) {
			return is_array( $saved ) && ! array_key_exists( 'rate_source', $saved );
		}

		/**
		 * Convert. Every other key is kept as is; the effective source does not change.
		 *
		 * @param array $saved Stored settings (1.0.0).
		 * @return array Settings with rate_source/backup_source and without rate_mode.
		 */
		public static function migrate( array $saved ) {
			if ( ! self::needs_migration( $saved ) ) {
				return $saved;
			}
			$mode = isset( $saved['rate_mode'] ) ? $saved['rate_mode'] : 'auto';
			// An upgrade must not change the source by itself: auto stays Navasan-API, no backup.
			$saved['rate_source']   = ( 'manual' === $mode ) ? 'manual' : 'navasan';
			$saved['backup_source'] = 'none';
			unset( $saved['rate_mode'] );
			return $saved;
		}
	}
}
