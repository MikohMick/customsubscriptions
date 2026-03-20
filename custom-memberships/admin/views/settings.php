<?php defined( 'ABSPATH' ) || exit;
$from_name    = get_option( 'cm_email_from_name', get_bloginfo( 'name' ) );
$from_email   = get_option( 'cm_email_from', get_option( 'admin_email' ) );
$renewal_page = (int) get_option( 'cm_renewal_page_id', 0 );
?>
<div class="wrap cm-wrap">
    <h1><?php esc_html_e( 'Membership Settings', 'custom-memberships' ); ?></h1>

    <form method="post">
        <?php wp_nonce_field( 'cm_save_settings', 'cm_settings_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cm_email_from_name"><?php esc_html_e( 'Email From Name', 'custom-memberships' ); ?></label>
                </th>
                <td>
                    <input type="text" id="cm_email_from_name" name="cm_email_from_name"
                           value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Name displayed in outgoing membership emails.', 'custom-memberships' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cm_email_from"><?php esc_html_e( 'Email From Address', 'custom-memberships' ); ?></label>
                </th>
                <td>
                    <input type="email" id="cm_email_from" name="cm_email_from"
                           value="<?php echo esc_attr( $from_email ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Reply-to address for membership emails.', 'custom-memberships' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cm_renewal_page_id"><?php esc_html_e( 'Renewal / Signup Page', 'custom-memberships' ); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages( [
                        'id'               => 'cm_renewal_page_id',
                        'name'             => 'cm_renewal_page_id',
                        'selected'         => $renewal_page,
                        'show_option_none' => __( '— Home page —', 'custom-memberships' ),
                        'option_none_value'=> 0,
                    ] );
                    ?>
                    <p class="description"><?php esc_html_e( 'Page linked in renewal reminder emails. Should contain the [membership_signup] shortcode.', 'custom-memberships' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Shortcode', 'custom-memberships' ); ?></h2>
        <p><?php esc_html_e( 'Place this shortcode on any page to show the membership signup form:', 'custom-memberships' ); ?></p>
        <code>[membership_signup]</code>
        <p><?php esc_html_e( 'Optional attributes:', 'custom-memberships' ); ?></p>
        <code>[membership_signup title="Join Us" subtitle="Choose your plan"]</code>

        <?php submit_button( __( 'Save Settings', 'custom-memberships' ) ); ?>
    </form>
</div>
