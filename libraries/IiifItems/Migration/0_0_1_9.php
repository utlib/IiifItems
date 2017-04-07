<?php

/**
 * Migration 0.0.1.9: Add caching table for regenerated JSON data.
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_9 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.9';
    
    /**
     * Migrate up
     */
    public function up() {
        set_option('iiifitems_mirador_css', 'css/mirador-combined.css');
        set_option('iiifitems_mirador_js', 'mirador.js');
    }
}
