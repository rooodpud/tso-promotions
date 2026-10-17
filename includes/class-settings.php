<?php
/**
 * Promotions > Settings (1.1.0): which page is the Offers page, and whether its menu items are
 * hidden while no offer is showing (so the menu never leads to an empty Offers page).
 */

namespace TravelStore\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const PAGE = 'tsp-settings';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'menu_items' ) );
		// Editors may save these settings too (options.php checks this capability for the group).
		add_filter( 'option_page_capability_' . Promotion::OPTION, array( __CLASS__, 'capability' ) );
	}

	public static function capability() {
		return 'edit_others_ts_promotions';
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Promotion::TYPE,
			__( 'Promotions settings', 'travelstore-promotions' ),
			__( 'Settings', 'travelstore-promotions' ),
			self::capability(),
			self::PAGE,
			array( __CLASS__, 'screen' )
		);
	}

	public static function register_setting() {
		register_setting(
			Promotion::OPTION,
			Promotion::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'clean' ),
				'default'           => array(),
			)
		);
	}

	public static function clean( $in ) {
		$in = is_array( $in ) ? $in : array();

		return array(
			'offers_page' => isset( $in['offers_page'] ) ? absint( $in['offers_page'] ) : 0,
			'hide_menu'   => empty( $in['hide_menu'] ) ? '' : '1',
		);
	}

	public static function screen() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$page    = (int) Promotion::setting( 'offers_page' );
		$running = count( Promotion::showing_anywhere() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Promotions settings', 'travelstore-promotions' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( Promotion::OPTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tsp-offers-page"><?php esc_html_e( 'Offers page', 'travelstore-promotions' ); ?></label></th>
						<td>
							<select id="tsp-offers-page" name="<?php echo esc_attr( Promotion::OPTION ); ?>[offers_page]">
								<option value="0"><?php esc_html_e( '— None —', 'travelstore-promotions' ); ?></option>
								<?php foreach ( get_pages( array( 'post_status' => 'publish,draft', 'sort_column' => 'post_title' ) ) as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>"<?php selected( $page, (int) $p->ID ); ?>><?php echo esc_html( $p->post_title . ( 'draft' === $p->post_status ? ' (' . __( 'Draft', 'travelstore-promotions' ) . ')' : '' ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The page that lists every offer (a page with a TS Promotion widget set to "Every offer showing now").', 'travelstore-promotions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Menu', 'travelstore-promotions' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( Promotion::OPTION ); ?>[hide_menu]" value="1"<?php checked( (bool) Promotion::setting( 'hide_menu' ) ); ?>> <?php esc_html_e( 'Hide menu links to the Offers page while no offer is showing', 'travelstore-promotions' ); ?></label>
							<p class="description">
								<?php
								/* translators: %d: number of offers */
								echo esc_html( sprintf( _n( 'Right now %d offer is showing.', 'Right now %d offers are showing.', $running, 'travelstore-promotions' ), $running ) );
								echo ' ';
								esc_html_e( 'Add the Offers page to a menu in Appearance > Menus as usual; this setting only hides it when it would be empty.', 'travelstore-promotions' );
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Leave out menu links to the Offers page (and anything below them) while no offer is showing.
	 *
	 * @param \WP_Post[] $items Menu items.
	 */
	public static function menu_items( $items ) {
		$page = (int) Promotion::setting( 'offers_page' );
		if ( ! $page || ! Promotion::setting( 'hide_menu' ) || is_admin() || Promotion::showing_anywhere() ) {
			return $items;
		}

		return self::without_page( $items, $page );
	}

	/** Menu items without the links to $page and the items below them. */
	public static function without_page( $items, $page ) {
		$gone = array();
		foreach ( $items as $item ) {
			$to_page = 'post_type' === $item->type && 'page' === $item->object && $page === (int) $item->object_id;
			if ( $to_page || in_array( (int) $item->menu_item_parent, $gone, true ) ) {
				$gone[] = (int) $item->ID;
			}
		}

		return $gone ? array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $gone ) {
					return ! in_array( (int) $item->ID, $gone, true );
				}
			)
		) : $items;
	}
}
