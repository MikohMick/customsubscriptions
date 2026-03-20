<?php
defined( 'ABSPATH' ) || exit;

class CM_Emails {

    public static function init() {
        // Nothing to hook; emails sent directly.
    }

    // -------------------------------------------------------------------------
    // Welcome email — sent when membership is activated
    // -------------------------------------------------------------------------

    public static function send_welcome( $member ) {
        if ( ! $member ) {
            return;
        }

        $package  = CM_Packages::get( $member->package_id );
        $subject  = apply_filters( 'cm_welcome_email_subject',
            sprintf( __( 'Welcome! Your %s membership is active', 'custom-memberships' ), get_bloginfo( 'name' ) ),
            $member
        );

        $sessions_text = (int) $member->sessions_total === 0
            ? __( 'Unlimited', 'custom-memberships' )
            : sprintf( _n( '%d session', '%d sessions', $member->sessions_total, 'custom-memberships' ), $member->sessions_total );

        $message = self::wrap( sprintf(
            /* translators: 1: first name, 2: plan name, 3: sessions, 4: site name */
            __(
                '<p>Hi %1$s,</p>
                <p>Your membership is now <strong>active</strong>. Here are your details:</p>
                <table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%%;max-width:480px">
                  <tr><td><strong>Plan</strong></td><td>%2$s</td></tr>
                  <tr><td><strong>Sessions</strong></td><td>%3$s</td></tr>
                </table>
                <p>We look forward to seeing you!</p>
                <p>– The %4$s team</p>',
                'custom-memberships'
            ),
            esc_html( explode( ' ', trim( $member->name ) )[0] ),
            esc_html( $package ? $package->name : '' ),
            esc_html( $sessions_text ),
            esc_html( get_bloginfo( 'name' ) )
        ), $subject );

        self::send( $member->email, $subject, $message );
    }

    // -------------------------------------------------------------------------
    // Renewal reminder — sent when sessions_remaining hits 0
    // -------------------------------------------------------------------------

    public static function send_renewal_reminder( $member ) {
        if ( ! $member ) {
            return;
        }

        $subject = apply_filters( 'cm_renewal_email_subject',
            sprintf( __( 'Your %s sessions have been used – time to renew!', 'custom-memberships' ), get_bloginfo( 'name' ) ),
            $member
        );

        $signup_url = apply_filters( 'cm_renewal_signup_url', home_url( '/' ), $member );

        $message = self::wrap( sprintf(
            __(
                '<p>Hi %1$s,</p>
                <p>You\'ve used all your sessions. We\'d love to have you back!</p>
                <p>
                  <a href="%2$s"
                     style="display:inline-block;padding:12px 28px;background-color:#c0555a;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold">
                    Renew Your Membership
                  </a>
                </p>
                <p>If you have any questions, just reply to this email.</p>
                <p>– The %3$s team</p>',
                'custom-memberships'
            ),
            esc_html( explode( ' ', trim( $member->name ) )[0] ),
            esc_url( $signup_url ),
            esc_html( get_bloginfo( 'name' ) )
        ), $subject );

        self::send( $member->email, $subject, $message );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private static function send( $to, $subject, $html_message ) {
        $from_name  = get_option( 'cm_email_from_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'cm_email_from', get_option( 'admin_email' ) );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];

        wp_mail( $to, $subject, $html_message, $headers );
    }

    /**
     * Wrap content in a simple, clean HTML email shell.
     */
    private static function wrap( $content, $title = '' ) {
        $site_name = esc_html( get_bloginfo( 'name' ) );
        $home      = esc_url( home_url( '/' ) );

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
<meta charset=\"UTF-8\">
<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">
<title>" . esc_html( $title ) . "</title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;font-size:15px;color:#333}
  .wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:#2d5a27;padding:28px 32px;text-align:center}
  .header a{color:#fff;text-decoration:none;font-size:20px;font-weight:bold}
  .body{padding:32px}
  .body p{line-height:1.6;margin:0 0 16px}
  .footer{padding:20px 32px;text-align:center;font-size:12px;color:#888;border-top:1px solid #eee}
</style>
</head>
<body>
<div class=\"wrap\">
  <div class=\"header\"><a href=\"{$home}\">{$site_name}</a></div>
  <div class=\"body\">{$content}</div>
  <div class=\"footer\">&copy; " . date( 'Y' ) . " {$site_name} &bull; <a href=\"{$home}\">{$home}</a></div>
</div>
</body>
</html>";
    }
}
