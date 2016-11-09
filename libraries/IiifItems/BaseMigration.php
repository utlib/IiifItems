<?php

abstract class IiifItems_BaseMigration {
    public static $version;
    protected $_db;
    
    abstract public function up();
    
    public function __construct()
    {
        $this->_db = get_db();
    }
    
    protected function _createTable($name, $schema) {
        $this->_db->query("CREATE TABLE IF NOT EXISTS `{$this->_db->prefix}{$name}` ({$schema}) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    }
    
    protected function _backupTable($name) {
        $originalTableName = $this->_db->prefix . $name;
        $backupTableName = $originalTableName . '_backup_' . $this->getVersionSlug();
        $this->_db->query("CREATE TABLE `{$backupTableName}` LIKE `{$originalTableName}`;");
        $this->_db->query("INSERT `{$backupTableName}` SELECT * FROM `{$originalTableName}`;");
    }
    
    protected function _getVersionSlug() {
        return preg_replace('/[^\da-z]/i', '_', $this->version);
    }
}
