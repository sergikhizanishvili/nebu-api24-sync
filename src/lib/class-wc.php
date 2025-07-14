<?php
/**
 * Class for extending WooCommerce functionality
 * for the Nebu API24 Sync plugin.
 *
 * @package Nebu API24 Sync
 * @since   1.0.0
 */

namespace Nebu\API24\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nebu\API24\Lib\Products_Sync;
use WP_Post;
use WC_Order;
use Nebu\API24\Lib\API24;

/**
 * WooCommerce class.
 *
 * @since   1.0.0
 */
class WC {
	/**
	 * Setup the class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		/**
		 * Add actions.
		 */
		add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_box' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'sync_product' ], 20, 1 );
		add_action( 'woocommerce_order_action_nebu_api24_manual_order', [ $this, 'handle_manual_order' ], 10, 1 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'register_new_order' ], 10, 3 );
		add_action( 'woocommerce_update_order', [ $this, 'update_api24_order' ], 10, 1 );

		/**
		 * Add filters.
		 */
		add_filter( 'woocommerce_order_actions', [ $this, 'add_custom_order_action' ], 10, 1 );
	}

	/**
	 * Add custom meta box to product and order edit screen.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_custom_meta_box(): void {
		add_meta_box(
			'nebu-api24_product_meta_box',
			__( 'API24 Sync', 'nebu-api24' ),
			[ $this, 'render_product_custom_meta_box' ],
			'product',
			'side',
			'high'
		);
	}

	/**
	 * Render custom meta box.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 * @since 1.0.0
	 */
	public function render_product_custom_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_checked = get_post_meta( $post->ID, '_nebu_api24_sync', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="_nebu_api24_sync" value="yes" <?php checked( $is_checked, 'yes' ); ?> />
				<?php esc_html_e( 'Sync with API24', 'nebu-api24' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Sync product with API24.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sync_product( int $post_id ): void {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$checkbox_value = isset( $_POST['_nebu_api24_sync'] ) ? true : false; // phpcs:ignore
		if ( $checkbox_value ) {
			Products_Sync::sync_single_product( $post_id );
		}
	}

	/**
	 * Add custom order action to the order.
	 *
	 * @param array $actions The array of actions.
	 * @return array
	 * @since 1.0.0
	 */
	public function add_custom_order_action( array $actions ): array {
		$actions['nebu_api24_manual_order'] = __( 'Create API24 Order', 'nebu-api24' );
		return $actions;
	}

	/**
	 * Handle manual API24 order creation.
	 *
	 * @param WC_Order $order The order object.
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_manual_order( WC_Order $order ): void {
		if ( ! $order ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_nebu_api24_order_created' ) ) {
			return;
		}

		if ( ! class_exists( API24::class ) ) {
			return;
		}

		$create = $this->register_api24_order( $order->get_id() );
		if ( ! $create ) {
			$order->add_order_note(
				__( 'Failed to create order in API24.', 'nebu-api24' )
			);
			return;
		}
	}

	/**
	 * Register new order in API24.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $from_status From status.
	 * @param string $to_status To status.
	 * @return void
	 * @since 1.0.0
	 */
	public function register_new_order( int $order_id, string $from_status, string $to_status ): void {

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->debug( 'Nebu API24: Order status changed: ' . $from_status . ' -> ' . $to_status, [ 'source' => 'nebu-api24' ] );

		if ( 'processing' !== $to_status && 'pending' !== $to_status ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_nebu_api24_order_created' ) ) {
			return;
		}

		$this->register_api24_order( $order_id );
	}

	/**
	 * Register order in API24 when order is paid.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 * @since 1.0.0
	 */
	public function register_api24_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
	}

	/**
	 * Update order in API24.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 * @since 1.0.0
	 */
	public function update_api24_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
	}
}
