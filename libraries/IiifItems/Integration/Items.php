<?php

/**
 * Integration for non-annotation items.
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_Items extends IiifItems_BaseIntegration {
    protected $_hooks = array(
        'items_browse_sql',
        'before_save_item',
        'before_delete_item',
        'admin_items_browse',
        'admin_items_browse_simple_each',
        'admin_items_show',
        'admin_items_show_sidebar',
        'public_items_show',    
    );
    
    /**
     * Returns whether the given item can be displayed in IIIF.
     * 
     * @param Item $item
     * @return boolean
     */
    protected function _isntIiifDisplayableItem($item) {
        return ($item->fileCount() == 0 && !$item->hasElementText('IIIF Item Metadata', 'JSON Data')) || IiifItems_Util_Canvas::isNonIiifItem($item);
    }
    
    /**
     * Install metadata elements for items.
     */
    public function install() {
        $elementTable = get_db()->getTable('Element');
        // Add Item type metadata elements
        $item_metadata = insert_element_set(array(
            'name' => 'IIIF Item Metadata',
            'description' => '',
            'record_type' => 'Item'
        ), array(
            array('name' => 'Display as IIIF?', 'description' => ''),
            array('name' => 'Original @id', 'description' => ''),
            array('name' => 'Parent Collection', 'description' => ''),
            array('name' => 'JSON Data', 'description' => ''),
        ));
        set_option('iiifitems_item_element_set', $item_metadata->id);
        set_option('iiifitems_item_display_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'Display as IIIF?')->id);
        set_option('iiifitems_item_atid_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'Original @id')->id);
        set_option('iiifitems_item_parent_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'Parent Collection')->id);
        set_option('iiifitems_item_json_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'JSON Data')->id);
    }
    
    /**
     * Remove metadata elements for items.
     */
    public function uninstall() {
        $elementSetTable = get_db()->getTable('ElementSet');
        // Remove Item Metadata element set
        $elementSetTable->find(get_option('iiifitems_item_element_set'))->delete();
        delete_option('iiifitems_item_element_set');
        delete_option('iiifitems_item_display_element');
        delete_option('iiifitems_item_atid_element');
        delete_option('iiifitems_item_parent_element');
        delete_option('iiifitems_item_json_element');
    }
    
    /**
     * Add cache expiry hooks and element filters for items.
     */
    public function initialize() {
        add_plugin_hook('after_save_item', 'hook_expire_cache');
        add_plugin_hook('after_delete_item', 'hook_expire_cache');
        add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'), array($this, 'inputForItemDisplay'));
        add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'Original @id'), array($this, 'inputForItemOriginalId'));
        add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Original @id'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'Parent Collection'), array($this, 'inputForItemParent'));
        add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Parent Collection'), 'filter_singular_form');
        add_filter(array('Display', 'Item', 'IIIF Item Metadata', 'Parent Collection'), 'filter_hide_element_display');
        add_filter(array('Display', 'Item', 'IIIF Item Metadata', 'UUID'), array($this, 'displayPreview'));
        add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'UUID'), array($this, 'inputForItemUuid'));
        add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'UUID'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_minimal_input');
        add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_singular_form');
    }
    
    /**
     * Hook for setting up the browsing SQL for items.
     * Hides annotation-type items from the "browse all items" view.
     * 
     * @param array $args
     */
    public function hookItemsBrowseSql($args) {
        $params = $args['params'];
        if (isset($params['controller']) && isset($params['action'])) {
            if (($params['controller'] == 'items') && ($params['action'] == 'index' || $params['action'] == 'browse') && !isset($params['search']) && !isset($params['tag']) && !isset($params['tags'])) {
                $select = $args['select'];
                $select->where("item_type_id != ? OR item_type_id IS NULL", get_option('iiifitems_annotation_item_type'));
            }
        }
    }
    
    /**
     * Hook for when an item is being saved.
     * Assigns items a new UUID if it does not already have one.
     * 
     * @param array $args
     */
    public function hookBeforeSaveItem($args) {
        // Unset sensitive fields
        $uuidElement = get_option('iiifitems_item_uuid_element');
        if (isset($args['post']['Elements'][$uuidElement])) {
            unset($args['post']['Elements'][$uuidElement]);
        }
        // Add UUID if it's new
        if ($args['insert']) {
            $args['record']->addTextForElement(get_record_by_id('Element', $uuidElement), generate_uuid());
        }
    }
    
    /**
     * Hook for when an item is being deleted.
     * Removes annotations pointing to the deleted item.
     * 
     * @param array $args
     */
    public function hookBeforeDeleteItem($args) {
        $item = $args['record'];
        if ($item->item_type_id != get_option('iiifitems_annotation_item_type')) {
            Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('IiifItems_Job_RemoveSubannotations', array(
                'item_uuid' => raw_iiif_metadata($item, 'iiifitems_item_uuid_element'),
            ));
        }
    }
    
    /**
     * Hook for the admin-side item browsing page.
     * 
     * @param array $args
     */
    public function hookAdminItemsBrowse($args) {
        echo '<style>.iiifitems-action-links { list-style-type: none; margin: 0; padding: 0; }</style>';
    }
    
    /**
     * Hook for entries on the admin-side item browsing page.
     * Adds action links to each non-annotation entry (Annotate and List Annotations).
     * Delegates to the annotations integration for annotation-type actions.
     * 
     * @param array $args
     */
    public function hookAdminItemsBrowseSimpleEach($args) {
        if ($this->_isntIiifDisplayableItem($args['item'])) {
            return;
        }
        $allowEdit = is_allowed($args['item'], 'edit');
        if ($args['item']->item_type_id != get_option('iiifitems_annotation_item_type')) {
            if ($uuid = raw_iiif_metadata($args['item'], 'iiifitems_item_uuid_element')) {
                $annotations = get_db()->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.text = ?', array(get_option('iiifitems_annotation_on_element'), $uuid));
                if ($allowEdit || $annotations) {
                    echo '<ul class="iiifitems-action-links">';
                    if ($allowEdit) {
                        echo '<li><a href="' . html_escape(admin_url(array('things' => 'items', 'id' => $args['item']->id), 'iiifitems_annotate')) . '">Annotate</a></li>';
                    }
                    if ($annotations) {
                        echo '<li><a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $uuid . '">List annotations (' . count($annotations) . ')</a></li>';
                    }
                    echo '</ul>';
                }
            }    
        } else {
            (new IiifItems_Integration_Annotations)->altHookAdminItemsBrowseSimpleEach($args);
        }
    }

    /**
     * Hook for displaying a single item on the admin side.
     * Adds Mirador viewer and IIIF information to IIIF-displayable items.
     * 
     * @param array $args
     */
    public function hookAdminItemsShow($args) {
        if (!isset($args['view'])) {
            $args['view'] = get_view();
        }
        $item = $args['view']->item;
        if ($this->_isntIiifDisplayableItem($item)) {
            return;
        }
        $iiifUrl = public_full_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_manifest');
        echo '<div class="element-set">';
        echo '<h2>IIIF ' . ($item->item_type_id == get_option('iiifitems_annotation_item_type') ? 'Annotation' : 'Item') . ' Information</h2>';
        echo '<p>';
        echo IiifItems_Util_CollectionOptions::getPathBreadcrumb($item, true);
        echo '</p>';
        echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_mirador')) . '"></iframe>';
        $this->_adminElementTextPair('Manifest URL', 'iiif-item-metadata-manifest-url', '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a>', true);
        $this->_adminElementTextPair('Original ID', 'iiif-item-metadata-original-id', metadata($item, array('IIIF Item Metadata', 'Original @id')), true);
        $this->_adminElementTextPair('UUID', 'iiif-item-metadata-uuid', metadata($item, array('IIIF Item Metadata', 'UUID'), array('no_filter' => true)), true);
        echo '</div>';
    }
    
    /**
     * Hook for displaying the sidebar in the admin-side individual item view.
     * Adds panel that displays how many annotations the non-annotation item has.
     * Delegates to the annotations integration for annotation-type items.
     * 
     * @param array $args
     */
    public function hookAdminItemsShowSidebar($args) {
        $item = $args['item'];
        if ($this->_isntIiifDisplayableItem($item)) {
            return;
        }
        $allowEdit = is_allowed($item, 'edit');
        if ($uuid = raw_iiif_metadata($item, 'iiifitems_item_uuid_element')) {
            if ($item->item_type_id != get_option('iiifitems_annotation_item_type')) {
                $onCanvasMatches = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ?", array(
                    get_option('iiifitems_annotation_on_element'),
                    $uuid,
                ));
                echo '<div class="panel"><h4>Annotations</h4>'
                    . '<p>This item has '
                    . '<a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $uuid . '">'
                    . count($onCanvasMatches)
                    . '</a>'
                    . ' annotation(s).</p>'
                    // . '<a href="' . html_escape(admin_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_annotate')) . '" class="big blue button">Annotate</a>'
                    . '</div>';
                if ($allowEdit) {
                    echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape(admin_url(array('things' => 'items', 'id' => $args['item']->id), 'iiifitems_annotate')) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
                    echo '<div class="panel"><h4>Repair</h4>'
                        . '<p>If this item is imported via IIIF Items and the files are '
                        . 'missing/corrupted, you can repair it below. All '
                        . 'files belonging to this item will be deleted and '
                        . 'then reloaded.</p>'
                        . '<form action="' . admin_url(array('id' => $item->id), 'iiifitems_repair_item') . '" method="POST">'
                        . '<input type="submit" value="Repair" class="big blue button" style="width:100%"/>'
                        . '</form>'
                        . '</div>';
                }
            } else {
                (new IiifItems_Integration_Annotations)->altHookAdminItemsShowSidebar($args);
            }
        }
    }
    
    /**
     * Hook for the single-item view in public.
     * Adds Mirador viewer and IIIF info for IIIF-displayable items.
     * 
     * @param array $args
     */
    public function hookPublicItemsShow($args) {
        if (!isset($args['view'])) {
            $args['view'] = get_view();
        }
        $item = $args['view']->item;
        if ($this->_isntIiifDisplayableItem($item)) {
            return;
        }
        $iiifUrl = absolute_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_manifest');
        echo '<div class="element-set">';
        echo '<h2>IIIF Manifest</h2>';
        echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(absolute_url(array('things' => 'items', 'id' => $args['view']->item->id), 'iiifitems_mirador')) . '"></iframe>';
        $this->_publicElementTextPair("Manifest URL", "iiifitems-metadata-manifest-url", '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>', true);
        echo '</div>'; 
    }
    
    /**
     * Element input filter for whether to display the item as IIIF.
     * Renders a no-yes radio button group.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForItemDisplay($comps, $args) {
        $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), array('' => __('Default'), 'Always' => __('Always'), 'Never' => __('Never')));
        return filter_minimal_input($comps, $args);
    }

    /**
     * Element input filter for the original IIIF ID.
     * Renders read-only text for the form input.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForItemOriginalId($comps, $args) {
        $comps['input'] = $args['value'] ? $args['value'] : '';
        return filter_minimal_input($comps, $args);
    }

    /**
     * Display filter for parent collection.
     * @deprecated since version 0.0.1.7
     * 
     * @param string $text
     * @param array $args
     * @return string
     */
    public function displayForItemParent($text, $args) {
        $collection = get_db()->getTable('Collection')->find($args['element_text']->text);
        return '<a href="' . url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">' . metadata($collection, array('Dublin Core', 'Title')) . '</a>';

    }

    /**
     * Element input filter for parent collection.
     * @deprecated since version 0.0.1.7
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForItemParent($comps, $args) {
        $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), get_table_options('Collection'));
        return filter_minimal_input($comps, $args);
    }

    /**
     * Element input filter for UUID.
     * Renders read-only text in its place, TBD if not yet determined.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForItemUuid($comps, $args) {
        $comps['input'] = $args['value'] ? $args['value'] : '&lt;TBD&gt;';
        return filter_minimal_input($comps, $args);
    }
    
    /**
     * Display filter for UUID.
     * Add hacked Mirador Viewer.
     * 
     * @param string $text
     * @param array $args
     * @return string
     */
    public function displayPreview($text, $args) {
        // Don't show preview on standard Omeka views
        // Note: This is in fact a hack that depends on non-Omeka views not having export actions.
        if (!empty(get_current_action_contexts())) {
            return $text;
        }
        // Render the viewer
        $item = find_item_by_uuid($args['element_text']->text);
        return '<iframe style="width:100%; min-height:480px;" allowfullscreen="allowfullscreen" src="' . public_url(array('id' => $item->id), 'iiifitems_neatline_mirador') . '"></iframe>';
    }
}
