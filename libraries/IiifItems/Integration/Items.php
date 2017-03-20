<?php

class IiifItems_Integration_Items extends IiifItems_BaseIntegration {
    protected $_hooks = array(
        'items_browse_sql',
        'before_save_item',
        'before_delete_item',
        'admin_items_browse_simple_each',
        'admin_items_show',
        'admin_items_show_sidebar',
        'public_items_show',    
    );
    
    protected function _isntIiifDisplayableItem($item) {
        return ($item->fileCount() == 0 && !$item->hasElementText('IIIF Item Metadata', 'JSON Data')) || IiifItems_Util_Canvas::isNonIiifItem($item);
    }
    
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
        add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'UUID'), array($this, 'inputForItemUuid'));
        add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'UUID'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_minimal_input');
        add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_singular_form');
    }
    
    public function hookItemsBrowseSql($args) {
        $params = $args['params'];
        if (isset($params['controller']) && isset($params['action'])) {
            if (($params['controller'] == 'items') && ($params['action'] == 'index' || $params['action'] == 'browse') && !isset($params['search']) && !isset($params['tag']) && !isset($params['tags'])) {
                $select = $args['select'];
                $select->where("item_type_id != ? OR item_type_id IS NULL", get_option('iiifitems_annotation_item_type'));
            }
        }
    }
    
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
    
    public function hookBeforeDeleteItem($args) {
        $item = $args['record'];
        if ($item->item_type_id != get_option('iiifitems_annotation_item_type')) {
            Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('IiifItems_Job_RemoveSubannotations', array(
                'item_uuid' => raw_iiif_metadata($item, 'iiifitems_item_uuid_element'),
            ));
        }
    }
    
    public function hookAdminItemsBrowseSimpleEach($args) {
        if ($this->_isntIiifDisplayableItem($args['item'])) {
            return;
        }
        if ($args['item']->item_type_id != get_option('iiifitems_annotation_item_type')) {
            if ($uuid = raw_iiif_metadata($args['item'], 'iiifitems_item_uuid_element')) {
                echo '<a href="' . html_escape(admin_url(array('things' => 'items', 'id' => $args['item']->id), 'iiifitems_annotate')) . '">Annotate</a>';
                echo '<br />';
                echo '<a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $uuid . '">List annotations</a>';
            }    
        } else {
            $on = raw_iiif_metadata($args['item'], 'iiifitems_annotation_on_element');
            if (($attachedItem = find_item_by_uuid($on)) || ($attachedItem = find_item_by_atid($on))) {
                $text = 'Attached to: <a href="' . url(array('id' => $attachedItem->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">' . metadata($attachedItem, array('Dublin Core', 'Title')) . '</a>';
                if ($attachedItem->collection_id !== null) {
                    $collection = get_record_by_id('Collection', $attachedItem->collection_id);
                    $collectionLink = url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id');
                    $collectionTitle = metadata($collection, array('Dublin Core', 'Title'));                                                                                                      
                    $text .= " (<a href=\"{$collectionLink}\">{$collectionTitle}</a>)";    
                }
                echo "<p>{$text}</p>";
            }
        }
    }

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
        $this->_adminElementTextPair('UUID', 'iiif-item-metadata-uuid', metadata($item, array('IIIF Item Metadata', 'UUID')), true);
        echo '</div>';
    }
    
    public function hookAdminItemsShowSidebar($args) {
        $item = $args['item'];
        if ($this->_isntIiifDisplayableItem($item)) {
            return;
        }
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
            } else {
                if ($onCanvasUuid = raw_iiif_metadata($item, 'iiifitems_annotation_on_element')) {
                    $onCanvasMatches = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ?", array(
                        get_option('iiifitems_annotation_on_element'),
                        $onCanvasUuid,
                    ));
                    $belongsTo = find_item_by_uuid($onCanvasUuid);
                    echo '<div class="panel"><h4>Annotations</h4>'
                        . '<p>This annotation is one of '
                        . '<a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $onCanvasUuid . '">'
                        . count($onCanvasMatches)
                        . '</a>'
                        . ' on the canvas "<a href="' . url(array('id' => $belongsTo->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">'
                        . metadata($belongsTo, array('Dublin Core', 'Title'))
                        . '</a>".</p>'
                        // . '<a href="' . html_escape(admin_url(array('things' => 'items', 'id' => $belongsTo->id), 'iiifitems_annotate')) . '" class="big blue button">Annotate</a>'
                        . '</div>';
                    echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape(admin_url(array('things' => 'items', 'id' => $belongsTo->id), 'iiifitems_annotate')) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
                }
            }
        }
    }
    
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
    
    public function inputForItemDisplay($comps, $args) {
        $comps['input'] = get_view()->formRadio($args['input_name_stem'] . '[text]', $args['value'], array(), array('No', 'Yes'));
        return filter_minimal_input($comps, $args);
    }

    public function inputForItemOriginalId($comps, $args) {
        $comps['input'] = $args['value'] ? $args['value'] : '';
        return filter_minimal_input($comps, $args);
    }

    public function displayForItemParent($text, $args) {
        $collection = get_db()->getTable('Collection')->find($args['element_text']->text);
        return '<a href="' . url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">' . metadata($collection, array('Dublin Core', 'Title')) . '</a>';

    }

    public function inputForItemParent($comps, $args) {
        $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), get_table_options('Collection'));
        return filter_minimal_input($comps, $args);
    }

    public function inputForItemUuid($comps, $args) {
        $comps['input'] = $args['value'] ? $args['value'] : '&lt;TBD&gt;';
        return filter_minimal_input($comps, $args);
    }
}
