<?php

class IiifItems_Migration_0_0_1_7 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.7';
    
    public function up() {
        // Copy over placeholder images
        $storage = Zend_Registry::get('storage');
        $placeholderDir = join(array(__DIR__, '..', 'placeholders'), DIRECTORY_SEPARATOR);
        foreach (array_diff(scandir($placeholderDir), array('.', '..')) as $fname) {
            try {
                copy($placeholderDir . DIRECTORY_SEPARATOR . $fname, $storage->getTempDir() . DIRECTORY_SEPARATOR . $fname);
                $storage->store($storage->getTempDir() . DIRECTORY_SEPARATOR . $fname, $storage->getPathByType($fname, 'original'));
            } catch (Exception $e) {}
        }
    }
    
    public function uninstall() {
        // Uninstall placeholder images
        $storage = Zend_Registry::get('storage');
        $placeholderDir = join(array(__DIR__, '..', 'placeholders'), DIRECTORY_SEPARATOR);
        foreach (array_diff(scandir($placeholderDir), array('.', '..')) as $fname) {
            try {
                $storage->delete($storage->getPathByType($fname, 'original'));
            } catch (Exception $e) {}
        }
    }
}
