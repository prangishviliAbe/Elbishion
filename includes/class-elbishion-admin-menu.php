<?php
/**
 * Admin menu and pages.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress admin UI.
 */
class Elbishion_Admin_Menu {

	/**
	 * Initialize admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_extra_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_single_action' ) );
		add_filter( 'gettext', array( __CLASS__, 'translate_core_table_text' ), 10, 3 );
	}

	/**
	 * Register universal inbox subpages.
	 */
	public static function register_extra_menu() {
		add_submenu_page( 'elbishion', __( 'Forms', 'elbishion' ), __( 'Forms', 'elbishion' ), 'manage_options', 'elbishion-forms', array( __CLASS__, 'render_forms_page' ) );
		add_submenu_page( 'elbishion', __( 'Integrations', 'elbishion' ), __( 'Integrations', 'elbishion' ), 'manage_options', 'elbishion-integrations', array( __CLASS__, 'render_integrations_page' ) );
		add_submenu_page( 'elbishion', __( 'Tools', 'elbishion' ), __( 'Tools', 'elbishion' ), 'manage_options', 'elbishion-tools', array( __CLASS__, 'render_tools_page' ) );
	}

	/**
	 * Register admin menu and submenus.
	 */
	public static function register_menu() {
		$unread = Elbishion_Database::count_unread();
		$bubble = $unread > 0 ? sprintf(
			' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
			absint( $unread )
		) : '';

		add_menu_page(
			__( 'Elbishion', 'elbishion' ),
			__( 'Elbishion', 'elbishion' ) . $bubble,
			'manage_options',
			'elbishion',
			array( __CLASS__, 'render_submissions_page' ),
			'dashicons-feedback',
			26
		);

		add_submenu_page( 'elbishion', __( 'ყველა განაცხადი', 'elbishion' ), __( 'ყველა განაცხადი', 'elbishion' ), 'manage_options', 'elbishion', array( __CLASS__, 'render_submissions_page' ) );
		add_submenu_page( 'elbishion', __( 'წაუკითხავი', 'elbishion' ), __( 'წაუკითხავი', 'elbishion' ), 'manage_options', 'elbishion-unread', array( __CLASS__, 'render_submissions_page' ) );
		add_submenu_page( 'elbishion', __( 'ვარსკვლავით მონიშნული', 'elbishion' ), __( 'ვარსკვლავით მონიშნული', 'elbishion' ), 'manage_options', 'elbishion-starred', array( __CLASS__, 'render_submissions_page' ) );
		add_submenu_page( 'elbishion', __( 'არქივი', 'elbishion' ), __( 'არქივი', 'elbishion' ), 'manage_options', 'elbishion-archived', array( __CLASS__, 'render_submissions_page' ) );
		add_submenu_page( 'elbishion', __( 'პარამეტრები', 'elbishion' ), __( 'პარამეტრები', 'elbishion' ), 'manage_options', 'elbishion-settings', array( 'Elbishion_Settings', 'render_page' ) );
	}

