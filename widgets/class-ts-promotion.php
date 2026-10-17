<?php
/**
 * Widget: TS Promotion — the place on a page where offers show as a band or a card.
 *
 * The offers themselves are entered in wp-admin > Promotions (tick "Band or card on the page" and
 * choose the page). This widget only decides how they look here. When no offer is showing it prints
 * nothing for visitors (an editor-only note in Elementor), so it can stay on the page all year.
 *
 * Never cached by Elementor (is_dynamic_content), so a starting or ending offer changes the page
 * at once; each offer also removes itself in the browser once its last day is over (Render).
 */

namespace TravelStore\Widgets;

use Elementor\Controls_Manager;
use TravelStore\Promotions\Promotion;
use TravelStore\Promotions\Render;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TS_Promotion extends TS_Base_Widget {

	public function get_name() {
		return 'ts-promotion';
	}

	public function get_title() {
		return __( 'TS Promotion', 'travelstore-promotions' );
	}

	public function get_icon() {
		return 'eicon-price-list';
	}

	public function get_keywords() {
		return array( 'promotion', 'offer', 'deal', 'discount', 'sale', 'band', 'travel' );
	}

	protected function is_dynamic_content(): bool {
		return true;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'ts_content',
			array( 'label' => __( 'Promotion', 'travelstore-promotions' ) )
		);
		$this->add_control(
			'ts_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: link to the Promotions screen */
					esc_html__( 'Offers are written in %s: tick "Band or card on the page" and choose this page. They appear and disappear here by themselves on their display days.', 'travelstore-promotions' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Promotion::TYPE ) ) . '" target="_blank">' . esc_html__( 'wp-admin > Promotions', 'travelstore-promotions' ) . '</a>'
				),
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_control(
			'look',
			array(
				'label'   => __( 'Look', 'travelstore-promotions' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'band',
				'options' => array(
					'band' => __( 'Band (wide; text beside the tiers)', 'travelstore-promotions' ),
					'card' => __( 'Card (compact; everything stacked)', 'travelstore-promotions' ),
				),
			)
		);
		// 1.1.0: an Offers page lists every offer showing now, whatever pages the offers chose.
		$this->add_control(
			'which',
			array(
				'label'   => __( 'Which offers', 'travelstore-promotions' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'page',
				'options' => array(
					'page' => __( 'Offers set for this page', 'travelstore-promotions' ),
					'all'  => __( 'Every offer showing now (for an Offers page)', 'travelstore-promotions' ),
				),
			)
		);
		$this->add_control(
			'empty_text',
			array(
				'label'       => __( 'Message when no offer is showing', 'travelstore-promotions' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => '',
				'description' => __( 'Leave empty to show nothing at all (usual on normal pages). On an Offers page, for example: There are no special offers right now. Ask us about your trip and we will find the best value for you.', 'travelstore-promotions' ),
			)
		);
		$this->add_control(
			'how_many',
			array(
				'label'       => __( 'When several offers are showing', 'travelstore-promotions' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'all',
				'options'     => array(
					'all'   => __( 'Show them all, one below the other', 'travelstore-promotions' ),
					'first' => __( 'Show only the most important one', 'travelstore-promotions' ),
				),
			)
		);
		$this->add_tag_control( 'title_tag', __( 'Headline level', 'travelstore-promotions' ), 'h2' );
		$this->add_control(
			'button_style',
			array(
				'label'   => __( 'Button look', 'travelstore-promotions' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'primary',
				'options' => $this->button_style_options(),
			)
		);
		$this->end_controls_section();

		// ---------- Style: box ----------
		$this->start_controls_section( 'ts_style_box', array( 'label' => __( 'Box', 'travelstore-promotions' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_color_control( 'box_bg', __( 'Background', 'travelstore-promotions' ), '.tsp-item', 'background-color', 'ts_cream' );
		$this->add_color_control( 'box_border', __( 'Border colour', 'travelstore-promotions' ), '.tsp-item', 'border-color' );
		$this->add_control(
			'box_border_width',
			array(
				'label'      => __( 'Border width', 'travelstore-promotions' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 8 ) ),
				'selectors'  => array( '{{WRAPPER}} .tsp-item' => 'border-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'box_radius',
			array(
				'label'      => __( 'Corner roundness', 'travelstore-promotions' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .tsp-item' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => __( 'Padding', 'travelstore-promotions' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .tsp-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'box_gap',
			array(
				'label'      => __( 'Space between offers', 'travelstore-promotions' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .tsp-spot' => 'row-gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'how_many' => 'all' ),
			)
		);
		$this->end_controls_section();

		// ---------- Style: badge, headline, text ----------
		$this->start_controls_section( 'ts_style_text', array( 'label' => __( 'Badge, headline and text', 'travelstore-promotions' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_color_control( 'badge_bg', __( 'Badge colour', 'travelstore-promotions' ), '.tsp-item__badge', 'background-color', 'ts_terracotta' );
		$this->add_color_control( 'badge_color', __( 'Badge text', 'travelstore-promotions' ), '.tsp-item__badge' );
		$this->add_color_control( 'title_color', __( 'Headline colour', 'travelstore-promotions' ), '.tsp-item__title', 'color', 'ts_espresso' );
		$this->add_typography_control( 'title_typography', __( 'Headline typography', 'travelstore-promotions' ), '.tsp-item__title', 'ts_heading' );
		$this->add_color_control( 'text_color', __( 'Text colour', 'travelstore-promotions' ), '.tsp-item__text, .tsp-item__details' );
		$this->add_typography_control( 'text_typography', __( 'Text typography', 'travelstore-promotions' ), '.tsp-item__text, .tsp-item__details', 'ts_body' );
		$this->end_controls_section();

		// ---------- Style: tiers ----------
		$this->start_controls_section( 'ts_style_tiles', array( 'label' => __( 'Offer tiers', 'travelstore-promotions' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'tiles_per_row',
			array(
				'label'     => __( 'Tiers per row', 'travelstore-promotions' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''   => __( 'Automatic (as many as fit)', 'travelstore-promotions' ),
					'n1' => '1',
					'n2' => '2',
					'n3' => '3',
					'n4' => '4',
				),
				'selectors_dictionary' => array( 'n1' => '1', 'n2' => '2', 'n3' => '3', 'n4' => '4' ),
				'selectors' => array( '{{WRAPPER}} .tsp-tiles' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			)
		);
		$this->add_color_control( 'tile_bg', __( 'Tier background', 'travelstore-promotions' ), '.tsp-tile', 'background-color', 'ts_sand' );
		$this->add_color_control( 'tile_border', __( 'Tier border colour', 'travelstore-promotions' ), '.tsp-tile', 'border-color' );
		$this->add_control(
			'tile_radius',
			array(
				'label'      => __( 'Tier corner roundness', 'travelstore-promotions' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .tsp-tile' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_color_control( 'tile_label_color', __( '"Who or when" colour', 'travelstore-promotions' ), '.tsp-tile__label' );
		$this->add_typography_control( 'tile_label_typography', __( '"Who or when" typography', 'travelstore-promotions' ), '.tsp-tile__label' );
		$this->add_color_control( 'tile_value_color', __( '"What you get" colour', 'travelstore-promotions' ), '.tsp-tile__value', 'color', 'ts_terracotta_dark' );
		$this->add_typography_control( 'tile_value_typography', __( '"What you get" typography', 'travelstore-promotions' ), '.tsp-tile__value', 'ts_heading' );
		$this->end_controls_section();

		// ---------- Style: dates and small print ----------
		$this->start_controls_section( 'ts_style_dates', array( 'label' => __( 'Dates and small print', 'travelstore-promotions' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_color_control( 'dates_color', __( 'Dates colour', 'travelstore-promotions' ), '.tsp-item__dates' );
		$this->add_typography_control( 'dates_typography', __( 'Dates typography', 'travelstore-promotions' ), '.tsp-item__dates' );
		$this->add_color_control( 'terms_color', __( 'Small print colour', 'travelstore-promotions' ), '.tsp-item__terms' );
		$this->add_typography_control( 'terms_typography', __( 'Small print typography', 'travelstore-promotions' ), '.tsp-item__terms' );
		$this->end_controls_section();

		$this->add_button_style_section();
		$this->register_extra_css_controls();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$page_id = (int) get_the_ID();
		$ids     = isset( $s['which'] ) && 'all' === $s['which'] ? Promotion::showing_anywhere() : Promotion::showing( 'spot', $page_id );
		if ( 'first' === $s['how_many'] ) {
			$ids = array_slice( $ids, 0, 1 );
		}
		if ( ! $ids ) {
			$empty = isset( $s['empty_text'] ) ? trim( (string) $s['empty_text'] ) : '';
			if ( '' !== $empty ) {
				// Same box and text styles as an offer (Box and Text controls apply).
				echo '<div class="ts-w tsp-spot"><div class="tsp-item tsp-item--empty"><p class="tsp-item__text">' . nl2br( esc_html( $empty ) ) . '</p></div></div>';
				return;
			}
			$this->render_empty( __( 'TS Promotion: no offer is showing on this page today, so visitors see nothing here. Offers are written in wp-admin > Promotions.', 'travelstore-promotions' ) );
			return;
		}

		$look   = 'card' === $s['look'] ? 'card' : 'band';
		$tag    = $this->safe_tag( $s['title_tag'], 'h2' );
		$styles = $this->button_style_options();
		$btn    = isset( $styles[ $s['button_style'] ] ) ? $s['button_style'] : 'primary';

		echo Render::spot_html(
			$ids,
			$page_id,
			$look,
			$tag,
			$btn,
			function ( $html ) {
				return $this->mark_external_links( $html );
			}
		); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
