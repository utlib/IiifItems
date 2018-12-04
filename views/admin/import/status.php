<?php
echo head(array(
    'title' => __('IIIF Toolkit'),
));
include __DIR__ . '/_nav.php';
?>

<style>
    progress {
        display: block;
        clear: both;
    }
    
    progress.failed {
        background-color: #ff0000;
    }
    
    .column-source {
        max-width: 15em;
        overflow-wrap: break-word;
    }
</style>
<div id="primary">
    <?php echo flash(); ?>
    <h2><?php echo html_escape(__('Status Panel')); ?></h2>
    <input type="hidden" value="<?php echo $t; ?>" id="status-last-updated" />
    <script>
        jQuery(function() {
            var translations = {
                'Queued': <?php echo js_escape(__('Queued')); ?>,
                'In Progress': <?php echo js_escape(__('In Progress')); ?>,
                'Completed': <?php echo js_escape(__('Completed')); ?>,
                'Failed': <?php echo js_escape(__('Failed')); ?>
            };
            setInterval(function() {
                jQuery.ajax({
                    url: '<?php echo url('iiif-items/status-update'); ?>',
                    data: { t: jQuery('#status-last-updated').val() },
                    success: function(data) {
                        jQuery('#status-last-updated').val(data.t);
                        jQuery.each(data.updates, function(k, u) {
                            var row = jQuery('#status-' + u.id);
                            if (row.length === 0) {
                                jQuery('#status-table-body').prepend('<tr id="status-' + u.id + '"><td class="column-source"></td><td class="column-dones"></td><td class="column-skips"></td><td class="column-fails"></td><td class="column-created"></td><td class="column-status"><span class="status-label"></span><progress></progress></td></tr>');
                                row = jQuery('#status-' + u.id);
                                row.find('.column-source').html(u.source);
                                row.find('.column-created').html(u.added);
                            }
                            row.find('.column-dones').html(u.dones);
                            row.find('.column-skips').html(u.skips);
                            row.find('.column-fails').html(u.fails);
                            row.find('.column-status .status-label').html(translations[u.status]);
                            if (u.status === 'Queued') {
                                row.find('.column-status progress').attr({
                                    value: null,
                                    max: null
                                });
                            } else {
                                row.find('.column-status progress').attr({
                                    value: u.progress.toString(),
                                    max: u.total.toString()
                                });
                            }
                        });
                    }
                })
            }, 1000);
        });
    </script>
    <table class="simple" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th><?php echo html_escape(__('Source')); ?></th>
                <th><?php echo html_escape(__('Imported Items')); ?></th>
                <th><?php echo html_escape(__('Skipped Items')); ?></th>
                <th><?php echo html_escape(__('Failed Items')); ?></th>
                <th><?php echo html_escape(__('Date')); ?></th>
                <th><?php echo html_escape(__('Status')); ?></th>
            </tr>
        </thead>
        <tbody id='status-table-body'>
            <?php foreach ($statuses as $status) { ?>
                <tr id="status-<?php echo $status->id ?>">
                    <td class="column-source"><?php echo html_escape($status->source); ?></td>
                    <td class="column-dones"><?php echo html_escape($status->dones); ?></td>
                    <td class="column-skips"><?php echo html_escape($status->skips); ?></td>
                    <td class="column-fails"><?php echo html_escape($status->fails); ?></td>
                    <td class="column-created"><?php echo html_escape($status->added); ?></td>
                    <td class="column-status">
                        <span class="status-label">
                            <?php echo html_escape(__($status->status)); ?>
                        </span>
                        <?php if ($status->status == 'Queued') : ?>
                            <progress></progress>
                        <?php else : ?>
                            <progress value="<?php echo $status->progress; ?>" max="<?php echo $status->total ?>"></progress>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php
echo foot();
?>
