<?php

/**
 * Migration 0.0.1.4: Add caching table for regenerated JSON data.
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_4 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.4';
    
    /**
     * Migrate up
     */
    public function up() {
        $this->_createTable('iiif_items_cached_json_data', "
            `id` int(10) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `record_id` int(11) NOT NULL,
            `record_type` varchar(50) NOT NULL,
            `url` varchar(255) NOT NULL,
            `data` mediumtext NOT NULL,
            `generated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");
    }
}
