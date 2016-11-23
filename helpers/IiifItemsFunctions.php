<?php

function public_full_url() {
    $serverUrlHelper = new Zend_View_Helper_ServerUrl;
    $args = func_get_args();
    return $serverUrlHelper->serverUrl() . call_user_func_array('public_url', $args);
}

function filter_hide_element_display($comps, $args) {
    return '';
}

function filter_singular_form($comps, $args) {
    $comps['add_input'] = false;
    return $comps;
}

function filter_minimal_input($comps, $args) {
    $comps['form_controls'] = '';
    $comps['html_checkbox'] = false;
    return $comps;
}

function hook_expire_cache($args) {
    if (!(isset($args['insert']) && $args['insert'])) {
        clear_iiifitems_cache_values_for($args['record']);
    }
}

function raw_iiif_metadata($record, $optionSlug) {
    if ($elementText = get_db()->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.record_type = ? AND element_texts.record_id = ?', array(get_option($optionSlug), get_class($record), $record->id))) {
        return $elementText[0]->text;
    } else {
        return '';
    }
}

function clear_iiifitems_cache_values_for($record, $bubble=true) {
    $db = get_db();
    $db->query("DELETE FROM `{$db->prefix}iiif_items_cached_json_data` WHERE record_id = ? AND record_type = ?");
    if ($bubble) {
        switch (get_class($record)) {
            case 'File':
                if ($item = $record->getItem()) {
                    clear_iiifitems_cache_for($item);
                }
            break;
            case 'Item':
                if ($collection = $record->getCollection()) {
                    clear_iiifitems_cache_for($collection);
                }
            break;
            case 'Collection':
                if ($parentCollectionId = raw_iiif_metadata($record, 'iiifitems_collection_parent_element')) {
                    if ($parentCollection = $db->getTable('Collection')->find($parentCollectionId)) {
                        clear_iiifitems_cache_for($parentCollection, false);
                    }
                }
            break;
        }
    }
}

function get_cached_iiifitems_value_for($record, $url='') {
    if ($entry = (get_db()->getTable('IiifItems_CachedJsonData')->findBySql('record_id = ? AND record_type = ? AND url = ?', array($record->id, get_class($record), $url)))) {
        return json_decode($entry[0]->data, true);
    } else {
        return null;
    }
}

function cache_iiifitems_value_for($record, $jsonData, $url='') {
    $db = get_db();
    $jsonStr = json_encode($jsonData, JSON_UNESCAPED_SLASHES);
    if ($cacheRecord = $db->getTable('IiifItems_CachedJsonData')->findBySql('record_id = ? AND record_type = ? AND url = ?', array($record->id, get_class($record), $url))) {
        $cacheRecord[0]->data = $jsonStr;
        $cacheRecord[0]->save();
    } else {
        $cacheRecordId = $db->insert('IiifItems_CachedJsonData', array(
            'record_id' => $record->id,
            'record_type' => get_class($record),
            'url' => $url,
            'data' => $jsonStr,
        ));
        $cacheRecord = $db->getTable('IiifItems_CachedJsonData')->find($cacheRecordId);
    }
    return $cacheRecord;
}