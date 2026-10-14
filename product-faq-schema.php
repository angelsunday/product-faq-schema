<?php
/**
 * Plugin Name: Product FAQ Schema
 * Description: Δημιουργεί αυτόματα FAQPage JSON-LD schema για κάθε προϊόν WooCommerce.
 * Version:     0.1.0
 * Author:      Sunday
 * Text Domain: product-faq-schema
 */

// Αποτροπή απευθείας πρόσβασης στο αρχείο
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Επιλέγει γλώσσα για τα templates: ελληνικά αν η γλώσσα του site είναι el, αλλιώς αγγλικά.
 */
function pfs_is_greek() {
	return 0 === strpos( get_locale(), 'el' );
}

/**
 * Φτιάχνει ερωτήσεις/απαντήσεις από το κείμενο της περιγραφής, με κανόνες (χωρίς AI).
 * Ψάχνει προτάσεις που περιέχουν λέξεις-κλειδιά (υλικό, καθαρισμός, χρήση, διαστάσεις,
 * χρώματα, περιεχόμενο, εγγύηση, προέλευση) και τις μετατρέπει σε ζεύγη ερώτηση/απάντηση.
 * Η απάντηση είναι η ίδια η πρόταση της περιγραφής, άρα δεν επινοούμε πληροφορίες.
 *
 * @return array Λίστα από [ 'q' => ..., 'a' => ... ]
 */
