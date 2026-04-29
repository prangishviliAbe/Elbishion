<?php
/**
 * Admin submissions list table.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Modern submissions table.
 */
class Elbishion_Admin_List extends WP_List_Table {

	/**
	 * Active status filter.
	 *
	 * @var string
	 */
	private $status = '';

	/**
	 * Current admin message.
	 *
	 * @var string
	 */
	private $message = '';

	/**
	 * Form names for dropdown.
	 *
	 * @var array
	 */
	private $form_names = array();

	/**
	 * Constructor.
	 *
	 * @param string $status Status filter.
	 */
	public function __construct( $status = '' ) {
		parent::__construct(
			array(
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			)
		);

		$this->status     = in_array( $status, Elbishion_Database::ALLOWED_STATUSES, true ) ? $status : '';
		$this->form_names = Elbishion_Database::get_form_names();
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox">',
			'source'       => __( 'Source Plugin', 'elbishion' ),
			'status'       => __( 'სტატუსი', 'elbishion' ),
			'form_name'    => __( 'სახელი და გვარი', 'elbishion' ),
			'contact'      => __( 'საკონტაქტო ინფორმაცია', 'elbishion' ),
			'message'      => __( 'კურსის დასახელება', 'elbishion' ),
			'page_url'     => __( 'გვერდის ბმული', 'elbishion' ),
			'created_at'   => __( 'თარიღი', 'elbishion' ),
			'row_actions'  => __( 'ქმედებები', 'elbishion' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'mark_read'   => __( 'წაკითხულად მონიშვნა', 'elbishion' ),
			'mark_unread' => __( 'წაუკითხავად მონიშვნა', 'elbishion' ),
			'star'        => __( 'ვარსკვლავით მონიშვნა', 'elbishion' ),
			'archive'     => __( 'დაარქივება', 'elbishion' ),
			'export'      => __( 'CSV ექსპორტი', 'elbishion' ),
			'delete'      => __( 'წაშლა', 'elbishion' ),
		);
	}

	/**
	 * Prepare table data.
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$settings = Elbishion_Settings::get_settings();
		$per_page = absint( $settings['items_per_page'] );
		$paged    = max( 1, absint( $_REQUEST['paged'] ?? 1 ) );
		$order    = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) ? 'ASC' : 'DESC';
		$args     = array(
			'status'    => $this->status ? $this->status : $this->request_value( 'status' ),
			'source'    => $this->request_value( 'source' ),
			'search'    => $this->request_value( 's' ),
			'form_name' => $this->request_value( 'form_name' ),
			'has_attachments' => $this->request_value( 'has_attachments' ),
			'user_scope' => $this->request_value( 'user_scope' ),
			'page_url' => $this->request_value( 'page_url' ),
			'date_from' => $this->request_value( 'date_from' ),
			'date_to'   => $this->request_value( 'date_to' ),
			'order'     => $order,
			'per_page'  => $per_page,
			'offset'    => ( $paged - 1 ) * $per_page,
		);

		if ( ! in_array( $args['status'], Elbishion_Database::ALLOWED_STATUSES, true ) ) {
			$args['status'] = '';
		}

		if ( ! in_array( $args['source'], Elbishion_Database::ALLOWED_SOURCES, true ) ) {
			$args['source'] = '';
		}

		$this->form_names = Elbishion_Database::get_form_names( $args['source'] );

		$total_items = Elbishion_Database::count_submissions( $args );
		$this->items = Elbishion_Database::get_submissions( $args );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'form_name' );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Print admin notice from actions.
	 */
	public function maybe_print_notice() {
		if ( empty( $this->message ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $this->message )
		);
	}

