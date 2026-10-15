<?php
/**
 * Εκτελείται όταν το plugin ΔΙΑΓΡΑΦΕΤΑΙ από το wp-admin (όχι όταν απενεργοποιείται).
 * Σβήνει τα δεδομένα μόνο αν το έχει ζητήσει ο χρήστης από τις Ρυθμίσεις,
 * ώστε οι χειροκίνητες FAQs να μη χάνονται κατά λάθος.
 */

// Αποτροπή απευθείας εκτέλεσης: το WordPress ορίζει αυτή τη σταθερά μόνο κατά το uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = get_option( 'pfs_options' );

// Αν δεν έχει τσεκαριστεί η "Διαγραφή όλων των δεδομένων", δεν σβήνουμε τίποτα
if ( ! is_array( $options ) || empty( $options['delete_data'] ) ) {
	return;
}

// Διαγραφή των ρυθμίσεων του plugin
delete_option( 'pfs_options' );

// Διαγραφή του meta "απενεργοποίηση ανά προϊόν" από όλα τα προϊόντα
delete_post_meta_by_key( '_pfs_disable' );

// Διαγραφή των χειροκίνητων FAQs από όλα τα προϊόντα
delete_post_meta_by_key( '_pfs_custom_faqs' );
