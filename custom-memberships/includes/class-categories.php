<?php
defined( 'ABSPATH' ) || exit;

/**
 * Membership categories — the top level of the signup accordion.
 * Each category holds one or more packages.
 */
class CM_Categories {

    public static function init() {
        // CRUD only; used by admin, frontend and API.
    }

    public static function get_all( $active_only = false ) {
        global $wpdb;
        $where = $active_only ? 'WHERE active = 1' : '';
        return $wpdb->get_results(
            "SELECT * FROM " . CM_TABLE_CATEGORIES . " $where ORDER BY sort_order ASC, id ASC"
        );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . CM_TABLE_CATEGORIES . " WHERE id = %d", $id
        ) );
    }

    public static function create( array $data ) {
        global $wpdb;
        $wpdb->insert( CM_TABLE_CATEGORIES, self::sanitize( $data ) );
        return (int) $wpdb->insert_id;
    }

    public static function update( $id, array $data ) {
        global $wpdb;
        $clean = self::sanitize( $data );
        if ( $clean ) {
            $wpdb->update( CM_TABLE_CATEGORIES, $clean, [ 'id' => $id ] );
        }
    }

    /**
     * Delete a category. Packages inside it are kept but unassigned,
     * so nothing is silently destroyed along with the category.
     */
    public static function delete( $id ) {
        global $wpdb;
        $wpdb->update( CM_TABLE_PACKAGES, [ 'category_id' => null ], [ 'category_id' => $id ] );
        $wpdb->delete( CM_TABLE_CATEGORIES, [ 'id' => $id ] );
    }

    /**
     * Categories with their packages attached as ->packages.
     * Used by the frontend accordion and the admin packages screen.
     *
     * @param bool $active_only Only active categories/packages.
     * @param bool $skip_empty  Omit categories that have no packages.
     */
    public static function get_with_packages( $active_only = false, $skip_empty = true ) {
        $categories = self::get_all( $active_only );
        $out        = [];

        foreach ( $categories as $cat ) {
            $cat->packages = CM_Packages::get_by_category( $cat->id, $active_only );
            if ( $skip_empty && empty( $cat->packages ) ) {
                continue;
            }
            $out[] = $cat;
        }

        // Any packages not assigned to a category still need somewhere to live.
        $orphans = CM_Packages::get_uncategorized( $active_only );
        if ( ! empty( $orphans ) ) {
            $other           = new stdClass();
            $other->id       = 0;
            $other->name     = __( 'Other Packages', 'custom-memberships' );
            $other->subtitle = '';
            $other->description = '';
            $other->packages = $orphans;
            $out[]           = $other;
        }

        return $out;
    }

    private static function sanitize( array $data ) {
        $clean = [];
        if ( isset( $data['name'] ) )        $clean['name']        = sanitize_text_field( $data['name'] );
        if ( isset( $data['subtitle'] ) )    $clean['subtitle']    = sanitize_text_field( $data['subtitle'] );
        if ( isset( $data['description'] ) ) $clean['description'] = sanitize_textarea_field( $data['description'] );
        if ( isset( $data['sort_order'] ) )  $clean['sort_order']  = intval( $data['sort_order'] );
        if ( isset( $data['active'] ) )      $clean['active']      = $data['active'] ? 1 : 0;
        return $clean;
    }
}
