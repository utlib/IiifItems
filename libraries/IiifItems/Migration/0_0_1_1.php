<?php

/**
 * Migration 0.0.1.1: Add IIIF metadata for files.
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_1 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.1';
    
    /**
     * Migrate up
     */
    public function up() {
        // Add IIIF Metadata for Files
        $file_metadata = insert_element_set(array(
            'name' => 'IIIF File Metadata',
            'description' => '',
            'record_type' => 'File'
        ), array(
            array('name' => 'Original @id', 'description' => ''),
            array('name' => 'JSON Data', 'description' => ''),
        ));
        set_option('iiifitems_file_element_set', $file_metadata->id);
    }
}
