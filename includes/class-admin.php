<?php
/**
 * wp-admin screens for offers: the edit form (three boxes plus a status box), the list columns
 * (status, where it shows, dates) and "Make a copy" for running an offer again.
 *
 * Every input is a named field, a dropdown, a tick box or a date picker (no typed formats).
 */

namespace TravelStore\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	const NONCE = 'tsp_save';

	public static function register() {
		add_action( 'add_meta_boxes_' . Promotion::TYPE, array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_' . Promotion::TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'manage_' . Promotion::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Promotion::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_action_tsp_copy', array( __CLASS__, 'copy' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	public static function title_placeholder( $text, $post ) {
		return Promotion::TYPE === $post->post_type ? __( 'Name of the offer (only seen in wp-admin)', 'travelstore-promotions' ) : $text;
	}

	private static function on_our_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && Promotion::TYPE === $screen->post_type;
	}

	public static function assets() {
		if ( ! self::on_our_screen() ) {
			return;
		}
		wp_enqueue_style( 'tsp-admin', TSP_URL . 'assets/admin.css', array(), (string) filemtime( TSP_PATH . 'assets/admin.css' ) );
		$screen = get_current_screen();
		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
			wp_enqueue_script( 'tsp-admin', TSP_URL . 'assets/admin.js', array( 'jquery' ), (string) filemtime( TSP_PATH . 'assets/admin.js' ), true );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Edit form                                                           */
	/* ------------------------------------------------------------------ */

	public static function boxes() {
		add_meta_box( 'tsp-text', __( 'What the offer says', 'travelstore-promotions' ), array( __CLASS__, 'box_text' ), null, 'normal', 'high' );
		add_meta_box( 'tsp-dates', __( 'When it shows', 'travelstore-promotions' ), array( __CLASS__, 'box_dates' ), null, 'normal', 'high' );
		add_meta_box( 'tsp-where', __( 'Where and how it shows', 'travelstore-promotions' ), array( __CLASS__, 'box_where' ), null, 'normal', 'high' );
		add_meta_box( 'tsp-status', __( 'Showing now?', 'travelstore-promotions' ), array( __CLASS__, 'box_status' ), null, 'side', 'high' );
	}

	private static function name( $key ) {
		return 'tsp[' . $key . ']';
	}

	private static function text_row( $o, $key, $label, $help = '', $args = array() ) {
		$id = 'tsp-' . $key;
		echo '<p class="tsp-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		printf(
			'<input type="%s" class="widefat" id="%s" name="%s" value="%s"%s>',
			esc_attr( isset( $args['type'] ) ? $args['type'] : 'text' ),
			esc_attr( $id ),
			esc_attr( self::name( $key ) ),
			esc_attr( (string) $o[ $key ] ),
			isset( $args['placeholder'] ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : ''
		);
		if ( $help ) {
			echo '<span class="description">' . esc_html( $help ) . '</span>';
		}
		echo '</p>';
	}

	private static function select( $name, $id, $options, $current ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( (string) $current, (string) $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/** Dropdown of Published pages (by name) for a link. */
	private static function page_select( $name, $id, $current ) {
		$options = array( 0 => __( '— Choose a page —', 'travelstore-promotions' ) );
		foreach ( get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) ) as $p ) {
			$options[ $p->ID ] = $p->post_title;
		}
		self::select( $name, $id, $options, (int) $current );
	}

	public static function box_text( $post ) {
		$o = Promotion::all( $post->ID );
		wp_nonce_field( self::NONCE, 'tsp_nonce' );

		self::text_row( $o, 'badge', __( 'Badge word', 'travelstore-promotions' ), __( 'A short word in a coloured label, for example Offer, Early bird, Last minute. Leave empty for no label.', 'travelstore-promotions' ) );
		self::text_row( $o, 'headline', __( 'Headline', 'travelstore-promotions' ), __( 'For example: Self-Drive Offer', 'travelstore-promotions' ) );
		self::text_row( $o, 'summary', __( 'Short line', 'travelstore-promotions' ), __( 'One sentence under the headline (band, card and pop-up).', 'travelstore-promotions' ) );

		echo '<div class="tsp-field"><span class="tsp-label">' . esc_html__( 'Offer tiers (optional)', 'travelstore-promotions' ) . '</span>';
		echo '<span class="description">' . esc_html__( 'Each tier is shown as its own tile. Fill in only the rows you need.', 'travelstore-promotions' ) . '</span>';
		echo '<table class="tsp-tiers"><thead><tr><th></th><th>' . esc_html__( 'Who or when', 'travelstore-promotions' ) . '</th><th>' . esc_html__( 'What you get', 'travelstore-promotions' ) . '</th></tr></thead><tbody>';
		for ( $i = 1; $i <= Promotion::TIERS; $i++ ) {
			printf(
				'<tr><th scope="row">%1$d</th><td><input type="text" class="widefat" aria-label="%2$s" name="%3$s" value="%4$s" placeholder="%5$s"></td><td><input type="text" class="widefat" aria-label="%6$s" name="%7$s" value="%8$s" placeholder="%9$s"></td></tr>',
				(int) $i,
				/* translators: %d: tier number */
				esc_attr( sprintf( __( 'Tier %d: who or when', 'travelstore-promotions' ), $i ) ),
				esc_attr( self::name( 'tier' . $i . '_label' ) ),
				esc_attr( (string) $o[ 'tier' . $i . '_label' ] ),
				1 === $i ? esc_attr__( 'For example: 1–6 days', 'travelstore-promotions' ) : '',
				/* translators: %d: tier number */
				esc_attr( sprintf( __( 'Tier %d: what you get', 'travelstore-promotions' ), $i ) ),
				esc_attr( self::name( 'tier' . $i . '_value' ) ),
				esc_attr( (string) $o[ 'tier' . $i . '_value' ] ),
				1 === $i ? esc_attr__( 'For example: 250 km per day', 'travelstore-promotions' ) : ''
			);
		}
		echo '</tbody></table></div>';

		echo '<div class="tsp-field"><span class="tsp-label">' . esc_html__( 'More details (optional)', 'travelstore-promotions' ) . '</span>';
		wp_editor(
			(string) $o['details'],
			'tsp-details',
			array(
				'textarea_name' => self::name( 'details' ),
				'textarea_rows' => 4,
				'media_buttons' => false,
				'teeny'         => true,
				'quicktags'     => false,
			)
		);
		echo '</div>';

		echo '<p class="tsp-field"><label for="tsp-terms">' . esc_html__( 'Small print (optional)', 'travelstore-promotions' ) . '</label>';
		echo '<textarea class="widefat" rows="2" id="tsp-terms" name="' . esc_attr( self::name( 'terms' ) ) . '">' . esc_textarea( (string) $o['terms'] ) . '</textarea></p>';

		echo '<fieldset class="tsp-field tsp-button"><legend class="tsp-label">' . esc_html__( 'Button (band, card and pop-up)', 'travelstore-promotions' ) . '</legend>';
		self::text_row( $o, 'button_text', __( 'Button text', 'travelstore-promotions' ), __( 'Leave empty for no button. For example: Ask about this offer', 'travelstore-promotions' ) );
		echo '<p class="tsp-field"><label for="tsp-button_kind">' . esc_html__( 'Button goes to', 'travelstore-promotions' ) . '</label>';
		self::select(
			self::name( 'button_kind' ),
			'tsp-button_kind',
			array(
				'none' => __( 'Nowhere (no button)', 'travelstore-promotions' ),
				'page' => __( 'A page on this site', 'travelstore-promotions' ),
				'url'  => __( 'A web address', 'travelstore-promotions' ),
			),
			$o['button_kind']
		);
		echo '</p><p class="tsp-field" data-tsp-show="button_kind=page"><label for="tsp-button_page">' . esc_html__( 'Page', 'travelstore-promotions' ) . '</label>';
		self::page_select( self::name( 'button_page' ), 'tsp-button_page', $o['button_page'] );
		echo '<span class="description">' . esc_html__( 'On that page itself the button is left out.', 'travelstore-promotions' ) . '</span></p>';
		echo '<div data-tsp-show="button_kind=url">';
		self::text_row( $o, 'button_url', __( 'Web address', 'travelstore-promotions' ), '', array( 'type' => 'url', 'placeholder' => 'https://' ) );
		echo '</div></fieldset>';

		echo '<p class="tsp-field"><label for="tsp-colour">' . esc_html__( 'Colour of the top bar and pop-up', 'travelstore-promotions' ) . '</label>';
		self::select( self::name( 'colour' ), 'tsp-colour', Promotion::colour_options(), $o['colour'] );
		echo '<span class="description">' . esc_html__( 'The band and card take their colours from the TS Promotion widget on the page.', 'travelstore-promotions' ) . '</span></p>';

		$img_id = (int) $o['image_id'];
		echo '<div class="tsp-field tsp-image"><span class="tsp-label">' . esc_html__( 'Pop-up picture (optional)', 'travelstore-promotions' ) . '</span>';
		echo '<input type="hidden" id="tsp-image_id" name="' . esc_attr( self::name( 'image_id' ) ) . '" value="' . esc_attr( $img_id ) . '">';
		echo '<div class="tsp-image__preview">' . ( $img_id ? wp_get_attachment_image( $img_id, 'medium' ) : '' ) . '</div>';
		echo '<button type="button" class="button tsp-image__pick">' . esc_html__( 'Choose picture', 'travelstore-promotions' ) . '</button> ';
		echo '<button type="button" class="button-link tsp-image__remove"' . ( $img_id ? '' : ' hidden' ) . '>' . esc_html__( 'Remove picture', 'travelstore-promotions' ) . '</button></div>';
	}

	public static function box_dates( $post ) {
		$o = Promotion::all( $post->ID );
		echo '<p class="description">' . sprintf(
			/* translators: %s: time zone name */
			esc_html__( 'Days are in the site\'s time zone (%s). The offer appears at the start of the first display day and disappears by itself after the end of the last display day.', 'travelstore-promotions' ),
			esc_html( wp_timezone_string() )
		) . '</p>';
		echo '<div class="tsp-dates-grid">';
		echo '<fieldset><legend class="tsp-label">' . esc_html__( 'Display on the site', 'travelstore-promotions' ) . '</legend>';
		self::text_row( $o, 'show_from', __( 'Show from', 'travelstore-promotions' ), __( 'Empty = as soon as it is published.', 'travelstore-promotions' ), array( 'type' => 'date' ) );
		self::text_row( $o, 'show_until', __( 'Show until (required)', 'travelstore-promotions' ), __( 'Last day it is shown. Without this day the offer is not shown at all.', 'travelstore-promotions' ), array( 'type' => 'date' ) );
		echo '</fieldset><fieldset><legend class="tsp-label">' . esc_html__( 'Shown to visitors (optional)', 'travelstore-promotions' ) . '</legend>';
		self::text_row( $o, 'book_by', __( 'Last day to book', 'travelstore-promotions' ), __( 'Shown as "Book by …".', 'travelstore-promotions' ), array( 'type' => 'date' ) );
		self::text_row( $o, 'travel_from', __( 'Travel from', 'travelstore-promotions' ), '', array( 'type' => 'date' ) );
		self::text_row( $o, 'travel_until', __( 'Travel until', 'travelstore-promotions' ), __( 'Shown as "Travel … – …".', 'travelstore-promotions' ), array( 'type' => 'date' ) );
		echo '</fieldset></div>';
	}

	/** Tick boxes of pages (Published and Draft) plus "every trip page", with a filter box. */
	private static function where_fields( $o, $place ) {
		$where = 'all' === $o[ $place . '_where' ] ? 'all' : 'chosen';
		$pages = array_map( 'intval', (array) $o[ $place . '_pages' ] );
		echo '<div class="tsp-where">';
		if ( 'ribbon' === $place ) {
			printf(
				'<input type="hidden" name="%s" value="chosen"><span class="tsp-label">%s</span>',
				esc_attr( self::name( $place . '_where' ) ),
				esc_html__( 'Cards that link to these pages or trips get the ribbon:', 'travelstore-promotions' )
			);
		} else {
			printf(
				'<label><input type="radio" name="%1$s" value="chosen"%2$s> %3$s</label> <label><input type="radio" name="%1$s" value="all"%4$s> %5$s</label>',
				esc_attr( self::name( $place . '_where' ) ),
				checked( $where, 'chosen', false ),
				esc_html__( 'On the pages ticked below', 'travelstore-promotions' ),
				checked( $where, 'all', false ),
				'spot' === $place ? esc_html__( 'On every page that has a TS Promotion widget', 'travelstore-promotions' ) : esc_html__( 'On every page of the site', 'travelstore-promotions' )
			);
		}
		echo '<div class="tsp-pages" data-tsp-show="' . esc_attr( $place ) . '_where=chosen">';
		echo '<input type="search" class="tsp-pages__filter" placeholder="' . esc_attr__( 'Type to find a page', 'travelstore-promotions' ) . '" aria-label="' . esc_attr__( 'Find a page', 'travelstore-promotions' ) . '">';
		echo '<ul class="tsp-pages__list">';
		foreach ( get_pages( array( 'post_status' => 'publish,draft', 'sort_column' => 'post_title' ) ) as $p ) {
			printf(
				'<li><label><input type="checkbox" name="%s[]" value="%d"%s> %s%s</label></li>',
				esc_attr( self::name( $place . '_pages' ) ),
				(int) $p->ID,
				checked( in_array( (int) $p->ID, $pages, true ), true, false ),
				esc_html( $p->post_title ),
				'draft' === $p->post_status ? ' <em>(' . esc_html__( 'Draft', 'travelstore-promotions' ) . ')</em>' : ''
			);
		}
		echo '</ul>';
		self::trip_fields( $o, $place );
		echo '</div></div>';
	}

	/**
	 * 1.2.0: which trips — every trip, trips of chosen kinds (WP Travel Engine Activities, Trip Type,
	 * Destination; a trip counts when it has any ticked kind) and trips ticked one by one.
	 * Folded away until something is chosen.
	 */
	private static function trip_fields( $o, $place ) {
		static $trips = null;
		if ( null === $trips ) {
			$trips = post_type_exists( 'trip' ) ? get_posts(
				array(
					'post_type'      => 'trip',
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			) : array();
		}
		if ( ! post_type_exists( 'trip' ) ) {
			return;
		}
		$every  = (bool) $o[ $place . '_trips' ];
		$terms  = array_map( 'intval', (array) $o[ $place . '_trip_terms' ] );
		$chosen = array_map( 'intval', (array) $o[ $place . '_trip_ids' ] );
		$count  = $every ? __( 'every trip', 'travelstore-promotions' ) : sprintf(
			/* translators: 1: "1 kind" / "3 kinds", 2: "1 trip" / "3 trips" */
			__( '%1$s and %2$s ticked', 'travelstore-promotions' ),
			/* translators: %d: number of kinds */
			sprintf( _n( '%d kind', '%d kinds', count( $terms ), 'travelstore-promotions' ), count( $terms ) ),
			/* translators: %d: number of trips */
			sprintf( _n( '%d trip', '%d trips', count( $chosen ), 'travelstore-promotions' ), count( $chosen ) )
		);

		printf(
			'<details class="tsp-trips"%s><summary><strong>%s</strong> <span class="tsp-trips__count">(%s)</span></summary>',
			( $every || $terms || $chosen ) ? ' open' : '',
			esc_html__( 'Trips', 'travelstore-promotions' ),
			esc_html( $count )
		);
		printf(
			'<p><label><input type="checkbox" name="%s" value="1"%s> <strong>%s</strong></label></p>',
			esc_attr( self::name( $place . '_trips' ) ),
			checked( $every, true, false ),
			esc_html__( 'Every trip', 'travelstore-promotions' )
		);

		echo '<p class="tsp-label">' . esc_html__( 'Trips of these kinds', 'travelstore-promotions' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'A trip counts when it has any of the ticked kinds (set in each trip, under Trips). Trips added to a kind later are included automatically.', 'travelstore-promotions' ) . '</p>';
		echo '<div class="tsp-kinds">';
		foreach ( Promotion::trip_taxonomies() as $tax ) {
			$list = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
			if ( is_wp_error( $list ) || ! $list ) {
				continue;
			}
			$obj = get_taxonomy( $tax );
			echo '<fieldset class="tsp-kinds__group"><legend>' . esc_html( $obj->labels->name ) . '</legend><ul>';
			foreach ( $list as $t ) {
				printf(
					'<li><label><input type="checkbox" name="%s[]" value="%d"%s> %s <span class="tsp-muted">(%s)</span></label></li>',
					esc_attr( self::name( $place . '_trip_terms' ) ),
					(int) $t->term_id,
					checked( in_array( (int) $t->term_id, $terms, true ), true, false ),
					esc_html( html_entity_decode( $t->name ) ),
					/* translators: %d: number of trips */
					esc_html( sprintf( _n( '%d trip', '%d trips', (int) $t->count, 'travelstore-promotions' ), (int) $t->count ) )
				);
			}
			echo '</ul></fieldset>';
		}
		echo '</div>';

		echo '<p class="tsp-label">' . esc_html__( 'These trips', 'travelstore-promotions' ) . '</p>';
		echo '<div class="tsp-pages">';
		echo '<input type="search" class="tsp-pages__filter" placeholder="' . esc_attr__( 'Type to find a trip', 'travelstore-promotions' ) . '" aria-label="' . esc_attr__( 'Find a trip', 'travelstore-promotions' ) . '">';
		echo '<ul class="tsp-pages__list">';
		foreach ( $trips as $p ) {
			printf(
				'<li><label><input type="checkbox" name="%s[]" value="%d"%s> %s%s</label></li>',
				esc_attr( self::name( $place . '_trip_ids' ) ),
				(int) $p->ID,
				checked( in_array( (int) $p->ID, $chosen, true ), true, false ),
				esc_html( $p->post_title ),
				'draft' === $p->post_status ? ' <em>(' . esc_html__( 'Draft', 'travelstore-promotions' ) . ')</em>' : ''
			);
		}
		echo '</ul></div></details>';
	}

	private static function place_box( $o, $place, $title, $intro ) {
		printf(
			'<fieldset class="tsp-place"><legend><label><input type="checkbox" class="tsp-place__on" name="%s" value="1"%s> <strong>%s</strong></label></legend><p class="description">%s</p><div class="tsp-place__body" data-tsp-show="%s_on=1">',
			esc_attr( self::name( $place . '_on' ) ),
			checked( (bool) $o[ $place . '_on' ], true, false ),
			esc_html( $title ),
			esc_html( $intro ),
			esc_attr( $place )
		);
	}

	public static function box_where( $post ) {
		$o = Promotion::all( $post->ID );

		self::place_box( $o, 'topbar', __( 'Top bar', 'travelstore-promotions' ), __( 'A thin bar above the site header with one line and a link. Visitors can close it for the rest of their visit.', 'travelstore-promotions' ) );
		self::text_row( $o, 'topbar_text', __( 'Top bar text', 'travelstore-promotions' ), __( 'Keep it short so it fits on phones. Empty = the short line.', 'travelstore-promotions' ) );
		self::text_row( $o, 'topbar_link', __( 'Link text', 'travelstore-promotions' ), __( 'Empty = "See the offer".', 'travelstore-promotions' ) );
		echo '<p class="tsp-field"><label for="tsp-topbar_page">' . esc_html__( 'Link goes to', 'travelstore-promotions' ) . '</label>';
		self::page_select( self::name( 'topbar_page' ), 'tsp-topbar_page', $o['topbar_page'] );
		echo '<span class="description">' . esc_html__( 'Usually the page with the full offer. On that page itself the link is left out.', 'travelstore-promotions' ) . '</span></p>';
		self::where_fields( $o, 'topbar' );
		echo '</div></fieldset>';

		self::place_box( $o, 'spot', __( 'Band or card on the page', 'travelstore-promotions' ), __( 'Shows where a TS Promotion widget has been placed on the page in Elementor (the widget decides band or card, and its colours). Without that widget on a page nothing shows there.', 'travelstore-promotions' ) );
		self::where_fields( $o, 'spot' );
		echo '</div></fieldset>';

		self::place_box( $o, 'popup', __( 'Pop-up', 'travelstore-promotions' ), __( 'A window over the page (on phones it slides up from the bottom). Closes with the × button, the Esc key or a click outside. If a slide-in is also on the page, only the one that appears first is shown; the other stays away for the rest of the visit.', 'travelstore-promotions' ) );
		self::timed_fields( $o, 'popup' );
		self::where_fields( $o, 'popup' );
		echo '</div></fieldset>';

		self::place_box( $o, 'slidein', __( 'Corner slide-in', 'travelstore-promotions' ), __( 'A small card that slides in at the bottom left without covering the page (on phones: along the bottom). The visitor can close it. If a pop-up is also on the page, only the one that appears first is shown; the other stays away for the rest of the visit.', 'travelstore-promotions' ) );
		self::timed_fields( $o, 'slidein' );
		self::where_fields( $o, 'slidein' );
		echo '</div></fieldset>';

		self::place_box( $o, 'bar', __( 'Bottom bar', 'travelstore-promotions' ), __( 'A bar fixed to the bottom of the screen with one line and a button, always in reach of the thumb. The visitor can close it for the rest of the visit.', 'travelstore-promotions' ) );
		self::text_row( $o, 'bar_text', __( 'Bar text', 'travelstore-promotions' ), __( 'Keep it very short. Empty = the top bar text, or else the short line.', 'travelstore-promotions' ) );
		echo '<p class="tsp-field"><label for="tsp-bar_link">' . esc_html__( 'Button in the bar', 'travelstore-promotions' ) . '</label>';
		self::select( self::name( 'bar_link' ), 'tsp-bar_link', Promotion::bar_link_options(), $o['bar_link'] );
		echo '</p><p class="tsp-field tsp-inline"><label for="tsp-bar_screens">' . esc_html__( 'Shown on', 'travelstore-promotions' ) . '</label>';
		self::select( self::name( 'bar_screens' ), 'tsp-bar_screens', Promotion::screens_options(), $o['bar_screens'] );
		echo '</p>';
		self::where_fields( $o, 'bar' );
		echo '</div></fieldset>';

		self::place_box( $o, 'ribbon', __( 'Ribbon on cards', 'travelstore-promotions' ), __( 'A small label in the corner of every card on the site (trip cards, media cards, cards) that links to one of the pages or trips ticked below. Cards that link elsewhere are not touched.', 'travelstore-promotions' ) );
		self::text_row( $o, 'ribbon_text', __( 'Ribbon word', 'travelstore-promotions' ), __( 'Empty = the badge word.', 'travelstore-promotions' ) );
		echo '<p class="tsp-field tsp-inline"><label for="tsp-ribbon_corner">' . esc_html__( 'Corner', 'travelstore-promotions' ) . '</label>';
		self::select( self::name( 'ribbon_corner' ), 'tsp-ribbon_corner', Promotion::corner_options(), $o['ribbon_corner'] );
		echo '<span class="description">' . esc_html__( 'If a card already has its own label in that corner, the ribbon uses the other top corner.', 'travelstore-promotions' ) . '</span></p>';
		self::where_fields( $o, 'ribbon' );
		echo '</div></fieldset>';
	}

	/** "Appears" (time / scroll / leaving), seconds, scroll depth and how often, for the pop-up and the slide-in. */
	private static function timed_fields( $o, $place ) {
		echo '<p class="tsp-field"><label for="tsp-' . esc_attr( $place ) . '_trigger">' . esc_html__( 'Appears', 'travelstore-promotions' ) . '</label>';
		self::select( self::name( $place . '_trigger' ), 'tsp-' . $place . '_trigger', Promotion::trigger_options(), $o[ $place . '_trigger' ] );
		echo '</p><p class="tsp-field tsp-inline" data-tsp-show="' . esc_attr( $place ) . '_trigger!=scroll"><label for="tsp-' . esc_attr( $place ) . '_delay">' . esc_html__( 'Seconds', 'travelstore-promotions' ) . '</label>';
		$delays = array();
		foreach ( Promotion::delay_options() as $s ) {
			/* translators: %d: number of seconds */
			$delays[ $s ] = sprintf( _n( '%d second', '%d seconds', $s, 'travelstore-promotions' ), $s );
		}
		self::select( self::name( $place . '_delay' ), 'tsp-' . $place . '_delay', $delays, (int) $o[ $place . '_delay' ] );
		echo '</p><p class="tsp-field tsp-inline" data-tsp-show="' . esc_attr( $place ) . '_trigger=scroll"><label for="tsp-' . esc_attr( $place ) . '_scroll">' . esc_html__( 'After scrolling', 'travelstore-promotions' ) . '</label>';
		$depths = array();
		foreach ( Promotion::scroll_options() as $p ) {
			/* translators: %d: percentage */
			$depths[ $p ] = sprintf( __( '%d%% of the page', 'travelstore-promotions' ), $p );
		}
		self::select( self::name( $place . '_scroll' ), 'tsp-' . $place . '_scroll', $depths, (int) $o[ $place . '_scroll' ] );
		echo '</p><p class="tsp-field tsp-inline"><label for="tsp-' . esc_attr( $place ) . '_often">' . esc_html__( 'How often', 'travelstore-promotions' ) . '</label>';
		self::select( self::name( $place . '_often' ), 'tsp-' . $place . '_often', Promotion::often_options(), $o[ $place . '_often' ] );
		echo '</p>';
	}

	/** Words for an offer's state, with its dates. */
	public static function state_text( $id ) {
		$state = Promotion::state( $id );
		$o     = Promotion::all( $id );
		switch ( $state ) {
			case 'draft':
				return __( 'Not published, so not shown.', 'travelstore-promotions' );
			case 'no_end':
				return __( 'Not shown: set a "Show until" day.', 'travelstore-promotions' );
			case 'ended':
				/* translators: %s: date */
				return sprintf( __( 'Ended after %s. Not shown any more.', 'travelstore-promotions' ), Promotion::day_text( $o['show_until'] ) );
			case 'scheduled':
				/* translators: %s: date */
				return sprintf( __( 'Waiting: starts showing on %s.', 'travelstore-promotions' ), Promotion::day_text( $o['show_from'] ) );
		}
		$places = self::places_text( $o );
		/* translators: 1: date, 2: list of places */
		return sprintf( __( 'Showing now, until the end of %1$s. %2$s', 'travelstore-promotions' ), Promotion::day_text( $o['show_until'] ), $places );
	}

	private static function places_text( $o ) {
		$names = array(
			'topbar' => __( 'top bar', 'travelstore-promotions' ),
			'spot'   => __( 'band or card', 'travelstore-promotions' ),
			'popup'  => __( 'pop-up', 'travelstore-promotions' ),
			'slidein' => __( 'corner slide-in', 'travelstore-promotions' ),
			'bar'    => __( 'bottom bar', 'travelstore-promotions' ),
			'ribbon' => __( 'ribbon on cards', 'travelstore-promotions' ),
		);
		$on    = array();
		foreach ( Promotion::PLACES as $place ) {
			if ( $o[ $place . '_on' ] ) {
				$on[] = $names[ $place ];
			}
		}

		return $on
			/* translators: %s: list such as "top bar, pop-up" */
			? sprintf( __( 'As: %s.', 'travelstore-promotions' ), implode( ', ', $on ) )
			: __( 'No way of showing is ticked, so it shows nowhere.', 'travelstore-promotions' );
	}

	public static function box_status( $post ) {
		$o     = Promotion::all( $post->ID );
		$state = Promotion::state( $post->ID );
		echo '<p class="tsp-state tsp-state--' . esc_attr( $state ) . '">' . esc_html( self::state_text( $post->ID ) ) . '</p>';
		echo '<p class="tsp-field"><label for="tsp-importance">' . esc_html__( 'Importance', 'travelstore-promotions' ) . '</label>';
		self::select( self::name( 'importance' ), 'tsp-importance', Promotion::importance_options(), $o['importance'] );
		echo '<span class="description">' . esc_html__( 'When two offers want the same place, the more important one wins (then the one ending sooner).', 'travelstore-promotions' ) . '</span></p>';
	}

	/* ------------------------------------------------------------------ */
	/* Saving                                                              */
	/* ------------------------------------------------------------------ */

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['tsp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tsp_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['tsp'] ) && is_array( $_POST['tsp'] ) ? wp_unslash( $_POST['tsp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised per field below.

		// update_post_meta() removes one level of slashes, so the cleaned values are slashed again.
		foreach ( self::clean( $in ) as $key => $value ) {
			update_post_meta( $post_id, Promotion::PREFIX . $key, wp_slash( $value ) );
		}
	}

	/** Sanitise the submitted fields (unticked boxes are saved as off). */
	public static function clean( $in ) {
		$out = array();
		$day = static function ( $v ) {
			$v = trim( (string) $v );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$pick = static function ( $v, $options, $default ) {
			return array_key_exists( (string) $v, $options ) ? (string) $v : $default;
		};

		foreach ( array( 'badge', 'headline', 'summary', 'button_text', 'topbar_text', 'topbar_link' ) as $key ) {
			$out[ $key ] = isset( $in[ $key ] ) ? sanitize_text_field( $in[ $key ] ) : '';
		}
		for ( $i = 1; $i <= Promotion::TIERS; $i++ ) {
			foreach ( array( '_label', '_value' ) as $part ) {
				$out[ 'tier' . $i . $part ] = isset( $in[ 'tier' . $i . $part ] ) ? sanitize_text_field( $in[ 'tier' . $i . $part ] ) : '';
			}
		}
		$out['details']     = isset( $in['details'] ) ? wp_kses_post( $in['details'] ) : '';
		$out['terms']       = isset( $in['terms'] ) ? sanitize_textarea_field( $in['terms'] ) : '';
		$out['image_id']    = isset( $in['image_id'] ) ? absint( $in['image_id'] ) : 0;
		$out['colour']      = $pick( isset( $in['colour'] ) ? $in['colour'] : '', Promotion::colour_options(), 'espresso' );
		$out['importance']  = $pick( isset( $in['importance'] ) ? $in['importance'] : '', Promotion::importance_options(), 'normal' );
		$out['button_kind'] = $pick( isset( $in['button_kind'] ) ? $in['button_kind'] : '', array( 'none' => 1, 'page' => 1, 'url' => 1 ), 'none' );
		$out['button_page'] = isset( $in['button_page'] ) ? absint( $in['button_page'] ) : 0;
		$out['button_url']  = isset( $in['button_url'] ) ? esc_url_raw( trim( (string) $in['button_url'] ) ) : '';
		$out['topbar_page'] = isset( $in['topbar_page'] ) ? absint( $in['topbar_page'] ) : 0;
		foreach ( array( 'show_from', 'show_until', 'book_by', 'travel_from', 'travel_until' ) as $key ) {
			$out[ $key ] = $day( isset( $in[ $key ] ) ? $in[ $key ] : '' );
		}
		foreach ( Promotion::TIMED as $place ) {
			$delay                    = isset( $in[ $place . '_delay' ] ) ? absint( $in[ $place . '_delay' ] ) : 8;
			$out[ $place . '_delay' ] = in_array( $delay, Promotion::delay_options(), true ) ? $delay : 8;
			$out[ $place . '_often' ] = $pick( isset( $in[ $place . '_often' ] ) ? $in[ $place . '_often' ] : '', Promotion::often_options(), 'visit' );
			$out[ $place . '_trigger' ] = $pick( isset( $in[ $place . '_trigger' ] ) ? $in[ $place . '_trigger' ] : '', Promotion::trigger_options(), 'time' );
			$scroll                    = isset( $in[ $place . '_scroll' ] ) ? absint( $in[ $place . '_scroll' ] ) : 50;
			$out[ $place . '_scroll' ] = in_array( $scroll, Promotion::scroll_options(), true ) ? $scroll : 50;
		}
		$out['bar_text']      = isset( $in['bar_text'] ) ? sanitize_text_field( $in['bar_text'] ) : '';
		$out['bar_link']      = $pick( isset( $in['bar_link'] ) ? $in['bar_link'] : '', Promotion::bar_link_options(), 'button' );
		$out['bar_screens']   = $pick( isset( $in['bar_screens'] ) ? $in['bar_screens'] : '', Promotion::screens_options(), 'phones' );
		$out['ribbon_text']   = isset( $in['ribbon_text'] ) ? sanitize_text_field( $in['ribbon_text'] ) : '';
		$out['ribbon_corner'] = $pick( isset( $in['ribbon_corner'] ) ? $in['ribbon_corner'] : '', Promotion::corner_options(), 'top-left' );
		foreach ( Promotion::PLACES as $place ) {
			$out[ $place . '_on' ]    = empty( $in[ $place . '_on' ] ) ? '' : '1';
			// Ribbons always go on cards that link to the ticked pages ("every page" has no meaning there).
			$out[ $place . '_where' ] = 'ribbon' !== $place && isset( $in[ $place . '_where' ] ) && 'all' === $in[ $place . '_where' ] ? 'all' : 'chosen';
			$out[ $place . '_pages' ] = isset( $in[ $place . '_pages' ] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $in[ $place . '_pages' ] ) ) ) ) : array();
			$out[ $place . '_trips' ] = empty( $in[ $place . '_trips' ] ) ? '' : '1';
			// Only real trips and real kinds (Activities, Trip Type, Destination) are kept.
			$trip_ids = isset( $in[ $place . '_trip_ids' ] ) ? array_unique( array_filter( array_map( 'intval', (array) $in[ $place . '_trip_ids' ] ) ) ) : array();
			$out[ $place . '_trip_ids' ] = array_values(
				array_filter(
					$trip_ids,
					static function ( $id ) {
						return $id > 0 && 'trip' === get_post_type( $id );
					}
				)
			);
			$terms = isset( $in[ $place . '_trip_terms' ] ) ? array_unique( array_filter( array_map( 'intval', (array) $in[ $place . '_trip_terms' ] ) ) ) : array();
			$out[ $place . '_trip_terms' ] = array_values(
				array_filter(
					$terms,
					static function ( $id ) {
						$term = $id > 0 ? get_term( $id ) : null;
						return $term && ! is_wp_error( $term ) && in_array( $term->taxonomy, Promotion::trip_taxonomies(), true );
					}
				)
			);
		}

		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Warnings on the edit screen                                         */
	/* ------------------------------------------------------------------ */

	public static function notices() {
		if ( ! self::on_our_screen() || 'post' !== get_current_screen()->base ) {
			return;
		}
		$post = get_post();
		if ( ! $post || 'auto-draft' === $post->post_status ) {
			return;
		}
		$o    = Promotion::all( $post->ID );
		$warn = array();
		if ( '' === $o['show_until'] ) {
			$warn[] = __( 'This offer has no "Show until" day, so it is not shown anywhere.', 'travelstore-promotions' );
		}
		list( $start, $end ) = Promotion::window( $post->ID );
		if ( $start && $end && $end < $start ) {
			$warn[] = __( '"Show until" is before "Show from", so the offer is never shown.', 'travelstore-promotions' );
		}
		if ( $o['spot_on'] || $o['topbar_on'] || $o['popup_on'] ) {
			foreach ( Promotion::PLACES as $place ) {
				if ( $o[ $place . '_on' ] && 'chosen' === $o[ $place . '_where' ] && ! $o[ $place . '_pages' ] && ! $o[ $place . '_trips' ] && ! $o[ $place . '_trip_terms' ] && ! $o[ $place . '_trip_ids' ] ) {
					$warn[] = __( 'A way of showing is ticked but no page or trip is chosen for it.', 'travelstore-promotions' );
					break;
				}
			}
		}
		foreach ( $warn as $w ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $w ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* List screen                                                         */
	/* ------------------------------------------------------------------ */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'date' === $key ) {
				continue;
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['tsp_state'] = __( 'Showing now?', 'travelstore-promotions' );
				$new['tsp_dates'] = __( 'Display days', 'travelstore-promotions' );
			}
		}

		return $new;
	}

	public static function column( $col, $post_id ) {
		if ( 'tsp_state' === $col ) {
			echo '<span class="tsp-state tsp-state--' . esc_attr( Promotion::state( $post_id ) ) . '">' . esc_html( self::state_text( $post_id ) ) . '</span>';
		} elseif ( 'tsp_dates' === $col ) {
			$from  = Promotion::day_text( Promotion::get( $post_id, 'show_from' ) );
			$until = Promotion::day_text( Promotion::get( $post_id, 'show_until' ) );
			echo esc_html( ( $from ? $from : __( 'when published', 'travelstore-promotions' ) ) . ' – ' . ( $until ? $until : '?' ) );
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( Promotion::TYPE === $post->post_type && current_user_can( 'edit_ts_promotions' ) ) {
			$url             = wp_nonce_url( admin_url( 'admin.php?action=tsp_copy&post=' . $post->ID ), 'tsp_copy_' . $post->ID );
			$actions['copy'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Make a copy', 'travelstore-promotions' ) . '</a>';
		}

		return $actions;
	}

	/** Copy an offer (all fields) as a new Draft, for running it again. */
	public static function copy() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'tsp_copy_' . $id );
		$post = get_post( $id );
		if ( ! $post || Promotion::TYPE !== $post->post_type || ! current_user_can( 'edit_ts_promotions' ) ) {
			wp_die( esc_html__( 'This offer cannot be copied.', 'travelstore-promotions' ) );
		}
		$new = wp_insert_post(
			array(
				'post_type'   => Promotion::TYPE,
				'post_status' => 'draft',
				/* translators: %s: offer name */
				'post_title'  => sprintf( __( '%s (copy)', 'travelstore-promotions' ), $post->post_title ),
			)
		);
		if ( ! $new || is_wp_error( $new ) ) {
			wp_die( esc_html__( 'The copy could not be made.', 'travelstore-promotions' ) );
		}
		foreach ( array_keys( Promotion::defaults() ) as $key ) {
			if ( metadata_exists( 'post', $id, Promotion::PREFIX . $key ) ) {
				update_post_meta( $new, Promotion::PREFIX . $key, wp_slash( Promotion::get( $id, $key ) ) );
			}
		}
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new ) );
		exit;
	}
}
