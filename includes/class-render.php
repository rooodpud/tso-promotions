<?php
/**
 * HTML pieces shared by the top bar, the pop-up and the TS Promotion widget.
 *
 * Every offer's outer element carries its first and last showing moment (data-tsp-start /
 * data-tsp-end, milliseconds). A short script right after it removes the offer in the visitor's
 * browser once that moment has passed, so a page kept in a cache (Elementor, the host, a browser)
 * can never show an ended offer.
 */

namespace TravelStore\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Render {

	/** Attributes for an offer's outer element. */
	public static function window_attrs( $id ) {
		list( $start, $end ) = Promotion::window( $id );

		return sprintf(
			'data-tsp-id="%d" data-tsp-start="%s" data-tsp-end="%s"',
			(int) $id,
			esc_attr( $start ? $start * 1000 : 0 ),
			esc_attr( $end * 1000 )
		);
	}

	/** Script placed straight after an offer: removes it at once if it has ended (see Frontend::head_guard). */
	public static function guard_script() {
		return '<script>window.tspGuard&&tspGuard(document.currentScript.previousElementSibling);</script>';
	}

	public static function badge( $o, $class = 'tsp-badge' ) {
		$text = trim( (string) $o['badge'] );

		return '' === $text ? '' : '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
	}

	/** Tiers that have at least one of their two fields filled in. */
	public static function tiers( $o ) {
		$out = array();
		for ( $i = 1; $i <= Promotion::TIERS; $i++ ) {
			$label = trim( (string) $o[ 'tier' . $i . '_label' ] );
			$value = trim( (string) $o[ 'tier' . $i . '_value' ] );
			if ( '' !== $label || '' !== $value ) {
				$out[] = array( 'label' => $label, 'value' => $value );
			}
		}

		return $out;
	}

	public static function tiers_html( $o, $prefix = 'tsp-tier' ) {
		$tiers = self::tiers( $o );
		if ( ! $tiers ) {
			return '';
		}
		$html = '<ul class="' . esc_attr( $prefix ) . 's" role="list">';
		foreach ( $tiers as $t ) {
			$html .= '<li class="' . esc_attr( $prefix ) . '">';
			if ( '' !== $t['label'] ) {
				$html .= '<span class="' . esc_attr( $prefix ) . '__label">' . esc_html( $t['label'] ) . '</span>';
			}
			if ( '' !== $t['value'] ) {
				$html .= '<span class="' . esc_attr( $prefix ) . '__value">' . esc_html( $t['value'] ) . '</span>';
			}
			$html .= '</li>';
		}

		return $html . '</ul>';
	}

	/** "Book by 31 Dec 2026" and "Travel 17 Oct 2026 – 30 Apr 2027" (each only when set). */
	public static function date_parts( $o ) {
		$parts = array();
		$book  = Promotion::day_text( $o['book_by'] );
		if ( $book ) {
			/* translators: %s: a date */
			$parts[] = sprintf( __( 'Book by %s', 'travelstore-promotions' ), $book );
		}
		$from  = Promotion::day_text( $o['travel_from'] );
		$until = Promotion::day_text( $o['travel_until'] );
		if ( $from && $until ) {
			/* translators: 1: first travel date, 2: last travel date */
			$parts[] = sprintf( __( 'Travel %1$s – %2$s', 'travelstore-promotions' ), $from, $until );
		} elseif ( $from ) {
			/* translators: %s: a date */
			$parts[] = sprintf( __( 'Travel from %s', 'travelstore-promotions' ), $from );
		} elseif ( $until ) {
			/* translators: %s: a date */
			$parts[] = sprintf( __( 'Travel until %s', 'travelstore-promotions' ), $until );
		}

		return $parts;
	}

	public static function dates_html( $o, $class = 'tsp-dates' ) {
		$parts = self::date_parts( $o );
		if ( ! $parts ) {
			return '';
		}
		$html = '<p class="' . esc_attr( $class ) . '">';
		foreach ( $parts as $i => $part ) {
			$html .= ( $i ? '<span class="' . esc_attr( $class ) . '__sep" aria-hidden="true"> · </span>' : '' ) . '<span>' . esc_html( $part ) . '</span>';
		}

		return $html . '</p>';
	}

