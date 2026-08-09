<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API — base: /wp-json/custom-memberships/v1/
 *
 * Authentication: pass header  X-CM-Api-Key: <key>
 *   or query param              ?cm_api_key=<key>
 *
 * Endpoints:
 *   GET    /packages                  — list packages
 *   GET    /packages/{id}             — single package
 *
 *   GET    /members                   — list members (read key)
 *   POST   /members                   — create member (write key)
 *   GET    /members/{id}              — single member (read key)
 *   PATCH  /members/{id}              — update member (write key)
 *   DELETE /members/{id}              — delete member (write key)
 *   POST   /members/{id}/use-session  — decrement sessions (write key)
 *
 *   GET    /stats                     — counts (read key)
 *
 *   GET    /keys                      — list API keys (WP admin only)
 *   POST   /keys                      — create API key (WP admin only)
 *   DELETE /keys/{id}                 — delete API key (WP admin only)
 */
class CM_API {

    const NS = 'custom-memberships/v1';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        // Categories (optionally with their packages nested).
        register_rest_route( self::NS, '/categories', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_categories' ],
            'permission_callback' => [ __CLASS__, 'require_read' ],
        ] );

        // Packages.
        register_rest_route( self::NS, '/packages', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_packages' ],
            'permission_callback' => [ __CLASS__, 'require_read' ],
        ] );
        register_rest_route( self::NS, '/packages/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_package' ],
            'permission_callback' => [ __CLASS__, 'require_read' ],
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );

        // Members.
        register_rest_route( self::NS, '/members', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_members' ],
                'permission_callback' => [ __CLASS__, 'require_read' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create_member' ],
                'permission_callback' => [ __CLASS__, 'require_write' ],
            ],
        ] );
        register_rest_route( self::NS, '/members/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_member' ],
                'permission_callback' => [ __CLASS__, 'require_read' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [ __CLASS__, 'update_member' ],
                'permission_callback' => [ __CLASS__, 'require_write' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete_member' ],
                'permission_callback' => [ __CLASS__, 'require_write' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
        ] );
        register_rest_route( self::NS, '/members/(?P<id>\d+)/use-session', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'use_session' ],
            'permission_callback' => [ __CLASS__, 'require_write' ],
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );

        // Stats.
        register_rest_route( self::NS, '/stats', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_stats' ],
            'permission_callback' => [ __CLASS__, 'require_read' ],
        ] );

        // API Key management (admin only).
        register_rest_route( self::NS, '/keys', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_keys' ],
                'permission_callback' => [ __CLASS__, 'require_admin' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create_key' ],
                'permission_callback' => [ __CLASS__, 'require_admin' ],
            ],
        ] );
        register_rest_route( self::NS, '/keys/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete_key' ],
            'permission_callback' => [ __CLASS__, 'require_admin' ],
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Permission callbacks
    // -------------------------------------------------------------------------

    private static function get_api_key_from_request( WP_REST_Request $request ) {
        $key = $request->get_header( 'X-CM-Api-Key' );
        if ( ! $key ) {
            $key = $request->get_param( 'cm_api_key' );
        }
        return $key ? sanitize_text_field( $key ) : null;
    }

    private static function resolve_key( $key ) {
        if ( ! $key ) {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . CM_TABLE_API_KEYS . " WHERE api_key = %s", $key
        ) );
        if ( $row ) {
            $wpdb->update( CM_TABLE_API_KEYS, [ 'last_used' => current_time( 'mysql' ) ], [ 'id' => $row->id ] );
        }
        return $row;
    }

    public static function require_read( WP_REST_Request $request ) {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }
        $key = self::resolve_key( self::get_api_key_from_request( $request ) );
        return $key && in_array( $key->permissions, [ 'read', 'read_write' ], true );
    }

    public static function require_write( WP_REST_Request $request ) {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }
        $key = self::resolve_key( self::get_api_key_from_request( $request ) );
        return $key && in_array( $key->permissions, [ 'write', 'read_write' ], true );
    }

    public static function require_admin( WP_REST_Request $request ) {
        return current_user_can( 'manage_options' );
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    public static function get_categories( WP_REST_Request $request ) {
        $active     = in_array( $request->get_param( 'active' ), [ '1', 'true' ], true );
        $with_pkgs  = in_array( $request->get_param( 'with_packages' ), [ '1', 'true' ], true );

        return rest_ensure_response(
            $with_pkgs
                ? CM_Categories::get_with_packages( $active )
                : CM_Categories::get_all( $active )
        );
    }

    // -------------------------------------------------------------------------
    // Packages
    // -------------------------------------------------------------------------

    public static function get_packages( WP_REST_Request $request ) {
        $active = $request->get_param( 'active' );
        return rest_ensure_response( CM_Packages::get_all( $active === '1' || $active === 'true' ) );
    }

    public static function get_package( WP_REST_Request $request ) {
        $pkg = CM_Packages::get( $request['id'] );
        if ( ! $pkg ) {
            return new WP_Error( 'not_found', 'Package not found.', [ 'status' => 404 ] );
        }
        return rest_ensure_response( $pkg );
    }

    // -------------------------------------------------------------------------
    // Members
    // -------------------------------------------------------------------------

    public static function get_members( WP_REST_Request $request ) {
        $args = [
            'status'  => $request->get_param( 'status' ) ?: '',
            'search'  => $request->get_param( 'search' ) ?: '',
            'limit'   => min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) ),
            'offset'  => intval( $request->get_param( 'offset' ) ?: 0 ),
            'orderby' => $request->get_param( 'orderby' ) ?: 'created_at',
            'order'   => $request->get_param( 'order' ) ?: 'DESC',
        ];
        $members = CM_Memberships::get_all( $args );
        $total   = CM_Memberships::count( array_intersect_key( $args, [ 'status' => 1 ] ) );

        $response = rest_ensure_response( $members );
        $response->header( 'X-CM-Total', $total );
        return $response;
    }

    public static function get_member( WP_REST_Request $request ) {
        $member = CM_Memberships::get( $request['id'] );
        if ( ! $member ) {
            return new WP_Error( 'not_found', 'Member not found.', [ 'status' => 404 ] );
        }
        return rest_ensure_response( $member );
    }

    public static function create_member( WP_REST_Request $request ) {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $required = [ 'name', 'email', 'phone', 'location', 'package_id' ];
        foreach ( $required as $field ) {
            if ( empty( $body[ $field ] ) ) {
                return new WP_Error( 'missing_field', "Field '$field' is required.", [ 'status' => 400 ] );
            }
        }
        $id = CM_Memberships::create( $body );
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        return new WP_REST_Response( CM_Memberships::get( $id ), 201 );
    }

    public static function update_member( WP_REST_Request $request ) {
        $member = CM_Memberships::get( $request['id'] );
        if ( ! $member ) {
            return new WP_Error( 'not_found', 'Member not found.', [ 'status' => 404 ] );
        }
        $body = $request->get_json_params() ?: $request->get_body_params();
        CM_Memberships::update( $request['id'], $body );
        return rest_ensure_response( CM_Memberships::get( $request['id'] ) );
    }

    public static function delete_member( WP_REST_Request $request ) {
        $member = CM_Memberships::get( $request['id'] );
        if ( ! $member ) {
            return new WP_Error( 'not_found', 'Member not found.', [ 'status' => 404 ] );
        }
        CM_Memberships::delete( $request['id'] );
        return new WP_REST_Response( null, 204 );
    }

    public static function use_session( WP_REST_Request $request ) {
        $body   = $request->get_json_params() ?: [];
        $amount = isset( $body['amount'] ) ? max( 1, intval( $body['amount'] ) ) : 1;
        $result = CM_Memberships::use_sessions( $request['id'], $amount );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( CM_Memberships::get( $request['id'] ) );
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    public static function get_stats( WP_REST_Request $request ) {
        return rest_ensure_response( CM_Memberships::stats() );
    }

    // -------------------------------------------------------------------------
    // API Key management
    // -------------------------------------------------------------------------

    public static function get_keys( WP_REST_Request $request ) {
        global $wpdb;
        $keys = $wpdb->get_results( "SELECT id, label, permissions, last_used, created_at FROM " . CM_TABLE_API_KEYS . " ORDER BY id DESC" );
        return rest_ensure_response( $keys );
    }

    public static function create_key( WP_REST_Request $request ) {
        global $wpdb;
        $body        = $request->get_json_params() ?: $request->get_body_params();
        $label       = sanitize_text_field( $body['label'] ?? 'API Key' );
        $permissions = in_array( $body['permissions'] ?? '', [ 'read', 'write', 'read_write' ], true )
            ? $body['permissions']
            : 'read';

        $raw_key = 'cm_' . bin2hex( random_bytes( 24 ) );
        $wpdb->insert( CM_TABLE_API_KEYS, [
            'label'       => $label,
            'api_key'     => $raw_key,
            'permissions' => $permissions,
        ] );

        return new WP_REST_Response( [
            'id'          => $wpdb->insert_id,
            'label'       => $label,
            'api_key'     => $raw_key, // Only returned once on creation.
            'permissions' => $permissions,
        ], 201 );
    }

    public static function delete_key( WP_REST_Request $request ) {
        global $wpdb;
        $wpdb->delete( CM_TABLE_API_KEYS, [ 'id' => $request['id'] ] );
        return new WP_REST_Response( null, 204 );
    }
}
