<?php defined( 'ABSPATH' ) || exit;
global $wpdb;
$keys = $wpdb->get_results(
    "SELECT id, label, permissions, last_used, created_at FROM " . CM_TABLE_API_KEYS . " ORDER BY id DESC"
);
$api_base = rest_url( 'custom-memberships/v1' );
?>
<div class="wrap cm-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'API Keys', 'custom-memberships' ); ?></h1>
    <button class="page-title-action" id="cm-btn-add-key"><?php esc_html_e( '+ New API Key', 'custom-memberships' ); ?></button>

    <p class="description">
        <?php
        printf(
            /* translators: %s: API base URL */
            esc_html__( 'Use these keys to integrate with external apps. Pass the key via the %1$s header or %2$s query parameter. Base URL: %3$s', 'custom-memberships' ),
            '<code>X-CM-Api-Key</code>',
            '<code>cm_api_key</code>',
            '<code>' . esc_html( $api_base ) . '</code>'
        );
        ?>
    </p>

    <table class="wp-list-table widefat fixed striped cm-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Label', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Permissions', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Last Used', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Created', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'custom-memberships' ); ?></th>
            </tr>
        </thead>
        <tbody id="cm-keys-tbody">
        <?php if ( $keys ) : ?>
            <?php foreach ( $keys as $key ) : ?>
            <tr id="cm-key-row-<?php echo (int) $key->id; ?>">
                <td><?php echo esc_html( $key->label ); ?></td>
                <td><span class="cm-badge cm-badge--<?php echo esc_attr( $key->permissions ); ?>"><?php echo esc_html( $key->permissions ); ?></span></td>
                <td><?php echo $key->last_used ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $key->last_used ) ) ) : '—'; ?></td>
                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $key->created_at ) ) ); ?></td>
                <td>
                    <button class="button button-small cm-btn-delete-key" data-id="<?php echo (int) $key->id; ?>">
                        <?php esc_html_e( 'Revoke', 'custom-memberships' ); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr id="cm-keys-empty"><td colspan="5" style="text-align:center;padding:24px">
                <?php esc_html_e( 'No API keys yet.', 'custom-memberships' ); ?>
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Endpoint reference -->
    <h2><?php esc_html_e( 'Endpoint Reference', 'custom-memberships' ); ?></h2>
    <table class="wp-list-table widefat fixed cm-table cm-table--endpoints">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Method', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Endpoint', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Description', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Permission', 'custom-memberships' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $endpoints = [
                [ 'GET',    '/packages',               'List all packages',                      'read' ],
                [ 'GET',    '/packages/{id}',           'Get single package',                     'read' ],
                [ 'GET',    '/members',                 'List members (filter: status, search)',   'read' ],
                [ 'POST',   '/members',                 'Create member',                          'write' ],
                [ 'GET',    '/members/{id}',            'Get single member',                      'read' ],
                [ 'PATCH',  '/members/{id}',            'Update member',                          'write' ],
                [ 'DELETE', '/members/{id}',            'Delete member',                          'write' ],
                [ 'POST',   '/members/{id}/use-session','Decrement sessions by amount (default 1)','write' ],
                [ 'GET',    '/stats',                   'Member counts by status',                'read' ],
            ];
            foreach ( $endpoints as $ep ) :
            ?>
            <tr>
                <td><code class="cm-method cm-method--<?php echo esc_attr( strtolower( $ep[0] ) ); ?>"><?php echo esc_html( $ep[0] ); ?></code></td>
                <td><code><?php echo esc_html( $api_base . $ep[1] ); ?></code></td>
                <td><?php echo esc_html( $ep[2] ); ?></td>
                <td><span class="cm-badge cm-badge--<?php echo esc_attr( $ep[3] ); ?>"><?php echo esc_html( $ep[3] ); ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Key modal -->
<div id="cm-key-modal" class="cm-modal" style="display:none" aria-modal="true" role="dialog">
    <div class="cm-modal__overlay" id="cm-key-modal-overlay"></div>
    <div class="cm-modal__box">
        <button class="cm-modal__close" id="cm-key-modal-close" aria-label="<?php esc_attr_e( 'Close', 'custom-memberships' ); ?>">&times;</button>
        <h2><?php esc_html_e( 'New API Key', 'custom-memberships' ); ?></h2>
        <div id="cm-key-modal-notice" class="cm-modal-notice" style="display:none"></div>

        <!-- Step 1: form -->
        <div id="cm-key-form-wrap">
            <form id="cm-key-form">
                <div class="cm-field">
                    <label for="cm-key-label"><?php esc_html_e( 'Label', 'custom-memberships' ); ?> *</label>
                    <input type="text" id="cm-key-label" name="label" required placeholder="e.g. Zapier integration">
                </div>
                <div class="cm-field">
                    <label for="cm-key-perms"><?php esc_html_e( 'Permissions', 'custom-memberships' ); ?></label>
                    <select id="cm-key-perms" name="permissions">
                        <option value="read"><?php esc_html_e( 'Read only', 'custom-memberships' ); ?></option>
                        <option value="write"><?php esc_html_e( 'Write only', 'custom-memberships' ); ?></option>
                        <option value="read_write"><?php esc_html_e( 'Read + Write', 'custom-memberships' ); ?></option>
                    </select>
                </div>
                <div class="cm-modal__actions">
                    <button type="button" class="button" id="cm-key-modal-cancel"><?php esc_html_e( 'Cancel', 'custom-memberships' ); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Key', 'custom-memberships' ); ?></button>
                </div>
            </form>
        </div>

        <!-- Step 2: show generated key -->
        <div id="cm-key-result-wrap" style="display:none">
            <p class="cm-notice cm-notice--warning">
                <strong><?php esc_html_e( 'Copy this key now — it will not be shown again.', 'custom-memberships' ); ?></strong>
            </p>
            <div class="cm-key-display">
                <input type="text" id="cm-generated-key" readonly>
                <button class="button" id="cm-copy-key"><?php esc_html_e( 'Copy', 'custom-memberships' ); ?></button>
            </div>
            <div class="cm-modal__actions">
                <button type="button" class="button button-primary cm-modal-close" id="cm-key-done-btn">
                    <?php esc_html_e( 'Done', 'custom-memberships' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
