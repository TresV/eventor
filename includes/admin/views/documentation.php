<?php
if (! defined('ABSPATH')) {
    exit;
}
// View variables ($collection, …) are injected by the renderer via a controlled
// data array; they are not global state.
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap evt-dashboard evt-docs" data-evt-docs-root>
    <h1><?php echo esc_html((string) $collection['page_title']); ?></h1>
    <p class="description">
        <?php echo esc_html((string) $collection['page_intro']); ?>
    </p>

    <div class="evt-docs__layout">
        <aside class="evt-docs__sidebar">
            <div class="evt-docs__sidebar-card">
                <h2><?php echo esc_html((string) $collection['sidebar_title']); ?></h2>
                <p class="evt-docs__sidebar-copy">
                    <?php echo esc_html((string) $collection['sidebar_copy']); ?>
                </p>
                <label class="screen-reader-text" for="evt-docs-search"><?php echo esc_html((string) $collection['search_label']); ?></label>
                <input
                    id="evt-docs-search"
                    class="evt-docs__search"
                    type="search"
                    placeholder="<?php echo esc_attr((string) $collection['search_label']); ?>"
                    data-evt-docs-search />
            </div>

            <nav class="evt-docs__nav" aria-label="<?php echo esc_attr((string) $collection['nav_label']); ?>">
                <?php foreach ($documents as $document) : ?>
                    <?php
                    $is_active = $document['slug'] === $active_slug;
                    $document_url = add_query_arg(
                        [
                            'page' => isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : 'evt-tickets-documentation',
                            'doc'  => $document['slug'],
                        ],
                        admin_url('admin.php')
                    );
                    ?>
                    <a
                        class="evt-docs__nav-item<?php echo $is_active ? ' is-active' : ''; ?>"
                        href="<?php echo esc_url($document_url); ?>"
                        data-evt-doc-item
                        data-doc-title="<?php echo esc_attr($document['title']); ?>"
                        data-doc-description="<?php echo esc_attr($document['description']); ?>">
                        <strong><?php echo esc_html($document['title']); ?></strong>
                        <span><?php echo esc_html($document['description']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <section class="evt-docs__content evt-card">
            <div class="evt-docs__content-head">
                <div>
                    <h2><?php echo esc_html($active_document['title']); ?></h2>
                    <p class="evt-docs__lead"><?php echo esc_html($active_document['description']); ?></p>
                </div>
                <span class="evt-docs__badge"><?php echo esc_html($active_document['file']); ?></span>
            </div>

            <div class="evt-docs__body">
                <?php echo $active_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                ?>
            </div>
        </section>
    </div>
</div>