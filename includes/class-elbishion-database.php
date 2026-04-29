<?php
/**
 * Database access layer.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles submission persistence and queries.
 */
class Elbishion_Database {

	const ALLOWED_STATUSES = array( 'unread', 'read', 'starred', 'archived' );
	const ALLOWED_SOURCES  = array( 'shortcode', 'elementor', 'contact_form_7', 'wpforms', 'gravity_forms', 'fluent_forms', 'ninja_forms', 'formidable_forms', 'jetformbuilder', 'forminator', 'woocommerce', 'wordpress_native', 'custom_html', 'api' );

	/**
	 * Submissions table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'elbishion_submissions';
	}

	/**
	 * Insert a submission.
	 *
	 * @param string $form_name Submitted form name.
	 * @param array  $submitted_data Submitted field data.
	 * @param array  $args Optional metadata.
	 * @return int|false Insert ID on success, false on failure or duplicate.
	 */
	public static function insert_submission( $form_name, $submitted_data, $args = array() ) {
		global $wpdb;

		if ( ! is_array( $submitted_data ) ) {
			return false;
		}

		$args['form_name'] = $form_name;
		$args['fields']    = $submitted_data;

		$submission = Elbishion_Normalizer::normalize_submission( $args );
		$settings   = Elbishion_Settings::get_settings();
		$now        = current_time( 'mysql' );

		do_action( 'elbishion_before_save_submission', $submission );

		if ( ! empty( $settings['duplicate_prevention'] ) && self::is_duplicate( $submission['submission_hash'], $settings ) ) {
			return false;
		}

		$submitted_json = wp_json_encode( $submission['submitted_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$raw_json       = wp_json_encode( $submission['raw_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $submitted_json || false === $raw_json ) {
			return false;
		}

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'source'           => $submission['source'],
				'source_plugin'    => $submission['source_plugin'],
				'source_form_id'   => $submission['source_form_id'],
				'source_form_name' => $submission['source_form_name'],
				'form_name'        => $submission['form_name'],
				'page_url'         => $submission['page_url'],
				'referer_url'      => $submission['referer_url'],
				'user_id'          => $submission['user_id'],
				'user_ip'          => $submission['user_ip'],
				'user_agent'       => $submission['user_agent'],
				'submitted_data'   => $submitted_json,
				'raw_data'         => $raw_json,
				'submission_hash'  => $submission['submission_hash'],
				'has_attachments'  => $submission['has_attachments'],
				'status'           => $submission['status'],
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$submission_id = absint( $wpdb->insert_id );

		do_action( 'elbishion_submission_saved', $submission_id, $submission['form_name'], $submission['submitted_data'], $args );
		do_action( 'elbishion_after_save_submission', $submission_id, $submission );

		return $submission_id;
	}

	/**
	 * Check whether a submission hash is already present inside the configured window.
	 *
	 * @param string $hash Submission hash.
	 * @param array  $settings Plugin settings.
	 * @return bool
	 */
	private static function is_duplicate( $hash, $settings ) {
		global $wpdb;

		if ( '' === (string) $hash ) {
			return false;
		}

		$table  = self::table_name();
		$window = ! empty( $settings['duplicate_window'] ) ? max( 1, absint( $settings['duplicate_window'] ) ) : 90;
		$since  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $window );

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE submission_hash = %s AND created_at >= %s LIMIT 1",
				$hash,
				$since
			)
		);
	}

	/**
	 * Get one submission by ID.
	 *
	 * @param int $id Submission ID.
	 * @return object|null
	 */
	public static function get_submission( $id ) {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $id )
			)
		);
	}

	/**
	 * Query submissions.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_submissions( $args = array() ) {
		global $wpdb;

		$args   = self::normalize_query_args( $args );
		$table  = self::table_name();
		$where  = self::build_where_clause( $args );
		$order  = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$values = $where['values'];
		$sql    = "SELECT * FROM {$table} WHERE {$where['sql']} ORDER BY created_at {$order}, id {$order}";

		if ( $args['per_page'] > 0 ) {
			$sql      .= ' LIMIT %d OFFSET %d';
			$values[] = absint( $args['per_page'] );
			$values[] = absint( $args['offset'] );
		}

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count submissions for query.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public static function count_submissions( $args = array() ) {
		global $wpdb;

		$args  = self::normalize_query_args( $args );
		$table = self::table_name();
		$where = self::build_where_clause( $args );
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE {$where['sql']}";

		if ( ! empty( $where['values'] ) ) {
			$sql = $wpdb->prepare( $sql, $where['values'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return absint( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count unread submissions.
	 *
	 * @return int
	 */
	public static function count_unread() {
		return self::count_submissions( array( 'status' => 'unread' ) );
	}

	/**
	 * Get form names for filter dropdown.
	 *
	 * @param string $source_plugin Optional source plugin.
	 * @return array
	 */
	public static function get_form_names( $source_plugin = '' ) {
		global $wpdb;

		$table         = self::table_name();
		$source_plugin = sanitize_key( $source_plugin );

		if ( in_array( $source_plugin, self::ALLOWED_SOURCES, true ) ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT form_name FROM {$table} WHERE form_name <> '' AND source_plugin = %s ORDER BY form_name ASC",
					$source_plugin
				)
			);
		}

		return $wpdb->get_col( "SELECT DISTINCT form_name FROM {$table} WHERE form_name <> '' ORDER BY form_name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get grouped forms overview rows.
	 *
	 * @return array
	 */
	public static function get_forms_overview() {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_results(
			"SELECT source_plugin, form_name, COUNT(*) AS total_submissions, SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) AS unread_submissions, MAX(created_at) AS last_submission_at
			FROM {$table}
			GROUP BY source_plugin, form_name
			ORDER BY last_submission_at DESC"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Update one or more submission statuses.
	 *
	 * @param array|string $ids IDs.
	 * @param string       $status New status.
	 * @return int|false
	 */
	public static function update_status( $ids, $status ) {
		global $wpdb;

		$ids    = array_filter( array_map( 'absint', (array) $ids ) );
		$status = sanitize_key( $status );

		if ( empty( $ids ) || ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table        = self::table_name();
		$values       = array_merge( array( $status, current_time( 'mysql' ) ), $ids );

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
				$values
			)
		);
	}

	/**
	 * Delete one or more submissions.
	 *
	 * @param array|string $ids IDs.
	 * @return int|false
	 */
	public static function delete_submissions( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', (array) $ids ) );

		if ( empty( $ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table        = self::table_name();

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$placeholders})",
				$ids
			)
		);
	}

	/**
	 * Delete old submissions according to retention settings.
	 *
	 * @param int $days Days to retain. Zero disables cleanup.
	 * @return int|false
	 */
	public static function delete_older_than( $days ) {
		global $wpdb;

		$days = absint( $days );

		if ( 0 === $days ) {
			return false;
		}

		$table = self::table_name();
		$date  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$date
			)
		);
	}

	/**
	 * Decode submitted JSON into an array.
	 *
	 * @param string $json JSON string.
	 * @return array
	 */
	public static function decode_data( $json ) {
		$data = json_decode( (string) $json, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( isset( $data['fields'] ) && is_array( $data['fields'] ) ) {
			return $data;
		}

		$fields = array();

		foreach ( $data as $key => $value ) {
			$fields[] = array(
				'field_id'    => (string) $key,
				'field_label' => Elbishion_Normalizer::humanize_label( $key ),
				'field_type'  => is_array( $value ) ? 'array' : 'text',
				'field_value' => $value,
			);
		}

		return array(
			'fields'      => $fields,
			'attachments' => array(),
		);
	}

	/**
	 * Normalize query args.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	private static function normalize_query_args( $args ) {
		$defaults = array(
			'search'          => '',
			'form_name'       => '',
			'source'          => '',
			'source_plugin'   => '',
			'status'          => '',
			'date_from'       => '',
			'date_to'         => '',
			'page_url'        => '',
			'user_scope'      => '',
			'user_id'         => '',
			'has_attachments' => '',
			'order'           => 'DESC',
			'per_page'        => 20,
			'offset'          => 0,
			'ids'             => array(),
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Build prepared WHERE clause parts.
	 *
	 * @param array $args Query args.
	 * @return array{sql:string,values:array}
	 */
	private static function build_where_clause( $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['ids'] ) ) {
			$ids = array_filter( array_map( 'absint', (array) $args['ids'] ) );

			if ( ! empty( $ids ) ) {
				$where[] = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
				$values  = array_merge( $values, $ids );
			}
		}

		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::ALLOWED_STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['form_name'] ) ) {
			$where[]  = 'form_name = %s';
			$values[] = sanitize_text_field( $args['form_name'] );
		}

		$source_plugin = ! empty( $args['source_plugin'] ) ? $args['source_plugin'] : $args['source'];

		if ( ! empty( $source_plugin ) && in_array( $source_plugin, self::ALLOWED_SOURCES, true ) ) {
			$where[]  = 'source_plugin = %s';
			$values[] = sanitize_key( $source_plugin );
		}

		if ( '' !== (string) $args['has_attachments'] ) {
			$where[]  = 'has_attachments = %d';
			$values[] = absint( $args['has_attachments'] );
		}

		if ( 'guest' === $args['user_scope'] ) {
			$where[] = '(user_id IS NULL OR user_id = 0)';
		} elseif ( 'user' === $args['user_scope'] ) {
			$where[] = 'user_id > 0';
		}

		if ( '' !== (string) $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( '' !== (string) $args['page_url'] ) {
			$where[]  = 'page_url LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['page_url'] ) ) . '%';
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(form_name LIKE %s OR page_url LIKE %s OR referer_url LIKE %s OR source_plugin LIKE %s OR submitted_data LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( ! empty( $args['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		return array(
			'sql'    => implode( ' AND ', $where ),
			'values' => $values,
		);
	}
}
