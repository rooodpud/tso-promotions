<?php
/**
 * Offers added to pages without editing them: the top bar (above the site header) and the pop-up.
 * The band and card are placed by the "TS Promotion" Elementor widget instead.
 *
 * At most one top bar and one pop-up per page: the most important offer, then the one ending soonest.
 */

namespace TravelStore\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	public static function register() {
		add_action( 'wp_head', array( __CLASS__, 'head_guard' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'wp_body_open', array( __CLASS__, 'topbar' ), 5 );
		// Before the footer scripts (printed at 20), so promotions.js finds the pop-up.
		add_action( 'wp_footer', array( __CLASS__, 'popup' ), 15 );
		// 1.1.0.
		add_action( 'wp_footer', array( __CLASS__, 'slidein' ), 15 );
		add_action( 'wp_footer', array( __CLASS__, 'bar' ), 15 );
		add_action( 'wp_footer', array( __CLASS__, 'ribbons' ), 15 );
		// 1.2.0: on trip pages (not built in Elementor) the band goes under the trip's header.
		add_action( 'wp_travel_engine_before_trip_content', array( __CLASS__, 'trip_band' ), 1 );
	}

	/** False in wp-admin, feeds and the Elementor editor preview. */
	private static function on_site() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return false;
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return false;
		}

		return true;
	}

	/** The page or trip being shown (0 on lists and archives). */
	public static function current_id() {
		return is_singular() ? (int) get_queried_object_id() : 0;
	}

	/** First offer showing now in $place on this page, or 0. */
	private static function first( $place ) {
		static $cache = array();
		if ( ! isset( $cache[ $place ] ) ) {
			$ids             = self::on_site() ? Promotion::showing( $place, self::current_id() ) : array();
			$cache[ $place ] = $ids ? (int) $ids[0] : 0;
		}

		return $cache[ $place ];
	}

	/**
	 * Tiny script in the page head: tspGuard(element) removes an offer whose showing time is over
	 * (or has not begun) by the visitor's clock, or a top bar the visitor closed during this visit.
	 * Printed only when at least one offer is Published.
	 */
	public static function head_guard() {
		if ( ! Promotion::published_ids() ) {
			return;
		}
		echo "<script>window.tspGuard=function(e){if(!e||!e.getAttribute)return;var n=Date.now(),s=+e.getAttribute('data-tsp-start')||0,t=+e.getAttribute('data-tsp-end')||0,c=false;try{c=e.hasAttribute('data-tsp-closable')&&!!sessionStorage.getItem('tsp-closed-'+e.getAttribute('data-tsp-id'))}catch(x){}if(n<s||(t&&n>t)||c){e.parentNode&&e.parentNode.removeChild(e)}};</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Stylesheet and script on every page while at least one offer is showing anywhere: besides the
	 * top bar, pop-up, slide-in and bottom bar of this page, ribbons can land on any page's cards and
	 * enquiry forms on any page name the offer the visitor came from (Enquiries).
	 */
	public static function assets() {
		if ( ! self::on_site() || ! Promotion::showing_anywhere() ) {
			return;
		}
		if ( is_singular( 'trip' ) && self::first( 'spot' ) && wp_style_is( 'ts-promotion', 'registered' ) ) {
			wp_enqueue_style( 'ts-promotion' );
		}
		wp_enqueue_style( 'tsp-promotions', TSP_URL . 'assets/css/promotions.css', array(), (string) filemtime( TSP_PATH . 'assets/css/promotions.css' ) );
		wp_enqueue_script( 'tsp-promotions', TSP_URL . 'assets/js/promotions.js', array(), (string) filemtime( TSP_PATH . 'assets/js/promotions.js' ), true );
		wp_localize_script(
			'tsp-promotions',
			'tspText',
			array(
				'asking'  => __( 'You are asking about:', 'travelstore-promotions' ),
				'notThis' => __( 'Not about this offer', 'travelstore-promotions' ),
			)
		);
	}

	private static function colour_class( $o ) {
		$colour = array_key_exists( $o['colour'], Promotion::colour_options() ) ? $o['colour'] : 'espresso';

		return 'tsp-c--' . $colour;
	}

	public static function topbar() {
		$id = self::first( 'topbar' );
		if ( ! $id ) {
			return;
		}
		$o    = Promotion::all( $id );
		$text = trim( (string) $o['topbar_text'] );
		if ( '' === $text ) {
			$text = trim( (string) ( '' !== trim( (string) $o['summary'] ) ? $o['summary'] : $o['headline'] ) );
		}
		if ( '' === $text ) {
			return;
		}
		$url  = Render::page_url( $o['topbar_page'], self::current_id() );
		$link = trim( (string) $o['topbar_link'] );
		if ( '' === $link ) {
			$link = __( 'See the offer', 'travelstore-promotions' );
		}
		?>
		<div class="tsp-topbar <?php echo esc_attr( self::colour_class( $o ) ); ?>" role="region" aria-label="<?php esc_attr_e( 'Current offer', 'travelstore-promotions' ); ?>" data-tsp-closable <?php echo Render::window_attrs( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<p class="tsp-topbar__in">
				<?php echo Render::badge( $o, 'tsp-topbar__badge' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="tsp-topbar__text"><?php echo esc_html( $text ); ?></span>
				<?php if ( $url ) : ?>
					<a class="tsp-topbar__link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $link ); ?><span aria-hidden="true"> →</span></a>
				<?php endif; ?>
			</p>
			<button type="button" class="tsp-topbar__close" aria-label="<?php esc_attr_e( 'Close this offer message', 'travelstore-promotions' ); ?>"><span aria-hidden="true">×</span></button>
		</div>
		<?php
		echo Render::guard_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function popup() {
		$id = self::first( 'popup' );
		if ( ! $id ) {
			return;
		}
		$o     = Promotion::all( $id );
		$url   = Render::button_url( $o, self::current_id() );
		$title = 'tsp-popup-title-' . $id;
		$img   = (int) $o['image_id'] ? wp_get_attachment_image( (int) $o['image_id'], 'medium_large', false, array( 'class' => 'tsp-popup__img', 'alt' => '', 'loading' => 'lazy' ) ) : '';
		?>
		<div class="tsp-popup <?php echo esc_attr( self::colour_class( $o ) ); ?>" hidden <?php echo self::timed_attrs( $o, 'popup' ) . ' ' . Render::window_attrs( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<div class="tsp-popup__backdrop" data-tsp-close></div>
			<div class="tsp-popup__box" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $title ); ?>" tabindex="-1">
				<button type="button" class="tsp-popup__close" data-tsp-close aria-label="<?php esc_attr_e( 'Close', 'travelstore-promotions' ); ?>"><span aria-hidden="true">×</span></button>
				<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="tsp-popup__body">
					<?php echo Render::badge( $o ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="tsp-popup__title" id="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( '' !== trim( (string) $o['headline'] ) ? $o['headline'] : get_the_title( $id ) ); ?></h2>
					<?php if ( '' !== trim( (string) $o['summary'] ) ) : ?>
						<p class="tsp-popup__text"><?php echo esc_html( $o['summary'] ); ?></p>
					<?php endif; ?>
					<?php
					echo Render::details_html( $o ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo Render::tiers_html( $o ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo Render::dates_html( $o ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
					<?php if ( $url ) : ?>
						<a class="tsp-popup__btn" <?php echo Render::link_attrs( $url ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $o['button_text'] ); ?></a>
					<?php endif; ?>
					<?php echo Render::terms_html( $o ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
		</div>
		<?php
		echo Render::guard_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ------------------------------------------------------------------ */
	/* 1.1.0: when pop-ups and slide-ins appear; slide-in; bottom bar;     */
	/* ribbons on cards                                                    */
	/* ------------------------------------------------------------------ */

	/** data- attributes for promotions.js: what makes it appear, after how long or how far, how often. */
	private static function timed_attrs( $o, $place ) {
		$trigger = array_key_exists( $o[ $place . '_trigger' ], Promotion::trigger_options() ) ? $o[ $place . '_trigger' ] : 'time';
		$delay   = in_array( (int) $o[ $place . '_delay' ], Promotion::delay_options(), true ) ? (int) $o[ $place . '_delay' ] : 8;
		$scroll  = in_array( (int) $o[ $place . '_scroll' ], Promotion::scroll_options(), true ) ? (int) $o[ $place . '_scroll' ] : 50;
		$often   = array_key_exists( $o[ $place . '_often' ], Promotion::often_options() ) ? $o[ $place . '_often' ] : 'visit';

		return sprintf(
			'data-trigger="%s" data-delay="%d" data-scroll="%d" data-often="%s"',
			esc_attr( $trigger ),
			$delay,
			$scroll,
			esc_attr( $often )
		);
	}

	public static function slidein() {
		$id = self::first( 'slidein' );
		if ( ! $id ) {
			return;
		}
		$o     = Promotion::all( $id );
		$url   = Render::button_url( $o, self::current_id() );
		$title = '' !== trim( (string) $o['headline'] ) ? $o['headline'] : get_the_title( $id );
		?>
		<aside class="tsp-slidein <?php echo esc_attr( self::colour_class( $o ) ); ?>" hidden aria-label="<?php esc_attr_e( 'Current offer', 'travelstore-promotions' ); ?>" <?php echo self::timed_attrs( $o, 'slidein' ) . ' ' . Render::window_attrs( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<button type="button" class="tsp-slidein__close" data-tsp-close aria-label="<?php esc_attr_e( 'Close this offer', 'travelstore-promotions' ); ?>"><span aria-hidden="true">×</span></button>
			<?php echo Render::badge( $o ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p class="tsp-slidein__title"><?php echo esc_html( $title ); ?></p>
			<?php if ( '' !== trim( (string) $o['summary'] ) ) : ?>
				<p class="tsp-slidein__text"><?php echo esc_html( $o['summary'] ); ?></p>
			<?php endif; ?>
			<?php
			echo Render::tiers_html( $o, 'tsp-mini' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo Render::dates_html( $o ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
			<?php if ( $url ) : ?>
				<a class="tsp-slidein__btn" <?php echo Render::link_attrs( $url ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $o['button_text'] ); ?></a>
			<?php endif; ?>
		</aside>
		<?php
		echo Render::guard_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function bar() {
		$id = self::first( 'bar' );
		if ( ! $id ) {
			return;
		}
		$o    = Promotion::all( $id );
		$text = trim( (string) $o['bar_text'] );
		foreach ( array( 'topbar_text', 'summary', 'headline' ) as $fallback ) {
			if ( '' === $text ) {
				$text = trim( (string) $o[ $fallback ] );
			}
		}
		if ( '' === $text ) {
			return;
		}
		if ( 'topbar' === $o['bar_link'] ) {
			$url  = Render::page_url( $o['topbar_page'], self::current_id() );
			$link = '' !== trim( (string) $o['topbar_link'] ) ? $o['topbar_link'] : __( 'See the offer', 'travelstore-promotions' );
		} else {
			$url  = Render::button_url( $o, self::current_id() );
			$link = $o['button_text'];
		}
		$screens = array_key_exists( $o['bar_screens'], Promotion::screens_options() ) ? $o['bar_screens'] : 'phones';
		?>
		<div class="tsp-bar tsp-bar--<?php echo esc_attr( $screens . ' ' . self::colour_class( $o ) ); ?>" role="region" aria-label="<?php esc_attr_e( 'Current offer', 'travelstore-promotions' ); ?>" data-tsp-closable <?php echo Render::window_attrs( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo Render::badge( $o, 'tsp-topbar__badge' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p class="tsp-bar__text"><?php echo esc_html( $text ); ?></p>
			<?php if ( $url ) : ?>
				<a class="tsp-bar__btn" <?php echo Render::link_attrs( $url ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $link ); ?></a>
			<?php endif; ?>
			<button type="button" class="tsp-bar__close" aria-label="<?php esc_attr_e( 'Close this offer message', 'travelstore-promotions' ); ?>"><span aria-hidden="true">×</span></button>
		</div>
		<?php
		echo Render::guard_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Ribbons: for each offer showing a ribbon, the addresses its cards link to. promotions.js puts
	 * the ribbon on every card (.ts-mcard, .ts-card) holding a link to one of them.
	 */
	public static function ribbons() {
		if ( ! self::on_site() ) {
			return;
		}
		$list = array();
		foreach ( Promotion::ribbons() as $id ) {
			$o    = Promotion::all( $id );
			$urls = array();
			foreach ( array_map( 'intval', (array) $o['ribbon_pages'] ) as $page ) {
				if ( 'publish' === get_post_status( $page ) ) {
					$urls[] = (string) get_permalink( $page );
					$urls[] = add_query_arg( 'page_id', $page, home_url( '/' ) );
				}
			}
			// 1.2.0: trips chosen one by one or by kind.
			foreach ( Promotion::chosen_trip_ids( $id, 'ribbon' ) as $trip ) {
				$urls[] = (string) get_permalink( $trip );
			}
			$trips = '';
			if ( $o['ribbon_trips'] && post_type_exists( 'trip' ) ) {
				$type  = get_post_type_object( 'trip' );
				$slug  = is_array( $type->rewrite ) && ! empty( $type->rewrite['slug'] ) ? $type->rewrite['slug'] : 'trip';
				$trips = home_url( '/' . trim( $slug, '/' ) . '/' );
			}
			if ( ! $urls && ! $trips ) {
				continue;
			}
			$text   = trim( (string) $o['ribbon_text'] );
			$list[] = array(
				'id'     => $id,
				'text'   => '' !== $text ? $text : ( '' !== trim( (string) $o['badge'] ) ? $o['badge'] : __( 'Offer', 'travelstore-promotions' ) ),
				'colour' => array_key_exists( $o['colour'], Promotion::colour_options() ) ? $o['colour'] : 'espresso',
				'corner' => array_key_exists( $o['ribbon_corner'], Promotion::corner_options() ) ? $o['ribbon_corner'] : 'top-left',
				'start'  => Promotion::window( $id )[0] * 1000,
				'end'    => Promotion::window( $id )[1] * 1000,
				'urls'   => $urls,
				'trips'  => $trips,
			);
		}
		if ( $list ) {
			echo '<script>window.tspRibbons=' . wp_json_encode( $list ) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * 1.2.0: the band on a trip page, straight under the Travel Store trip header (trip pages are
	 * WP Travel Engine templates, not Elementor pages, so there is no TS Promotion widget there).
	 */
	public static function trip_band() {
		if ( ! is_singular( 'trip' ) || ! self::first( 'spot' ) ) {
			return;
		}
		$ids = Promotion::showing( 'spot', self::current_id() );
		echo '<div class="tsp-trip-band"><div class="ts-trip__inner">' . Render::spot_html( $ids, self::current_id(), 'band', 'h2', 'primary' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
