<?php

/**
 * @package helpers
 */

/**
 * Returns a complete URL, including everything up to the domain and protocol.
 * Takes the same arguments as public_url().
 * 
 * @return string
 */
function public_full_url() {
    $serverUrlHelper = new Zend_View_Helper_ServerUrl;
    $args = func_get_args();
    return $serverUrlHelper->serverUrl() . call_user_func_array('public_url', $args);
}

/**
 * Hides the value of a metadata element.
 * For use with Element Display filters only.
 * 
 * @param array $comps
 * @param array $args
 * @return string
 */
function filter_hide_element_display($comps, $args) {
    return '';
}

/**
 * Removes the "Add more" button from metadata element forms.
 * For use with Element ElementForm filters only.
 * 
 * @param array $comps
 * @param array $args
 * @return array
 */
function filter_singular_form($comps, $args) {
    $comps['add_input'] = false;
    return $comps;
}

/**
 * Reduces the input fields for a metadata element form to a single field.
 * For use with Element ElementInput filters only.
 * 
 * @param array $comps
 * @param array $args
 * @return array
 */
function filter_minimal_input($comps, $args) {
    $comps['form_controls'] = '';
    $comps['html_checkbox'] = false;
    return $comps;
}

/**
 * Expires all cached data entries associated with the given record (in $args['record']).
 * For use with hooks that support $args['record'] only.
 * 
 * @param array $args
 */
function hook_expire_cache($args) {
    if (!(isset($args['insert']) && $args['insert'])) {
        clear_iiifitems_cache_values_for($args['record']);
    }
}

/**
 * Returns the single, unaltered metadata text belonging to the given record.
 * 
 * @param Collection|Item|File $record The given record
 * @param string $optionSlug The metadata element's option name, as seen in the install() methods of integrations
 * @return string
 */
function raw_iiif_metadata($record, $optionSlug) {
    if ($elementText = get_db()->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.record_type = ? AND element_texts.record_id = ?', array(get_option($optionSlug), get_class($record), $record->id))) {
        return $elementText[0]->text;
    } else {
        return '';
    }
}

/**
 * Expires all cached data entries associated with the given record.
 * 
 * @param Collection|Item|File $record The given record
 * @param boolean $bubble Whether to cascade onto the records's parent
 */
function clear_iiifitems_cache_values_for($record, $bubble=true) {
    $db = get_db();
    $db->query("DELETE FROM `{$db->prefix}iiif_items_cached_json_data` WHERE record_id = ? AND record_type = ?", array($record->id, get_class($record)));
    if ($bubble) {
        switch (get_class($record)) {
            case 'File':
                if ($item = $record->getItem()) {
                    clear_iiifitems_cache_values_for($item);
                }
            break;
            case 'Item':
                if ($collection = $record->getCollection()) {
                    clear_iiifitems_cache_values_for($collection);
                }
            break;
            case 'Collection':
                if ($parentCollectionId = raw_iiif_metadata($record, 'iiifitems_collection_parent_element')) {
                    if ($parentCollection = IiifItems_Util_Collection::findParentFor($record)) {
                        clear_iiifitems_cache_values_for($parentCollection, false);
                    }
                }
            break;
        }
    }
}

/**
 * Returns the cached JSON value for the given record, in decoded JSON form if available, null if not.
 * 
 * @param Collection|Item|File $record The specified record
 * @param $url (optional) Rough description of where the cached value comes from
 * @return array|null
 */
function get_cached_iiifitems_value_for($record, $url='') {
    if ($entry = (get_db()->getTable('IiifItems_CachedJsonData')->findBySql('record_id = ? AND record_type = ? AND url = ?', array($record->id, get_class($record), $url)))) {
        return json_decode($entry[0]->data, true);
    } else {
        return null;
    }
}

/**
 * Returns the IiifItems_CachedJsonData for the given record, null if unavailable.
 * 
 * @param Collection|Item|File $record The specified record
 * @param array $jsonData The JSON data to store
 * @param string $url
 * @return IiifItems_CachedJsonData
 */
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

/**
 * Returns a randomly generated UUID.
 * 
 * @return string
 */
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

/**
 * Returns the item with the given UUID, null if not found.
 * 
 * @param string $uuid
 * @return Item|null
 */
function find_item_by_uuid($uuid) {
    $db = get_db();
    if ($matchingTexts = $db->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.text = ?', array(get_option('iiifitems_item_uuid_element'), $uuid))) {
        return get_record_by_id($matchingTexts[0]->record_type, $matchingTexts[0]->record_id);
    }
    return null;
}

/**
 * Returns the collection with the given UUID, null if not found.
 * 
 * @param string $uuid
 * @return Collection|null
 */
function find_collection_by_uuid($uuid) {
    $db = get_db();
    if ($matchingTexts = $db->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.text = ?', array(get_option('iiifitems_collection_uuid_element'), $uuid))) {
        return get_record_by_id($matchingTexts[0]->record_type, $matchingTexts[0]->record_id);
    }
    return null;
}
