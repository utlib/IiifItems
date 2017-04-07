<?php

/**
 * Integrations for the IIIF search API.
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_Search extends IiifItems_BaseIntegration {
    protected $_filters = array(
        'search_element_texts',
    );
    
    /**
     * Filter for removing elements not relevant for general text searching.
     * 
     * @param ElementText[] $allElementTexts
     * @return ElementText[]
     */
    public function filterSearchElementTexts($allElementTexts) {
        // List of unwanted elements
        // Includes JSON data, typing and other non-text, non-contextual data
        $doNotSearchElementIds = array(
            get_option('iiifitems_collection_type_element'),
            get_option('iiifitems_collection_json_element'),
            get_option('iiifitems_item_display_element'),
            get_option('iiifitems_item_json_element'),
            get_option('iiifitems_annotation_selector_element'),
            get_option('iiifitems_annotation_xywh_element'),
            get_option('iiifitems_file_json_element'),
        );
        $newElementTexts = array();
        foreach ($allElementTexts as $elementText) {
            if (!in_array($elementText->element_id, $doNotSearchElementIds)) {
                $newElementTexts[] = $elementText;
            }
        }
        return $newElementTexts;
    }
}