function pfs_generate_faqs_from_text( $text, $name ) {
	$gr = pfs_is_greek();

	// Καθαρό κείμενο, χωρίς HTML και πολλαπλά κενά
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( mb_strlen( $text ) < 40 ) {
		return array();
	}

	// Σπάσιμο σε προτάσεις (τελεία, θαυμαστικό, ερωτηματικό)
	$sentences = preg_split( '/(?<=[.!?;])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	$used      = array(); // προτάσεις που έχουν ήδη χρησιμοποιηθεί
	$faqs      = array();

	// Κανόνες: regex για λέξεις-κλειδιά => ερώτηση (ελληνικά / αγγλικά)
	$rules = array(
		array(
			'/(φτιαγμ[εέ]ν|κατασκευασμ[εέ]ν|υλικ|made (of|from)|material)/iu',
			"Από τι υλικό είναι φτιαγμένο το {$name};",
			"What is {$name} made of?",
		),
		array(
			'/(πλ[υύ]σιμο|πλ[εέ]νεται|καθαρ[ιί]ζ|καθαρισμ|συντ[ηή]ρηση|wash|clean)/iu',
			"Πώς καθαρίζεται και συντηρείται το {$name};",
			"How do I clean and care for {$name}?",
		),
		array(
			'/(κατ[αά]λληλ|ιδανικ|χρησιμοποι|suitable for|ideal for|perfect for)/iu',
			"Για ποια χρήση είναι κατάλληλο το {$name};",
			"What is {$name} suitable for?",
		),
		array(
			'/(διαστ[αά]σεις|μ[εέ]γεθος|\d+\s?(cm|εκ|mm|χιλ)|dimensions|size)/iu',
			"Ποιες είναι οι διαστάσεις του {$name};",
			"What are the dimensions of {$name}?",
		),
		array(
			'/(χρ[ωώ]μα|colou?rs?)/iu',
			"Σε ποια χρώματα διατίθεται το {$name};",
			"What colors does {$name} come in?",
		),
		array(
			'/(περιλαμβ[αά]νει|συνοδε[υύ]εται|περι[εέ]χει|includes|comes with|contains)/iu',
			"Τι περιλαμβάνει το {$name};",
			"What is included with {$name}?",
		),
		array(
			'/(εγγ[υύ]ηση|warranty|guarantee)/iu',
			"Έχει εγγύηση το {$name};",
			"Does {$name} come with a warranty?",
		),
		array(
			'/(χειροποίητ|χειροποιητ|παρ[αά]γεται|handmade|made in)/iu',
			"Πού και πώς παρασκευάζεται το {$name};",
			"Where and how is {$name} made?",
		),
	);

	// 1. Γενική ερώτηση "Τι είναι": η πρώτη πρόταση, αν είναι αρκετά περιεκτική
	if ( mb_strlen( $sentences[0] ) >= 40 ) {
		$faqs[] = array(
			'q' => $gr ? "Τι είναι το {$name};" : "What is {$name}?",
			'a' => $sentences[0],
		);
		$used[0] = true;
	}

	// 2. Ένας κανόνας => το πολύ μία ερώτηση, από την πρώτη πρόταση που ταιριάζει
	foreach ( $rules as $rule ) {
		foreach ( $sentences as $i => $sentence ) {
			if ( isset( $used[ $i ] ) || mb_strlen( $sentence ) < 20 ) {
				continue;
			}
			if ( preg_match( $rule[0], $sentence ) ) {
				$answer = mb_strlen( $sentence ) > 500 ? mb_substr( $sentence, 0, 497 ) . '...' : $sentence;
				$faqs[] = array(
					'q' => $gr ? $rule[1] : $rule[2],
					'a' => $answer,
				);
				$used[ $i ] = true;
				break; // επόμενος κανόνας
			}
		}
	}

	return $faqs;
}

/**
 * Φτιάχνει FAQs από δεδομένα WooCommerce (τιμή, διαθεσιμότητα, SKU, κατηγορία, βάρος, διαστάσεις).
 * Χρησιμοποιείται ΜΟΝΟ όταν δεν βρέθηκαν ερωτήσεις μέσα στο περιεχόμενο.
 * Παραλείπει όποια ερώτηση δεν έχει δεδομένο, ώστε να μην βγαίνουν κενές απαντήσεις.
 */
function pfs_get_template_faqs( $post_id ) {
	// Χωρίς WooCommerce δεν υπάρχουν δεδομένα προϊόντος
	if ( ! function_exists( 'wc_get_product' ) ) {
		return array();
	}

	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return array();
	}

	$gr   = pfs_is_greek();
	$name = $product->get_name();
	$faqs = array();

	// 1. Τιμή
	if ( pfs_template_on( 'price' ) && '' !== $product->get_price() ) {
		// wc_price επιστρέφει HTML, το καθαρίζουμε σε απλό κείμενο
		$price = html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ) );
		$faqs[] = array(
			'q' => $gr ? "Πόσο κοστίζει το {$name};" : "How much does {$name} cost?",
			'a' => $gr ? "Η τιμή του {$name} είναι {$price}." : "The price of {$name} is {$price}.",
		);
	}

	// 2. Διαθεσιμότητα
	$status = $product->get_stock_status(); // instock | outofstock | onbackorder
	$labels = array(
		'instock'     => $gr ? 'Ναι, είναι διαθέσιμο και έτοιμο για αποστολή.' : 'Yes, it is in stock and ready to ship.',
		'outofstock'  => $gr ? 'Αυτή τη στιγμή το προϊόν δεν είναι διαθέσιμο.' : 'This product is currently out of stock.',
		'onbackorder' => $gr ? 'Το προϊόν είναι διαθέσιμο κατόπιν παραγγελίας.' : 'This product is available on backorder.',
	);
	if ( pfs_template_on( 'stock' ) && isset( $labels[ $status ] ) ) {
		$faqs[] = array(
			'q' => $gr ? "Είναι διαθέσιμο το {$name};" : "Is {$name} in stock?",
			'a' => $labels[ $status ],
		);
	}

	// 3. SKU
	if ( pfs_template_on( 'sku' ) && $product->get_sku() ) {
		$faqs[] = array(
			'q' => $gr ? "Ποιος είναι ο κωδικός (SKU) του {$name};" : "What is the SKU of {$name}?",
			'a' => $gr ? "Ο κωδικός του προϊόντος είναι {$product->get_sku()}." : "The product SKU is {$product->get_sku()}.",
		);
	}

	// 4. Κατηγορία
	$cats = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'names' ) );
	if ( pfs_template_on( 'category' ) && ! is_wp_error( $cats ) && ! empty( $cats ) ) {
		$list   = implode( ', ', $cats );
		$faqs[] = array(
			'q' => $gr ? "Σε ποια κατηγορία ανήκει το {$name};" : "Which category does {$name} belong to?",
			'a' => $gr ? "Το {$name} ανήκει στην κατηγορία: {$list}." : "{$name} belongs to: {$list}.",
		);
	}

	// 5. Βάρος και διαστάσεις (μόνο αν έχουν συμπληρωθεί)
	$weight     = $product->get_weight();
	$dimensions = $product->get_dimensions( false ); // πίνακας length/width/height
	$has_dims   = ! empty( $dimensions['length'] ) && ! empty( $dimensions['width'] ) && ! empty( $dimensions['height'] );

	if ( pfs_template_on( 'dims' ) && ( $weight || $has_dims ) ) {
		$parts = array();
		if ( $weight ) {
			$parts[] = ( $gr ? 'Βάρος: ' : 'Weight: ' ) . $weight . ' ' . get_option( 'woocommerce_weight_unit' );
		}
		if ( $has_dims ) {
			$parts[] = ( $gr ? 'Διαστάσεις: ' : 'Dimensions: ' )
				. $dimensions['length'] . ' x ' . $dimensions['width'] . ' x ' . $dimensions['height']
				. ' ' . get_option( 'woocommerce_dimension_unit' );
		}
		$faqs[] = array(
			'q' => $gr ? "Ποιες είναι οι διαστάσεις και το βάρος του {$name};" : "What are the size and weight of {$name}?",
			'a' => implode( '. ', $parts ) . '.',
		);
	}

	return $faqs;
}

