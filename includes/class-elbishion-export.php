<?php
/**
 * CSV export handling.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSV exports.
 */
class Elbishion_Export {

	/**
	 * Initialize export hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_export' ) );
	}

	/**
	 * Detect and run exports before admin output.
	 */
	public static function maybe_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( 0 !== strpos( $page, 'elbishion' ) ) {
			return;
		}

		$is_bulk_export = isset( $_REQUEST['action'] ) && 'export' === sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
		$is_bulk_export = $is_bulk_export || ( isset( $_REQUEST['action2'] ) && 'export' === sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) ) );
		$is_button      = isset( $_GET['elbishion_export'] );

		if ( ! $is_bulk_export && ! $is_button ) {
			return;
		}

		if ( $is_bulk_export ) {
			check_admin_referer( 'bulk-submissions' );
			$ids = isset( $_REQUEST['submissions'] ) && is_array( $_REQUEST['submissions'] )
				? array_map( 'absint', wp_unslash( $_REQUEST['submissions'] ) )
				: array();

			if ( empty( $ids ) ) {
				wp_die( esc_html__( 'ექსპორტისთვის მონიშნეთ მინიმუმ ერთი განაცხადი.', 'elbishion' ) );
			}

			self::send_csv( array( 'ids' => $ids ) );
		}

		check_admin_referer( 'elbishion_export' );

		$type = sanitize_key( wp_unslash( $_GET['elbishion_export'] ) );
		$args = array(
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'source'    => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'form_name' => isset( $_GET['form_name'] ) ? sanitize_text_field( wp_unslash( $_GET['form_name'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'order'     => isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC',
			'per_page'  => -1,
		);

		if ( 'all' === $type ) {
			$args = array( 'per_page' => -1 );
		}

		self::send_csv( $args );
	}

	/**
	 * Send CSV response.
	 *
	 * @param array $args Query args.
	 */
	private static function send_csv( $args ) {
		$rows     = Elbishion_Database::get_submissions( wp_parse_args( $args, array( 'per_page' => -1 ) ) );
		$filename = 'elbishion-submissions-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		fputcsv(
			$output,
			array(
				'ID',
				'Source',
				'ფორმის სახელი',
				'შევსებული მონაცემები',
				'გვერდის ბმული',
				'IP',
				'სტატუსი',
				'თარიღი',
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row->id,
					isset( $row->source ) ? $row->source : 'api',
					$row->form_name,
					$row->submitted_data,
					$row->page_url,
					$row->user_ip,
					$row->status,
					$row->created_at,
				)
			);
		}

		fclose( $output );
		exit;
	}
}
