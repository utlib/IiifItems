<?php

/**
 * Migration 0.0.1.5: Added Annotation item type and associated Text element
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_5 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.5';
    
    /**
     * Migrate up
     */
    public function up() {
        // Find or create text element
        $textElement = $this->_db->getTable('Element')->findBy(array('name' => 'Text'));
        if ($textElement) {
            $textElement = $textElement[0];
            $textElementId = $textElement->id;
        } else {
            $textElementId = $this->_db->insert('Element', array(
                'name' => 'Text',
                'description' => 'Any textual data included in the document',
            ));
        }
        
        // Add element to annotation item type
        $itemType = $this->_db->getTable('ItemType')->find(get_option('iiifitems_annotation_item_type'));
        $itemType->addElementById($textElementId);
        $itemType->save();
        set_option('iiifitems_annotation_text_element', $textElementId);
    }
}
