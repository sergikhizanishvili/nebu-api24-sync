<?php
/**
 * Admin settings class.
 *
 * @package Nebu API24 Sync
 * @since 1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nebu\API24\Lib\API24;
use Exception;

/**
 * Admin settings class.
 *
 * @since 1.0.0
 */
class Admin_Settings {
	/**
	 * Setup the class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		/**
		 * Add settings menu page.
		 *
		 * @since 1.0.0
		 */
		add_action( 'admin_menu', [ $this, 'add_admin_submenu' ] );
	}

	/**
	 * Add admin menu inside WooCommerce menu.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_admin_submenu(): void {

		add_submenu_page(
			'woocommerce',
			__( 'Nebu API24', 'nebu-api24' ),
			__( 'Nebu API24', 'nebu-api24' ),
			'manage_options',
			'nebu-api24-settings',
			[ $this, 'api24_settings_callback' ]
		);
	}

	/**
	 * API24 settings callback.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function api24_settings_callback(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nebu-api24' ) );
			return;
		}

		/**
		 * Check if it's a settings update request.
		 */
		if (
			isset( $_SERVER['REQUEST_METHOD'] ) &&
			'POST' === $_SERVER['REQUEST_METHOD'] &&
			isset( $_POST['update-nebu-api24-settings'] ) &&
			isset( $_POST['nebu-api24-settings-nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nebu-api24-settings-nonce'] ) ), 'nebu-api24-settings' )
		) {
			update_option(
				'nebu_api24_settings',
				maybe_serialize(
					[
						'base_url' => isset( $_POST['base_url'] ) ? sanitize_text_field( wp_unslash( $_POST['base_url'] ) ) : '',
						'token'    => isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '',
						'debug'    => isset( $_POST['debug'] ) ? 'yes' : 'no',
					]
				)
			);
		}

		$settings = maybe_unserialize( get_option( 'nebu_api24_settings', [] ) );
		try {
			$api_24 = new API24();
		} catch ( Exception $e ) { // phpcs:ignore
			/**
			 * Do nothing.
			 */
		}
		?>
		<div class="wrap">
			<h2 class="wp-heading-inline">
				<?php esc_html_e( 'API24 settings', 'nebu-api24' ); ?>
			</h2>

			<form method="post" action="" style="margin-top: 25px;">
				<?php wp_nonce_field( 'nebu-api24-settings', 'nebu-api24-settings-nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'API Base URL', 'nebu-api24' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="base_url" value="<?php echo esc_attr( $settings['base_url'] ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'API Token', 'nebu-api24' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="username" value="<?php echo esc_attr( $settings['token'] ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Debug', 'nebu-api24' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="debug" value="yes" <?php echo ( $settings['debug'] ?? '' ) === 'yes' ? 'checked' : ''; ?>>
								<?php esc_html_e( 'Enable debug', 'nebu-api24' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p>
					<input
						type="submit"
						name="update-nebu-api24-settings"
						id="update-nebu-api24-settings"
						class="button button-primary"
						value="<?php esc_attr_e( 'Save Changes', 'nebu-api24' ); ?>"
					>
				</p>
			</form>
		</div>
		<?php
	}
}