	/**
	 * Default column output.
	 *
	 * @param object $item Row.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'status':
				return $this->column_status( $item );
			case 'source':
				return $this->column_source( $item );
			case 'form_name':
				return $this->column_form_name( $item );
			case 'contact':
				return $this->column_contact( $item );
			case 'message':
				return $this->column_message( $item );
			case 'page_url':
				return $this->column_page_url( $item );
			case 'created_at':
				return $this->column_created_at( $item );
			case 'row_actions':
				return $this->column_actions( $item );
			default:
				return '';
		}
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="submissions[]" value="%d">', absint( $item->id ) );
	}

	/**
	 * Status indicator.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_status( $item ) {
		return sprintf(
			'<span class="elbishion-status-dot elbishion-status-%1$s" title="%2$s"></span><span class="screen-reader-text">%2$s</span>',
			esc_attr( $item->status ),
			esc_attr( self::status_label( $item->status ) )
		);
	}

	/**
	 * Submission source column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_source( $item ) {
		$source = isset( $item->source_plugin ) && $item->source_plugin ? $item->source_plugin : ( $item->source ?? 'api' );

		return sprintf(
			'<span class="elbishion-source-badge elbishion-source-%1$s">%2$s</span>',
			esc_attr( $source ),
			esc_html( self::source_label( $source ) )
		);
	}

	/**
	 * Form name column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_form_name( $item ) {
		$data  = Elbishion_Database::decode_data( $item->submitted_data );
		$name  = $this->find_value( $data, array( 'name', 'full name', 'first name', 'სახელი', 'სახელი და გვარი', 'სრული სახელი' ) );
		$title = $name ? $name : $item->form_name;
		$url = add_query_arg(
			array(
				'page' => 'elbishion',
				'view' => 'detail',
				'id'   => absint( $item->id ),
			),
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<a class="elbishion-primary-link" href="%1$s">%2$s</a><div class="elbishion-row-id">%3$s · #%4$d</div>',
			esc_url( $url ),
			esc_html( $title ),
			esc_html( $item->form_name ),
			absint( $item->id )
		);
	}

	/**
	 * Main contact info column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_contact( $item ) {
		$data  = Elbishion_Database::decode_data( $item->submitted_data );
		$name  = $this->find_value( $data, array( 'name', 'full name', 'first name', 'სახელი', 'სახელი და გვარი', 'სრული სახელი' ) );
		$email = $this->find_value( $data, array( 'email', 'email address', 'ელფოსტა', 'ელ. ფოსტა', 'ელ-ფოსტა', 'მეილი', 'e-mail' ) );
		$phone = $this->find_value( $data, array( 'phone', 'telephone', 'mobile', 'phone_number', 'ტელეფონი', 'მობილური', 'ტელეფონის ნომერი' ) );
		$html  = '';

		if ( $name ) {
			$html .= '<strong>' . esc_html( $name ) . '</strong>';
		}

		if ( $email ) {
			$html .= '<span><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></span>';
		}

		if ( $phone ) {
			$html .= '<span>' . esc_html( $phone ) . '</span>';
		}

		return $html ? '<div class="elbishion-contact-stack">' . $html . '</div>' : '<span class="elbishion-muted">&mdash;</span>';
	}

	/**
	 * Message preview column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_message( $item ) {
		$data    = Elbishion_Database::decode_data( $item->submitted_data );
		$message = $this->find_message_preview( $data );

		return '<span class="elbishion-message-preview">' . esc_html( wp_trim_words( (string) $message, 18, '...' ) ) . '</span>';
	}

	/**
	 * Page URL column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_page_url( $item ) {
		if ( empty( $item->page_url ) ) {
			return '<span class="elbishion-muted">&mdash;</span>';
		}

		return sprintf(
			'<a class="elbishion-url" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $item->page_url ),
			esc_html( wp_trim_words( $item->page_url, 7, '...' ) )
		);
	}

	/**
	 * Date column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->created_at ) );
	}

	/**
	 * Row actions column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	private function column_actions( $item ) {
		$view_url = add_query_arg(
			array(
				'page' => 'elbishion',
				'view' => 'detail',
				'id'   => absint( $item->id ),
			),
			admin_url( 'admin.php' )
		);

		$archive_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'             => 'elbishion',
					'elbishion_action' => 'archive',
					'id'               => absint( $item->id ),
				),
				admin_url( 'admin.php' )
			),
			'elbishion_submission_action_' . absint( $item->id )
		);

		return sprintf(
			'<div class="elbishion-actions"><a class="button button-small" href="%1$s">%2$s</a><a class="button button-small elbishion-confirm-action" href="%3$s">%4$s</a></div>',
			esc_url( $view_url ),
			esc_html__( 'ნახვა', 'elbishion' ),
			esc_url( $archive_url ),
			esc_html__( 'არქივი', 'elbishion' )
		);
	}

	/**
	 * Extra filters.
	 *
	 * @param string $which Top or bottom nav.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_form   = $this->request_value( 'form_name' );
		$current_source = $this->request_value( 'source' );
		$date_from    = $this->request_value( 'date_from' );
		$date_to      = $this->request_value( 'date_to' );
		$order        = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) ? 'asc' : 'desc';
		?>
		<div class="alignleft actions elbishion-filters">
			<select name="source" aria-label="<?php esc_attr_e( 'Filter by source', 'elbishion' ); ?>">
				<option value=""><?php esc_html_e( 'All sources', 'elbishion' ); ?></option>
				<?php foreach ( self::source_labels() as $source => $label ) : ?>
					<option value="<?php echo esc_attr( $source ); ?>" <?php selected( $current_source, $source ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="form_name" aria-label="<?php esc_attr_e( 'ფორმის სახელით გაფილტვრა', 'elbishion' ); ?>">
				<option value=""><?php esc_html_e( 'ყველა ფორმა', 'elbishion' ); ?></option>
				<?php foreach ( $this->form_names as $form_name ) : ?>
					<option value="<?php echo esc_attr( $form_name ); ?>" <?php selected( $current_form, $form_name ); ?>><?php echo esc_html( $form_name ); ?></option>
				<?php endforeach; ?>
			</select>

			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" aria-label="<?php esc_attr_e( 'საწყისი თარიღი', 'elbishion' ); ?>">
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" aria-label="<?php esc_attr_e( 'საბოლოო თარიღი', 'elbishion' ); ?>">

			<select name="order" aria-label="<?php esc_attr_e( 'დალაგება', 'elbishion' ); ?>">
				<option value="desc" <?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'ჯერ ახალი', 'elbishion' ); ?></option>
				<option value="asc" <?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'ჯერ ძველი', 'elbishion' ); ?></option>
			</select>

			<?php submit_button( __( 'გაფილტვრა', 'elbishion' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		?>
		<div class="elbishion-empty-state">
			<div class="elbishion-empty-icon">E</div>
			<h2><?php esc_html_e( 'განაცხადები ვერ მოიძებნა', 'elbishion' ); ?></h2>
			<p><?php esc_html_e( 'ახალი განაცხადები shortcode-დან, developer hook-იდან ან Elementor Pro Forms-იდან აქ გამოჩნდება.', 'elbishion' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		$action = $this->current_action();

		if ( empty( $action ) || 'export' === $action ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'ამ ქმედების შესრულების უფლება არ გაქვთ.', 'elbishion' ) );
		}

		check_admin_referer( 'bulk-submissions' );

		$ids = isset( $_REQUEST['submissions'] ) && is_array( $_REQUEST['submissions'] )
			? array_map( 'absint', wp_unslash( $_REQUEST['submissions'] ) )
			: array();

		if ( empty( $ids ) ) {
			return;
		}

		switch ( $action ) {
			case 'mark_read':
				Elbishion_Database::update_status( $ids, 'read' );
				$this->message = __( 'მონიშნული განაცხადები წაკითხულად მოინიშნა.', 'elbishion' );
				break;
			case 'mark_unread':
				Elbishion_Database::update_status( $ids, 'unread' );
				$this->message = __( 'მონიშნული განაცხადები წაუკითხავად მოინიშნა.', 'elbishion' );
				break;
			case 'star':
				Elbishion_Database::update_status( $ids, 'starred' );
				$this->message = __( 'მონიშნულ განაცხადებს ვარსკვლავი დაემატა.', 'elbishion' );
				break;
			case 'archive':
				Elbishion_Database::update_status( $ids, 'archived' );
				$this->message = __( 'მონიშნული განაცხადები დაარქივდა.', 'elbishion' );
				break;
			case 'delete':
				Elbishion_Database::delete_submissions( $ids );
				$this->message = __( 'მონიშნული განაცხადები წაიშალა.', 'elbishion' );
				break;
		}
	}

	/**
	 * Find a likely value by key.
	 *
	 * @param array $data Submitted data.
	 * @param array $keys Possible keys.
	 * @return string
	 */
	private function find_value( $data, $keys ) {
		$structured_value = $this->find_structured_field_value( $data, $keys );

		if ( '' !== $structured_value ) {
			return $structured_value;
		}

		$normalized = array();

		foreach ( $data as $key => $value ) {
			$normalized[ strtolower( trim( (string) $key ) ) ] = is_array( $value ) ? wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $value;
		}

		foreach ( $keys as $key ) {
			if ( isset( $normalized[ strtolower( $key ) ] ) && '' !== $normalized[ strtolower( $key ) ] ) {
				return $normalized[ strtolower( $key ) ];
			}
		}

		return '';
	}

