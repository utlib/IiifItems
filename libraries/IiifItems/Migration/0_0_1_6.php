<?php

/**
 * Migration 0.0.1.6: Added UUID element and migration job.
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_6 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.6';
    
    /**
     * Migrate up
     */
    public function up() {
        // Add elements
        $collectionElementSet = get_record_by_id('ElementSet', get_option('iiifitems_collection_element_set'));
        try {
            $collectionElementSet->addElements(array('UUID'));
            $collectionElementSet->save();
        } catch (Omeka_Validate_Exception $ex) {
            debug("Passed UUID element for collections.");
        }
        $itemElementSet = get_record_by_id('ElementSet', get_option('iiifitems_item_element_set'));
        try {
            $itemElementSet->addElements(array('UUID'));
            $itemElementSet->save();
        } catch (Omeka_Validate_Exception $ex) {
            debug("Passed UUID element for items.");
        }
        // Set quick-access options
        $tableElement = get_db()->getTable('Element');
        $uuidCollectionElement = $tableElement->findByElementSetNameAndElementName($collectionElementSet->name, 'UUID');
        set_option('iiifitems_collection_uuid_element', $uuidCollectionElement->id);
        $uuidItemElement = $tableElement->findByElementSetNameAndElementName($itemElementSet->name, 'UUID');
        set_option('iiifitems_item_uuid_element', $uuidItemElement->id);
        
        // Start UUID job
        Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('IiifItems_Job_AddUuid', array());
    }
}
