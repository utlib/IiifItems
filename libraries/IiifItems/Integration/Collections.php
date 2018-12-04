<?php

/**
 * Integration for collection-type collections.
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_Collections extends IiifItems_BaseIntegration {
    protected $_hooks = array(
        'collections_browse_sql',
        'before_save_collection',
        'before_delete_collection',
        'admin_collections_browse',
        'admin_collections_browse_each',
        'admin_collections_show',
        'admin_collections_show_sidebar',
        'admin_items_search',
        'public_collections_browse',
        'public_collections_browse_each',
        'public_collections_show',
        'public_items_search',
    );
    
    /**
     * Returns whether a collection can't be displayed in IIIF.
     * 
     * @param Collection $collection
     * @return boolean
     */
    protected function _isntIiifDisplayableCollection($collection) {
        return $collection->totalItems() == 0 && !$collection->hasElementText('IIIF Collection Metadata', 'JSON Data') && empty(IiifItems_Util_Collection::findSubmembersFor($collection));
    }
    
    /**
     * Install metadata elements for collections.
     */
    public function install() {
        $addCollectionsMigration = new IiifItems_Migration_1_0_1_1_Unification;
        $addCollectionsMigration->up();
    }
    
    /**
     * Remove metadata elements for collections.
     */
    public function uninstall() {
        $elementSetTable = get_db()->getTable('ElementSet');
        // Remove Collection Metadata element set
        $elementSetTable->find(get_option('iiifitems_collection_element_set'))->delete();
        delete_option('iiifitems_collection_element_set');
        delete_option('iiifitems_collection_atid_element');
        delete_option('iiifitems_collection_type_element');
        delete_option('iiifitems_collection_parent_element');
        delete_option('iiifitems_collection_json_element');
        delete_option('iiifitems_collection_uuid_element');
    }
    
    /**
     * Add cache expiration hooks and element filters.
     */
    public function initialize() {
        add_plugin_hook('after_save_collection', 'hook_expire_cache');
        add_plugin_hook('after_delete_collection', 'hook_expire_cache');
        
        add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Original @id'), array($this, 'inputForCollectionOriginalId'));
        add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Original @id'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'), array($this, 'inputForCollectionIiifType'));
        add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'), 'filter_singular_form');
        add_filter(array('Display', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'), array($this, 'displayForCollectionParent'));
        add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'), array($this, 'inputForCollectionParent'));
        add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'), 'filter_singular_form');
        add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_minimal_input');
        add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'UUID'), array($this, 'inputForCollectionUuid'));
        add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'UUID'), 'filter_singular_form');
        add_filter('collections_select_options', array($this, 'filterCollectionsSelectOptions'));
    }
        
    /**
     * Hook for setting up the browsing SQL for collections.
     * Removes non-top collections from the "Browse all collections" view.
     * 
     * @param array $args
     */
    public function hookCollectionsBrowseSql($args) {
        $params = $args['params'];
        if (isset($params['controller']) && isset($params['action'])) {
            $select = $args['select'];
            $select->joinLeft(array('element_textsA' => get_db()->ElementText), "element_textsA.element_id = " . get_option('iiifitems_collection_parent_element') . " AND element_textsA.record_type = 'Collection' AND element_textsA.record_id = collections.id", array('text'));
            $select->where("element_textsA.text IS NULL OR element_textsA.text = ''");
        }
    }
    
    /**
     * Hook for when a collection is saved.
     * Prepares newly saved collections for IIIF presentation.
     * 
     * @param array $args
     */
    public function hookBeforeSaveCollection($args) {
        $uuidElementId = get_option('iiifitems_collection_uuid_element');
        $parentElementId = get_option('iiifitems_collection_parent_element');
        $uuidElement = get_record_by_id('Element', $uuidElementId);
        $parentElement = get_record_by_id('Element', $parentElementId);
        $record = $args['record'];
        // Unset sensitive fields
        if (isset($args['post']['Elements'][$uuidElementId])) {
            unset($args['post']['Elements'][$uuidElementId]);
        }
        // Add UUID if it's new
        if ($args['insert']) {
            $record->addTextForElement($uuidElement, generate_uuid());
        }
        // Check Parent Collection element text
        if (isset($args['post']['Elements'][$parentElementId])) {
            $parentUuid = $args['post']['Elements'][$parentElementId][0]['text'];
            if ($parentUuid) {
                // Manifests can't be parents
                $parent = find_collection_by_uuid($parentUuid);
                if (raw_iiif_metadata($parent, 'iiifitems_collection_type_element') != 'Collection') {
                    $record->addError('Parent Collection', __('A collection can only have collection-type collections as its parent.'));
                }
                // User must have permission to use a new parent
                $currentUser = current_user();
                if ($currentUser == 'contributor' && $parent->owner_id != $currentUser->id && $parentUuid != raw_iiif_metadata($record, 'iiifitems_collection_parent_element')) {
                    $record->addError('Parent Collection', __('You do not have the permission reassign this parent as a contributor.'));
                }
                // Anti-loop check if is collection has a parent
                $visitedUuids = array($record->getElementTextsByRecord($uuidElement)[0]->text => true);
                $current = $parent;
                while ($current) {
                    $currentUuid = raw_iiif_metadata($current, 'iiifitems_collection_uuid_element');
                    if (isset($visitedUuids[$currentUuid])) {
                        $record->addError('Parent Collection', __('A collection cannot have itself or a descendent as its parent.'));
                        break;
                    }
                    $visitedUuids[$currentUuid] = true;
                    $current = find_collection_by_uuid(raw_iiif_metadata($current, 'iiifitems_collection_parent_element'));
                }
            }
        }
    }
    
    /**
     * Hook for when a collection is deleted.
     * Unlink children collections when the parent is deleted.
     * 
     * @param array $args
     */
    public function hookBeforeDeleteCollection($args) {
        $db = get_db();
        $collection = $args['record'];
        if (IiifItems_Util_Collection::isCollection($collection) && $uuid = raw_iiif_metadata($collection, 'iiifitems_collection_uuid_element')) {
            $db->query("DELETE FROM `{$db->prefix}element_texts` WHERE element_id IN (?, ?) AND text = ?;", array(get_option('iiifitems_collection_parent_element'), get_option('iiifitems_manifest_parent_element'), $uuid));
        }
    }

    /**
     * Hook for the admin-side collection listings.
     * Use client-side JS to rewrite the "items in a collection" reminder.
     * 
     * @param array $args
     */
    public function hookAdminCollectionsBrowse($args) {
        $db = get_db();
        $itemsTable = $db->getTable('Item');
        $select = $itemsTable->getSelectForCount()->where('items.collection_id IS NULL AND (items.item_type_id IS NULL OR items.item_type_id <> ?)', array(get_option('iiifitems_annotation_item_type')));
        $totalItemsWithoutCollection = $db->fetchOne($select);
        if ($totalItemsWithoutCollection) {
            $withoutCollectionMessage = __(plural('%s%d item%s has no collection.', "%s%d items%s aren't in a collection.", $totalItemsWithoutCollection), '<a href="' . html_escape(url('items/browse?collection=0')) . '">', $totalItemsWithoutCollection, '</a>');
        } else {
            $withoutCollectionMessage = __('All items are in a collection.');
        }
        echo '<script>jQuery(document).ready(function() {'
                . 'jQuery(".not-in-collections").html(' . js_escape($withoutCollectionMessage) . ');'
                . 'jQuery(".iiifitems-replace-items-link").each(function() {'
                    . 'var _this = jQuery(this),'
                    . '_parent = _this.parent().parent();'
                    . '_parent.find("td:last a").attr("href", _this.data("newurl")).text(_this.data("newcount"));'
                    . '_parent.find("td:first").prepend(jQuery("<a></a>").addClass("image").attr("href", _this.data("showurl")).prepend(jQuery("<img>").attr("src", ' . js_escape(src('icon_collection', 'img', 'png')) . ')));'
                    . '_this.remove();'
                . '});'
            . '});</script>';
        echo '<style>.iiifitems-action-links { list-style-type: none; margin: 0; padding: 0; } .iiifitems-action-links li { display: inline-block }</style>';
    }

    /**
     * Hook for entries in the admin-side collection listings.
     * Adds the appropriate action links.
     * 
     * @param array $args
     */
    public function hookAdminCollectionsBrowseEach($args) {
        if ($this->_isntIiifDisplayableCollection($args['collection'])) {
            return;
        }
        $allowEdit = is_allowed($args['collection'], 'edit');
        $type = raw_iiif_metadata($args['collection'], 'iiifitems_collection_type_element');
        if ($type == 'Collection') {
            if ($uuid = raw_iiif_metadata($args['collection'], 'iiifitems_collection_uuid_element')) {
                $count = IiifItems_Util_Collection::countSubmembersFor($args['collection']);
                echo '<span class="iiifitems-replace-items-link" data-newcount="' . $count . '" data-newurl="' . admin_url(array('id' => $args['collection']->id), 'iiifitems_collection_members') . '" data-showurl="' . admin_url(array('id' => $args['collection']->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '"></span>'
                        . '<ul class="iiifitems-action-links"><li><a href="' . admin_url(array('id' => $args['collection']->id), 'iiifitems_collection_members') . '">' . __("List Members") . '</a></li></ul>';
            }    
        } else if ($type != 'None') {
            if ($allowEdit && IiifItems_Util_Manifest::isManifest($args['collection'])) {
                echo '<ul class="iiifitems-action-links"><li><a href="' . html_escape(admin_url(array('things' => 'collections', 'id' => $args['collection']->id), 'iiifitems_annotate')) . '">' . __("Annotate") . '</a></li></ul>';
            }
        }
    }
        
    /**
     * Hook for viewing a single collection on the admin side.
     * Adds Mirador viewer and other IIIF info.
     * 
     * @param array $args
     */
    public function hookAdminCollectionsShow($args) {
        if (!isset($args['view'])) {
            $args['view'] = get_view();
        }
        if ($this->_isntIiifDisplayableCollection($args['collection'])) {
            return;
        }
        switch ($collectionType = raw_iiif_metadata($args['view']->collection, 'iiifitems_collection_type_element')) {
            case 'None':
                return;
            break;
            case 'Collection':
                $iiifLabel = __('IIIF Collection Information');
                $urlLabel = __('Collection URL');
                $iiifUrl = public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_collection');
                $count = IiifItems_Util_Collection::countSubmembersFor($args['collection']);
                echo '<script>jQuery(document).ready(function() { jQuery(".total-items a:first").attr("href", ' . js_escape(admin_url(array('id' => $args['collection']->id), 'iiifitems_collection_members')) . ').text("' . $count . '"); });</script>';
            break;
            case 'Manifest': default:
                if ($args['view']->collection->totalItems() == 0) {
                    return;
                }
                $iiifLabel = __('IIIF Manifest Information');
                $urlLabel = __('Manifest URL');
                $iiifUrl = public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
            break;
        }
        echo '<div class="element-set">';
        echo '<h2>' . $iiifLabel . '</h2>';
        echo '<p>' . IiifItems_Util_CollectionOptions::getPathBreadcrumb($args['collection'], true) . '</p>';
        if (($collectionType != 'Collection' && get_option('iiifitems_show_mirador_manifests')) || ($collectionType == 'Collection' && get_option('iiifitems_show_mirador_collections'))) {
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
        }
        $this->_adminElementTextPair($urlLabel, "iiifitems-metadata-manifest-url", '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a>', true);
        echo '</div>';
        if ($collectionType == 'Collection') {
            echo '<div class="element-set">';
            echo '<iframe src="' . admin_url(array('id' => $args['collection']->id), 'iiifitems_collection_explorer') . '" style="width:100%;"></iframe>';
            echo '</div>';
        }
    }
    
    /**
     * Hook for the sidebar while viewing a single collection on the admin side.
     * Adds the "Clean" button for refreshing the cache's entry on the collection.
     * 
     * @param array $args
     */
    public function hookAdminCollectionsShowSidebar($args) {
        $collection = $args['collection'];
        if ($this->_isntIiifDisplayableCollection($collection)) {
            return;
        }
        $allowEdit = is_allowed($collection, 'edit');
        $isCollection = IiifItems_Util_Collection::isCollection($collection);
        $isManifest = IiifItems_Util_Manifest::isManifest($collection);
        $searchUrl = admin_url('items') . '/browse?search=&type=' . get_option('iiifitems_annotation_item_type') . '&iiif_collection_id=' . $collection->id . '&submembers=1';
        if (!$isCollection && $isManifest) {
            if ($collection->totalItems() == 0) {
                return;
            }
            if ($allowEdit) {
                $url = admin_url(array('things' => 'collections', 'id' => $collection->id), 'iiifitems_annotate');
                echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape($url) . ' + "\" class=\"big blue button\">' . __("Annotate") . '</a>");</script>';
            }
            if ($annotationCount = IiifItems_Util_Manifest::countAnnotationsFor($collection)) {
                echo '<div class="panel">'
                    . '<h4>' . __("Annotations") . '</h4>'
                    . '<p>' . __(plural('This manifest contains %s%d%s annotation.', 'This manifest contains %s%d%s annotations.', $annotationCount), '<a href="' . $searchUrl . '">', $annotationCount, '</a>') . '</p></div>';
            }
        } else if ($isCollection && !$isManifest) {
            $annotationCount = IiifItems_Util_Collection::countAnnotationsFor($collection);
            if ($annotationCount = IiifItems_Util_Collection::countAnnotationsFor($collection)) {
                echo '<div class="panel">'
                    . '<h4>' . __("Annotations") . '</h4>'
                    . '<p>' . __(plural('This collection contains %s%d%s annotation.', 'This collection contains %s%d%s annotations.', $annotationCount), '<a href="' . $searchUrl . '">', $annotationCount, '</a>') . '</p></div>';
            }
        }
        if ($allowEdit) {
            echo '<div class="panel"><h4>' . __("Cache Management") . '</h4>'
                . '<p>' . __('If the content shown in the viewer looks out of date, you can clear the cache to regenerate the manifest.') . '</p>'
                . '<form action="' . admin_url(array(), 'iiifItemsCleanCache') . '" method="POST"><input type="hidden" name="type" value="Collection"><input type="hidden" name="id" value="' . $collection->id . '"><input type="submit" value="' . __("Clean") . '" class="big blue button" style="width: 100%;"></form>'
                . '</div>';
        }
    }
    
    /**
     * Hook for admin items search.
     * Add the "include submembers" checkbox.
     */
    public function hookAdminItemsSearch($args)
    {
        $this->_addIncludeSubmembers($args);
    }

    /**
     * Hook for admin items search.
     * Add the "include submembers" checkbox.
     */
    public function hookPublicItemsSearch($args)
    {
        $this->_addIncludeSubmembers($args);
    }

    /**
     * Echo the "include submembers" checkbox.
     */
    protected function _addIncludeSubmembers($args)
    {
        echo '<div class="field">';
        echo '<div class="two columns alpha"><label for="include_submembers">';
        echo __("Include IIIF Submembers");
        echo '</label></div>';
        echo '<div class="five columns omega inputs">';
        echo $args['view']->formCheckbox('submembers', null, array('checked' => true));
        echo '</div>';
        echo '</div>';
    }

    /**
     * Hook for each entry of the public collection browsing view.
     * Add folder icon and submember viewing link for collection-type collections.
     * 
     * @param array $args
     */
    public function hookPublicCollectionsBrowseEach($args) {
        $collection = $args['collection'];
        if (IiifItems_Util_Collection::isCollection($collection)) {
            echo '<p class="view-members-link"><a href="' . html_escape(public_url(array('id' => $collection->id), 'iiifitems_collection_members')) . '" data-hasmembers="' . $collection->id . '">' . html_escape(__("View submembers in %s", metadata($collection, array('Dublin Core', 'Title')))) . '</a></p>';
        }
    }

    /**
     * Hook for the public collection browsing view.
     * Adds a script that hides the "view items" link in collection-type collections.
     * 
     * @param array $args
     */
    public function hookPublicCollectionsBrowse($args) {
        echo <<<EOF
<style>
    a.iiifitems-has-submembers:before { 
        content: url("data:image/svg+xml,%3Csvg%20width%3D%221em%22%20height%3D%221em%22%20viewBox%3D%220%200%202048%201792%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M1845%20931q0-35-53-35h-1088q-40%200-85.5%2021.5t-71.5%2052.5l-294%20363q-18%2024-18%2040%200%2035%2053%2035h1088q40%200%2086-22t71-53l294-363q18-22%2018-39zm-1141-163h768v-160q0-40-28-68t-68-28h-576q-40%200-68-28t-28-68v-64q0-40-28-68t-68-28h-320q-40%200-68%2028t-28%2068v853l256-315q44-53%20116-87.5t140-34.5zm1269%20163q0%2062-46%20120l-295%20363q-43%2053-116%2087.5t-140%2034.5h-1088q-92%200-158-66t-66-158v-960q0-92%2066-158t158-66h320q92%200%20158%2066t66%20158v32h544q92%200%20158%2066t66%20158v160h192q54%200%2099%2024.5t67%2070.5q15%2032%2015%2068z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fsvg%3E"); 
        padding-right: 1em;
        mix-blend-mode: difference;
    }
</style>
<script>
    jQuery(document).ready(function() {
        jQuery("[data-hasmembers]").each(function() {
            var jqt = jQuery(this), jqtp = jqt.parent(".view-members-link"); 
            jqtp.closest(".collection")
                .find(".view-items-link a")
                .text(jqt.text())
                .attr("href", jqt.attr("href"))
                .addClass("iiifitems-has-submembers"); 
            jqtp.remove();
        });
    });
</script>
EOF;
    }
    
    /**
     * Hook for the public view of single collections.
     * Adds Mirador viewer and other IIIF info.
     * 
     * @param array $args
     */
    public function hookPublicCollectionsShow($args) {
        if (!isset($args['view'])) {
            $args['view'] = get_view();
        }
        if ($this->_isntIiifDisplayableCollection($args['collection'])) {
            return;
        }
        switch ($collectionType = raw_iiif_metadata($args['view']->collection, 'iiifitems_collection_type_element')) {
            case 'None':
                return;
            break;
            case 'Collection':
                $iiifLabel = __('IIIF Collection');
                $urlLabel = __('Collection URL');
                $iiifUrl = absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_collection');
                if ($args['collection']->totalItems() == 0) {
                    echo '<script>jQuery(document).ready(function() { jQuery("#collection-items").remove(); });</script>';
                }
            break;
            case 'Manifest': default:
                $iiifLabel = __('IIIF Manifest');
                $urlLabel = __('Manifest URL');
                $iiifUrl = absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
            break;
        }
        echo '<div class="element-set">';
        echo '<h2>' . $iiifLabel . '</h2>';
        echo '<p>';
        echo IiifItems_Util_CollectionOptions::getPathBreadcrumb($args['collection'], true);
        echo '</p>';
        if (($collectionType != 'Collection' && get_option('iiifitems_show_mirador_manifests')) || ($collectionType == 'Collection' && get_option('iiifitems_show_mirador_collections'))) {
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
        }
        $this->_publicElementTextPair($urlLabel, "iiifitems-metadata-manifest-url", '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a>', true);
        echo '</div>';
        if ($collectionType == 'Collection') {
            echo '<div class="element-set">';
            echo '<iframe src="' . public_url(array('id' => $args['collection']->id), 'iiifitems_collection_explorer') . '" style="width:100%;"></iframe>';
            echo '</div>';
        }
    }
    
    /**
     * Element input filter for collection's original ID.
     * Replace it with a single static value.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForCollectionOriginalId($comps, $args) {
        $comps['input'] = $args['value'] ? $args['value'] : '';
        return filter_minimal_input($comps, $args);
    }

    /**
     * Element input filter for collection's IIIF type.
     * Replace it with a single dropdown.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForCollectionIiifType($comps, $args) {
        $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), array(''=>__('Default'),'Manifest'=>__('Manifest'),'Collection'=>__('Collection'),'None'=>__('Hidden')));
        return filter_minimal_input($comps, $args);
    }

    /**
     * Display filter for collection's parent.
     * Replace it with a link to the parent.
     * 
     * @param string $text
     * @param array $args
     * @return string
     */
    public function displayForCollectionParent($text, $args) {
        $uuid = $args['element_text']->text;
        $collection = find_collection_by_uuid($uuid);
        if (!$collection) {
            return $uuid;
        }
        return '<a href="' . url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">' . metadata($collection, array('Dublin Core', 'Title')) . '</a> (' . $uuid. ')';
    }

    /**
     * Element input filter for collection parent.
     * Replace it with a single dropdown for possible parents.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForCollectionParent($comps, $args) {
        $currentUser = current_user();
        $uuidOptions = IiifItems_Util_CollectionOptions::getCollectionOptions(null, ($currentUser->role == 'contributor') ? $currentUser : null);
        if (isset($_GET['parent']) && find_collection_by_uuid($_GET['parent'])) {
            $args['value'] = $_GET['parent'];
        }
        $parent = find_collection_by_uuid($args['value']);
        if ($currentUser->role == 'contributor' && $args['value'] && $parent && $parent->owner_id != $currentUser->owner_id) {
            $comps['input'] = metadata($parent, array('Dublin Core', 'Title'));
        } else {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), $uuidOptions);
        }
        return filter_minimal_input($comps, $args);
    }

    /**
     * Element input filter for UUID.
     * Replace it with a single, read-only display.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForCollectionUuid($comps, $args) {
        $comps['input'] = $args['value'] ? (get_view()->formHidden($args['input_name_stem'] . '[text]', $args['value']) . $args['value']) : html_escape(__('<TBD>'));
        return filter_minimal_input($comps, $args);
    }
    
    /**
     * Manage search options for collections.
     *
     * @param array Search options for collections.
     * @return array Filtered search options for collections.
     */
    public function filterCollectionsSelectOptions($options)
    {
        $currentUser = current_user();
        $treeOptions = IiifItems_Util_CollectionOptions::getFullIdOptions(null, ($currentUser->role == 'contributor') ? $currentUser : null);
        return array_intersect_key($treeOptions, $options);
    }
}
