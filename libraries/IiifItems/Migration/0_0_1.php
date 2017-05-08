<?php

/**
 * Migration 0.0.1: Add table for IiifItems_JobStatus
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1 extends IiifItems_BaseMigration {
    public static $version = '0.0.1';
    
    /**
     * Migrate up
     */
    public function up() {
        $this->_createTable('iiif_items_job_statuses', "
            `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `source` varchar(255) NOT NULL,
            `dones` int(11) NOT NULL,
            `skips` int(11) NOT NULL,
            `fails` int(11) NOT NULL,
            `status` varchar(32) NOT NULL,
            `progress` int(11) NOT NULL DEFAULT 0,
            `total` int(11) NOT NULL DEFAULT 100,
            `added` timestamp DEFAULT '2016-11-01 00:00:00',
            `modified` timestamp DEFAULT NOW() ON UPDATE NOW()
        ");
    }
}
