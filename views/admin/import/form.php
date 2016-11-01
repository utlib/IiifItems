<?php
echo head(array(
    'title' => __('IIIF Import'),
));
?>
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
    );
    echo nav($navArray, 'admin_navigation_settings');
    ?>
</nav>
<div id="primary">
    <?php echo flash(); ?>
    <h2><?php echo __('Import Items'); ?></h2>
    <?php echo $this->form; ?>
    <script type="text/javascript">
    //<![CDATA[
    jQuery(document).ready(function() {
        var fadeDelay = 200;
        if (!jQuery('#items_import_source-0').is(':checked')) {
            jQuery('#items_import_source_file').val('').parent().parent().hide();
        }
        if (!jQuery('#items_import_source-1').is(':checked')) {
            jQuery('#items_import_source_url').parent().parent().hide();
        }
        if (!jQuery('#items_import_source-2').is(':checked')) {
            jQuery('#items_import_source_json').parent().parent().hide();
        }
        jQuery('#items_import_source-0').click(function() {
            jQuery('#items_import_source_file').parent().parent().show(fadeDelay);
            jQuery('#items_import_source_url').val('').parent().parent().hide(fadeDelay);
            jQuery('#items_import_source_json').val('').parent().parent().hide(fadeDelay);
        });
        jQuery('#items_import_source-1').click(function() {
            jQuery('#items_import_source_file').val('').parent().parent().hide(fadeDelay);
            jQuery('#items_import_source_url').parent().parent().show(fadeDelay);
            jQuery('#items_import_source_json').val('').parent().parent().hide(fadeDelay);
        });
        jQuery('#items_import_source-2').click(function() {
            jQuery('#items_import_source_file').val('').parent().parent().hide(fadeDelay);
            jQuery('#items_import_source_url').val('').parent().parent().hide(fadeDelay);
            jQuery('#items_import_source_json').parent().parent().show(fadeDelay);
        });
    });
    //]]>
    </script>
</div>
<?php
echo foot();
?>
