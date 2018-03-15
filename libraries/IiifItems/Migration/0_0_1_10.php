<?php

/**
 * Migration 0.0.1.10: Kill off "Parent Collection" attribute in items.
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_10 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.10';
    
    /**
     * Migrate up
     */
    public function up() {
        $unwantedParentElementId = get_option('iiifitems_item_parent_element');
        $unwantedParentElement = get_db()->getTable('Element')->find($unwantedParentElementId);
        $unwantedParentElement->delete();
        delete_option('iiifitems_item_parent_element');
    }
}
