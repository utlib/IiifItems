<?php

class IiifItems_Integration_System extends IiifItems_BaseIntegration {
    public function install() {
        $this->__addTables();
        $this->__addIiif();
        $this->__addMediaPlaceholders();
        $this->__addUuids();
    }
    
    private function __addTables() {
        $db = get_db();
        $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}iiif_items_job_statuses` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
        $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}iiif_items_cached_json_data` (
            `id` int(10) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `record_id` int(11) NOT NULL,
            `record_type` varchar(50) NOT NULL,
            `url` varchar(255) NOT NULL,
            `data` mediumtext NOT NULL,
            `generated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    }
    
    private function __addIiif() {
        set_option('iiifitems_bridge_prefix', '');
        $serverUrlHelper = new Zend_View_Helper_ServerUrl;
        set_option('iiifitems_mirador_path', $serverUrlHelper->serverUrl() . public_url('plugins') . '/IiifItems/views/shared/js/mirador');
    }
    
    private function __addMediaPlaceholders() {
        $addMediaPlaceholdersMigration = new IiifItems_Migration_0_0_1_7();
        $addMediaPlaceholdersMigration->up();
    }
    
    private function __addUuids() {
        $addUuidElementMigration = new IiifItems_Migration_0_0_1_6();
        $addUuidElementMigration->up();
    }
    
    public function uninstall() {
        $this->__removeIiif();
        $this->__removeTables();
        $this->__removeMediaPlaceholders();
    }
    
    private function __removeTables() {
        $db = get_db();
        $db->query("DROP TABLE IF EXISTS `{$db->prefix}iiif_items_job_statuses`;");
        $db->query("DROP TABLE IF EXISTS `{$db->prefix}iiif_items_cached_json_data`;");
    }
    
    private function __removeIiif() {
        delete_option('iiifitems_bridge_prefix');
        delete_option('iiifitems_mirador_path');
    }
    
    private function __removeMediaPlaceholders() {
        $addMediaPlaceholdersMigration = new IiifItems_Migration_0_0_1_7();
        $addMediaPlaceholdersMigration->uninstall();
    }
}
