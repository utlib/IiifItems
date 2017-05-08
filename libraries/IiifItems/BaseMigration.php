<?php

/**
 * Template for migrations.
 * Use this to implement actions that should occur between version updates.
 * @package IiifItems
 */
abstract class IiifItems_BaseMigration {
    public static $version; // The plugin version that this migration migrates to
    protected $_db;
    
    /**
     * Add the updating actions here.
     */
    abstract public function up();
    
    /**
     * Set up this migration.
     */
    public function __construct()
    {
        $this->_db = get_db();
    }
    
    /**
     * Adds a table to the database.
     * 
     * @param string $name Name of the table, without the Omeka prefix
     * @param string $schema The schema part of the table, in SQL
     */
    protected function _createTable($name, $schema) {
        $this->_db->query("CREATE TABLE IF NOT EXISTS `{$this->_db->prefix}{$name}` ({$schema}) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    }
    
    /**
     * Copies an existing table to a backup.
     * 
     * @param string $name Name of the table, without the Omeka prefix
     */
    protected function _backupTable($name) {
        $originalTableName = $this->_db->prefix . $name;
        $backupTableName = $originalTableName . '_backup_' . $this->getVersionSlug();
        $this->_db->query("CREATE TABLE `{$backupTableName}` LIKE `{$originalTableName}`;");
        $this->_db->query("INSERT `{$backupTableName}` SELECT * FROM `{$originalTableName}`;");
    }
    
    /**
     * Returns the version of this plugin, with dots replaced by underscores.
     * 
     * @return string
     */
    protected function _getVersionSlug() {
        return preg_replace('/[^\da-z]/i', '_', $this->version);
    }
}