	/**
	 * Find a value inside structured Elementor field JSON.
	 *
	 * @param array $data Submitted data.
	 * @param array $keys Possible field IDs or labels.
	 * @return string
	 */
	private function find_structured_field_value( $data, $keys ) {
		if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return '';
		}

		$normalized_keys = array_map( 'strtolower', array_map( 'trim', $keys ) );

		foreach ( $data['fields'] as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_id = strtolower( trim( (string) ( $field['field_id'] ?? ( $field['id'] ?? '' ) ) ) );
			$label    = strtolower( trim( (string) ( $field['field_label'] ?? ( $field['label'] ?? '' ) ) ) );

			if ( ! in_array( $field_id, $normalized_keys, true ) && ! in_array( $label, $normalized_keys, true ) ) {
				continue;
			}

			$value = $field['field_value'] ?? ( $field['value'] ?? '' );

			return is_array( $value ) ? (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $value;
		}

		return '';
	}

	/**
	 * Get request value.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function request_value( $key ) {
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	/**
	 * Build a readable preview from likely message fields.
	 *
	 * @param array $data Submitted data.
	 * @return string
	 */
	private function find_message_preview( $data ) {
		$course_name = $this->find_course_name_preview( $data );

		if ( '' !== $course_name ) {
			return $course_name;
		}

		$known = $this->find_value(
			$data,
			array(
				'message',
				'your message',
				'comments',
				'comment',
				'description',
				'შეტყობინება',
				'მესიჯი',
				'კომენტარი',
				'აღწერა',
				'ტექსტი',
			)
		);

		if ( $known ) {
			return $known;
		}

		if ( ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
			return __( 'კურსის დასახელება არ არის', 'elbishion' );
		}

		$skip_keys = array( 'name', 'full name', 'first name', 'სახელი', 'სახელი და გვარი', 'email', 'email address', 'ელფოსტა', 'ელ. ფოსტა', 'phone', 'phone_number', 'ტელეფონი', 'მობილური' );
		$best      = '';

		foreach ( $data as $key => $value ) {
			$normalized_key = strtolower( trim( (string) $key ) );

			if ( in_array( $normalized_key, $skip_keys, true ) ) {
				continue;
			}

			$value = is_array( $value ) ? wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $value;

			if ( strlen( $value ) > strlen( $best ) ) {
				$best = $value;
			}
		}

		return $best ? $best : __( 'კურსის დასახელება არ არის', 'elbishion' );
	}

