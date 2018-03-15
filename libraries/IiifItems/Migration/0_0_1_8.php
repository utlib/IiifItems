<?php

/**
 * Migration 0.0.1.8: Added xywh region element.
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_8 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.8';
    
    /**
     * Migrate up
     */
    public function up() {
        // Search for existing element or create new
        $xywhElement = $this->_db->getTable('Element')->findBy(array('name' => 'Annotated Region'));
        if ($xywhElement) {
            $xywhElement = $xywhElement[0];
            $xywhElementId = $xywhElement->id;
        } else {
            $xywhElementId = $this->_db->insert('Element', array(
                'name' => 'Annotated Region',
                'description' => 'The rectangular region of the annotation, in xywh format.',
                'element_set_id' => $this->_db->getTable('ElementSet')->findBy(array('name' => 'Item Type Metadata'), 1)[0]->id,
            ));
        }
        // Add element to annotation item type
        $itemType = $this->_db->getTable('ItemType')->find(get_option('iiifitems_annotation_item_type'));
        $itemType->addElementById($xywhElementId);
        $itemType->save();
        set_option('iiifitems_annotation_xywh_element', $xywhElementId);
    }
}
