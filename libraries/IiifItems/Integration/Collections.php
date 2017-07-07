<?php

/**
 * Integration for collection-type collections.
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_Collections extends IiifItems_BaseIntegration {
    protected $_hooks = array(
        'before_save_collection',
        'admin_collections_browse',
        'admin_collections_browse_each',
        'admin_collections_show',
        'admin_collections_show_sidebar',
        'public_collections_show',
    );
    
    /**
     * Returns whether a collection can't be displayed in IIIF.
     * 
     * @param Collection $collection
     * @return boolean
     */
    protected function _isntIiifDisplayableCollection($collection) {
        return $collection->totalItems() == 0 && !$collection->hasElementText('IIIF Collection Metadata', 'JSON Data');
    }
    
    /**
     * Install metadata elements for collections.
     */
    public function install() {
        $elementTable = get_db()->getTable('Element');
        // Add Collection type metadata elements
        $collection_metadata = insert_element_set_failsafe(array(
            'name' => 'IIIF Collection Metadata',
            'description' => '',
            'record_type' => 'Collection'
        ), array(
            array('name' => 'Original @id', 'description' => ''),
            array('name' => 'IIIF Type', 'description' => ''),
            array('name' => 'JSON Data', 'description' => ''),
        ));
        set_option('iiifitems_collection_element_set', $collection_metadata->id);
        set_option('iiifitems_collection_atid_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Original @id')->id);
        set_option('iiifitems_collection_type_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'IIIF Type')->id);
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
        add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_minimal_input');
        add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'UUID'), array($this, 'inputForCollectionUuid'));
        add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'UUID'), 'filter_singular_form');
    }
    
    /**
     * Hook for when a collection is saved.
     * Prepares newly saved collections for IIIF presentation.
     * 
     * @param array $args
     */
    public function hookBeforeSaveCollection($args) {
        $uuidElementId = get_option('iiifitems_collection_uuid_element');
        $uuidElement = get_record_by_id('Element', $uuidElementId);
        $record = $args['record'];
        // Unset sensitive fields
        if (isset($args['post']['Elements'][$uuidElementId])) {
            unset($args['post']['Elements'][$uuidElementId]);
        }
        // Add UUID if it's new
        if ($args['insert']) {
            $record->addTextForElement($uuidElement, generate_uuid());
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
        if ($type != 'None') {
            if ($allowEdit && IiifItems_Util_Manifest::isManifest($args['collection'])) {
                echo '<ul class="action-links"><li><a href="' . html_escape(admin_url(array('things' => 'collections', 'id' => $args['collection']->id), 'iiifitems_annotate')) . '">Annotate</a></li></ul>';
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
        echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
        $this->_adminElementTextPair($urlLabel, "iiifitems-metadata-manifest-url", '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a>', true);
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
        $allowEdit = is_allowed($collection, 'edit');
        if (IiifItems_Util_Manifest::isManifest($collection) && $allowEdit) {
            if ($collection->totalItems() == 0) {
                return;
            }
            $url = admin_url(array('things' => 'collections', 'id' => $collection->id), 'iiifitems_annotate');
            echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape($url) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
            if ($annotationCount = IiifItems_Util_Manifest::countAnnotationsFor($collection)) {
                echo '<div class="panel">'
                    . '<h4>Annotations</h4>'
                    . '<p>This manifest contains ' . __(plural('1 annotation', '%s%d annotations', $annotationCount), '', $annotationCount, '') . '.</p></div>';
            }
        }
        if ($allowEdit) {
            echo '<div class="panel"><h4>Cache Management</h4>'
                . '<p>If the content shown in the viewer looks out of date, you can clear the cache to regenerate the manifest.</p>'
                . '<form action="' . admin_url(array(), 'iiifItemsCleanCache') . '" method="POST"><input type="hidden" name="type" value="Collection"><input type="hidden" name="id" value="' . $collection->id . '"><input type="submit" value="Clean" class="big blue button" style="width: 100%;"></form>'
                . '</div>';
        }
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
            case 'Manifest': default:
                $iiifLabel = __('IIIF Manifest');
                $urlLabel = __('Manifest URL');
                $iiifUrl = absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
            break;
        }
        echo '<div class="element-set">';
        echo '<h2>' . $iiifLabel . '</h2>';
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
        $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), array(''=>__('Default'),'Manifest'=>__('Manifest'),'None'=>__('Hidden')));
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
