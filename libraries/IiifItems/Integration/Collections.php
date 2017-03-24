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
        'define_acl',
        'admin_collections_browse',
        'admin_collections_browse_each',
        'admin_collections_show',
        'admin_collections_show_sidebar',
        'public_collections_browse',
        'public_collections_browse_each',
        'public_collections_show',
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
        $elementTable = get_db()->getTable('Element');
        // Add Collection type metadata elements
        $collection_metadata = insert_element_set(array(
            'name' => 'IIIF Collection Metadata',
            'description' => '',
            'record_type' => 'Collection'
        ), array(
            array('name' => 'Original @id', 'description' => ''),
            array('name' => 'IIIF Type', 'description' => ''),
            array('name' => 'Parent Collection', 'description' => ''),
            array('name' => 'JSON Data', 'description' => ''),
        ));
        set_option('iiifitems_collection_element_set', $collection_metadata->id);
        set_option('iiifitems_collection_atid_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Original @id')->id);
        set_option('iiifitems_collection_type_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'IIIF Type')->id);
        set_option('iiifitems_collection_parent_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Parent Collection')->id);
        set_option('iiifitems_collection_json_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'JSON Data')->id);
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
     * Hook for adding ACL entries.
     * Allow public users to traverse the collection tree and the top-level collection.
     * 
     * @param array $args
     */
    public function hookDefineAcl($args) {
        $acl = $args['acl'];
        // Solve login redirect when viewing submembers or collection.json as public user
        $acl->allow(null, 'Collections', 'members');
        $acl->allow(null, 'Collections', 'collection');
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
        $select = $itemsTable->getSelectForCount()->where('items.collection_id IS NULL AND items.item_type_id <> ?', array(get_option('iiifitems_annotation_item_type')));
        $totalItemsWithoutCollection = $db->fetchOne($select);
        if ($totalItemsWithoutCollection) {
            $withoutCollectionMessage = __(plural('%sOne item has no collection.', "%s%d items%s aren't in a collection.", $totalItemsWithoutCollection), '<a href="' . html_escape(url('items/browse?collection=0')) . '">', $totalItemsWithoutCollection, '</a>');
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
        if (raw_iiif_metadata($args['collection'], 'iiifitems_collection_type_element') == 'Collection') {
            if ($uuid = raw_iiif_metadata($args['collection'], 'iiifitems_collection_uuid_element')) {
                $count = IiifItems_Util_Collection::countSubmembersFor($args['collection']);
                echo '<span class="iiifitems-replace-items-link" data-newcount="' . $count . '" data-newurl="' . admin_url(array('id' => $args['collection']->id), 'iiifitems_collection_members') . '" data-showurl="' . admin_url(array('id' => $args['collection']->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '"></span>'
                        . '<a href="' . admin_url(array('id' => $args['collection']->id), 'iiifitems_collection_members') . '">List Members</a>';
            }    
        } else {
            echo '<a href="' . html_escape(admin_url(array('things' => 'collections', 'id' => $args['collection']->id), 'iiifitems_annotate')) . '">Annotate</a>';
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
        switch (raw_iiif_metadata($args['view']->collection, 'iiifitems_collection_type_element')) {
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
                $iiifLabel = __('IIIF Manifest Information');
                $urlLabel = __('Manifest URL');
                $iiifUrl = public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
            break;
        }
        echo '<div class="element-set">';
        echo '<h2>' . $iiifLabel . '</h2>';
        echo '<p>' . IiifItems_Util_CollectionOptions::getPathBreadcrumb($args['collection'], true) . '</p>';
        echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
        $this->_adminElementTextPair("Manifest URL", "iiifitems-metadata-manifest-url", '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a>', true);
        echo '</div>';
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
        if (!IiifItems_Util_Collection::isCollection($collection)) {
            $url = admin_url(array('things' => 'collections', 'id' => $collection->id), 'iiifitems_annotate');
            echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape($url) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
        }
        echo '<div class="panel"><h4>Cache Management</h4>'
            . '<p>If the content shown in the viewer looks out of date, you can clear the cache to regenerate the manifest.</p>'
            . '<form action="' . admin_url(array(), 'iiifItemsCleanCache') . '" method="POST"><input type="hidden" name="type" value="Collection"><input type="hidden" name="id" value="' . $collection->id . '"><input type="submit" value="Clean" class="big blue button" style="width: 100%;"></form>'
            . '</div>';
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
            if ($collection->getFile() === null) {
                echo '<a href="' . html_escape(public_url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id')) . '" class="image"><img src="' . html_escape(src('icon_collection', 'img', 'png')) . '"></a>';
            }
            echo '<p class="view-members-link"><a href="' . html_escape(public_url(array('id' => $collection->id), 'iiifitems_collection_members')) . '" data-hasmembers="' . $collection->id . '">View Submembers</a></p>';
        }
    }

    /**
     * Hook for the public collection browsing view.
     * Adds a script that hides the "view items" link in collection-type collections.
     * 
     * @param array $args
     */
    public function hookPublicCollectionsBrowse($args) {
        echo '<script>jQuery(document).ready(function() { jQuery("[data-hasmembers]").each(function() { jQuery(this).parent().parent().find(".view-items-link").remove(); }); });</script>';
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
        switch (raw_iiif_metadata($args['view']->collection, 'iiifitems_collection_type_element')) {
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
        echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
        $this->_publicElementTextPair($urlLabel, "iiifitems-metadata-manifest-url", '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a>', true);
        echo '</div>';  
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
        $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), array(''=>'None','Manifest'=>'Manifest','Collection'=>'Collection'));
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
        $uuidOptions = IiifItems_Util_CollectionOptions::getCollectionOptions();
        if (isset($_GET['parent']) && find_collection_by_uuid($_GET['parent'])) {
            $args['value'] = $_GET['parent'];
        }
        $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), $uuidOptions);
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
        $comps['input'] = $args['value'] ? $args['value'] : '&lt;TBD&gt;';
        return filter_minimal_input($comps, $args);
    }
}