	/**
	 * Enqueue admin assets only on Elbishion pages.
	 *
	 * @param string $hook Current hook suffix.
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'elbishion' ) ) {
			return;
		}

		wp_enqueue_style( 'elbishion-admin', ELBISHION_PLUGIN_URL . 'assets/css/admin.css', array(), ELBISHION_VERSION );
		wp_enqueue_script( 'elbishion-admin', ELBISHION_PLUGIN_URL . 'assets/js/admin.js', array(), ELBISHION_VERSION, true );
	}

	/**
	 * Render grouped forms overview.
	 */
	public static function render_forms_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elbishion' ) );
		}

		$forms = Elbishion_Database::get_forms_overview();
		?>
		<div class="wrap elbishion-admin">
			<div class="elbishion-page-header">
				<div>
					<h1><?php esc_html_e( 'Forms', 'elbishion' ); ?></h1>
					<p><?php esc_html_e( 'Submission totals grouped by source plugin and form name.', 'elbishion' ); ?></p>
				</div>
			</div>
			<div class="elbishion-list-form">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Form Name', 'elbishion' ); ?></th>
							<th><?php esc_html_e( 'Source Plugin', 'elbishion' ); ?></th>
							<th><?php esc_html_e( 'Total', 'elbishion' ); ?></th>
							<th><?php esc_html_e( 'Unread', 'elbishion' ); ?></th>
							<th><?php esc_html_e( 'Last Submission', 'elbishion' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'elbishion' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $forms ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No forms captured yet.', 'elbishion' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $forms as $form ) : ?>
							<?php
							$url = add_query_arg(
								array(
									'page'   => 'elbishion',
									'source' => $form->source_plugin,
									'form_name' => $form->form_name,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr>
								<td><?php echo esc_html( $form->form_name ); ?></td>
								<td><?php echo esc_html( self::source_label( $form->source_plugin ) ); ?></td>
								<td><?php echo esc_html( absint( $form->total_submissions ) ); ?></td>
								<td><?php echo esc_html( absint( $form->unread_submissions ) ); ?></td>
								<td><?php echo esc_html( $form->last_submission_at ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View submissions', 'elbishion' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render integrations settings page.
	 */
	public static function render_integrations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elbishion' ) );
		}

		$settings     = Elbishion_Settings::get_settings();
		$integrations = Elbishion_Integrations_Manager::integrations();
		?>
		<div class="wrap elbishion-admin">
			<div class="elbishion-page-header">
				<div>
					<h1><?php esc_html_e( 'Integrations', 'elbishion' ); ?></h1>
					<p><?php esc_html_e( 'Enable automatic capture for installed form systems and tune per-form rules.', 'elbishion' ); ?></p>
				</div>
			</div>
			<form method="post" action="options.php" class="elbishion-card elbishion-settings-card">
				<?php settings_fields( 'elbishion_settings_group' ); ?>
				<?php foreach ( $settings as $key => $value ) : ?>
					<?php if ( 'integrations' === $key || is_array( $value ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<input type="hidden" name="elbishion_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
				<?php endforeach; ?>
				<?php foreach ( $integrations as $slug => $integration ) : ?>
					<?php $row = Elbishion_Settings::get_integration_settings( $slug ); ?>
					<div class="elbishion-integration-row">
						<div>
							<h2><?php echo esc_html( $integration['label'] ); ?></h2>
							<p>
								<?php echo Elbishion_Integrations_Manager::is_detected( $slug ) ? esc_html__( 'Active', 'elbishion' ) : esc_html__( 'Not Active', 'elbishion' ); ?>
							</p>
						</div>
						<label><input type="checkbox" name="elbishion_settings[integrations][<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $row['enabled'], 1 ); ?>> <?php esc_html_e( 'Enabled', 'elbishion' ); ?></label>
						<label><input type="checkbox" name="elbishion_settings[integrations][<?php echo esc_attr( $slug ); ?>][capture_all]" value="1" <?php checked( $row['capture_all'], 1 ); ?>> <?php esc_html_e( 'Capture all forms', 'elbishion' ); ?></label>
						<label><input type="checkbox" name="elbishion_settings[integrations][<?php echo esc_attr( $slug ); ?>][save_ip]" value="1" <?php checked( $row['save_ip'], 1 ); ?>> <?php esc_html_e( 'Save IP', 'elbishion' ); ?></label>
						<label><input type="checkbox" name="elbishion_settings[integrations][<?php echo esc_attr( $slug ); ?>][save_user_agent]" value="1" <?php checked( $row['save_user_agent'], 1 ); ?>> <?php esc_html_e( 'Save user agent', 'elbishion' ); ?></label>
						<?php if ( 'woocommerce' === $slug ) : ?>
							<label><input type="checkbox" name="elbishion_settings[integrations][<?php echo esc_attr( $slug ); ?>][capture_checkout]" value="1" <?php checked( $row['capture_checkout'], 1 ); ?>> <?php esc_html_e( 'Capture checkout fields', 'elbishion' ); ?></label>
						<?php endif; ?>
						<label><?php esc_html_e( 'Selected forms', 'elbishion' ); ?><textarea name="elbishion_settings[integrations][<?php echo esc_attr( $slug ); ?>][selected_forms]" rows="2"><?php echo esc_textarea( $row['selected_forms'] ); ?></textarea></label>
						<label><?php esc_html_e( 'Ignored forms', 'elbishion' ); ?><textarea name="elbishion_settings[integrations][<?php echo esc_attr( $slug ); ?>][ignore_forms]" rows="2"><?php echo esc_textarea( $row['ignore_forms'] ); ?></textarea></label>
					</div>
				<?php endforeach; ?>
				<?php submit_button( __( 'Save Integrations', 'elbishion' ), 'primary elbishion-primary-button' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render tools page.
	 */
	public static function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elbishion' ) );
		}

		?>
		<div class="wrap elbishion-admin">
			<div class="elbishion-page-header">
				<div>
					<h1><?php esc_html_e( 'Tools', 'elbishion' ); ?></h1>
					<p><?php esc_html_e( 'Universal capture helpers for developers.', 'elbishion' ); ?></p>
				</div>
			</div>
			<div class="elbishion-card elbishion-settings-card">
				<h2><?php esc_html_e( 'Custom HTML capture', 'elbishion' ); ?></h2>
				<pre>&lt;input type="hidden" name="elbishion_capture" value="1"&gt;
&lt;input type="hidden" name="elbishion_form_name" value="Contact Form"&gt;</pre>
				<h2><?php esc_html_e( 'Developer action', 'elbishion' ); ?></h2>
				<pre>do_action( 'elbishion_save_submission', $form_name, $submitted_data, $source, $meta );</pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Render submissions list or detail page.
	 */
	public static function render_submissions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'ამ გვერდზე წვდომის უფლება არ გაქვთ.', 'elbishion' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';

		if ( 'detail' === $view ) {
			self::render_detail_page();
			return;
		}

		$status = self::status_from_page();
		$table  = new Elbishion_Admin_List( $status );
		$table->prepare_items();
		$title  = self::title_from_status( $status );
		?>
		<div class="wrap elbishion-admin">
			<div class="elbishion-page-header">
				<div>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p><?php esc_html_e( 'ნახეთ, მოძებნეთ, დააექსპორტეთ და დაალაგეთ ყველა განაცხადი ერთ სამუშაო სივრცეში.', 'elbishion' ); ?></p>
				</div>
				<div class="elbishion-header-actions">
					<a class="button elbishion-secondary-button" href="<?php echo esc_url( self::export_url( 'filtered' ) ); ?>"><?php esc_html_e( 'გაფილტრულის ექსპორტი', 'elbishion' ); ?></a>
					<a class="button button-primary elbishion-primary-button" href="<?php echo esc_url( self::export_url( 'all' ) ); ?>"><?php esc_html_e( 'ყველას ექსპორტი', 'elbishion' ); ?></a>
				</div>
			</div>

			<?php $table->maybe_print_notice(); ?>

			<form method="get" class="elbishion-list-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::current_page_slug() ); ?>">
				<?php if ( $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<?php endif; ?>
				<?php $table->search_box( __( 'განაცხადების ძებნა', 'elbishion' ), 'elbishion-search' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render detail view.
	 */
	private static function render_detail_page() {
		$id         = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$submission = $id ? Elbishion_Database::get_submission( $id ) : null;

		if ( ! $submission ) {
			?>
			<div class="wrap elbishion-admin">
				<div class="elbishion-empty-state">
					<h1><?php esc_html_e( 'განაცხადი ვერ მოიძებნა', 'elbishion' ); ?></h1>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=elbishion' ) ); ?>"><?php esc_html_e( 'სიაში დაბრუნება', 'elbishion' ); ?></a>
				</div>
			</div>
			<?php
			return;
		}

		$data       = Elbishion_Database::decode_data( $submission->submitted_data );
		$display_data = self::display_data( $data );
		$message    = self::find_message_field( $display_data );
		$title      = self::submission_title( $submission, $display_data );
		$status_badge = sprintf( '<span class="elbishion-badge elbishion-badge-%1$s">%2$s</span>', esc_attr( $submission->status ), esc_html( self::status_label( $submission->status ) ) );
		?>
		<div class="wrap elbishion-admin elbishion-detail-page">
			<div class="elbishion-page-header">
				<div>
					<a class="elbishion-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=elbishion' ) ); ?>">&larr; <?php esc_html_e( 'სიაში დაბრუნება', 'elbishion' ); ?></a>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p>
						<?php echo wp_kses_post( $status_badge ); ?>
						<span><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submission->created_at ) ); ?></span>
					</p>
				</div>
				<div class="elbishion-header-actions">
					<?php echo wp_kses_post( self::detail_action_link( $submission, 'read' === $submission->status ? 'mark_unread' : 'mark_read' ) ); ?>
					<?php echo wp_kses_post( self::detail_action_link( $submission, 'starred' === $submission->status ? 'mark_read' : 'star' ) ); ?>
					<?php echo wp_kses_post( self::detail_action_link( $submission, 'archive' ) ); ?>
					<?php echo wp_kses_post( self::detail_action_link( $submission, 'delete' ) ); ?>
				</div>
			</div>

			<div class="elbishion-detail-grid">
				<section class="elbishion-card elbishion-detail-main">
					<h2><?php esc_html_e( 'შევსებული ველები', 'elbishion' ); ?></h2>
					<div class="elbishion-field-grid">
						<?php foreach ( $display_data as $key => $value ) : ?>
							<?php if ( strtolower( (string) $key ) === strtolower( (string) $message['key'] ) ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<div class="elbishion-field-card">
								<span><?php echo esc_html( self::field_label( $key ) ); ?></span>
								<strong><?php echo esc_html( self::stringify_value( $value ) ); ?></strong>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( $message['value'] ) : ?>
						<div class="elbishion-message-card">
							<span><?php echo esc_html( self::field_label( $message['key'] ) ); ?></span>
							<p><?php echo nl2br( esc_html( $message['value'] ) ); ?></p>
						</div>
					<?php endif; ?>
				</section>

				<aside class="elbishion-card elbishion-meta-card">
					<h2><?php esc_html_e( 'განაცხადის დეტალები', 'elbishion' ); ?></h2>
					<dl>
						<dt><?php esc_html_e( 'განაცხადის ID', 'elbishion' ); ?></dt>
						<dd>#<?php echo esc_html( $submission->id ); ?></dd>
						<dt><?php esc_html_e( 'ფორმის სახელი', 'elbishion' ); ?></dt>
						<dd><?php echo esc_html( $submission->form_name ); ?></dd>
						<dt><?php esc_html_e( 'Source Plugin', 'elbishion' ); ?></dt>
						<dd><?php echo esc_html( self::source_label( isset( $submission->source_plugin ) && $submission->source_plugin ? $submission->source_plugin : ( $submission->source ?? 'api' ) ) ); ?></dd>
						<dt><?php esc_html_e( 'გვერდის ბმული', 'elbishion' ); ?></dt>
						<dd>
							<?php if ( $submission->page_url ) : ?>
								<a href="<?php echo esc_url( $submission->page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $submission->page_url ); ?></a>
							<?php else : ?>
								<span class="elbishion-muted">&mdash;</span>
							<?php endif; ?>
						</dd>
						<dt><?php esc_html_e( 'IP მისამართი', 'elbishion' ); ?></dt>
						<dd><?php echo $submission->user_ip ? esc_html( $submission->user_ip ) : '<span class="elbishion-muted">&mdash;</span>'; ?></dd>
						<dt><?php esc_html_e( 'ბრაუზერის ინფორმაცია', 'elbishion' ); ?></dt>
						<dd><?php echo $submission->user_agent ? esc_html( $submission->user_agent ) : '<span class="elbishion-muted">&mdash;</span>'; ?></dd>
						<dt><?php esc_html_e( 'განახლდა', 'elbishion' ); ?></dt>
						<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submission->updated_at ) ); ?></dd>
					</dl>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle row/detail actions.
	 */
	public static function handle_single_action() {
		if ( empty( $_GET['elbishion_action'] ) || empty( $_GET['id'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'ამ ქმედების შესრულების უფლება არ გაქვთ.', 'elbishion' ) );
		}

		$id     = absint( $_GET['id'] );
		$action = sanitize_key( wp_unslash( $_GET['elbishion_action'] ) );

		check_admin_referer( 'elbishion_submission_action_' . $id );

		switch ( $action ) {
			case 'mark_read':
				Elbishion_Database::update_status( array( $id ), 'read' );
				break;
			case 'mark_unread':
				Elbishion_Database::update_status( array( $id ), 'unread' );
				break;
			case 'star':
				Elbishion_Database::update_status( array( $id ), 'starred' );
				break;
			case 'archive':
				Elbishion_Database::update_status( array( $id ), 'archived' );
				break;
			case 'delete':
				Elbishion_Database::delete_submissions( array( $id ) );
				wp_safe_redirect( admin_url( 'admin.php?page=elbishion' ) );
				exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'elbishion',
					'view' => 'detail',
					'id'   => $id,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Build export URL from current filters.
	 *
	 * @param string $type all or filtered.
	 * @return string
	 */
	private static function export_url( $type ) {
		$args = array(
			'page'              => self::current_page_slug(),
			'elbishion_export'  => $type,
		);

		foreach ( array( 'status', 'source', 's', 'form_name', 'date_from', 'date_to', 'order', 'has_attachments', 'user_scope', 'user_id', 'page_url' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		if ( self::status_from_page() ) {
			$args['status'] = self::status_from_page();
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php' ) ), 'elbishion_export' );
	}

	/**
	 * Detail action link.
	 *
	 * @param object $submission Submission row.
	 * @param string $action Action key.
	 * @return string
	 */
	private static function detail_action_link( $submission, $action ) {
		$labels = array(
			'mark_read'   => __( 'წაკითხულად', 'elbishion' ),
			'mark_unread' => __( 'წაუკითხავად', 'elbishion' ),
			'star'        => __( 'ვარსკვლავი', 'elbishion' ),
			'archive'     => __( 'არქივი', 'elbishion' ),
			'delete'      => __( 'წაშლა', 'elbishion' ),
		);

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'page'             => 'elbishion',
					'view'             => 'detail',
					'id'               => absint( $submission->id ),
					'elbishion_action' => $action,
				),
				admin_url( 'admin.php' )
			),
			'elbishion_submission_action_' . absint( $submission->id )
		);

		$class = 'button';

		if ( 'delete' === $action ) {
			$class .= ' elbishion-danger-button elbishion-confirm-action';
		}

		return sprintf(
			'<a class="%1$s" href="%2$s">%3$s</a>',
			esc_attr( $class ),
			esc_url( $url ),
			esc_html( $labels[ $action ] ?? $action )
		);
	}

	/**
	 * Current page slug.
	 *
	 * @return string
	 */
	private static function current_page_slug() {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'elbishion';
	}

	/**
	 * Derive status from current submenu.
	 *
	 * @return string
	 */
	private static function status_from_page() {
		$page = self::current_page_slug();

		if ( 'elbishion-unread' === $page ) {
			return 'unread';
		}

		if ( 'elbishion-starred' === $page ) {
			return 'starred';
		}

		if ( 'elbishion-archived' === $page ) {
			return 'archived';
		}

		return '';
	}

	/**
	 * Convert structured Elementor JSON into a readable field map for admin detail views.
	 *
	 * @param array $data Submitted data.
	 * @return array
	 */
	private static function display_data( $data ) {
		if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return $data;
		}

		$display = array();

		foreach ( $data['fields'] as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = ! empty( $field['field_label'] ) ? $field['field_label'] : ( $field['label'] ?? ( $field['field_id'] ?? ( $field['id'] ?? __( 'Field', 'elbishion' ) ) ) );
			$value = $field['field_value'] ?? ( $field['value'] ?? '' );
			$key   = $label;

			$field_id = $field['field_id'] ?? ( $field['id'] ?? '' );

			if ( ! empty( $field_id ) && strtolower( (string) $field_id ) !== strtolower( (string) $label ) ) {
				$key = sprintf( '%1$s (%2$s)', $label, $field_id );
			}

			$display[ $key ] = $value;
		}

		return $display;
	}

	/**
	 * Page title by status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function title_from_status( $status ) {
		if ( 'unread' === $status ) {
			return __( 'წაუკითხავი განაცხადები', 'elbishion' );
		}

		if ( 'starred' === $status ) {
			return __( 'ვარსკვლავით მონიშნული განაცხადები', 'elbishion' );
		}

		if ( 'archived' === $status ) {
			return __( 'დაარქივებული განაცხადები', 'elbishion' );
		}

		return __( 'ყველა განაცხადი', 'elbishion' );
	}

	/**
	 * Locate message field.
	 *
	 * @param array $data Submission data.
	 * @return array
	 */
	private static function find_message_field( $data ) {
		foreach ( $data as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), array( 'message', 'your message', 'comments', 'comment', 'description', 'შეტყობინება', 'მესიჯი', 'კომენტარი', 'აღწერა', 'ტექსტი' ), true ) ) {
				return array(
					'key'   => $key,
					'value' => self::stringify_value( $value ),
				);
			}
		}

		return array(
			'key'   => '',
			'value' => '',
		);
	}

	/**
	 * Humanize field key.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function humanize_key( $key ) {
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $key ) );
	}

	/**
	 * Return a human title for the submission, preferring the submitter name.
	 *
	 * @param object $submission Submission row.
	 * @param array  $data Submitted data.
	 * @return string
	 */
	private static function submission_title( $submission, $data ) {
		$name = self::find_data_value(
			$data,
			array(
				'name',
				'full name',
				'first name',
				'სახელი',
				'სახელი და გვარი',
				'სრული სახელი',
			)
		);

		return $name ? $name : $submission->form_name;
	}

	/**
	 * Translate common submitted field keys into Georgian labels.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function field_label( $key ) {
		$normalized = strtolower( trim( str_replace( '-', '_', (string) $key ) ) );
		$labels     = array(
			'name'              => __( 'სახელი და გვარი', 'elbishion' ),
			'full_name'         => __( 'სახელი და გვარი', 'elbishion' ),
			'first_name'        => __( 'სახელი', 'elbishion' ),
			'email'             => __( 'ელფოსტა', 'elbishion' ),
			'email_address'     => __( 'ელფოსტა', 'elbishion' ),
			'phone'             => __( 'ტელეფონი', 'elbishion' ),
			'phone_number'      => __( 'ტელეფონის ნომერი', 'elbishion' ),
			'mobile'            => __( 'მობილური', 'elbishion' ),
			'subject'           => __( 'თემა', 'elbishion' ),
			'message'           => __( 'შეტყობინება', 'elbishion' ),
			'comment'           => __( 'კომენტარი', 'elbishion' ),
			'comments'          => __( 'კომენტარი', 'elbishion' ),
			'description'       => __( 'აღწერა', 'elbishion' ),
		);

		return $labels[ $normalized ] ?? self::humanize_key( $key );
	}

	/**
	 * Find a submitted value by possible keys.
	 *
	 * @param array $data Submitted data.
	 * @param array $keys Possible keys.
	 * @return string
	 */
	private static function find_data_value( $data, $keys ) {
		$normalized = array();

		foreach ( $data as $key => $value ) {
			$normalized[ strtolower( trim( (string) $key ) ) ] = is_array( $value ) ? wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $value;
		}

		foreach ( $keys as $key ) {
			if ( isset( $normalized[ strtolower( $key ) ] ) && '' !== trim( $normalized[ strtolower( $key ) ] ) ) {
				return trim( $normalized[ strtolower( $key ) ] );
			}
		}

		return '';
	}

	/**
	 * Stringify field value.
	 *
	 * @param mixed $value Field value.
	 * @return string
	 */
	private static function stringify_value( $value ) {
		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		return (string) $value;
	}

	/**
	 * Translate WordPress core table controls only on Elbishion admin pages.
	 *
	 * @param string $translation Translated text.
	 * @param string $text Source text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function translate_core_table_text( $translation, $text, $domain ) {
		if ( 'default' !== $domain || ! is_admin() || empty( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'elbishion' ) ) {
			return $translation;
		}

		$map = array(
			'Bulk actions' => 'მასობრივი ქმედებები',
			'Apply'        => 'გამოყენება',
			'Search'       => 'ძებნა',
			'Select All'   => 'ყველას მონიშვნა',
			'%s item'      => '%s ჩანაწერი',
			'%s items'     => '%s ჩანაწერი',
			'items'        => 'ჩანაწერი',
			'item'         => 'ჩანაწერი',
		);

		return $map[ $text ] ?? $translation;
	}

	/**
	 * Georgian status labels.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private static function source_label( $source ) {
		$labels = array(
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