/**
 * Κόβει μια απάντηση στο όριο χαρακτήρων, κατά προτίμηση στο τέλος πρότασης.
 */
function pfs_trim_answer( $text, $limit = 500 ) {
	// Καθαρό κείμενο, χωρίς HTML και πολλαπλά κενά
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}

	// Βρίσκουμε το τελευταίο τέλος πρότασης μέσα στο όριο
	$cut = mb_substr( $text, 0, $limit );
	$pos = max( (int) mb_strrpos( $cut, '.' ), (int) mb_strrpos( $cut, '!' ), (int) mb_strrpos( $cut, '?' ) );

	// Αν υπάρχει λογικό σημείο κοπής (όχι πολύ νωρίς), κόβουμε εκεί· αλλιώς σκληρή κοπή
	if ( $pos > 150 ) {
		return mb_substr( $cut, 0, $pos + 1 );
	}
	return mb_substr( $text, 0, $limit - 3 ) . '...';
}

/**
 * Φτιάχνει FAQs από τα custom πεδία (SCF/ACF) του προϊόντος.
 * Κάθε πεδίο => μία ερώτηση. Αν το πεδίο είναι κενό, παραλείπεται.
 * Η λίστα πεδίων μπορεί να αλλάξει με το φίλτρο 'pfs_custom_fields'.
 */
function pfs_get_custom_field_faqs( $post_id, $name ) {
	$gr = pfs_is_greek();

	// meta key => [ ερώτηση ελληνικά, ερώτηση αγγλικά ]
	$fields = apply_filters(
		'pfs_custom_fields',
		array(
			'product_ingredients'  => array(
				"Ποια είναι τα συστατικά του {$name};",
				"What are the ingredients of {$name}?",
			),
			'product_general_tips' => array(
				"Ποιες είναι οι χρήσιμες συμβουλές για το {$name};",
				"What are the useful tips for {$name}?",
			),
		)
	);

	$faqs = array();
	foreach ( $fields as $key => $questions ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( ! is_string( $value ) ) {
			continue; // αγνοούμε πεδία που δεν είναι κείμενο
		}

		$answer = pfs_trim_answer( $value );
		if ( mb_strlen( $answer ) < 20 ) {
			continue; // πολύ μικρό ή κενό πεδίο
		}

		$faqs[] = array(
			'q' => $gr ? $questions[0] : $questions[1],
			'a' => $answer,
		);
	}

	return $faqs;
}

/**
 * Συγκεντρώνει τις FAQs ενός προϊόντος.
 * Σειρά: custom πεδία, ερωτήσεις από το κείμενο της περιγραφής, templates WooCommerce.
 */
