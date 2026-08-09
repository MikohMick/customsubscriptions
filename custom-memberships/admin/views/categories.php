<?php defined( 'ABSPATH' ) || exit;
$categories = CM_Categories::get_all();
?>
<div class="wrap cm-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Membership Categories', 'custom-memberships' ); ?></h1>
    <button class="page-title-action cm-btn-add-category"><?php esc_html_e( '+ New Category', 'custom-memberships' ); ?></button>

    <p class="description">
        <?php esc_html_e( 'Categories group your packages on the signup form. Each category becomes one accordion section — members open a category and pick a single plan inside it.', 'custom-memberships' ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped cm-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Subtitle', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Packages', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Order', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Active', 'custom-memberships' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'custom-memberships' ); ?></th>
            </tr>
        </thead>
        <tbody id="cm-categories-tbody">
        <?php if ( $categories ) : ?>
            <?php foreach ( $categories as $cat ) : ?>
                <?php $pkg_count = count( CM_Packages::get_by_category( $cat->id ) ); ?>
                <tr id="cm-category-row-<?php echo (int) $cat->id; ?>">
                    <td><strong><?php echo esc_html( $cat->name ); ?></strong></td>
                    <td><?php echo esc_html( $cat->subtitle ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cm-packages' ) ); ?>">
                            <?php echo (int) $pkg_count; ?>
                        </a>
                    </td>
                    <td><?php echo (int) $cat->sort_order; ?></td>
                    <td>
                        <span class="cm-badge cm-badge--<?php echo $cat->active ? 'active' : 'inactive'; ?>">
                            <?php echo $cat->active ? esc_html__( 'Yes', 'custom-memberships' ) : esc_html__( 'No', 'custom-memberships' ); ?>
                        </span>
                    </td>
                    <td class="cm-row-actions">
                        <button class="button button-small cm-btn-edit-category"
                                data-id="<?php echo (int) $cat->id; ?>"
                                data-category="<?php echo esc_attr( wp_json_encode( $cat ) ); ?>">
                            <?php esc_html_e( 'Edit', 'custom-memberships' ); ?>
                        </button>
                        <button class="button button-small cm-btn-delete-category"
                                data-id="<?php echo (int) $cat->id; ?>">
                            <?php esc_html_e( 'Delete', 'custom-memberships' ); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="6" style="text-align:center;padding:24px">
                <?php esc_html_e( 'No categories yet. Add your first one!', 'custom-memberships' ); ?>
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Category modal -->
<div id="cm-category-modal" class="cm-modal" style="display:none" aria-modal="true" role="dialog">
    <div class="cm-modal__overlay cm-modal-close"></div>
    <div class="cm-modal__box">
        <button class="cm-modal__close cm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'custom-memberships' ); ?>">&times;</button>
        <h2 id="cm-category-modal-title"><?php esc_html_e( 'Add Category', 'custom-memberships' ); ?></h2>
        <div id="cm-category-modal-notice" class="cm-modal-notice" style="display:none"></div>

        <form id="cm-category-form">
            <input type="hidden" name="id" id="cm-cat-id" value="0">

            <div class="cm-form-grid">
                <div class="cm-field">
                    <label for="cm-cat-name"><?php esc_html_e( 'Category Name', 'custom-memberships' ); ?> *</label>
                    <input type="text" id="cm-cat-name" name="name" required placeholder="e.g. FlowForm Essentials">
                </div>
                <div class="cm-field">
                    <label for="cm-cat-subtitle"><?php esc_html_e( 'Subtitle', 'custom-memberships' ); ?></label>
                    <input type="text" id="cm-cat-subtitle" name="subtitle" placeholder="e.g. Yoga + Mat Pilates">
                </div>
                <div class="cm-field cm-field--full">
                    <label for="cm-cat-desc"><?php esc_html_e( 'Description', 'custom-memberships' ); ?></label>
                    <textarea id="cm-cat-desc" name="description" rows="2"
                              placeholder="<?php esc_attr_e( 'Shown inside the accordion, above the plans', 'custom-memberships' ); ?>"></textarea>
                </div>
                <div class="cm-field">
                    <label for="cm-cat-order"><?php esc_html_e( 'Sort Order', 'custom-memberships' ); ?></label>
                    <input type="number" id="cm-cat-order" name="sort_order" value="0">
                </div>
                <div class="cm-field">
                    <label>
                        <input type="checkbox" id="cm-cat-active" name="active" value="1" checked>
                        <?php esc_html_e( 'Active (visible on signup form)', 'custom-memberships' ); ?>
                    </label>
                </div>
            </div>

            <div class="cm-modal__actions">
                <button type="button" class="button cm-modal-close"><?php esc_html_e( 'Cancel', 'custom-memberships' ); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Category', 'custom-memberships' ); ?></button>
            </div>
        </form>
    </div>
</div>
