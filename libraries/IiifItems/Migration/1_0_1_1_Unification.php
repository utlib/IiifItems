<?php

/**
 * Migration 1.0.1.1: Unify schema for no-collections and with-collections
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_1_0_1_1_Unification extends IiifItems_BaseMigration {
    public static $version = '1.0.1.1';
    
    /**
     * Migrate up
     */
    public function up() {
        $db = get_db();
        $elementSetTable = $db->getTable('ElementSet');
        if (empty($collection_metadata = $elementSetTable->findByName('IIIF Collection Metadata'))) {
            // Add Collection type metadata elements
            $collection_metadata = insert_element_set_failsafe(array(
                'name' => 'IIIF Collection Metadata',
                'description' => '',
                'record_type' => 'Collection'
            ), array(
                array('name' => 'Original @id', 'description' => ''),
                array('name' => 'IIIF Type', 'description' => ''),
                array('name' => 'Parent Collection', 'description' => ''),
                array('name' => 'JSON Data', 'description' => ''),
                array('name' => 'UUID', 'description' => ''),
            ));
        }
        $elementTable = $db->getTable('Element');
        if (empty($elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Parent Collection'))) {
            $parentElement = new Element();
            $parentElement->element_set_id = $collection_metadata->id;
            $parentElement->name = 'Parent Collection';
            $parentElement->save();
        }
        set_option('iiifitems_collection_element_set', $collection_metadata->id);
        set_option('iiifitems_collection_atid_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Original @id')->id);
        set_option('iiifitems_collection_type_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'IIIF Type')->id);
        set_option('iiifitems_collection_parent_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Parent Collection')->id);
        set_option('iiifitems_collection_json_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'JSON Data')->id);
        set_option('iiifitems_collection_uuid_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'UUID')->id);
    }
}