function pfs_get_product_faqs( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}

	// Περιγραφή + σύντομη περιγραφή του προϊόντος
	$html = $post->post_content . "\n" . $post->post_excerpt;

	// Περνάμε το περιεχόμενο από το the_content ώστε να εκτελεστούν blocks/shortcodes
	$html = apply_filters( 'the_content', $html );

	$faqs = array();

	// 1. Custom πεδία (συστατικά, χρήσιμες συμβουλές) και ερωτήσεις που παράγονται από το κείμενο
	if ( pfs_get_option( 'use_generated' ) ) {
		$faqs = pfs_get_custom_field_faqs( $post_id, $post->post_title );
		$faqs = array_merge( $faqs, pfs_generate_faqs_from_text( $html, $post->post_title ) );
	}

	// 2. Templates WooCommerce (τιμή, διαθεσιμότητα, SKU...) για να συμπληρωθεί η λίστα
	if ( pfs_get_option( 'use_templates' ) ) {
		$faqs = array_merge( $faqs, pfs_get_template_faqs( $post_id ) );
	}

	// Μέγιστος αριθμός FAQs (0 = χωρίς όριο)
	$max = (int) pfs_get_option( 'max_faqs' );
	if ( $max > 0 ) {
		$faqs = array_slice( $faqs, 0, $max );
	}

	return $faqs;
}

/**
 * Δημιουργεί και τυπώνει το FAQPage JSON-LD στο <head>.
 */
function pfs_output_schema() {
	// Μόνο σε σελίδες μεμονωμένου προϊόντος
	if ( ! is_singular( 'product' ) ) {
		return;
	}

	// Global διακόπτης από τις ρυθμίσεις
	if ( ! pfs_get_option( 'enabled' ) ) {
		return;
	}

	// Απενεργοποίηση ανά προϊόν (από το meta box)
	if ( '1' === get_post_meta( get_the_ID(), '_pfs_disable', true ) ) {
		return;
	}

	$faqs = pfs_get_product_faqs( get_the_ID() );
	if ( empty( $faqs ) ) {
		return;
	}

	// Χτίσιμο του schema σύμφωνα με το schema.org/FAQPage
	$entities = array();
	foreach ( $faqs as $faq ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $faq['q'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $faq['a'],
			),
		);
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);

	// wp_json_encode: σωστό escaping και UTF-8 (JSON_UNESCAPED_UNICODE για τα ελληνικά)
	echo '<script type="application/ld+json">'
		. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		. '</script>' . "\n";
}
add_action( 'wp_head', 'pfs_output_schema', 20 );

/* ------------------------------------------------------------------
 * ΡΥΘΜΙΣΕΙΣ
 * ---------------------------------------------------------------- */

/**
 * Προεπιλεγμένες τιμές ρυθμίσεων.
 */
function pfs_default_options() {
	return array(
		'enabled'      => 1,
		'show_tab'     => 1, // εμφάνιση των FAQs ως tab στη σελίδα προϊόντος
		'use_generated' => 1, // ερωτήσεις που παράγονται από το κείμενο της περιγραφής
		'use_templates' => 1,
		'tpl_price'    => 1,
		'tpl_stock'    => 1,
		'tpl_sku'      => 1,
		'tpl_category' => 1,
		'tpl_dims'     => 1,
		'max_faqs'     => 8,
	);
}

/**
 * Διαβάζει μία ρύθμιση (με fallback στις προεπιλογές).
 */
function pfs_get_option( $key ) {
	$opts = wp_parse_args( get_option( 'pfs_options', array() ), pfs_default_options() );
	return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
}

/**
 * Είναι ενεργό ένα συγκεκριμένο template; (price, stock, sku, category, dims)
 */
function pfs_template_on( $key ) {
	return (bool) pfs_get_option( 'tpl_' . $key );
}

/**
 * Καθαρισμός τιμών πριν αποθηκευτούν.
 * Τα checkboxes που δεν τσεκαρίστηκαν δεν στέλνονται, άρα γίνονται 0.
 */
function pfs_sanitize_options( $input ) {
	$clean    = array();
	$checkbox = array( 'enabled', 'show_tab', 'use_generated', 'use_templates', 'tpl_price', 'tpl_stock', 'tpl_sku', 'tpl_category', 'tpl_dims' );

	foreach ( $checkbox as $key ) {
		$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
	}
	$clean['max_faqs'] = isset( $input['max_faqs'] ) ? max( 0, (int) $input['max_faqs'] ) : 8;

	return $clean;
}

/**
 * Καταχώρηση ρύθμισης και σελίδας στο μενού Ρυθμίσεις.
 */
function pfs_register_settings() {
	register_setting( 'pfs_group', 'pfs_options', array( 'sanitize_callback' => 'pfs_sanitize_options' ) );
}
add_action( 'admin_init', 'pfs_register_settings' );

