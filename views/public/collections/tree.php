<?php 
$pageTitle = __('Browse Catalogue');
echo head(array('title' => $pageTitle, 'bodyclass' => 'collections browse'));
?>

<h1><?php echo $pageTitle . ' ' .  __('(%s total)', $total_results); ?></h1>
<?php echo pagination_links(); ?>


<?php
$sortLinks = array(
    __('Title') => 'Dublin Core,Title',
    __('Date Added') => 'added',
);
?>   
<div id="sort-links">
    <span class="sort-label"><?php echo __('Sort by: '); ?></span><?php echo browse_sort_links($sortLinks); ?>
</div>

<style>
    .iiifitems-catalogue-expand {
        display: block;
        width: 32px;
        position: absolute;
        top: 50%;
        margin-top: -16px;
        border: none !important;
    }
    .iiifitems-catalogue-expand:after {
        content: "\f067";
        font-family: "FontAwesome";
        height: 100%;
        width: 100%;
        background-color: rgba(0, 0, 0, .3);
        -webkit-box-shadow: 0px -1px 1px rgba(255,255,255,.3);
        -moz-box-shadow: 0px -1px 1px rgba(255,255,255,.3);
        box-shadow: 0px -1px 1px rgba(255,255,255,.3);
        -webkit-border-radius: 20px;
        -moz-border-radius: 20px;
        border-radius: 16px;
        /* color: #99C05C; */
        display: block;
        position: relative;
        font-size: 32px;
        top: 0;
        float: left;
        text-indent: 3px;
        line-height: 32px;
        text-shadow: none;
        left: -48px;
    }
    .iiifitems-catalogue-expand.opened:after {
        content: "\f068";
        font-family: "FontAwesome";
    }
    .iiifitems-catalogue-node {
        position: relative;
    }
    .expandable {
        padding-left: 64px !important;
    }
</style>

<script>
jQuery(function() {
    function makeRow(v, depth, parentid) {
        var jr = jQuery('<div class="collection record iiifitems-catalogue-node"></div>');
        jr.append(
            jQuery('<h2/>').append(
                jQuery('<a/>').attr('href', v.link).text(v.title)
            )
        );
        if (v.type === 'Collection') {
            jr.addClass('expandable');
            jr.append(
                jQuery('<a class="iiifitems-catalogue-expand"/>')
                    .data('depth', depth)
                    .data('status', 'unexpanded')
                    .data('id', v.id)
                    .data('expand-url', v['expand-url'])
            );
        }
        if (v.thumbnail) {
            jr.append(
                jQuery('<a class="image"/>').attr('href', v.link).append(
                    jQuery('<img>').attr('src', v.thumbnail)
                )
            );
        }
        if (v.description_html) {
            jr.append('<div class="collection-description">' + v.description_html + "</div>");
        }
        if (v.contributors_html) {
            jr.append('<div class="contributors-description">' + v.contributors_html + "</div>");
        }
        jr.append(
            jQuery('<p class="view-items-link"/>').append(
                jQuery('<a/>').attr('href', v.subitems_link).text(v.subitems_text)
            )
        );
        jr.addClass('iiifitem-descendent-of-' + parentid);
        jr.attr('style', 'margin-left:' + depth*64 + 'px !important;');
        return jr;
    }
    
    jQuery('body').on('click', '.iiifitems-catalogue-expand', function() {
        var jqt = jQuery(this),
            jqnr = jQuery(this).parent().next('.iiifitems-catalogue-node, .iiifitems-catalogue-stop');
        switch (jqt.data('status')) {
            case 'unexpanded':
                jQuery.ajax({
                    url: jqt.data('expand-url'),
                    success: function(data) {
                        jQuery.each(data, function(k, v) {
                            jqnr.before(makeRow(v, parseInt(jqt.data('depth'))+1, jqt.data('id')));
                        });
                        jqt.data('status', 'open');
                        jqt.addClass('opened');
                        jqt.removeClass('loading');
                    },
                    error: function() {
                        jqt.hide();
                    }
                });
                jqt.data('status', 'loading');
                jqt.addClass('loading');
                break;
            case 'open':
                jqt.data('status', 'closed');
                jqt.removeClass('opened');
                jQuery('.iiifitem-descendent-of-' + jqt.data('id') + ' .iiifitems-catalogue-expand').each(function() {
                    if (jQuery(this).data('status') === 'open') {
                        jQuery(this).click();
                    }
                });
                jQuery('.iiifitem-descendent-of-' + jqt.data('id')).hide();
                break;
            case 'closed':
                jqt.data('status', 'open');
                jqt.addClass('opened');
                jQuery('.iiifitem-descendent-of-' + jqt.data('id')).show();
                break;
        }
    });
});
</script>

<?php foreach ($collections as $member): ?>
    <?php $isCollection = IiifItems_Util_Collection::isCollection($member); ?>
    <div class="collection record iiifitems-catalogue-node<?php echo $isCollection ? ' expandable ' : ''; ?>">
        <h2>
            <a href="<?php echo html_escape(url(array('controller' => 'items', 'action' => 'browse', 'id' => ''), 'id', array('collection' => $member->id))); ?>"><?php echo metadata($member, array('Dublin Core', 'Title')); ?></a>
        </h2>
        
        <?php if ($isCollection): ?>
            <a class="iiifitems-catalogue-expand" data-status="unexpanded" data-depth="0" data-id="<?php echo $member->id; ?>" data-expand-url="<?php echo html_escape(url(array('id' => $member->id), 'iiifitems_collection_tree_ajax')); ?>"></a>
            <a href="<?php echo html_escape(url(array('controller' => 'items', 'action' => 'browse', 'id' => ''), 'id', array('collection' => $member->id))); ?>" class="image">
                <img src="<?php echo html_escape(src('icon_collection', 'img', 'png')); ?>">
            </a>
        <?php elseif ($file = $member->getFile()): ?>
            <a href="<?php echo html_escape(url(array('controller' => 'items', 'action' => 'browse', 'id' => ''), 'id', array('collection' => $member->id))); ?>" class="image">
                <img src="<?php echo $file->getWebPath('square_thumbnail'); ?>">
            </a>
        <?php endif; ?>

        <div class="collection-description">
        <?php if (metadata($member, array('Dublin Core', 'Description'))): ?>
            <?php echo text_to_paragraphs(metadata($member, array('Dublin Core', 'Description'), array('snippet' => 150))); ?>
        <?php endif; ?>
        </div>

        <?php if ($member->hasContributor()): ?>
            <div class="collection-contributors">
                <p>
                <strong><?php echo __('Contributors'); ?>:</strong>
                <?php echo metadata($member, array('Dublin Core', 'Contributor'), array('all' => true, 'delimiter' => ', ')); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($isCollection): ?>
            <p class="view-items-link"><a href="<?php echo html_escape(public_url(array('id' => $member->id), 'iiifitems_collection_members')); ?>"><?php echo __('View the submembers in %s', metadata($member, array('Dublin Core', 'Title'))); ?></a></p>
        <?php else: ?>
            <p class="view-items-link"><?php echo link_to_items_browse(__('View the items in %s', metadata($member, array('Dublin Core', 'Title'))), array('collection' => metadata($member, 'id'))); ?></p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<span class="iiifitems-catalogue-stop"></span>

<?php echo pagination_links(); ?>

<?php
echo foot();
