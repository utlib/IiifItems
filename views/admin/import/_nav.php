<nav id="section-nav" class="navigation vertical">
    <?php
    $navArray = array(
        array(
            'label' => __('Import Items'),
            'uri' => url('iiif-items/import'),
        ),
        array(
            'label' => __('Status'),
            'uri' => url('iiif-items/status'),
        ),
        array(
            'label' => __('Maintenance'),
            'uri' => url('iiif-items/maintenance'),
        )
    );
    echo nav($navArray, 'admin_navigation_settings');
    ?>
</nav>