function pfs_add_menu() {
	add_options_page( 'Product FAQ Schema', 'Product FAQ Schema', 'manage_options', 'pfs-settings', 'pfs_render_settings_page' );
}
add_action( 'admin_menu', 'pfs_add_menu' );

/**
 * Βοηθητική: τυπώνει ένα checkbox για τη φόρμα ρυθμίσεων.
 */
function pfs_checkbox( $key, $label ) {
	printf(
		'<label><input type="checkbox" name="pfs_options[%1$s]" value="1" %2$s> %3$s</label><br>',
		esc_attr( $key ),
		checked( 1, (int) pfs_get_option( $key ), false ),
		esc_html( $label )
	);
}

/**
 * Η σελίδα ρυθμίσεων.
 */
function pfs_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Product FAQ Schema</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'pfs_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th>Γενικά</th>
					<td>
						<?php
						pfs_checkbox( 'enabled', 'Ενεργοποίηση FAQ schema στα προϊόντα' );
						pfs_checkbox( 'show_tab', 'Εμφάνιση των FAQs ως tab "Συχνές ερωτήσεις" στη σελίδα προϊόντος' );
						?>
					</td>
				</tr>
				<tr>
					<th>Πηγές</th>
					<td>
						<?php
						pfs_checkbox( 'use_generated', 'Δημιουργία ερωτήσεων από το κείμενο της περιγραφής (αν δεν υπάρχουν)' );
						pfs_checkbox( 'use_templates', 'Fallback με templates WooCommerce (αν δεν βρεθούν ερωτήσεις)' );
						?>
					</td>
				</tr>
				<tr>
					<th>Templates</th>
					<td>
						<?php
						pfs_checkbox( 'tpl_price', 'Τιμή' );
						pfs_checkbox( 'tpl_stock', 'Διαθεσιμότητα' );
						pfs_checkbox( 'tpl_sku', 'Κωδικός (SKU)' );
						pfs_checkbox( 'tpl_category', 'Κατηγορία' );
						pfs_checkbox( 'tpl_dims', 'Βάρος και διαστάσεις' );
						?>
					</td>
				</tr>
				<tr>
					<th><label for="pfs_max">Μέγιστες FAQs</label></th>
					<td>
						<input id="pfs_max" type="number" min="0" name="pfs_options[max_faqs]" value="<?php echo esc_attr( pfs_get_option( 'max_faqs' ) ); ?>" class="small-text">
						<p class="description">0 = χωρίς όριο.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ------------------------------------------------------------------
 * META BOX ΑΝΑ ΠΡΟΪΟΝ
 * ---------------------------------------------------------------- */

function pfs_add_meta_box() {
	add_meta_box( 'pfs_meta', 'FAQ Schema', 'pfs_render_meta_box', 'product', 'side' );
}
add_action( 'add_meta_boxes', 'pfs_add_meta_box' );

function pfs_render_meta_box( $post ) {
	// Nonce για ασφάλεια κατά την αποθήκευση
	wp_nonce_field( 'pfs_save_meta', 'pfs_meta_nonce' );
	$disabled = get_post_meta( $post->ID, '_pfs_disable', true );
	echo '<label><input type="checkbox" name="pfs_disable" value="1" ' . checked( '1', $disabled, false ) . '> Απενεργοποίηση FAQ schema για αυτό το προϊόν</label>';
}

function pfs_save_meta( $post_id ) {
	// Έλεγχοι ασφαλείας: nonce, autosave, δικαιώματα
	if ( ! isset( $_POST['pfs_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pfs_meta_nonce'] ) ), 'pfs_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! empty( $_POST['pfs_disable'] ) ) {
		update_post_meta( $post_id, '_pfs_disable', '1' );
	} else {
		delete_post_meta( $post_id, '_pfs_disable' );
	}
}
add_action( 'save_post_product', 'pfs_save_meta' );

/* ------------------------------------------------------------------
 * ΟΡΑΤΗ ΕΜΦΑΝΙΣΗ ΣΤΗ ΣΕΛΙΔΑ ΠΡΟΪΟΝΤΟΣ (WooCommerce tab)
 * ---------------------------------------------------------------- */

/**
 * Προσθέτει το tab "Συχνές ερωτήσεις" δίπλα στα Περιγραφή / Κριτικές.
 * Εμφανίζεται μόνο αν υπάρχουν FAQs και δεν έχει απενεργοποιηθεί το προϊόν.
 */