	/**
	 * Address of a page by id: Published pages and trips only, and never the page being shown
	 * (a link to the page you are on is no use). '' otherwise.
	 */
	public static function page_url( $page_id, $current_id = 0 ) {
		$page_id = (int) $page_id;
		if ( ! $page_id || $page_id === (int) $current_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/** Address of the offer's button ('' = no button). */
	public static function button_url( $o, $current_id = 0 ) {
		if ( '' === trim( (string) $o['button_text'] ) ) {
			return '';
		}
		if ( 'page' === $o['button_kind'] ) {
			$url = self::page_url( $o['button_page'], $current_id );
			// 1.1.0: the page's enquiry forms then say which offer the visitor is asking about (Enquiries).
			return '' !== $url && ! empty( $o['id'] ) ? add_query_arg( Enquiries::PARAM, (int) $o['id'], $url ) : $url;
		}
		if ( 'url' === $o['button_kind'] ) {
			return esc_url_raw( (string) $o['button_url'] );
		}

		return '';
	}

	/** Link attributes for a web address: links to other websites open in a new tab. */
	public static function link_attrs( $url ) {
		$attrs = 'href="' . esc_url( $url ) . '"';
		$host  = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && preg_replace( '/^www\./', '', strtolower( $host ) ) !== preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) ) {
			$attrs .= ' target="_blank" rel="noopener"';
		}

		return $attrs;
	}

	/**
	 * Offers as bands or cards: used by the TS Promotion widget and, on trip pages, below the trip's
	 * header (1.2.0). $details_filter (optional callable) post-processes the formatted details.
	 *
	 * @param int[] $ids Offer ids.
	 */
	public static function spot_html( $ids, $page_id, $look = 'band', $tag = 'h2', $btn = 'primary', $details_filter = null ) {
		$look = 'card' === $look ? 'card' : 'band';
		$tag  = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ), true ) ? $tag : 'h2';
		$html = '<div class="ts-w tsp-spot tsp-spot--' . esc_attr( $look ) . '">';
		foreach ( $ids as $id ) {
			$o       = Promotion::all( $id );
			$url     = self::button_url( $o, $page_id );
			$title   = trim( (string) $o['headline'] );
			$details = self::details_html( $o, 'tsp-item__details' );
			if ( $details && is_callable( $details_filter ) ) {
				$details = (string) call_user_func( $details_filter, $details );
			}
			$html .= '<div class="tsp-item" ' . self::window_attrs( $id ) . '>';
			$html .= '<div class="tsp-item__intro">' . self::badge( $o, 'tsp-item__badge' );
			if ( '' !== $title ) {
				$html .= sprintf( '<%1$s class="tsp-item__title">%2$s</%1$s>', esc_html( $tag ), esc_html( $title ) );
			}
			if ( '' !== trim( (string) $o['summary'] ) ) {
				$html .= '<p class="tsp-item__text">' . esc_html( $o['summary'] ) . '</p>';
			}
			$html .= $details . '</div>';
			$html .= self::tiers_html( $o, 'tsp-tile' );
			$dates = self::dates_html( $o, 'tsp-item__dates' );
			if ( $dates || $url ) {
				$html .= '<div class="tsp-item__foot">' . $dates;
				if ( $url ) {
					$html .= '<a class="ts-btn ts-btn--' . esc_attr( $btn ) . '" ' . self::link_attrs( $url ) . '>' . esc_html( $o['button_text'] ) . '</a>';
				}
				$html .= '</div>';
			}
			$html .= self::terms_html( $o, 'tsp-item__terms' ) . '</div>' . self::guard_script();
		}

		return $html . '</div>';
	}

	public static function details_html( $o, $class = 'tsp-details' ) {
		$details = trim( (string) $o['details'] );

		return '' === $details ? '' : '<div class="' . esc_attr( $class ) . '">' . wpautop( wp_kses_post( $details ) ) . '</div>';
	}

	public static function terms_html( $o, $class = 'tsp-terms' ) {
		$terms = trim( (string) $o['terms'] );

		return '' === $terms ? '' : '<p class="' . esc_attr( $class ) . '">' . nl2br( esc_html( $terms ) ) . '</p>';
	}
}
