<?php

/**
 * Template for IIIF utility classes.
 * @package IiifItems
 */
class IiifItems_IiifUtil {
    // Disallow instantiation
    private function __construct() {
    }
    
    /**
     * Retrieve the JSON Data element for the given record
     * @param Record $record
     * @param boolean $raw Whether to return a string (true) or an array (false)
     * @return string or array
     */
    protected static function fetchJsonData($record, $raw=false) {
        try {
            $recordClass = get_class($record);
            switch ($recordClass) {
                case 'Collection':
                    $iiifMetadataSlug = 'iiifitems_collection_json_element';
                break;
                case 'Item':
                    $iiifMetadataSlug = 'iiifitems_item_json_element';
                break;
                case 'File':
                    $iiifMetadataSlug = 'iiifitems_file_json_element';
                break;
                default:
                    return null;
                break;
            }
            $iiifJsonDataText = raw_iiif_metadata($record, $iiifMetadataSlug);
            if ($iiifJsonDataText) {
                if ($raw) {
                    return $iiifJsonDataText;
                } else {
                    return json_decode($iiifJsonDataText, true);
                }
            }
        } catch (Exception $ex) {
        }
        return null;
    }
    
    /**
     * Apply Dublin Core metadata from the record onto the JSON array
     * @param array $jsonData
     * @param Record $record
     */
    protected static function addDublinCoreMetadata(&$jsonData, $record) {
        $elements = all_element_texts($record, array(
            'return_type' => 'array',
            'show_element_set_headings' => true,
        ));
        if (isset($elements['Dublin Core'])) {
            if (isset($elements['Dublin Core']['Title'])) {
                $jsonData['label'] = join($elements['Dublin Core']['Title'], ' ');
                unset($elements['Dublin Core']['Title']);
            }
            if (isset($elements['Dublin Core']['Description'])) {
                $jsonData['description'] = join($elements['Dublin Core']['Description'], '<br>');
                unset($elements['Dublin Core']['Description']);
            }
            if (isset($elements['Dublin Core']['Publisher'])) {
                $jsonData['attribution'] = join($elements['Dublin Core']['Publisher'], '<br>');
                unset($elements['Dublin Core']['Publisher']);
            }
            if (isset($elements['Dublin Core']['Rights'])) {
                $jsonData['license'] = join($elements['Dublin Core']['Rights'], '<br>');
                unset($elements['Dublin Core']['Rights']);
            }
            if (!empty($elements['Dublin Core'])) {
                if (!isset($jsonData['metadata'])) {
                    $jsonData['metadata'] = array();
                }
                foreach ($elements['Dublin Core'] as $elementName => $elementContent) {
                    $jsonData['metadata'][] = array(
                        'label' => $elementName,
                        'value' => join($elementContent, '<br>')
                    );
                }
            }
        }
    }
    
    /**
     * Attach a LEFT JOIN to the given metadata elements, with the given prefixes
     * @param Omeka_Db_Select $select The selector to use
     * @param array $elements The metadata element(s) to attach, in prefix => string|Element|Element ID form
     * @param string|null $primaryType (optional) The type of record held by the primary table
     * @param string|null $primaryPrefix (optional) The table prefix for the primary table in the selector
     * @throws InvalidArgumentException
     * @return The original selector passed in
     */
    protected static function attachMetadataToSelect($select, $elements, $primaryType=null, $primaryPrefix=null) {
        // Convert all elements to prefix => element ID form
        $theElements = array();
        foreach ($elements as $prefix => $element) {
            if (is_string($element)) {
                $theElements[$prefix] = get_option($element);
            } elseif (is_numeric($element)) {
                $theElements[$prefix] = $element;
            } elseif (get_class($element) == 'Element') {
                $theElements[$prefix] = $element->id;
            } else {
                throw new InvalidArgumentException(__('attachMetadataToSelect only accepts string, numeric or Element for elements. Input was: %s', $element));
            }
        }
        // Attach left joins on $select
        $thePrimaryPrefix = $primaryPrefix ? ($primaryPrefix.'.') : '';
        $db = get_db();
        foreach ($theElements as $thePrefix => $theElementId) {
            $select->joinLeft(array($thePrefix => $db->ElementText), 
                "{$thePrefix}.record_id = {$thePrimaryPrefix}id"
                . " AND {$thePrefix}.element_id = {$theElementId}"
                . (($primaryType) ? " AND {$thePrefix}.record_type = '{$primaryType}'" : ""), 
            array('text'));
        }
        // Done
        return $select;
    }
}