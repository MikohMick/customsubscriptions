<?php
defined( 'ABSPATH' ) || exit;

class CM_Memberships {

    public static function init() {
        // Cron for expiry checks is set up here.
        add_action( 'cm_daily_check', [ __CLASS__, 'daily_check' ] );
        if ( ! wp_next_scheduled( 'cm_daily_check' ) ) {
            wp_schedule_event( time(), 'daily', 'cm_daily_check' );
        }
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public static function get_all( array $args = [] ) {
        global $wpdb;
        $where  = [];
        $values = [];

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $values   = array_merge( $values, [ $like, $like, $like ] );
        }

        $sql = "SELECT m.*, p.name AS package_name, p.sessions AS package_sessions
                FROM " . CM_TABLE_MEMBERS . " m
                LEFT JOIN " . CM_TABLE_PACKAGES . " p ON p.id = m.package_id";

        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        $order_by = isset( $args['orderby'] ) ? sanitize_sql_orderby( $args['orderby'] ) : 'created_at';
        $order    = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $sql     .= " ORDER BY m.{$order_by} {$order}";

        if ( isset( $args['limit'] ) ) {
            $offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;
            $sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', intval( $args['limit'] ), $offset );
        }

        return $values
            ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) )
            : $wpdb->get_results( $sql );
    }

    public static function count( array $args = [] ) {
        global $wpdb;
        $where  = [];
        $values = [];
        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        $sql = "SELECT COUNT(*) FROM " . CM_TABLE_MEMBERS;
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_var( $sql ) );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, p.name AS package_name, p.sessions AS package_sessions
             FROM " . CM_TABLE_MEMBERS . " m
             LEFT JOIN " . CM_TABLE_PACKAGES . " p ON p.id = m.package_id
             WHERE m.id = %d",
            $id
        ) );
    }

    public static function get_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . CM_TABLE_MEMBERS . " WHERE email = %s ORDER BY created_at DESC",
            $email
        ) );
    }

    public static function create( array $data ) {
        global $wpdb;
        $package = CM_Packages::get( $data['package_id'] );
        if ( ! $package ) {
            return new WP_Error( 'invalid_package', 'Package not found.' );
        }
        $sessions = (int) $package->sessions; // 0 = unlimited stored as 0.

        $row = [
            'name'               => sanitize_text_field( $data['name'] ),
            'email'              => sanitize_email( $data['email'] ),
            'phone'              => sanitize_text_field( $data['phone'] ),
            'location'           => sanitize_text_field( $data['location'] ),
            'package_id'         => (int) $package->id,
            'sessions_total'     => $sessions,
            'sessions_remaining' => $sessions,
            'status'             => isset( $data['status'] ) ? $data['status'] : 'pending',
            'order_id'           => isset( $data['order_id'] ) ? intval( $data['order_id'] ) : null,
        ];

        $wpdb->insert( CM_TABLE_MEMBERS, $row );
        return (int) $wpdb->insert_id;
    }

    public static function update( $id, array $data ) {
        global $wpdb;
        $clean = [];
        $allowed = [ 'name', 'email', 'phone', 'location', 'status', 'notes', 'sessions_remaining', 'sessions_total', 'renewal_email_sent', 'order_id' ];
        foreach ( $allowed as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                switch ( $field ) {
                    case 'name':
                    case 'phone':
                    case 'location':
                    case 'status':
                        $clean[ $field ] = sanitize_text_field( $data[ $field ] );
                        break;
                    case 'email':
                        $clean[ $field ] = sanitize_email( $data[ $field ] );
                        break;
                    case 'notes':
                        $clean[ $field ] = sanitize_textarea_field( $data[ $field ] );
                        break;
                    default:
                        $clean[ $field ] = intval( $data[ $field ] );
                }
            }
        }
        if ( $clean ) {
            $wpdb->update( CM_TABLE_MEMBERS, $clean, [ 'id' => $id ] );
        }
    }

    public static function delete( $id ) {
        global $wpdb;
        $wpdb->delete( CM_TABLE_MEMBERS, [ 'id' => $id ] );
    }

    /**
     * Get the most recent active membership for an email.
     * Falls back to any record (most recent) if no active one exists.
     */
    public static function get_latest_by_email( $email ) {
        global $wpdb;
        // Active first.
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, p.name AS package_name, p.sessions AS package_sessions
             FROM " . CM_TABLE_MEMBERS . " m
             LEFT JOIN " . CM_TABLE_PACKAGES . " p ON p.id = m.package_id
             WHERE m.email = %s AND m.status = 'active'
             ORDER BY m.created_at DESC LIMIT 1",
            $email
        ) );
        if ( $row ) return $row;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, p.name AS package_name, p.sessions AS package_sessions
             FROM " . CM_TABLE_MEMBERS . " m
             LEFT JOIN " . CM_TABLE_PACKAGES . " p ON p.id = m.package_id
             WHERE m.email = %s
             ORDER BY m.created_at DESC LIMIT 1",
            $email
        ) );
    }

    /**
     * Top up a member's sessions when they purchase a new package.
     * Adds new package sessions on top of what's remaining.
     * For unlimited packages (sessions = 0) switches them to unlimited.
     */
    public static function top_up_sessions( $member_id, $package ) {
        $member = self::get( $member_id );
        if ( ! $member ) return;

        $new_sessions = (int) $package->sessions;

        if ( $new_sessions === 0 ) {
            // Upgrading to an unlimited plan.
            self::update( $member_id, [
                'package_id'         => (int) $package->id,
                'sessions_total'     => 0,
                'sessions_remaining' => 0,
                'status'             => 'active',
                'renewal_email_sent' => 0,
            ] );
        } else {
            // Add new sessions on top of what's left (0 if previously unlimited).
            $current_remaining = (int) $member->sessions_total === 0
                ? 0
                : (int) $member->sessions_remaining;
            $new_remaining = $current_remaining + $new_sessions;

            self::update( $member_id, [
                'package_id'         => (int) $package->id,
                'sessions_total'     => $new_sessions,
                'sessions_remaining' => $new_remaining,
                'status'             => 'active',
                'renewal_email_sent' => 0,
            ] );
        }
    }

    // -------------------------------------------------------------------------
    // Session management
    // -------------------------------------------------------------------------

    /**
     * Decrement sessions_remaining by $amount.
     * Returns WP_Error or true. Triggers renewal email at 0.
     */
    public static function use_sessions( $member_id, $amount = 1 ) {
        $member = self::get( $member_id );
        if ( ! $member ) {
            return new WP_Error( 'not_found', 'Member not found.' );
        }

        // Unlimited sessions (0) never decrement.
        if ( (int) $member->sessions_total === 0 ) {
            return true;
        }

        $remaining = max( 0, (int) $member->sessions_remaining - (int) $amount );
        self::update( $member_id, [ 'sessions_remaining' => $remaining ] );

        if ( $remaining === 0 ) {
            self::maybe_send_renewal_email( $member_id );
        }

        return true;
    }

    public static function maybe_send_renewal_email( $member_id ) {
        $member = self::get( $member_id );
        if ( ! $member || (int) $member->renewal_email_sent ) {
            return;
        }
        CM_Emails::send_renewal_reminder( $member );
        self::update( $member_id, [ 'renewal_email_sent' => 1 ] );
    }

    // -------------------------------------------------------------------------
    // Cron
    // -------------------------------------------------------------------------

    public static function daily_check() {
        global $wpdb;
        // Find active members with 0 sessions remaining (non-unlimited) who haven't been emailed.
        $rows = $wpdb->get_results(
            "SELECT id FROM " . CM_TABLE_MEMBERS . "
             WHERE status = 'active'
               AND sessions_total > 0
               AND sessions_remaining = 0
               AND renewal_email_sent = 0"
        );
        foreach ( $rows as $row ) {
            self::maybe_send_renewal_email( $row->id );
        }
    }

    // -------------------------------------------------------------------------
    // Stats helper
    // -------------------------------------------------------------------------

    public static function stats() {
        return [
            'total'   => self::count(),
            'active'  => self::count( [ 'status' => 'active' ] ),
            'pending' => self::count( [ 'status' => 'pending' ] ),
            'expired' => self::count( [ 'status' => 'expired' ] ),
        ];
    }
}
