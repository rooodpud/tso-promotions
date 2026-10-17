<?php
/**
 * The "Promotion" content type: one post per offer, its saved fields, and the rules that decide
 * whether an offer is showing now and on which pages.
 *
 * Only Published offers are ever shown. An offer shows from the start of its "Show from" day until
 * the end of its "Show until" day, in the site's time zone (Settings > General, Asia/Muscat).
 * An offer without a "Show until" day is never shown, so nothing can stay on the site by mistake.
 */

namespace TravelStore\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Promotion {

	const TYPE   = 'ts_promotion';
	const PREFIX = '_tsp_';

	/**
	 * The ways an offer can be shown: top bar, band/card (TS Promotion widget), pop-up (1.0.0);
	 * corner slide-in, bottom bar and ribbons on cards (1.1.0).
	 */
	const PLACES = array( 'topbar', 'spot', 'popup', 'slidein', 'bar', 'ribbon' );

	/** Places that appear after a while (pop-up, slide-in): when and how often. */
	const TIMED = array( 'popup', 'slidein' );

	/** Number of offer tiers ("who / when" + "what you get"). */
	const TIERS = 4;

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_type' ) );
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_caps' ), 10, 4 );
	}

	public static function register_type() {
		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Promotions', 'travelstore-promotions' ),
					'singular_name'      => __( 'Promotion', 'travelstore-promotions' ),
					'menu_name'          => __( 'Promotions', 'travelstore-promotions' ),
					'add_new'            => __( 'Add offer', 'travelstore-promotions' ),
					'add_new_item'       => __( 'Add a new offer', 'travelstore-promotions' ),
					'edit_item'          => __( 'Edit offer', 'travelstore-promotions' ),
					'new_item'           => __( 'New offer', 'travelstore-promotions' ),
					'search_items'       => __( 'Search offers', 'travelstore-promotions' ),
					'not_found'          => __( 'No offers yet.', 'travelstore-promotions' ),
					'not_found_in_trash' => __( 'No offers in the Trash.', 'travelstore-promotions' ),
					'all_items'          => __( 'All offers', 'travelstore-promotions' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_position'   => 26,
				'menu_icon'       => 'dashicons-megaphone',
				'supports'        => array( 'title' ),
				'capability_type' => array( 'ts_promotion', 'ts_promotions' ),
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/**
	 * Editors and Administrators (everyone who may edit other people's posts) may manage offers.
	 * Given at run time, so nothing is written to the user roles and nothing stays behind if the
	 * plugin is switched off.
	 */
	public static function grant_caps( $allcaps, $caps, $args, $user ) {
		if ( empty( $allcaps['edit_others_posts'] ) ) {
			return $allcaps;
		}
		foreach ( array( 'edit', 'edit_others', 'edit_private', 'edit_published', 'publish', 'read_private', 'delete', 'delete_others', 'delete_private', 'delete_published' ) as $verb ) {
			$allcaps[ $verb . '_ts_promotions' ] = true;
		}

		return $allcaps;
	}

	/* ------------------------------------------------------------------ */
	/* Saved fields                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Every saved field with its default. Keys are frozen once offers use them.
	 */
	public static function defaults() {
		$d = array(
			'badge'          => __( 'Offer', 'travelstore-promotions' ),
			'headline'       => '',
			'summary'        => '',
			'details'        => '',
			'terms'          => '',
			'image_id'       => 0,
			'colour'         => 'espresso',
			'importance'     => 'normal',
			'button_text'    => '',
			'button_kind'    => 'none',
			'button_page'    => 0,
			'button_url'     => '',
			'show_from'      => '',
			'show_until'     => '',
			'book_by'        => '',
			'travel_from'    => '',
			'travel_until'   => '',
			'topbar_text'    => '',
			'topbar_link'    => '',
			'topbar_page'    => 0,
			'popup_delay'    => 8,
			'popup_often'    => 'visit',
			'popup_trigger'  => 'time',
			'popup_scroll'   => 50,
			'slidein_delay'  => 8,
			'slidein_often'  => 'visit',
			'slidein_trigger' => 'time',
			'slidein_scroll' => 50,
			'bar_text'       => '',
			'bar_link'       => 'button',
			'bar_screens'    => 'phones',
			'ribbon_text'    => '',
			'ribbon_corner'  => 'top-left',
		);
		for ( $i = 1; $i <= self::TIERS; $i++ ) {
			$d[ 'tier' . $i . '_label' ] = '';
			$d[ 'tier' . $i . '_value' ] = '';
		}
		foreach ( self::PLACES as $place ) {
			$d[ $place . '_on' ]         = '';
			$d[ $place . '_where' ]      = 'chosen';
			$d[ $place . '_pages' ]      = array();
			$d[ $place . '_trips' ]      = ''; // Every trip.
			$d[ $place . '_trip_terms' ] = array(); // 1.2.0: trips of these kinds (term ids).
			$d[ $place . '_trip_ids' ]   = array(); // 1.2.0: these trips.
		}

		return $d;
	}

	/** One field of an offer (with its default when not saved). */
	public static function get( $post_id, $key ) {
		$defaults = self::defaults();
		if ( ! array_key_exists( $key, $defaults ) ) {
			return null;
		}
		if ( ! metadata_exists( 'post', $post_id, self::PREFIX . $key ) ) {
			return $defaults[ $key ];
		}

		return get_post_meta( $post_id, self::PREFIX . $key, true );
	}

	/** All fields of an offer. */
	public static function all( $post_id ) {
		$out = array();
		foreach ( array_keys( self::defaults() ) as $key ) {
			$out[ $key ] = self::get( $post_id, $key );
		}
		$out['id'] = (int) $post_id;

		return $out;
	}

	/** Colours offered for the top bar and the pop-up (named, from the site palette). */
	public static function colour_options() {
		return array(
			'espresso'   => __( 'Espresso (dark brown)', 'travelstore-promotions' ),
			'terracotta' => __( 'Terracotta (orange)', 'travelstore-promotions' ),
			'turquoise'  => __( 'Turquoise (blue)', 'travelstore-promotions' ),
			'oasis'      => __( 'Oasis (green)', 'travelstore-promotions' ),
			'gold'       => __( 'Gold (yellow)', 'travelstore-promotions' ),
		);
	}

	public static function importance_options() {
		return array(
			'normal' => __( 'Normal', 'travelstore-promotions' ),
			'high'   => __( 'High', 'travelstore-promotions' ),
			'top'    => __( 'Highest', 'travelstore-promotions' ),
		);
	}

	public static function often_options() {
		return array(
			'visit' => __( 'Once per visit', 'travelstore-promotions' ),
			'day'   => __( 'Once a day', 'travelstore-promotions' ),
			'once'  => __( 'Only once (not again on the same device)', 'travelstore-promotions' ),
		);
	}

	public static function delay_options() {
		return array( 3, 5, 8, 10, 15, 20, 30 );
	}

	public static function trigger_options() {
		return array(
			'time'   => __( 'After a number of seconds', 'travelstore-promotions' ),
			'scroll' => __( 'After scrolling part of the page', 'travelstore-promotions' ),
			'leave'  => __( 'When the visitor is about to leave (computers; on phones and tablets after the seconds below)', 'travelstore-promotions' ),
		);
	}

	public static function scroll_options() {
		return array( 25, 50, 75 );
	}

	public static function screens_options() {
		return array(
			'phones'  => __( 'Phones only', 'travelstore-promotions' ),
			'tablets' => __( 'Phones and tablets', 'travelstore-promotions' ),
			'all'     => __( 'All screens', 'travelstore-promotions' ),
		);
	}

	public static function bar_link_options() {
		return array(
			'button' => __( 'The offer\'s button (text and page as set above)', 'travelstore-promotions' ),
			'topbar' => __( 'The top bar link (text and page as set for the top bar)', 'travelstore-promotions' ),
		);
	}

	public static function corner_options() {
		return array(
			'top-left'  => __( 'Top left', 'travelstore-promotions' ),
			'top-right' => __( 'Top right', 'travelstore-promotions' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Settings (Promotions > Settings)                                    */
	/* ------------------------------------------------------------------ */

	const OPTION = 'tsp_settings';

	public static function setting( $key ) {
		$defaults = array(
			'offers_page' => 0,
			'hide_menu'   => '1',
		);
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		return array_key_exists( $key, $saved ) ? $saved[ $key ] : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : null );
	}

	/* ------------------------------------------------------------------ */
	/* Dates                                                               */
	/* ------------------------------------------------------------------ */

	/** A saved day (YYYY-MM-DD) as a time stamp at the start or end of that day, site time zone. 0 when empty. */
	public static function day_stamp( $day, $end_of_day = false ) {
		$day = (string) $day;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
			return 0;
		}
		try {
			$dt = new \DateTimeImmutable( $day . ( $end_of_day ? ' 23:59:59' : ' 00:00:00' ), wp_timezone() );
		} catch ( \Exception $e ) {
			return 0;
		}

		return $dt->getTimestamp();
	}

	/** A saved day as shown to visitors, e.g. "31 Dec 2026". */
	public static function day_text( $day ) {
		$ts = self::day_stamp( $day );

		return $ts ? wp_date( 'j M Y', $ts ) : '';
	}

	/** First and last second the offer shows (time stamps). Start 0 = from publishing; end 0 = no end day set. */
	public static function window( $post_id ) {
		return array(
			self::day_stamp( self::get( $post_id, 'show_from' ) ),
			self::day_stamp( self::get( $post_id, 'show_until' ), true ),
		);
	}

	/**
	 * State of an offer: draft, no_end, scheduled, running or ended.
	 */
	public static function state( $post_id, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return 'draft';
		}
		list( $start, $end ) = self::window( $post_id );
		if ( ! $end ) {
			return 'no_end';
		}
		if ( $now > $end ) {
			return 'ended';
		}
		if ( $start && $now < $start ) {
			return 'scheduled';
		}

		return 'running';
	}

	/* ------------------------------------------------------------------ */
	/* Which offers show where                                             */
	/* ------------------------------------------------------------------ */

	/** Ids of all Published offers (cached for the request). */
	public static function published_ids() {
		static $ids = null;
		if ( null === $ids ) {
			$ids = get_posts(
				array(
					'post_type'        => self::TYPE,
					'post_status'      => 'publish',
					'posts_per_page'   => 200,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
				)
			);
		}

		return $ids;
	}

	/** True when the offer is set to show in $place on the page or trip $page_id. */
	public static function targets( $post_id, $place, $page_id ) {
		if ( ! self::get( $post_id, $place . '_on' ) ) {
			return false;
		}
		if ( 'all' === self::get( $post_id, $place . '_where' ) ) {
			return true;
		}
		$page_id = (int) $page_id;
		if ( ! $page_id ) {
			return false;
		}
		$pages = array_map( 'intval', (array) self::get( $post_id, $place . '_pages' ) );
		if ( in_array( $page_id, $pages, true ) ) {
			return true;
		}

		return 'trip' === get_post_type( $page_id ) && self::targets_trip( $post_id, $place, $page_id );
	}

	/* ------------------------------------------------------------------ */
	/* Trips (1.2.0): every trip, trips of chosen kinds, chosen trips      */
	/* ------------------------------------------------------------------ */

	/** WP Travel Engine groupings offered for "trips of these kinds", in this order (those that exist). */
	public static function trip_taxonomies() {
		return array_values( array_filter( array( 'activities', 'trip_types', 'destination' ), 'taxonomy_exists' ) );
	}

	/** Term ids of a trip in the offered groupings (cached for the request). */
	public static function trip_term_ids( $trip_id ) {
		static $cache = array();
		$trip_id = (int) $trip_id;
		if ( ! isset( $cache[ $trip_id ] ) ) {
			$ids               = wp_get_object_terms( $trip_id, self::trip_taxonomies(), array( 'fields' => 'ids' ) );
			$cache[ $trip_id ] = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
		}

		return $cache[ $trip_id ];
	}

	/** True when the trip $trip_id is among the trips the offer chose for $place. */
	public static function targets_trip( $post_id, $place, $trip_id ) {
		if ( self::get( $post_id, $place . '_trips' ) ) {
			return true;
		}
		if ( in_array( (int) $trip_id, array_map( 'intval', (array) self::get( $post_id, $place . '_trip_ids' ) ), true ) ) {
			return true;
		}
		$terms = array_map( 'intval', (array) self::get( $post_id, $place . '_trip_terms' ) );

		return $terms && array_intersect( $terms, self::trip_term_ids( $trip_id ) );
	}

	/**
	 * Published trips the offer chose for $place one by one or by kind (not "every trip").
	 *
	 * @return int[]
	 */
	public static function chosen_trip_ids( $post_id, $place ) {
		$ids   = array_map( 'intval', (array) self::get( $post_id, $place . '_trip_ids' ) );
		$terms = array_map( 'intval', (array) self::get( $post_id, $place . '_trip_terms' ) );
		if ( $terms ) {
			$query = array( 'relation' => 'OR' );
			foreach ( self::trip_taxonomies() as $tax ) {
				$query[] = array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $terms );
			}
			$ids = array_merge(
				$ids,
				get_posts(
					array(
						'post_type'      => 'trip',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'tax_query'      => $query, // phpcs:ignore WordPress.DB.SlowDBQuery
					)
				)
			);
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return 'publish' === get_post_status( $id ) && 'trip' === get_post_type( $id );
				}
			)
		);
	}

	/**
	 * Offers showing now in $place on $page_id, most important first, then the one ending soonest.
	 *
	 * @return int[]
	 */
	public static function showing( $place, $page_id ) {
		return self::running(
			static function ( $id ) use ( $place, $page_id ) {
				return self::targets( $id, $place, $page_id );
			}
		);
	}

	/**
	 * Every offer showing now (for an Offers page), in the same order.
	 *
	 * @return int[]
	 */
	public static function showing_anywhere() {
		return self::running( '__return_true' );
	}

	/**
	 * Offers showing now that put a ribbon on cards.
	 *
	 * @return int[]
	 */
	public static function ribbons() {
		return self::running(
			static function ( $id ) {
				return (bool) self::get( $id, 'ribbon_on' );
			}
		);
	}

	/**
	 * Running offers accepted by $filter, most important first, then the one ending soonest.
	 *
	 * @param callable $filter Receives the offer id.
	 * @return int[]
	 */
	private static function running( $filter ) {
		$now  = time();
		$rank = array( 'top' => 0, 'high' => 1, 'normal' => 2 );
		$list = array();
		foreach ( self::published_ids() as $id ) {
			if ( 'running' === self::state( $id, $now ) && call_user_func( $filter, $id ) ) {
				$importance = (string) self::get( $id, 'importance' );
				$list[]     = array(
					'id'   => (int) $id,
					'rank' => isset( $rank[ $importance ] ) ? $rank[ $importance ] : 2,
					'end'  => self::window( $id )[1],
				);
			}
		}
		usort(
			$list,
			static function ( $a, $b ) {
				return array( $a['rank'], $a['end'], $a['id'] ) <=> array( $b['rank'], $b['end'], $b['id'] );
			}
		);

		return wp_list_pluck( $list, 'id' );
	}
}
