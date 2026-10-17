<?php
/**
 * "You are asking about: <offer>" on enquiry forms (1.1.0).
 *
 * An offer's button to a page on this site carries ?tsp_offer=<id>. On arrival the visitor's
 * browser remembers the offer for the rest of the visit (only while the offer is showing); every
 * enquiry form (TS Enquiry Form and the contact flyout's short form) then shows a line naming the
 * offer, with a way to remove it, and sends the offer's id. When TS Enquiries saves the enquiry,
 * this adds an "Offer" line to it (filter ts_enquiry_before_save), so it is in the Enquiries list
 * and in the office email. An id that is not a showing offer is ignored.
 */

namespace TravelStore\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Enquiries {

	const PARAM = 'tsp_offer';

	public static function register() {
		add_filter( 'ts_enquiry_before_save', array( __CLASS__, 'add_offer' ), 10, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'arrival_data' ), 15 );
	}

	/** The offer id from a request value, if that offer is showing now; else 0. */
	public static function running_id( $value ) {
		$id = absint( $value );

		return $id && Promotion::TYPE === get_post_type( $id ) && 'running' === Promotion::state( $id ) ? $id : 0;
	}

	/** Name of an offer as visitors see it. */
	public static function offer_name( $id ) {
		$headline = trim( (string) Promotion::get( $id, 'headline' ) );

		return '' !== $headline ? $headline : get_the_title( $id );
	}

	/**
	 * Add the offer to an enquiry being saved (TS Enquiries Store::create).
	 *
	 * @param array $clean  Checked form data: rows (key, label, value), data, …
	 * @param array $source Page the form was sent from.
	 */
	public static function add_offer( $clean, $source ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- the enquiry's own nonce was checked before saving.
		$id = isset( $_POST[ self::PARAM ] ) ? self::running_id( wp_unslash( $_POST[ self::PARAM ] ) ) : 0;
		if ( ! $id || ! is_array( $clean ) ) {
			return $clean;
		}
		$name = self::offer_name( $id );
		if ( ! isset( $clean['rows'] ) || ! is_array( $clean['rows'] ) ) {
			$clean['rows'] = array();
		}
		array_unshift(
			$clean['rows'],
			array(
				'key'   => 'offer',
				'label' => __( 'Offer', 'travelstore-promotions' ),
				'value' => $name,
			)
		);
		if ( isset( $clean['data'] ) && is_array( $clean['data'] ) ) {
			$clean['data']['offer']    = $name;
			$clean['data']['offer_id'] = $id;
		}

		return $clean;
	}

	/**
	 * On a page opened from an offer's button: the offer's name and last showing moment, for the
	 * browser to remember (read by promotions.js).
	 */
	public static function arrival_data() {
		// phpcs:ignore WordPress.Security.NonceVerification -- read-only.
		if ( is_admin() || ! isset( $_GET[ self::PARAM ] ) ) {
			return;
		}
		$id = self::running_id( wp_unslash( $_GET[ self::PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id ) {
			return;
		}
		$data = array(
			'id'   => $id,
			'name'   => self::offer_name( $id ),
			'end'    => Promotion::window( $id )[1] * 1000,
			'colour' => array_key_exists( Promotion::get( $id, 'colour' ), Promotion::colour_options() ) ? Promotion::get( $id, 'colour' ) : 'espresso',
		);
		echo '<script>window.tspArrival=' . wp_json_encode( $data ) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
