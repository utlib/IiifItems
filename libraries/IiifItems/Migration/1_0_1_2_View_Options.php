<?php

/**
 * Migration 1.0.1.2: Add viewing options
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_1_0_1_2_View_Options extends IiifItems_BaseMigration {
    public static $version = '1.0.1.2';
    
    /**
     * Migrate up
     */
    public function up() {
        set_option('iiifitems_show_public_catalogue', 1);
        set_option('iiifitems_show_mirador_collections', 1);
        set_option('iiifitems_show_mirador_manifests', 1);
        set_option('iiifitems_show_mirador_items', 1);
        set_option('iiifitems_show_mirador_files', 1);
    }
}