function pfs_add_product_tab( $tabs ) {
	if ( ! pfs_get_option( 'enabled' ) || ! pfs_get_option( 'show_tab' ) ) {
		return $tabs;
	}
	if ( '1' === get_post_meta( get_the_ID(), '_pfs_disable', true ) ) {
		return $tabs;
	}
	if ( empty( pfs_get_product_faqs( get_the_ID() ) ) ) {
		return $tabs;
	}

	$tabs['pfs_faq'] = array(
		'title'    => pfs_is_greek() ? 'Συχνές ερωτήσεις' : 'FAQ',
		'priority' => 25, // μετά τις Πρόσθετες πληροφορίες (20), πριν τις Κριτικές (30)
		'callback' => 'pfs_render_faq_tab',
	);
	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'pfs_add_product_tab' );

/**
 * Τυπώνει τις FAQs ως accordion: κουμπί (ερώτηση) + πάνελ (απάντηση).
 * Χωρίς JavaScript οι απαντήσεις μένουν ορατές (progressive enhancement).
 */
function pfs_render_faq_tab() {
	$faqs    = pfs_get_product_faqs( get_the_ID() );
	$post_id = get_the_ID();

	echo '<div class="pfs-faq">';
	foreach ( $faqs as $i => $faq ) {
		// Μοναδικό id ώστε το κουμπί να συνδέεται με το πάνελ του (προσβασιμότητα)
		$panel_id = 'pfs-faq-' . $post_id . '-' . $i;

		echo '<div class="pfs-faq__item">';
		echo '<button type="button" class="pfs-faq__q" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">'
			. '<span>' . esc_html( $faq['q'] ) . '</span>'
			. '<span class="pfs-faq__icon" aria-hidden="true"></span>'
			. '</button>';
		echo '<div class="pfs-faq__a" id="' . esc_attr( $panel_id ) . '" role="region">'
			. '<div class="pfs-faq__a-inner"><p>' . esc_html( $faq['a'] ) . '</p></div>'
			. '</div>';
		echo '</div>';
	}
	echo '</div>';
}

/**
 * CSS και JavaScript του accordion (φορτώνονται μόνο στις σελίδες προϊόντος).
 */
