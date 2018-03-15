<?php
echo head(array(
    'title' => __('IIIF Toolkit'),
));
include __DIR__ . '/_nav.php';
echo flash();
?>

<div class="primary">
    <h2><?php echo __('Maintenance'); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <label><?php echo __('Purge JSON cache'); ?></label>
        </div>
        <div class="inputs eight columns omega">
            <p class="explanation"><?php echo __('Cleans all cached manifest data. It is recommended to this after upgrading, changing the IIIF prefix or making any other major configuration change.'); ?></p>
            <p><form method="POST" action="clean-cache">
                <input type="hidden" name="type" value="all">
                <input type="hidden" name="id" value="all">
                <input type="submit" value="<?php echo __('Clean'); ?>" class="blue button">
            </form></p>
        </div>
    </div>
</div>
<?php echo foot(); ?>