	/**
	 * Find course name/title value from structured and legacy submissions.
	 *
	 * @param array $data Submitted data.
	 * @return string
	 */
	private function find_course_name_preview( $data ) {
		$course = $this->find_value(
			$data,
			array(
				'course',
				'course name',
				'course_name',
				'course title',
				'course_title',
				'კურსის დასახელება',
				'კურსის სახელი',
			)
		);

		if ( '' !== $course ) {
			return $course;
		}

		if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) || ! $this->has_course_context( $data['fields'] ) ) {
			return '';
		}

		foreach ( $data['fields'] as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = trim( (string) ( $field['field_label'] ?? ( $field['label'] ?? '' ) ) );
			$id    = trim( (string) ( $field['field_id'] ?? ( $field['id'] ?? '' ) ) );
			$value = $field['field_value'] ?? ( $field['value'] ?? '' );

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' === $value || $this->is_contact_or_course_meta_field( $label, $id ) ) {
				continue;
			}

			if ( $this->same_readable_label( $label, $value ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Check whether sibling fields indicate a course submission.
	 *
	 * @param array $fields Normalized fields.
	 * @return bool
	 */
	private function has_course_context( $fields ) {
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = (string) ( $field['field_label'] ?? ( $field['label'] ?? '' ) );
			$id    = (string) ( $field['field_id'] ?? ( $field['id'] ?? '' ) );

			if ( $this->contains_text( $label, array( 'კურსის ფასი', 'ხანგრძლივობა', 'course price', 'duration' ) ) || $this->contains_text( $id, array( 'course_price', 'course-price', 'duration' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Avoid using contact and course metadata fields as course titles.
	 *
	 * @param string $label Field label.
	 * @param string $id Field ID.
	 * @return bool
	 */
	private function is_contact_or_course_meta_field( $label, $id ) {
		$needles = array(
			'name',
			'full name',
			'first name',
			'email',
			'phone',
			'phone_number',
			'course price',
			'duration',
			'სახელი',
			'სახელი და გვარი',
			'ელფოსტა',
			'ტელეფონი',
			'ტელეფონის ნომერი',
			'კურსის ფასი',
			'ხანგრძლივობა',
		);

		return $this->contains_text( $label, $needles ) || $this->contains_text( $id, $needles );
	}

	/**
	 * Case-insensitive contains helper for ASCII while keeping Georgian text intact.
	 *
	 * @param string $value Haystack.
	 * @param array  $needles Needles.
	 * @return bool
	 */
	private function contains_text( $value, $needles ) {
		$value       = (string) $value;
		$value_lower = strtolower( $value );

		foreach ( $needles as $needle ) {
			$needle       = (string) $needle;
			$needle_lower = strtolower( $needle );

			if ( false !== strpos( $value_lower, $needle_lower ) || false !== strpos( $value, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compare readable versions of two field labels.
	 *
	 * @param string $left First label.
	 * @param string $right Second label.
	 * @return bool
	 */
	private function same_readable_label( $left, $right ) {
		$left  = strtolower( trim( str_replace( array( '_', '-' ), ' ', (string) $left ) ) );
		$right = strtolower( trim( str_replace( array( '_', '-' ), ' ', (string) $right ) ) );

		return '' !== $left && $left === $right;
	}

	/**
	 * Georgian status labels.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private static function source_labels() {
		return array(
			'shortcode'        => __( 'Shortcode', 'elbishion' ),
			'elementor'        => __( 'Elementor', 'elbishion' ),
			'contact_form_7'   => __( 'Contact Form 7', 'elbishion' ),
			'wpforms'          => __( 'WPForms', 'elbishion' ),
			'gravity_forms'    => __( 'Gravity Forms', 'elbishion' ),
			'fluent_forms'     => __( 'Fluent Forms', 'elbishion' ),
			'ninja_forms'      => __( 'Ninja Forms', 'elbishion' ),
			'formidable_forms' => __( 'Formidable Forms', 'elbishion' ),
			'jetformbuilder'   => __( 'JetFormBuilder', 'elbishion' ),
			'forminator'       => __( 'Forminator', 'elbishion' ),
			'woocommerce'      => __( 'WooCommerce', 'elbishion' ),
			'wordpress_native' => __( 'WordPress Native', 'elbishion' ),
			'custom_html'      => __( 'Custom HTML', 'elbishion' ),
			'api'              => __( 'API', 'elbishion' ),
		);
	}

	private static function source_label( $source ) {
		$labels = self::source_labels();

		return $labels[ $source ] ?? __( 'API', 'elbishion' );
	}

	private static function status_label( $status ) {
		$labels = array(
			'unread'   => __( 'წაუკითხავი', 'elbishion' ),
			'read'     => __( 'წაკითხული', 'elbishion' ),
			'starred'  => __( 'ვარსკვლავით მონიშნული', 'elbishion' ),
			'archived' => __( 'დაარქივებული', 'elbishion' ),
		);

		return $labels[ $status ] ?? $status;
	}
}