function pfs_enqueue_assets() {
	if ( ! is_singular( 'product' ) ) {
		return;
	}

	// --- CSS ---
	// Το ύψος του πάνελ κινείται με grid-template-rows (0fr -> 1fr): ομαλό άνοιγμα χωρίς σταθερό ύψος.
	// Χρώματα μέσω CSS variables: άλλαξέ τα εδώ (ή από το theme) για να ταιριάζουν με το site.
	$css = '
.pfs-faq-section{max-width:1080px;margin:56px auto;padding:0 20px;box-sizing:border-box}
.pfs-faq-section__title{margin:0 0 28px;text-align:center}
.pfs-faq{--pfs-accent:#000;--pfs-border:#e4e4e4;--pfs-bg:#fff;--pfs-bg-hover:#f7f7f7;display:flex;flex-direction:column;gap:12px}
.pfs-faq__item{background:var(--pfs-bg);border:1px solid var(--pfs-border);border-radius:14px;overflow:hidden;transition:border-color .25s ease,box-shadow .25s ease}
.pfs-faq__item:hover{border-color:#c9c9c9}
.pfs-faq__item.is-open{border-color:var(--pfs-accent);box-shadow:0 4px 18px rgba(0,0,0,.06)}
.pfs-faq__q{display:flex;justify-content:space-between;align-items:center;gap:1.2em;width:100%;padding:20px 24px;margin:0;background:none;border:0;border-radius:0;color:inherit;font:inherit;font-size:1.05em;font-weight:600;line-height:1.4;text-align:left;text-transform:none;letter-spacing:0;cursor:pointer;transition:background .2s ease}
.pfs-faq__q:hover{background:var(--pfs-bg-hover)}
.pfs-faq__q:focus-visible{outline:2px solid var(--pfs-accent);outline-offset:-2px}
.pfs-faq__icon{flex:none;width:.6em;height:.6em;margin-top:-.2em;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:transform .3s ease}
.pfs-faq__a{display:grid;grid-template-rows:1fr}
.pfs-faq__a-inner{min-height:0;overflow:hidden}
.pfs-faq__a-inner p{margin:0;padding:0 24px 22px;color:#444;line-height:1.7}
@media (max-width:600px){.pfs-faq__q{padding:16px 18px}.pfs-faq__a-inner p{padding:0 18px 18px}}
/* Οι απαντήσεις κρύβονται μόνο όταν το JavaScript έχει φορτώσει */
.pfs-faq--js .pfs-faq__a{grid-template-rows:0fr;transition:grid-template-rows .35s ease}
.pfs-faq--js .pfs-faq__a-inner{visibility:hidden;transition:visibility .35s}
.pfs-faq--js .is-open .pfs-faq__a{grid-template-rows:1fr}
.pfs-faq--js .is-open .pfs-faq__a-inner{visibility:visible}
.pfs-faq--js .is-open .pfs-faq__icon{transform:rotate(-135deg)}
@media (prefers-reduced-motion:reduce){.pfs-faq--js .pfs-faq__a,.pfs-faq__icon{transition:none}}
';
	wp_register_style( 'pfs-faq', false ); // inline style χωρίς εξωτερικό αρχείο
	wp_enqueue_style( 'pfs-faq' );
	wp_add_inline_style( 'pfs-faq', $css );

	// --- JavaScript ---
	// Ένα κλικ ανοίγει την ερώτηση και κλείνει όποια άλλη ήταν ανοιχτή (κλικ στην ανοιχτή την κλείνει).
	$js = "
document.querySelectorAll('.pfs-faq').forEach(function (box) {
	box.classList.add('pfs-faq--js');
	box.addEventListener('click', function (e) {
		var btn = e.target.closest('.pfs-faq__q');
		if (!btn) { return; }
		var item   = btn.parentNode;
		var wasOpen = item.classList.contains('is-open');

		// Κλείνουμε όλες τις ανοιχτές
		box.querySelectorAll('.pfs-faq__item.is-open').forEach(function (i) {
			i.classList.remove('is-open');
			i.querySelector('.pfs-faq__q').setAttribute('aria-expanded', 'false');
		});

		// Ανοίγουμε την επιλεγμένη (αν δεν ήταν ήδη ανοιχτή)
		if (!wasOpen) {
			item.classList.add('is-open');
			btn.setAttribute('aria-expanded', 'true');
		}
	});
});
";
	wp_register_script( 'pfs-faq', false, array(), '0.1.0', true ); // στο footer, χωρίς εξωτερικό αρχείο
	wp_enqueue_script( 'pfs-faq' );
	wp_add_inline_script( 'pfs-faq', $js );
}
add_action( 'wp_enqueue_scripts', 'pfs_enqueue_assets' );

/**
 * Shortcode [product_faq]: τυπώνει το accordion των FAQs οπουδήποτε στη σελίδα προϊόντος.
 * Χρήσιμο όταν το προϊόν χρησιμοποιεί custom template (π.χ. Oxygen) που δεν εμφανίζει τα
 * προεπιλεγμένα tabs του WooCommerce, άρα το tab "Συχνές ερωτήσεις" δεν φαίνεται.
 */
function pfs_faq_shortcode() {
	$post_id = get_the_ID();

	// Σεβόμαστε τον global διακόπτη και την απενεργοποίηση ανά προϊόν
	if ( ! $post_id || ! pfs_get_option( 'enabled' ) ) {
		return '';
	}
	if ( '1' === get_post_meta( $post_id, '_pfs_disable', true ) ) {
		return '';
	}
	if ( empty( pfs_get_product_faqs( $post_id ) ) ) {
		return '';
	}

	// Χρησιμοποιούμε την ίδια συνάρτηση με το tab, αλλά επιστρέφουμε το HTML αντί να το τυπώσουμε
	// Το shortcode μπαίνει συνήθως έξω από tab, οπότε προσθέτουμε περιέχον πλαίσιο και τίτλο
	ob_start();
	echo '<section class="pfs-faq-section">';
	echo '<h2 class="pfs-faq-section__title">' . esc_html( pfs_is_greek() ? 'Συχνές ερωτήσεις' : 'Frequently Asked Questions' ) . '</h2>';
	pfs_render_faq_tab();
	echo '</section>';
	return ob_get_clean();
}
add_shortcode( 'product_faq', 'pfs_faq_shortcode' );
