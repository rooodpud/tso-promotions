<?php
/**
 * Plugin Name: Travel Store Promotions
 * Description: Offers entered once in wp-admin (Promotions) and shown on chosen pages as a top bar, a band or card in the "TS Promotion" Elementor widget, a pop-up, a corner slide-in, a bottom bar or a ribbon on cards. Each offer appears on its first display day and disappears after its last one by itself (FUT-021).
 * Version:     1.2.1
 * Author:      Pradeep Hingorani
 * GitHub Plugin URI: https://github.com/rooodpud/tso-promotions
 * Primary Branch: main
 * Text Domain: travelstore-promotions
 * Requires Plugins: elementor, travelstore-custom-widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'TSP_URL', plugin_dir_url( __FILE__ ) );
define( 'TSP_VERSION', '1.2.1' );

require_once TSP_PATH . 'includes/class-promotion.php';
require_once TSP_PATH . 'includes/class-render.php';
require_once TSP_PATH . 'includes/class-admin.php';
require_once TSP_PATH . 'includes/class-frontend.php';
require_once TSP_PATH . 'includes/class-settings.php';
require_once TSP_PATH . 'includes/class-enquiries.php';

\TravelStore\Promotions\Promotion::register();
\TravelStore\Promotions\Admin::register();
\TravelStore\Promotions\Frontend::register();
\TravelStore\Promotions\Settings::register();
\TravelStore\Promotions\Enquiries::register();

/**
 * Stylesheet of the "TS Promotion" widget. Elementor loads it only where the widget is used.
 */
add_action(
	'elementor/frontend/after_register_styles',
	static function () {
		$file = 'assets/css/ts-promotion.css';
		wp_register_style( 'ts-promotion', TSP_URL . $file, array( 'ts-base' ), (string) filemtime( TSP_PATH . $file ) );
	}
);

/**
 * The "TS Promotion" widget: the place on a page where running offers show as a band or a card.
 * It uses the shared base of the Travel Store widgets (DEC-015), so it is registered after them.
 */
add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		if ( ! defined( 'TSW_PATH' ) || ! is_readable( TSW_PATH . 'includes/class-ts-base-widget.php' ) ) {
			return;
		}
		require_once TSW_PATH . 'includes/class-ts-base-widget.php';
		require_once TSP_PATH . 'widgets/class-ts-promotion.php';
		$widgets_manager->register( new \TravelStore\Widgets\TS_Promotion() );
	},
	20
);
