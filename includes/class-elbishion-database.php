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
	 * @return int|false
	 */
	public static function insert_submission( $form_name, $submitted_data, $args = array() ) {
		global $wpdb;

		if ( ! is_array( $submitted_data ) ) {
			return false;
		}

		$form_name = sanitize_text_field( $form_name );
		$form_name = $form_name ? $form_name : __( 'უსახელო ფორმა', 'elbishion' );
		$status    = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'unread';
		$status    = in_array( $status, self::ALLOWED_STATUSES, true ) ? $status : 'unread';
		$settings  = Elbishion_Settings::get_settings();
		$now       = current_time( 'mysql' );

		$page_url = isset( $args['page_url'] ) ? esc_url_raw( $args['page_url'] ) : self::current_page_url();
		$user_ip  = '';
		if ( ! empty( $settings['save_ip'] ) ) {
			$user_ip = isset( $args['user_ip'] ) ? sanitize_text_field( $args['user_ip'] ) : self::current_ip();
		}

		$user_agent = '';
		if ( ! empty( $settings['save_user_agent'] ) ) {
			$user_agent = isset( $args['user_agent'] ) ? sanitize_textarea_field( $args['user_agent'] ) : self::current_user_agent();
		}

		$json = wp_json_encode( self::sanitize_data( $submitted_data ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			return false;
		}

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'form_name'      => $form_name,
				'page_url'       => $page_url,
				'user_ip'        => $user_ip,
				'user_agent'     => $user_agent,
				'submitted_data' => $json,
				'status'         => $status,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$submission_id = absint( $wpdb->insert_id );
		do_action( 'elbishion_submission_saved', $submission_id, $form_name, self::sanitize_data( $submitted_data ), $args );

		return $submission_id;
	}

	/**
	 * Recursively sanitize submitted data.
	 *
	 * @param mixed $data Raw data.
	 * @return mixed
	 */
	public static function sanitize_data( $data ) {
		if ( is_array( $data ) ) {
			$clean = array();

			foreach ( $data as $key => $value ) {
				$clean_key           = sanitize_text_field( (string) $key );
				$clean[ $clean_key ] = self::sanitize_data( $value );
			}

			return $clean;
		}

		if ( is_scalar( $data ) || null === $data ) {
			return sanitize_textarea_field( (string) $data );
		}

		return '';
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
	 * @return array
	 */
	public static function get_form_names() {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_col( "SELECT DISTINCT form_name FROM {$table} WHERE form_name <> '' ORDER BY form_name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Decode submitted JSON into an array.
	 *
	 * @param string $json JSON string.
	 * @return array
	 */
	public static function decode_data( $json ) {
		$data = json_decode( (string) $json, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Normalize query args.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	private static function normalize_query_args( $args ) {
		$defaults = array(
			'search'    => '',
			'form_name' => '',
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
			'order'     => 'DESC',
			'per_page'  => 20,
			'offset'    => 0,
			'ids'       => array(),
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

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(form_name LIKE %s OR page_url LIKE %s OR submitted_data LIKE %s)';
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

	/**
	 * Current request URL.
	 *
	 * @return string
	 */
	private static function current_page_url() {
		$referer = wp_get_referer();

		if ( $referer ) {
			return esc_url_raw( $referer );
		}

		return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
	}

	/**
	 * Current request IP.
	 *
	 * @return string
	 */
	private static function current_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Current request user agent.
	 *
	 * @return string
	 */
	private static function current_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}
}
