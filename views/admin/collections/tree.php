<?php 
$pageTitle = __('Browse Collections') . ' ' .  __('(%s total)', $total_results);
echo head(array('title' => $pageTitle, 'bodyclass' => 'collections'));
echo flash();
?>

<style>
    .iiifitems-tree-explorer {
        margin-bottom: 1em;
    }
    .iiifitems-catalogue-node {
        position: relative;
        display: block;
        width: 100%;
        padding-left: 48px;
        box-sizing: border-box;
    }
    .iiifitems-catalogue-this {
        display: block;
        position: relative;
        width: 100%;
    }
    .iiifitems-catalogue-children {
        display: block;
        width: 100%;
    }
    .iiifitems-catalogue-expand {
        display: block;
        width: 32px;
        position: absolute;
        left: -42px;
        top: 50%;
        margin-top: -16px;
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
    }
    .iiifitems-catalogue-expand.opened:after {
        content: "\f068";
        font-family: "FontAwesome";
    }
    .iiifitems-catalogue-body {
        position: relative;
        display: block;
        width: 100%;
        padding: 0 15px;
        background-color: white;
        border: 1px solid #d8d8d8;
        box-sizing: border-box;
    }
    .iiifitems-catalogue-thumbnail {
        width: 48px;
        height: 48px;
        float: left;
        top: 50%;
        position: absolute;
        margin-top: -24px;
    }
    .iiifitems-catalogue-description {
        display: block;
        margin-left: 64px;
    }
    .iiifitems-catalogue-folder-icon::after {
        content: "\f114";
        font-family: "FontAwesome";
        height: 48px;
        width: 48px;
        display: block;
        position: relative;
        font-size: 48px;
        top: 50%;
        margin-top: -24px;
        position: absolute;
        float: left;
        text-indent: 4px;
        line-height: 48px;
        text-shadow: none;
    }
    .iiifitems-catalogue-folder-icon.opened::after {
        content: "\f115";
        font-family: "FontAwesome";
    }
    .iiifitems-catalogue-folder-icon.loading::after {
        content: "\f110";
        font-family: "FontAwesome";
        -webkit-animation: fa-spin 1s infinite steps(8);
        animation: fa-spin 1s infinite steps(8);
    }
    
    #sort-links-list {
        display: inline-block;
        margin: 0.5em;
        margin-left: 0;
        padding: 0;
    }
    #sort-links-list li {
        display: inline;
        margin-left: 1em;
    }
    
    #sort-links-list li.desc a:after, #sort-links-list li.asc a:after {
        font-family: "FontAwesome";
        display: inline-block;
    }
    #sort-links-list li.desc a:after {
        content: "\00a0\f0d8";
    }
    #sort-links-list li.asc a:after {
        content: "\00a0\f0d7";
    }
</style>

