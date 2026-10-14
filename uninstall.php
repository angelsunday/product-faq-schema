<?php
/**
 * Εκτελείται όταν το plugin ΔΙΑΓΡΑΦΕΤΑΙ από το wp-admin.
 * Καθαρίζει τις ρυθμίσεις και τα meta δεδομένα που έφτιαξε το plugin.
 */

// Αποτροπή απευθείας εκτέλεσης: το WordPress ορίζει αυτή τη σταθερά μόνο κατά το uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Διαγραφή των ρυθμίσεων του plugin
delete_option( 'pfs_options' );

// Διαγραφή του meta "απενεργοποίηση ανά προϊόν" από όλα τα προϊόντα
delete_post_meta_by_key( '_pfs_disable' );

// Διαγραφή των χειροκίνητων FAQs από όλα τα προϊόντα
delete_post_meta_by_key( '_pfs_custom_faqs' );
