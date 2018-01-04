<?php

/**
 * Integrations for the general IIIF Toolkit system.
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_System extends IiifItems_BaseIntegration {
    protected $_filters = array(
        'admin_navigation_main',
        'public_navigation_main',
        'display_elements',
    );
    
    /**
     * General installation procedures.
     */
    public function install() {
        $this->__addTables();
        $this->__addIiif();
        $this->__addMediaPlaceholders();
        $this->__addUuids();
        $this->__addViewOptions();
    }
    
    /**
     * Add new tables for IIIF Toolkit-specific models.
     */
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
    
    /**
     * Add IIIF settings entries.
     */
    private function __addIiif() {
        set_option('iiifitems_bridge_prefix', '');
        set_option('iiifitems_mirador_path', WEB_PLUGIN . '/IiifItems/views/shared/js/mirador');
        $addCssAndJsMigration = new IiifItems_Migration_0_0_1_9();
        $addCssAndJsMigration->up();
    }
    
    /**
     * Copy placeholders for non-IIIF content into /files/originals.
     */
    private function __addMediaPlaceholders() {
        $addMediaPlaceholdersMigration = new IiifItems_Migration_0_0_1_7();
        $addMediaPlaceholdersMigration->up();
    }
    
    /**
     * Start job that adds UUIDs to existing collections, items and files.
     */
    private function __addUuids() {
        $addUuidElementMigration = new IiifItems_Migration_0_0_1_6();
        $addUuidElementMigration->up();
    }
    
    /**
     * Add viewing options.
     */
    private function __addViewOptions() {
        set_option('iiifitems_show_public_catalogue', 1);
        set_option('iiifitems_show_mirador_collections', 1);
        set_option('iiifitems_show_mirador_manifests', 1);
        set_option('iiifitems_show_mirador_items', 1);
        set_option('iiifitems_show_mirador_files', 1);
    }
    
    /**
     * Removes IIIF Toolkit-specific elements.
     */
    public function uninstall() {
        $this->__removeIiif();
        $this->__removeTables();
        $this->__removeMediaPlaceholders();
        $this->__removeViewOptions();
    }
    
    /**
     * Drop tables for IIIF Toolkit-specific models.
     */
    private function __removeTables() {
        $db = get_db();
        $db->query("DROP TABLE IF EXISTS `{$db->prefix}iiif_items_job_statuses`;");
        $db->query("DROP TABLE IF EXISTS `{$db->prefix}iiif_items_cached_json_data`;");
    }
    
    /**
     * Delete IIIF settings.
     */
    private function __removeIiif() {
        delete_option('iiifitems_bridge_prefix');
        delete_option('iiifitems_mirador_path');
        delete_option('iiifitems_mirador_css');
        delete_option('iiifitems_mirador_js');
    }
    
    /**
     * Delete placeholder images for non-IIIF content from /files/originals.
     */
    private function __removeMediaPlaceholders() {
        $addMediaPlaceholdersMigration = new IiifItems_Migration_0_0_1_7();
        $addMediaPlaceholdersMigration->uninstall();
    }
    
    /**
     * Remove viewing options.
     */
    private function __removeViewOptions() {
        delete_option('iiifitems_show_public_catalogue');
        delete_option('iiifitems_show_mirador_collections');
        delete_option('iiifitems_show_mirador_manifests');
        delete_option('iiifitems_show_mirador_items');
        delete_option('iiifitems_show_mirador_files');
    }
    
    /**
     * Filter for the main admin navigation.
     * Adds navigation link to the IIIF Toolkit import form, status screen and maintenance options.
     * Also add IIIF Catalogue Tree
     * 
     * @param array $nav
     * @return array
     */
    public function filterAdminNavigationMain($nav) {
        // Add link to import form, status screen, etc. to qualified users
        if (current_user()->role != 'researcher') {
            $nav[] = array(
                'label' => __('IIIF Toolkit'),
                'uri' => url('iiif-items/import'),
            );
        }
        // Add link to tree navigator
        foreach ($nav as $i => $navEntry) {
            if ($navEntry['label'] === __('Collections')) {
                array_splice($nav, $i+1, 0, array(array(
                    'label' => __('IIIF Catalogue Tree'),
                    'uri' => url('iiif-items/tree'),
                )));
                break;
            }
        }
        // Return navigation
        return $nav;
    }
    
    /**
     * Filter for the public navigation menu.
     * Add the catalogue tree link.
     * 
     * @param array $nav
     * @return array
     */
    public function filterPublicNavigationMain($nav) {
        // Add link to navigator in third position, after collections
        if (get_option('iiifitems_show_public_catalogue')) {
            array_splice($nav, 2, 0, array(array(
                'label' => __('Browse Catalogue'),
                'uri' => url('iiif-items/tree'),
            )));
        }
        // Return navigation
        return $nav;
    }

    /**
     * Filter for which elements to display.
     * Unset most IIIF Toolkit-specific metadata for manual handling.
     * 
     * @param array $elementsBySet
     * @return array
     */
    public function filterDisplayElements($elementsBySet) {
        // Hack for hiding the preview from the standard Item view
        // The standard Item view has a non-empty action context (i.e. list of exports)
        if ($item = get_current_record('item', false)) {
            if (empty(get_current_action_contexts()) && !IiifItems_Util_Canvas::isNonIiifItem($item)) {
                $elementsBySet = array_merge(array(__('IIIF Preview') => array('' => $elementsBySet['IIIF Item Metadata']['UUID'])), $elementsBySet);
            }
        }
        // Hide all IIIF-specific metadata for manual rendering later
        unset($elementsBySet['Annotation Item Type Metadata']['Selector']);
        unset($elementsBySet['Annotation Item Type Metadata']['Annotated Region']);
        unset($elementsBySet['IIIF File Metadata']);
        unset($elementsBySet['IIIF Item Metadata']);
        unset($elementsBySet['IIIF Collection Metadata']);
        return $elementsBySet;
    }
}
