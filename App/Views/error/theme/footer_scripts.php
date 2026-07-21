<?php
/**
 * @var \App\Core\AssetManager $assetManager
 */
?>
<!--begin::Script-->
<?= $assetManager->renderJs() ?>
<!--begin::OverlayScrollbars Configure-->
<script>
    const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';

    // Sidebar için varsayılan ayarlar (koyu arka plan olduğu için açık renk scroll)
    const SidebarDefault = {
        scrollbarTheme: 'os-theme-light',
        scrollbarAutoHide: 'leave',
        scrollbarClickScroll: true,
    };

    // Diğer alanlar için varsayılan ayarlar (açık arka plan olduğu için koyu renk ve her zaman görünür scroll)
    const MainDefault = {
        scrollbarTheme: 'os-theme-dark',
        scrollbarAutoHide: 'never',
        scrollbarClickScroll: true,
    };

    function initOverlayScrollbars(el, options = MainDefault) {
        if (!window.OverlayScrollbarsGlobal || OverlayScrollbarsGlobal.OverlayScrollbars(el)) return;

        const { overlayscrollbarsOverflowX: x, overlayscrollbarsOverflowY: y } = el.dataset;

        OverlayScrollbarsGlobal.OverlayScrollbars(el, {
            scrollbars: {
                theme: options.scrollbarTheme,
                autoHide: options.scrollbarAutoHide,
                clickScroll: options.scrollbarClickScroll,
            },
            ...( (x || y) && { overflow: { ...(x && {x}), ...(y && {y}) } } )
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
        if (sidebarWrapper) initOverlayScrollbars(sidebarWrapper, SidebarDefault);
        
        // Custom OverlayScrollbars for layout and specified elements
        document.querySelectorAll('[data-overlayscrollbars-initialize]')
            .forEach(el => initOverlayScrollbars(el, MainDefault));

        // Watch for dynamically added elements
        new MutationObserver(mutations => {
            for (const { addedNodes } of mutations) {
                for (const node of addedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.dataset.overlayscrollbarsInitialize !== undefined) initOverlayScrollbars(node, MainDefault);
                        node.querySelectorAll('[data-overlayscrollbars-initialize]').forEach(el => initOverlayScrollbars(el, MainDefault));
                    }
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    });
</script>
<!--end::OverlayScrollbars Configure-->

<!--end::Script-->