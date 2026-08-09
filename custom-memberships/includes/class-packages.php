<?php
defined( 'ABSPATH' ) || exit;

class CM_Packages {

    public static function init() {
        // Nothing to hook at runtime; CRUD used by admin & WC classes.
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public static function get_all( $active_only = false ) {
        global $wpdb;
        $where = $active_only ? 'WHERE p.active = 1' : '';
        return $wpdb->get_results(
            "SELECT p.*, c.name AS category_name
             FROM " . CM_TABLE_PACKAGES . " p
             LEFT JOIN " . CM_TABLE_CATEGORIES . " c ON c.id = p.category_id
             $where
             ORDER BY c.sort_order ASC, p.sort_order ASC, p.id ASC"
        );
    }

    public static function get_by_category( $category_id, $active_only = false ) {
        global $wpdb;
        $sql = "SELECT * FROM " . CM_TABLE_PACKAGES . " WHERE category_id = %d";
        if ( $active_only ) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $wpdb->get_results( $wpdb->prepare( $sql, $category_id ) );
    }

    public static function get_uncategorized( $active_only = false ) {
        global $wpdb;
        $sql = "SELECT * FROM " . CM_TABLE_PACKAGES . " WHERE (category_id IS NULL OR category_id = 0)";
        if ( $active_only ) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $wpdb->get_results( $sql );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT p.*, c.name AS category_name
             FROM " . CM_TABLE_PACKAGES . " p
             LEFT JOIN " . CM_TABLE_CATEGORIES . " c ON c.id = p.category_id
             WHERE p.id = %d",
            $id
        ) );
    }

    public static function create( array $data ) {
        global $wpdb;
        $wpdb->insert( CM_TABLE_PACKAGES, self::sanitize( $data ) );
        $id = (int) $wpdb->insert_id;
        if ( $id ) {
            self::sync_product( $id );
        }
        return $id;
    }

    public static function update( $id, array $data ) {
        global $wpdb;
        $wpdb->update( CM_TABLE_PACKAGES, self::sanitize( $data ), [ 'id' => $id ] );
        self::sync_product( $id );
    }

    public static function delete( $id ) {
        global $wpdb;
        $package = self::get( $id );
        if ( $package && $package->product_id ) {
            wp_trash_post( $package->product_id );
        }
        $wpdb->delete( CM_TABLE_PACKAGES, [ 'id' => $id ] );
    }

    // -------------------------------------------------------------------------
    // WooCommerce product sync
    // -------------------------------------------------------------------------

    public static function sync_product( $package_id ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        $package = self::get( $package_id );
        if ( ! $package ) {
            return;
        }

        $product_data = [
            'post_title'   => sanitize_text_field( $package->name ),
            'post_content' => sanitize_textarea_field( $package->description ),
            'post_status'  => $package->active ? 'publish' : 'draft',
            'post_type'    => 'product',
        ];

        if ( $package->product_id && get_post( $package->product_id ) ) {
            $product_data['ID'] = $package->product_id;
            wp_update_post( $product_data );
            $product_id = $package->product_id;
        } else {
            $product_id = wp_insert_post( $product_data );
            if ( is_wp_error( $product_id ) ) {
                return;
            }
            global $wpdb;
            $wpdb->update( CM_TABLE_PACKAGES, [ 'product_id' => $product_id ], [ 'id' => $package_id ] );
        }

        // Set product type to simple and price.
        wp_set_object_terms( $product_id, 'simple', 'product_type' );
        update_post_meta( $product_id, '_regular_price', $package->price );
        update_post_meta( $product_id, '_price', $package->price );
        update_post_meta( $product_id, '_manage_stock', 'no' );
        update_post_meta( $product_id, '_sold_individually', 'yes' );
        update_post_meta( $product_id, '_virtual', 'yes' );
        // Hide from shop & catalog; only accessible via direct add-to-cart.
        update_post_meta( $product_id, '_visibility', 'hidden' );
        update_post_meta( $product_id, '_cm_package_id', $package_id );

        // WC 3+ catalog visibility.
        wp_set_object_terms( $product_id, 'hidden', 'product_visibility' );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function sanitize( array $data ) {
        $clean = [];
        if ( isset( $data['name'] ) )         $clean['name']         = sanitize_text_field( $data['name'] );
        if ( isset( $data['description'] ) )  $clean['description']  = sanitize_textarea_field( $data['description'] );
        if ( isset( $data['perks'] ) )        $clean['perks']        = sanitize_textarea_field( $data['perks'] );
        if ( isset( $data['sessions'] ) )     $clean['sessions']     = max( 0, intval( $data['sessions'] ) );
        if ( isset( $data['session_unit'] ) ) $clean['session_unit'] = sanitize_text_field( $data['session_unit'] ) ?: 'Session';
        if ( isset( $data['price'] ) )        $clean['price']        = round( floatval( $data['price'] ), 2 );
        if ( isset( $data['sort_order'] ) )   $clean['sort_order']   = intval( $data['sort_order'] );
        if ( isset( $data['active'] ) )       $clean['active']       = $data['active'] ? 1 : 0;
        if ( isset( $data['highlight'] ) )    $clean['highlight']    = $data['highlight'] ? 1 : 0;
        if ( array_key_exists( 'category_id', $data ) ) {
            $clean['category_id'] = intval( $data['category_id'] ) ?: null;
        }
        return $clean;
    }

    /**
     * Pluralise a session unit: Class → Classes, Session → Sessions.
     */
    public static function pluralize_unit( $unit ) {
        $unit = trim( (string) $unit );
        if ( $unit === '' ) {
            return 'Sessions';
        }
        if ( preg_match( '/(s|x|z|ch|sh)$/i', $unit ) ) {
            return $unit . 'es';
        }
        return $unit . 's';
    }

    /**
     * Human label for a package's session allowance,
     * e.g. "Unlimited", "1 Class", "8 Reformer Classes".
     */
    public static function sessions_label( $sessions, $unit = 'Session' ) {
        $sessions = (int) $sessions;
        if ( $sessions === 0 ) {
            return __( 'Unlimited', 'custom-memberships' );
        }
        $unit = $unit ?: 'Session';
        return $sessions === 1
            ? $sessions . ' ' . $unit
            : $sessions . ' ' . self::pluralize_unit( $unit );
    }

    /**
     * Perks stored one-per-line, returned as a clean array.
     */
    public static function perks_list( $perks ) {
        if ( empty( $perks ) ) {
            return [];
        }
        return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $perks ) ) ) );
    }
}