<script>
jQuery(function() {
    jQuery('.iiifitems-tree-explorer').on('click', '.iiifitems-catalogue-expand', function() {
        var jqt = jQuery(this);
        switch (jqt.data('status')) {
            case 'unexpanded':
                jQuery.ajax({
                    url: jqt.data('expand-url'),
                    success: function(data) {
                        jQuery.each(data, function(k, v) {
                            var newNode = jQuery('<div class="iiifitems-catalogue-node"></div>'),
                                thisNode = jQuery('<div class="iiifitems-catalogue-this"></div>'),
                                bodyNode = jQuery('<div class="iiifitems-catalogue-body"></div>');
                            if (v.type === 'Collection') {
                                var expandNode = jQuery('<a class="iiifitems-catalogue-expand"></a>').data({
                                    status: 'unexpanded',
                                    id: v.id,
                                    'expand-url': v['expand-url']
                                });
                                expandNode.appendTo(thisNode);
                                bodyNode.append('<span class="iiifitems-catalogue-folder-icon"></span>');
                                jQuery('<div class="iiifitems-catalogue-description">').append(jQuery('<p></p>').text(v.title)).append(jQuery('<p></p>').text(<?php echo js_escape(__("Submembers: ")); ?> + v.count)).appendTo(bodyNode);
                            } else {
                                if (v.thumbnail) {
                                    jQuery('<a></a>').attr('href', v.link).append(
                                        jQuery('<img>').attr({
                                            src: v.thumbnail,
                                            'class': 'iiifitems-catalogue-thumbnail'
                                        })
                                    ).appendTo(bodyNode);
                                }
                                (v.thumbnail ?
                                    jQuery('<div class="iiifitems-catalogue-description">').append(
                                        jQuery('<p></p>').append(
                                            jQuery('<a></a>').attr('href', v.link).text(v.title)
                                        )
                                    ) :
                                    jQuery('<p></p>').append(
                                        jQuery('<a></a>').attr('href', v.link).text(v.title)
                                    )
                                ).append(
                                    jQuery('<p></p>').text(<?php echo js_escape(__("Items: ")); ?>).append(
                                        (v.count > 0) ?
                                            jQuery('<a></a>').attr('href', v.subitems_link).text(v.count) :
                                            jQuery('<span>0</span>')
                                    )
                                ).appendTo(bodyNode);
                            }
                            bodyNode.appendTo(thisNode);
                            thisNode.appendTo(newNode);
                            newNode.append('<div class="iiifitems-catalogue-children"></div>');
                            newNode.appendTo(jqt.closest('.iiifitems-catalogue-node').find('.iiifitems-catalogue-children')[0]);
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
                jqt.parent().next('.iiifitems-catalogue-children').hide();
            break;
            case 'closed':
                jqt.data('status', 'open');
                jqt.addClass('opened');
                jqt.parent().next('.iiifitems-catalogue-children').show();
            break;
        }
    });
});
</script>

<?php echo pagination_links(); ?>
<a href="/omeka/admin/collections/add" class="small green button"><?php echo __("Add a Collection"); ?></a>
<p class="not-in-collections"><?php echo $withoutCollectionMessage; ?></p>

<?php
$sortLinks = array(
    __('Title') => 'Dublin Core,Title',
    __('Date Added') => 'added',
);
?>   

<div id="sort-links">
    <span class="sort-label"><?php echo __('Sort by:'); ?></span>
    <ul id="sort-links-list">
        <?php echo browse_sort_links($sortLinks, array('link_tag' => 'li', 'list_tag' => '')); ?>
    </ul>
</div>

<div class="iiifitems-tree-explorer">
    <?php foreach ($collections as $member): ?>
        <div class="iiifitems-catalogue-node">
            <div class="iiifitems-catalogue-this">
                <?php if (IiifItems_Util_Collection::isCollection($member)): ?>
                    <a class="iiifitems-catalogue-expand" data-status="unexpanded" data-id="<?php echo $member->id; ?>" data-expand-url="<?php echo html_escape(admin_url(array('id' => $member->id), 'iiifitems_collection_tree_ajax')); ?>"></a>
                <?php endif; ?>
                <div class="iiifitems-catalogue-body">
                    <?php if (IiifItems_Util_Collection::isCollection($member)): ?>
                        <span class="iiifitems-catalogue-folder-icon"></span>
                        <div class="iiifitems-catalogue-description">
                            <p><?php echo metadata($member, array('Dublin Core', 'Title')); ?></p>
                            <p><?php echo __('Submembers: '); ?><?php echo IiifItems_Util_Collection::countSubmembersFor($member); ?></p>
                        </div>
                    <?php elseif ($file = $member->getFile()): ?>
                        <img src="<?php echo $file->getWebPath('square_thumbnail'); ?>" class="iiifitems-catalogue-thumbnail">
                        <div class="iiifitems-catalogue-description">
                            <p><a href=""><?php echo metadata($member, array('Dublin Core', 'Title')); ?></a></p>
                            <p><?php echo __("Items: "); ?><a href="<?php echo html_escape(url(array('controller' => 'items', 'action' => 'browse', 'id' => ''), 'id', array('collection' => $member->id))); ?>"><?php echo $member->totalItems(); ?></a></p>    
                        </div>
                    <?php else: ?>
                        <p><a href=""><?php echo metadata($member, array('Dublin Core', 'Title')); ?></a></p>
                        <p><?php echo __("Items: "); ?><a href="<?php echo html_escape(url(array('controller' => 'items', 'action' => 'browse', 'id' => ''), 'id', array('collection' => $member->id))); ?>"><?php echo $member->totalItems(); ?></a></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="iiifitems-catalogue-children"></div>
        </div>
    <?php endforeach; ?>
</div>

<?php echo pagination_links(); ?>
<a href="/omeka/admin/collections/add" class="small green button"><?php echo __("Add a Collection"); ?></a>
<p class="not-in-collections"><?php echo $withoutCollectionMessage; ?></p>

<?php
echo foot();
