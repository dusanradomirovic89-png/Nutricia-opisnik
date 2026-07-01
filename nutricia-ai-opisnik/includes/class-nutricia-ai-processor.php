<?php
/**
 * Product processor: collects product data, prompts the LLM and applies
 * the generated SEO/CRO content back to the product.
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI_Processor {

	/**
	 * Process a single product by ID.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $force      Ignore "already done" state and reprocess.
	 * @return true|WP_Error
	 */
	public static function process( $product_id, $force = false ) {
		$product_id = absint( $product_id );

		if ( ! Nutricia_AI_Settings::is_configured() ) {
			return new WP_Error( 'nutricia_not_configured', __( 'Plugin nije podešen (nedostaje API ključ ili model).', 'nutricia-ai-opisnik' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'nutricia_no_product', __( 'Proizvod nije pronađen.', 'nutricia-ai-opisnik' ) );
		}

		update_post_meta( $product_id, Nutricia_AI_Settings::META_STATUS, 'processing' );

		$data = self::collect_product_data( $product );

		$system_prompt = self::build_system_prompt();
		$user_content  = self::build_user_content( $data );

		$result = Nutricia_AI_OpenRouter::chat( $system_prompt, $user_content );

		if ( is_wp_error( $result ) ) {
			self::mark_error( $product_id, $result->get_error_message() );
			return $result;
		}

		$applied = self::apply_result( $product, $result );
		if ( is_wp_error( $applied ) ) {
			self::mark_error( $product_id, $applied->get_error_message() );
			return $applied;
		}

		update_post_meta( $product_id, Nutricia_AI_Settings::META_STATUS, 'done' );
		update_post_meta( $product_id, Nutricia_AI_Settings::META_PROCESSED, current_time( 'mysql' ) );
		delete_post_meta( $product_id, Nutricia_AI_Settings::META_ERROR );

		Nutricia_AI_Logger::success(
			sprintf(
				/* translators: %s: product title */
				__( 'Obrađen proizvod: %s', 'nutricia-ai-opisnik' ),
				get_the_title( $product_id )
			),
			$product_id
		);

		return true;
	}

	/**
	 * Record an error against a product and bump attempt counter.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $message    Error message.
	 */
	protected static function mark_error( $product_id, $message ) {
		$attempts = (int) get_post_meta( $product_id, Nutricia_AI_Settings::META_ATTEMPTS, true );
		$attempts++;
		update_post_meta( $product_id, Nutricia_AI_Settings::META_ATTEMPTS, $attempts );
		update_post_meta( $product_id, Nutricia_AI_Settings::META_STATUS, 'error' );
		update_post_meta( $product_id, Nutricia_AI_Settings::META_ERROR, wp_strip_all_tags( $message ) );

		Nutricia_AI_Logger::error(
			sprintf(
				/* translators: 1: product title, 2: error message */
				__( 'Greška za "%1$s": %2$s', 'nutricia-ai-opisnik' ),
				get_the_title( $product_id ),
				$message
			),
			$product_id
		);
	}

	/**
	 * Collect all relevant data about a product.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function collect_product_data( $product ) {
		$settings   = Nutricia_AI_Settings::all();
		$product_id = $product->get_id();

		// Category hierarchy as "Parent > Child > Grandchild" strings.
		$category_paths = self::get_category_paths( $product_id );

		// Existing tags on this product.
		$current_tags = wp_get_object_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
		if ( is_wp_error( $current_tags ) ) {
			$current_tags = array();
		}

		// Composition meta field.
		$composition = get_post_meta( $product_id, $settings['composition_meta'], true );
		if ( is_array( $composition ) ) {
			$composition = implode( ', ', array_map( 'strval', $composition ) );
		}

		return array(
			'id'                => $product_id,
			'title'             => $product->get_name(),
			'sku'               => $product->get_sku(),
			'short_description' => wp_strip_all_tags( $product->get_short_description() ),
			'long_description'  => wp_strip_all_tags( $product->get_description() ),
			'categories'        => $category_paths,
			'current_tags'      => array_values( $current_tags ),
			'composition'       => is_string( $composition ) ? wp_strip_all_tags( $composition ) : '',
			'image_url'         => self::get_product_image_url( $product ),
		);
	}

	/**
	 * Build "Parent > Child" path strings for every category on a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	protected static function get_category_paths( $product_id ) {
		$terms = wp_get_object_terms( $product_id, 'product_cat' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$paths = array();
		foreach ( $terms as $term ) {
			$ancestors = get_ancestors( $term->term_id, 'product_cat' );
			$ancestors = array_reverse( $ancestors );
			$names     = array();
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'product_cat' );
				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$names[] = $ancestor->name;
				}
			}
			$names[] = $term->name;
			$paths[] = implode( ' > ', $names );
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Get the full-size main image URL for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	protected static function get_product_image_url( $product ) {
		$image_id = $product->get_image_id();
		if ( ! $image_id ) {
			return '';
		}
		$src = wp_get_attachment_image_url( $image_id, 'large' );
		return $src ? $src : '';
	}

	/**
	 * Decide whether to attach the image based on the vision setting.
	 *
	 * @param array $data Product data.
	 * @return bool
	 */
	protected static function should_send_image( $data ) {
		$settings = Nutricia_AI_Settings::all();

		if ( empty( $data['image_url'] ) ) {
			return false;
		}

		switch ( $settings['vision_mode'] ) {
			case 'never':
				return false;
			case 'always':
				return true;
			case 'auto':
			default:
				$text_len = mb_strlen(
					$data['short_description'] . ' ' . $data['long_description'] . ' ' . $data['composition']
				);
				return $text_len < (int) $settings['vision_threshold'];
		}
	}

	/**
	 * System prompt: role, task and strict output contract.
	 *
	 * @return string
	 */
	public static function build_system_prompt() {
		$settings = Nutricia_AI_Settings::all();
		$context  = trim( $settings['site_context'] );
		$max_tags = (int) $settings['max_tags'];

		$prompt  = "Ti si iskusan copywriter i SEO stručnjak za srpsku onlajn prodavnicu zdrave hrane, suplemenata i prirodne kozmetike.\n\n";
		$prompt .= "KONTEKST PRODAVNICE:\n" . $context . "\n\n";
		$prompt .= "ZADATAK: Na osnovu prosleđenih podataka o proizvodu napiši kompletno SEO i CRO optimizovan sadržaj na srpskom jeziku (ijekavica ili ekavica prati jezik iz naslova, podrazumevano ekavica).\n\n";
		$prompt .= "PRAVILA:\n";
		$prompt .= "- KRATAK OPIS: prodajan, sažet (2-4 rečenice), uvodi kupca u proizvod i ističe glavne benefite i poziv na akciju. Bez izmišljanja medicinskih tvrdnji.\n";
		$prompt .= "- DUGAČAK OPIS: kvalitetan, SEO optimizovan HTML (koristi <p>, <h3>, <ul><li>). Opiši šta je proizvod, kome je namenjen, glavne prednosti, način upotrebe ako je poznat i sastav ako je dostupan. Prirodno uklopi ključne reči. Bez lažnih ili neproverenih zdravstvenih obećanja.\n";
		$prompt .= "- ALT TEKST SLIKE: kratak, opisan alt atribut za glavnu sliku proizvoda (uključi naziv proizvoda).\n";
		$prompt .= "- TAGOVI: najviše {$max_tags} tagova. Obavezno iskoristi relevantne POSTOJEĆE tagove iz prosleđene liste kada odgovaraju, a možeš dodati i nove relevantne tagove.\n";
		$prompt .= "- META NASLOV (Rank Math): do 60 karaktera, privlačan, sadrži glavnu ključnu reč.\n";
		$prompt .= "- META OPIS (Rank Math): do 155 karaktera, ubedljiv, poziva na klik.\n";
		$prompt .= "- FOKUS KLJUČNA REČ (Rank Math): jedna glavna ključna reč/frazu za proizvod.\n\n";
		$prompt .= "Ne izmišljaj sastav, gramaže ni sertifikate koji nisu navedeni. Ako informacija nema, piši opšte ali tačno.\n\n";
		$prompt .= "ODGOVOR: Vrati ISKLJUČIVO validan JSON objekat (bez markdown ograda) sa tačno ovim ključevima:\n";
		$prompt .= "{\n";
		$prompt .= '  "short_description": "string (HTML dozvoljen, kratko)",' . "\n";
		$prompt .= '  "long_description": "string (HTML)",' . "\n";
		$prompt .= '  "image_alt": "string",' . "\n";
		$prompt .= '  "tags": ["string", "..."],' . "\n";
		$prompt .= '  "meta_title": "string",' . "\n";
		$prompt .= '  "meta_description": "string",' . "\n";
		$prompt .= '  "focus_keyword": "string"' . "\n";
		$prompt .= "}\n";

		return $prompt;
	}

	/**
	 * Build the user message content (text, plus image part when applicable).
	 *
	 * @param array $data Product data.
	 * @return array|string
	 */
	public static function build_user_content( $data ) {
		$existing_store_tags = self::get_store_tags();

		$lines   = array();
		$lines[] = 'PODACI O PROIZVODU:';
		$lines[] = 'Naslov: ' . ( $data['title'] ?: '(nema)' );
		if ( ! empty( $data['sku'] ) ) {
			$lines[] = 'SKU: ' . $data['sku'];
		}
		$lines[] = 'Postojeći kratki opis: ' . ( $data['short_description'] ?: '(nema)' );
		$lines[] = 'Postojeći dugački opis: ' . ( $data['long_description'] ?: '(nema)' );
		$lines[] = 'Kategorije (hijerarhija): ' . ( ! empty( $data['categories'] ) ? implode( ' | ', $data['categories'] ) : '(nema)' );
		$lines[] = 'Sastav: ' . ( $data['composition'] ?: '(nema)' );
		$lines[] = 'Postojeći tagovi ovog proizvoda: ' . ( ! empty( $data['current_tags'] ) ? implode( ', ', $data['current_tags'] ) : '(nema)' );
		$lines[] = 'Postojeći tagovi u prodavnici (biraj iz ove liste kad odgovara): ' . ( ! empty( $existing_store_tags ) ? implode( ', ', $existing_store_tags ) : '(nema)' );

		$text = implode( "\n", $lines );

		if ( self::should_send_image( $data ) ) {
			return array(
				array(
					'type' => 'text',
					'text' => $text . "\n\nAnaliziraj i priloženu sliku proizvoda kako bi opis bio precizniji.",
				),
				array(
					'type'      => 'image_url',
					'image_url' => array( 'url' => $data['image_url'] ),
				),
			);
		}

		return $text;
	}

	/**
	 * Return up to 300 existing store tag names, most used first.
	 *
	 * @return array
	 */
	protected static function get_store_tags() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'number'     => 300,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'fields'     => 'names',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_values( $terms );
	}

	/**
	 * Apply the AI result to the product.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $result  Parsed AI JSON.
	 * @return true|WP_Error
	 */
	public static function apply_result( $product, $result ) {
		$settings   = Nutricia_AI_Settings::all();
		$product_id = $product->get_id();

		$short = isset( $result['short_description'] ) ? self::clean_html( $result['short_description'] ) : '';
		$long  = isset( $result['long_description'] ) ? self::clean_html( $result['long_description'] ) : '';
		$alt   = isset( $result['image_alt'] ) ? sanitize_text_field( $result['image_alt'] ) : '';
		$mtitle = isset( $result['meta_title'] ) ? sanitize_text_field( $result['meta_title'] ) : '';
		$mdesc  = isset( $result['meta_description'] ) ? sanitize_text_field( $result['meta_description'] ) : '';
		$focus  = isset( $result['focus_keyword'] ) ? sanitize_text_field( $result['focus_keyword'] ) : '';

		$tags = array();
		if ( ! empty( $result['tags'] ) && is_array( $result['tags'] ) ) {
			foreach ( $result['tags'] as $tag ) {
				$tag = sanitize_text_field( $tag );
				if ( '' !== $tag ) {
					$tags[] = $tag;
				}
			}
			$tags = array_slice( array_values( array_unique( $tags ) ), 0, (int) $settings['max_tags'] );
		}

		if ( '' === $short && '' === $long && '' === $mtitle && empty( $tags ) ) {
			return new WP_Error( 'nutricia_empty_result', __( 'AI je vratio prazan rezultat.', 'nutricia-ai-opisnik' ) );
		}

		$overwrite = ! empty( $settings['overwrite_desc'] );

		// Short & long description on the product object.
		$dirty = false;
		if ( '' !== $short && ( $overwrite || '' === trim( (string) $product->get_short_description() ) ) ) {
			$product->set_short_description( $short );
			$dirty = true;
		}
		if ( '' !== $long && ( $overwrite || '' === trim( (string) $product->get_description() ) ) ) {
			$product->set_description( $long );
			$dirty = true;
		}
		if ( $dirty ) {
			$product->save();
		}

		// Image alt text on the attachment.
		if ( '' !== $alt ) {
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				update_post_meta( $image_id, '_wp_attachment_image_alt', $alt );
			}
		}

		// Tags.
		if ( ! empty( $tags ) ) {
			wp_set_object_terms( $product_id, $tags, 'product_tag', false );
		}

		// Rank Math meta.
		if ( '' !== $mtitle ) {
			update_post_meta( $product_id, 'rank_math_title', $mtitle );
		}
		if ( '' !== $mdesc ) {
			update_post_meta( $product_id, 'rank_math_description', $mdesc );
		}
		if ( '' !== $focus && ! empty( $settings['set_focus_keyword'] ) ) {
			update_post_meta( $product_id, 'rank_math_focus_keyword', $focus );
		}

		return true;
	}

	/**
	 * Sanitize AI-provided HTML using the WooCommerce post allowed tags.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	protected static function clean_html( $html ) {
		$html = (string) $html;
		return trim( wp_kses_post( $html ) );
	}
}
