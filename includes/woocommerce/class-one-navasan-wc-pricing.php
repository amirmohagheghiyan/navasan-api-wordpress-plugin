<?php
/**
 * WooCommerce product pricing from the gold rate.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Computes and stores product prices (real stored prices via the WC CRUD).
 */
class One_Navasan_WC_Pricing {

	const CHUNK        = 50;
	const JOB          = 'one_navasan_job';
	const JOB_HOOK     = 'one_navasan_job_event';
	const REPORT       = 'one_navasan_report';
	const ENABLED      = 'one_navasan_prices_enabled';
	const PREVIEW_DONE = 'one_navasan_preview_done';
	const APPLIED_HASH = 'one_navasan_applied_hash';
	const LOCK         = 'one_navasan_job_lock';
	const SHOW_ROWS    = 60;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::JOB_HOOK, array( __CLASS__, 'background_step' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Reading product configuration                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Trimmed plugin meta value of a product.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     Key without prefix.
	 * @return string
	 */
	public static function meta( $product, $key ) {
		$value = $product->get_meta( 'one_navasan_' . $key, true );
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Own meta, falling back to the parent's meta for variations.
	 *
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent.
	 * @param string          $key     Key.
	 * @return string
	 */
	private static function field( $product, $parent, $key ) {
		$value = self::meta( $product, $key );
		if ( '' === $value && $parent && $parent->get_id() !== $product->get_id() ) {
			$value = self::meta( $parent, $key );
		}
		return $value;
	}

	/**
	 * Text of a product attribute (term name or custom value). Variations use their own selection.
	 *
	 * @param WC_Product      $product   Product or variation.
	 * @param WC_Product|null $parent    Parent.
	 * @param string          $attribute Attribute name (e.g. pa_weight).
	 * @return string Empty when unavailable or ambiguous.
	 */
	public static function attribute_text( $product, $parent, $attribute ) {
		if ( '' === $attribute ) {
			return '';
		}
		if ( $product->is_type( 'variation' ) ) {
			$attrs = $product->get_attributes();
			if ( isset( $attrs[ $attribute ] ) && '' !== (string) $attrs[ $attribute ] ) {
				$value = (string) $attrs[ $attribute ];
				if ( taxonomy_exists( $attribute ) ) {
					$term = get_term_by( 'slug', $value, $attribute );
					return $term ? $term->name : $value;
				}
				return $value;
			}
		}
		$source = $parent ? $parent : $product;
		$text   = $source->get_attribute( $attribute );
		if ( '' === $text ) {
			return '';
		}
		$values = array_filter( array_map( 'trim', explode( ',', $text ) ), 'strlen' );
		return 1 === count( $values ) ? reset( $values ) : '';
	}

	/**
	 * Is this product included by the configured scope?
	 *
	 * @param WC_Product $parent  Parent (or simple) product.
	 * @return bool
	 */
	public static function in_scope( $parent ) {
		$flag = self::meta( $parent, 'enabled' );
		if ( 'no' === $flag ) {
			return false;
		}
		if ( 'yes' === $flag ) {
			return true;
		}
		$scope = One_Navasan_Settings::get( 'scope' );
		if ( 'flagged' === $scope ) {
			return false;
		}
		if ( 'categories' === $scope ) {
			$allowed = self::scope_category_ids();
			$terms   = wp_get_post_terms( $parent->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
			return ! is_wp_error( $terms ) && (bool) array_intersect( $allowed, $terms );
		}
		return true;
	}

	/**
	 * Selected categories plus descendants.
	 *
	 * @return int[]
	 */
	private static function scope_category_ids() {
		static $cache = null;
		if ( null === $cache ) {
			$cache = array();
			foreach ( (array) One_Navasan_Settings::get( 'scope_cats' ) as $id ) {
				$cache[] = (int) $id;
				$children = get_term_children( (int) $id, 'product_cat' );
				if ( ! is_wp_error( $children ) ) {
					$cache = array_merge( $cache, array_map( 'intval', $children ) );
				}
			}
			$cache = array_unique( $cache );
		}
		return $cache;
	}

	/**
	 * Compute the price for one product/variation.
	 *
	 * @param WC_Product $product Product (simple or variation).
	 * @return array Calculator result plus 'inputs', or {ok:false, reason}.
	 */
	public static function compute( $product ) {
		$rate = One_Navasan_Rate_Service::store_rate();
		if ( null === $rate ) {
			return array( 'ok' => false, 'reason' => 'no_rate' );
		}
		$s      = One_Navasan_Settings::all();
		$parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( ! $parent ) {
			return array( 'ok' => false, 'reason' => 'no_parent' );
		}
		if ( ! self::in_scope( $parent ) ) {
			return array( 'ok' => false, 'reason' => 'out_of_scope' );
		}
		$own_flag = $product->is_type( 'variation' ) ? self::meta( $product, 'enabled' ) : '';
		if ( 'no' === $own_flag ) {
			return array( 'ok' => false, 'reason' => 'out_of_scope' );
		}

		// Weight.
		$weight_raw = self::field( $product, $parent, 'weight' );
		if ( '' === $weight_raw ) {
			$weight_raw = ( 'attribute' === $s['weight_source'] ) ? self::attribute_text( $product, $parent, (string) $s['weight_attr'] ) : (string) $product->get_weight();
		}
		$weight = One_Navasan_Number_Parser::parse_positive( $weight_raw );
		if ( null === $weight ) {
			return array( 'ok' => false, 'reason' => 'bad_weight' );
		}
		if ( 'kg' === $s['weight_unit'] ) {
			$weight *= 1000;
		}

		// Karat.
		$karat_raw = self::field( $product, $parent, 'karat' );
		if ( '' === $karat_raw && 'attribute' === $s['karat_source'] ) {
			$karat_raw = self::attribute_text( $product, $parent, (string) $s['karat_attr'] );
		}
		if ( '' === $karat_raw ) {
			$karat = (float) $s['default_karat'];
		} else {
			$karat = One_Navasan_Number_Parser::parse_positive( $karat_raw );
			if ( null === $karat ) {
				return array( 'ok' => false, 'reason' => 'bad_karat' );
			}
		}

		// Wage.
		$wage_mode = 'percent';
		$wage_raw  = self::field( $product, $parent, 'wage' );
		if ( '' !== $wage_raw ) {
			$mode      = self::field( $product, $parent, 'wage_mode' );
			$wage_mode = ( 'fixed' === $mode ) ? 'fixed' : 'percent';
		} elseif ( 'attribute' === $s['wage_source'] ) {
			$wage_raw = self::attribute_text( $product, $parent, (string) $s['wage_attr'] );
		}
		if ( '' === $wage_raw ) {
			$wage_value = (float) $s['wage_pct'];
		} else {
			$wage_value = One_Navasan_Number_Parser::parse( $wage_raw );
			if ( null === $wage_value || $wage_value < 0 ) {
				return array( 'ok' => false, 'reason' => 'bad_wage' );
			}
		}

		// Profit and extra.
		$profit_raw = self::field( $product, $parent, 'profit' );
		if ( '' === $profit_raw ) {
			$profit = (float) $s['profit_pct'];
		} else {
			$profit = One_Navasan_Number_Parser::parse( $profit_raw );
			if ( null === $profit || $profit < 0 ) {
				return array( 'ok' => false, 'reason' => 'bad_profit' );
			}
		}
		$extra_raw = self::field( $product, $parent, 'extra' );
		if ( '' === $extra_raw ) {
			$extra = 0.0;
		} else {
			$extra = One_Navasan_Number_Parser::parse( $extra_raw );
			if ( null === $extra || $extra < 0 ) {
				return array( 'ok' => false, 'reason' => 'bad_extra' );
			}
		}

		$inputs = array(
			'base_rate'  => $rate,
			'base_karat' => (float) $s['base_karat'],
			'karat'      => $karat,
			'weight'     => $weight,
			'wage_mode'  => $wage_mode,
			'wage_value' => $wage_value,
			'profit_pct' => $profit,
			'tax_pct'    => (float) $s['tax_pct'],
			'extra'      => $extra,
			'round_step' => (float) $s['round_step'],
			'round_dir'  => $s['round_dir'],
		);
		$result = One_Navasan_Price_Calculator::calculate( $inputs );
		if ( ! $result['ok'] ) {
			return $result;
		}
		/**
		 * Filters the calculated product price (store currency, before storing).
		 *
		 * @param float      $total     Price.
		 * @param WC_Product $product   Product or variation.
		 * @param array      $breakdown Calculation breakdown.
		 */
		$total = apply_filters( 'one_navasan_product_price', $result['total'], $product, $result );
		if ( is_numeric( $total ) && (float) $total > 0 ) {
			$result['total'] = (float) $total;
		}
		$result['inputs'] = $inputs;
		return $result;
	}

	/**
	 * Human labels of skip reasons.
	 *
	 * @param string $code Reason.
	 * @return string
	 */
	public static function reason_label( $code ) {
		$labels = array(
			'no_rate'    => __( 'نرخ در دسترس نیست', ONE_NAVASAN_DOMAIN ),
			'no_parent'  => __( 'محصول والد پیدا نشد', ONE_NAVASAN_DOMAIN ),
			'bad_weight' => __( 'وزن معتبر ندارد', ONE_NAVASAN_DOMAIN ),
			'bad_karat'  => __( 'عیار نامعتبر', ONE_NAVASAN_DOMAIN ),
			'bad_wage'   => __( 'اجرت نامعتبر', ONE_NAVASAN_DOMAIN ),
			'bad_profit' => __( 'سود نامعتبر', ONE_NAVASAN_DOMAIN ),
			'bad_extra'  => __( 'مبلغ اضافه نامعتبر', ONE_NAVASAN_DOMAIN ),
			'bad_rate'   => __( 'نرخ نامعتبر', ONE_NAVASAN_DOMAIN ),
		);
		return isset( $labels[ $code ] ) ? $labels[ $code ] : $code;
	}

	/* ------------------------------------------------------------------ */
	/* Processing                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Process one product (simple or variation).
	 *
	 * @param WC_Product $product Product.
	 * @param bool       $apply   Save the price.
	 * @return array {status: updated|unchanged|skipped|out, reason?, current, new, breakdown?, sale_removed?}
	 */
	public static function process_one( $product, $apply ) {
		$calc = self::compute( $product );
		if ( ! $calc['ok'] ) {
			if ( 'out_of_scope' === $calc['reason'] ) {
				return array( 'status' => 'out' );
			}
			return array( 'status' => 'skipped', 'reason' => $calc['reason'] );
		}
		$decimals = wc_get_price_decimals();
		$new      = wc_format_decimal( $calc['total'], $decimals );
		$current  = (string) $product->get_regular_price();
		$changed  = ( '' === $current ) || abs( (float) $current - (float) $new ) > 0.000001;
		$sale     = (string) $product->get_sale_price();
		$sale_bad = ( '' !== $sale && (float) $sale >= (float) $new );

		if ( $apply && ( $changed || $sale_bad ) ) {
			$product->set_regular_price( $new );
			if ( $sale_bad ) {
				$product->set_sale_price( '' );
				One_Navasan_Logger::add( 'info', sprintf( 'Removed sale price of product %d (it was not below the new regular price).', $product->get_id() ) );
			}
			$product->save();
		}
		return array(
			'status'    => $changed ? 'updated' : 'unchanged',
			'current'   => $current,
			'new'       => $new,
			'breakdown' => $calc,
			'sale_bad'  => $sale_bad,
		);
	}

	/**
	 * Next product IDs after the cursor.
	 *
	 * @param int $cursor Last processed ID.
	 * @param int $limit  Limit.
	 * @return int[]
	 */
	private static function next_ids( $cursor, $limit ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','pending','private') AND ID > %d ORDER BY ID ASC LIMIT %d",
				(int) $cursor,
				(int) $limit
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Process a chunk of products.
	 *
	 * @param string $mode   preview|apply.
	 * @param int    $cursor Last processed product ID.
	 * @param int    $shown  Preview rows already shown by the client.
	 * @return array|WP_Error
	 */
	public static function process_chunk( $mode, $cursor, $shown = 0 ) {
		$apply = ( 'apply' === $mode );
		if ( null === One_Navasan_Rate_Service::store_rate() ) {
			return new WP_Error( 'one_navasan_no_rate', __( 'ابتدا نرخ را دریافت کنید یا نرخ دستی وارد کنید.', ONE_NAVASAN_DOMAIN ) );
		}
		$ids      = self::next_ids( $cursor, self::CHUNK );
		$counts   = array( 'products' => 0, 'items' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => array(), 'examples' => array() );
		$rows     = '';
		$rows_cnt = 0;
		$last     = (int) $cursor;

		foreach ( $ids as $id ) {
			$last    = $id;
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$items = array();
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child ) {
						$items[] = $child;
					}
				}
			} elseif ( $product->is_type( 'simple' ) ) {
				$items[] = $product;
			}
			$counts['products']++;
			$touched = false;
			foreach ( $items as $item ) {
				$r = self::process_one( $item, $apply );
				if ( 'out' === $r['status'] ) {
					continue;
				}
				$counts['items']++;
				if ( 'skipped' === $r['status'] ) {
					$counts['skipped'][ $r['reason'] ] = ( isset( $counts['skipped'][ $r['reason'] ] ) ? $counts['skipped'][ $r['reason'] ] : 0 ) + 1;
					if ( count( $counts['examples'] ) < 15 ) {
						$counts['examples'][] = array( 'id' => $item->get_id(), 'name' => $item->get_name(), 'reason' => self::reason_label( $r['reason'] ) );
					}
					continue;
				}
				$counts[ $r['status'] ]++;
				if ( 'updated' === $r['status'] ) {
					$touched = true;
				}
				if ( ! $apply && ( $shown + $rows_cnt ) < self::SHOW_ROWS ) {
					$rows .= self::preview_row( $item, $r );
					$rows_cnt++;
				}
			}
			if ( $apply && $touched && $product->is_type( 'variable' ) ) {
				WC_Product_Variable::sync( $product->get_id() );
				wc_delete_product_transients( $product->get_id() );
			}
		}

		return array(
			'done'       => count( $ids ) < self::CHUNK,
			'cursor'     => $last,
			'rows_html'  => $rows,
			'rows_count' => $rows_cnt,
			'counts'     => $counts,
		);
	}

	/**
	 * HTML row for the preview table (escaped).
	 *
	 * @param WC_Product $product Product.
	 * @param array      $r       process_one() result.
	 * @return string
	 */
	private static function preview_row( $product, $r ) {
		$b    = $r['breakdown'];
		$fmt  = function ( $n ) {
			return One_Navasan_Number_Formatter::format( $n, true, 'en' );
		};
		$name = $product->get_name();
		$link = get_edit_post_link( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(), 'raw' );
		$detail = sprintf(
			/* translators: breakdown parts. */
			__( 'نرخ عیار %1$s × وزن %2$s = طلا %3$s | اجرت %4$s | سود %5$s | مالیات %6$s | اضافه %7$s', ONE_NAVASAN_DOMAIN ),
			$fmt( $b['karat_rate'] ),
			$fmt( $b['inputs']['weight'] ),
			$fmt( $b['gold'] ),
			$fmt( $b['wage'] ),
			$fmt( $b['profit'] ),
			$fmt( $b['tax'] ),
			$fmt( $b['extra'] )
		);
		return '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</td>'
			. '<td>' . esc_html( '' === $r['current'] ? '—' : $fmt( (float) $r['current'] ) ) . '</td>'
			. '<td><strong>' . esc_html( $fmt( (float) $r['new'] ) ) . '</strong></td>'
			. '<td class="one-detail">' . esc_html( $detail ) . ( $r['sale_bad'] ? ' <em>' . esc_html__( '(قیمت حراج پاک می‌شود)', ONE_NAVASAN_DOMAIN ) . '</em>' : '' ) . '</td></tr>';
	}

	/* ------------------------------------------------------------------ */
	/* Automatic recalculation (background)                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Hash of everything that influences prices.
	 *
	 * @return string
	 */
	public static function pricing_hash() {
		$s    = One_Navasan_Settings::all();
		$keys = array( 'base_karat', 'round_step', 'round_dir', 'profit_pct', 'tax_pct', 'wage_pct', 'weight_source', 'weight_attr', 'weight_unit', 'wage_source', 'wage_attr', 'karat_source', 'karat_attr', 'default_karat', 'scope', 'scope_cats', 'currency_unit' );
		$part = array( 'rate' => One_Navasan_Rate_Service::store_rate() );
		foreach ( $keys as $k ) {
			$part[ $k ] = $s[ $k ];
		}
		return md5( wp_json_encode( $part ) );
	}

	/**
	 * Start a background recalculation when prices are enabled and something changed.
	 */
	public static function maybe_start_recalculation() {
		if ( ! get_option( self::ENABLED ) ) {
			return; // Nothing is touched until the admin previewed and applied once.
		}
		if ( null === One_Navasan_Rate_Service::store_rate() || get_option( self::APPLIED_HASH ) === self::pricing_hash() ) {
			return;
		}
		self::start_background();
	}

	/**
	 * Start the background job.
	 */
	public static function start_background() {
		$job = get_option( self::JOB );
		if ( is_array( $job ) && 'running' === $job['status'] && ( time() - (int) $job['touched'] ) < 600 ) {
			return;
		}
		update_option(
			self::JOB,
			array(
				'status'  => 'running',
				'cursor'  => 0,
				'hash'    => self::pricing_hash(),
				'started' => time(),
				'touched' => time(),
				'counts'  => array( 'products' => 0, 'items' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => array(), 'examples' => array() ),
			),
			false
		);
		self::queue_step();
	}

	/**
	 * Queue the next step.
	 */
	private static function queue_step() {
		if ( function_exists( 'as_enqueue_async_action' ) && class_exists( 'ActionScheduler' ) && did_action( 'init' ) ) {
			as_enqueue_async_action( self::JOB_HOOK, array(), 'one-navasan' );
		} else {
			wp_schedule_single_event( time() + 2, self::JOB_HOOK );
		}
	}

	/**
	 * Background step.
	 */
	public static function background_step() {
		$job = get_option( self::JOB );
		if ( ! is_array( $job ) || 'running' !== $job['status'] ) {
			return;
		}
		if ( get_transient( self::LOCK ) ) {
			return;
		}
		set_transient( self::LOCK, 1, 120 );
		$chunk = self::process_chunk( 'apply', (int) $job['cursor'] );
		if ( is_wp_error( $chunk ) ) {
			$job['status'] = 'failed';
			update_option( self::JOB, $job, false );
			One_Navasan_Logger::add( 'error', 'Background recalculation stopped: ' . $chunk->get_error_message() );
			delete_transient( self::LOCK );
			return;
		}
		$job['cursor']  = $chunk['cursor'];
		$job['touched'] = time();
		$job['counts']  = self::merge_counts( $job['counts'], $chunk['counts'] );
		if ( $chunk['done'] ) {
			self::finish( $job['counts'], $job['hash'] );
			delete_option( self::JOB );
		} else {
			update_option( self::JOB, $job, false );
			self::queue_step();
		}
		delete_transient( self::LOCK );
	}

	/**
	 * Merge two count arrays.
	 *
	 * @param array $a First.
	 * @param array $b Second.
	 * @return array
	 */
	public static function merge_counts( array $a, array $b ) {
		foreach ( array( 'products', 'items', 'updated', 'unchanged' ) as $k ) {
			$a[ $k ] += $b[ $k ];
		}
		foreach ( $b['skipped'] as $reason => $n ) {
			$a['skipped'][ $reason ] = ( isset( $a['skipped'][ $reason ] ) ? $a['skipped'][ $reason ] : 0 ) + $n;
		}
		$a['examples'] = array_slice( array_merge( $a['examples'], $b['examples'] ), 0, 15 );
		return $a;
	}

	/**
	 * Finish an apply run: caches, report, hash.
	 *
	 * @param array       $counts Totals.
	 * @param string|null $hash   Pricing hash at the start of the run.
	 */
	public static function finish( array $counts, $hash = null ) {
		self::clear_caches();
		update_option( self::APPLIED_HASH, null === $hash ? self::pricing_hash() : $hash, false );
		update_option( self::REPORT, array( 'time' => time(), 'counts' => $counts ), false );
		One_Navasan_Logger::add( 'info', sprintf( 'Prices applied: %d updated, %d unchanged, %d skipped.', $counts['updated'], $counts['unchanged'], array_sum( $counts['skipped'] ) ) );
		/**
		 * Fires after a pricing run finished.
		 *
		 * @param array $counts Totals.
		 */
		do_action( 'one_navasan_prices_applied', $counts );
	}

	/**
	 * Clear WooCommerce caches related to prices.
	 */
	public static function clear_caches() {
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'product', true );
		}
		delete_transient( 'wc_products_onsale' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'products' );
		}
	}
}
