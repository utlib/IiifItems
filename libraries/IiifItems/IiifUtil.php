<?php
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
    protected function fetchJsonData($record, $raw=false) {
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
    protected function addDublinCoreMetadata(&$jsonData, $record) {
        $elements = all_element_texts($record, array(
            'return_type' => 'array',
            'show_element_set_headings' => true,
        ));
        if (isset($elements['Dublin Core'])) {
            if (isset($elements['Dublin Core']['Title'])) {
                $jsonData['label'] = join($elements['Dublin Core']['Title'], '<br>');
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
}