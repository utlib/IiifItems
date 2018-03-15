<?php

/**
 * Migration 0.0.1.3: Add string-referenceable options to help reference IIIF Toolkit metadata elements.
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_3 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.3';
    
    /**
     * Migrate up
     */
    public function up() {
        set_option('iiifitems_file_atid_element', $this->getElementId('IIIF File Metadata', 'Original @id'));
        set_option('iiifitems_file_json_element', $this->getElementId('IIIF File Metadata', 'JSON Data'));
        set_option('iiifitems_item_display_element', $this->getElementId('IIIF Item Metadata', 'Display as IIIF?'));
        set_option('iiifitems_item_atid_element', $this->getElementId('IIIF Item Metadata', 'Original @id'));
        set_option('iiifitems_item_parent_element', $this->getElementId('IIIF Item Metadata', 'Parent Collection'));
        set_option('iiifitems_item_json_element', $this->getElementId('IIIF Item Metadata', 'JSON Data'));
        set_option('iiifitems_collection_atid_element', $this->getElementId('IIIF Collection Metadata', 'Original @id'));
        set_option('iiifitems_collection_type_element', $this->getElementId('IIIF Collection Metadata', 'IIIF Type'));
        set_option('iiifitems_collection_parent_element', $this->getElementId('IIIF Collection Metadata', 'Parent Collection'));
        set_option('iiifitems_collection_json_element', $this->getElementId('IIIF Collection Metadata', 'JSON Data'));
        set_option('iiifitems_annotation_on_element', $this->getElementId('Item Type Metadata', 'On Canvas'));
        set_option('iiifitems_annotation_selector_element', $this->getElementId('Item Type Metadata', 'Selector'));
    }
    
    /**
     * Helper for finding the element ID of an ElementSet-Element combination.
     * @param string $elementSetName Name of the element set
     * @param string $elementName Name of the element
     * @return integer
     */
    private function getElementId($elementSetName, $elementName) {
        return $this->_db->getTable('Element')->findByElementSetNameAndElementName($elementSetName, $elementName)->id;
    }
}
