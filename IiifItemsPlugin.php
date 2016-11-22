<?php
defined('IIIF_ITEMS_DIRECTORY') or define('IIIF_ITEMS_DIRECTORY', dirname(__FILE__));
require_once dirname(__FILE__) . '/helpers/IiifItemsFunctions.php';

class IiifItemsPlugin extends Omeka_Plugin_AbstractPlugin
{   
	protected $_hooks = array(
            'install',
            'uninstall',
            'upgrade',
            'initialize',
            'define_routes',
            'config_form',
            'config',
            'public_items_show',
            'admin_items_show',
            'public_collections_show',
            'admin_collections_show',
            'admin_files_show',
	);
	
	protected $_filters = array(
            'admin_navigation_main',
            'display_elements',
            // Annotation Type Metadata
            'inputForAnnotationOnCanvas' => array('ElementInput', 'Item', 'Item Type Metadata', 'On Canvas'),
            // File Metadata
            'inputForFileOriginalId' => array('ElementInput', 'File', 'IIIF File Metadata', 'Original @id'),
            // Item Metadata
            'inputForItemDisplay' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'),
            'inputForItemOriginalId' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Original @id'),
            'inputForItemParent' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Parent Collection'),
            // Collection Metadata
            'inputForCollectionOriginalId' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Original @id'),
            'inputForCollectionIiifType' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'),
            'inputForCollectionParent' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'),
	);
        
        public function hookInstall() {
            // Init
            $elementTable = get_db()->getTable('Element');
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
            set_option('iiifitems_file_atid_element', $elementTable->findByElementSetNameAndElementName('IIIF File Metadata', 'Original @id')->id);
            set_option('iiifitems_file_json_element', $elementTable->findByElementSetNameAndElementName('IIIF File Metadata', 'JSON Data')->id);
            // Add Item Metadata element set
            $item_metadata = insert_element_set(array(
                'name' => 'IIIF Item Metadata',
                'description' => '',
                'record_type' => 'Item'
            ), array(
                array('name' => 'Display as IIIF?', 'description' => ''),
                array('name' => 'Original @id', 'description' => ''),
                array('name' => 'Parent Collection', 'description' => ''),
                array('name' => 'JSON Data', 'description' => ''),
            ));
            set_option('iiifitems_item_element_set', $item_metadata->id);
            set_option('iiifitems_item_display_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'Display as IIIF?')->id);
            set_option('iiifitems_item_atid_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'Original @id')->id);
            set_option('iiifitems_item_parent_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'Parent Collection')->id);
            set_option('iiifitems_item_json_element', $elementTable->findByElementSetNameAndElementName('IIIF Item Metadata', 'JSON Data')->id);
            // Add Collection Metadata element set
            $collection_metadata = insert_element_set(array(
                'name' => 'IIIF Collection Metadata',
                'description' => '',
                'record_type' => 'Collection'
            ), array(
                array('name' => 'Original @id', 'description' => ''),
                array('name' => 'IIIF Type', 'description' => ''),
                array('name' => 'Parent Collection', 'description' => ''),
                array('name' => 'JSON Data', 'description' => ''),
            ));
            set_option('iiifitems_collection_element_set', $collection_metadata->id);
            set_option('iiifitems_collection_atid_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Original @id')->id);
            set_option('iiifitems_collection_type_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'IIIF Type')->id);
            set_option('iiifitems_collection_parent_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'Parent Collection')->id);
            set_option('iiifitems_collection_json_element', $elementTable->findByElementSetNameAndElementName('IIIF Collection Metadata', 'JSON Data')->id);
            // Add Annotation item type elements
            $annotation_metadata = insert_item_type(array(
                'name' => 'Annotation',
                'description' => 'An OA-compliant annotation',
            ), array(
                array('name' => 'On Canvas', 'description' => 'URI of the attached canvas'),
                array('name' => 'Selector', 'description' => 'The SVG boundary area of the annotation'),
            ));
            set_option('iiifitems_annotation_item_type', $annotation_metadata->id);
            set_option('iiifitems_annotation_on_element', $elementTable->findByElementSetNameAndElementName('Item Type Metadata', 'On Canvas')->id);
            set_option('iiifitems_annotation_selector_element', $elementTable->findByElementSetNameAndElementName('Item Type Metadata', 'Selector')->id);
            $annotation_metadata_elements = array();
            foreach (get_db()->getTable('Element')->findByItemType($annotation_metadata->id) as $element) {
                $annotation_metadata_elements[] = $element->id;
            }
            set_option('iiifitems_annotation_elements', json_encode($annotation_metadata_elements));
            // Add IIIF server options
            set_option('iiifitems_bridge_prefix', '');
            $serverUrlHelper = new Zend_View_Helper_ServerUrl;
            set_option('iiifitems_mirador_path', $serverUrlHelper->serverUrl() . public_url('plugins') . '/IiifItems/views/shared/js/mirador');
            // Add tables
            $db = $this->_db;
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
        }
        
        public function hookUninstall() {
            $elementSetTable = get_db()->getTable('ElementSet');
            $itemTypeTable = get_db()->getTable('ItemType');
            // Remove File Metadata element set
            $elementSetTable->find(get_option('iiifitems_file_element_set'))->delete();
            delete_option('iiifitems_file_atid_element');
            delete_option('iiifitems_file_json_element');
            // Remove Item Metadata element set
            $elementSetTable->find(get_option('iiifitems_item_element_set'))->delete();
            delete_option('iiifitems_item_element_set');
            delete_option('iiifitems_item_display_element');
            delete_option('iiifitems_item_atid_element');
            delete_option('iiifitems_item_parent_element');
            delete_option('iiifitems_item_json_element');
            // Remove Collection Metadata element set
            $elementSetTable->find(get_option('iiifitems_collection_element_set'))->delete();
            delete_option('iiifitems_collection_element_set');
            delete_option('iiifitems_collection_atid_element');
            delete_option('iiifitems_collection_type_element');
            delete_option('iiifitems_collection_parent_element');
            delete_option('iiifitems_collection_json_element');
            // Remove Annotation item type elements
            $annotationItemType = $itemTypeTable->find(get_option('iiifitems_annotation_item_type'));
            foreach (json_decode(get_option('iiifitems_annotation_elements')) as $_ => $element_id) {
                get_db()->getTable('Element')->find($element_id)->delete();
            }
            $annotationItemType->delete();
            delete_option('iiifitems_annotation_item_type');
            delete_option('iiifitems_annotation_on_element');
            delete_option('iiifitems_annotation_selector_element');
            // Remove IIIF server options
            delete_option('iiifitems_bridge_prefix');
            delete_option('iiifitems_mirador_path');
            // Drop tables
            $db = $this->_db;
            $db->query("DROP TABLE IF EXISTS `{$db->prefix}iiif_items_job_statuses`;");
        }
        
        public function hookUpgrade($args) {
            $oldVersion = $args['old_version'];
            $newVersion = $args['new_version'];
            $doMigrate = false;
            
            $versions = array();
            foreach (glob(dirname(__FILE__) . '/migrations/*.php') as $migrationFile) {
                $className = 'IiifItems_Migration_' . basename($migrationFile, '.php');
                include $migrationFile;
                $versions[$className::$version] = new $className();
            }
            uksort($versions, 'version_compare');
            
            foreach ($versions as $version => $migration) {
                if (version_compare($version, $oldVersion, '>')) {
                    $doMigrate = true;
                }
                if ($doMigrate) {
                    $migration->up();
                    if (version_compare($version, $newVersion, '>')) {
                        break;
                    }
                }
            }
        }
        
        public function hookInitialize() {
            add_plugin_hook('after_save_file', 'hook_expire_cache');
            add_plugin_hook('after_save_item', 'hook_expire_cache');
            add_plugin_hook('after_save_collection', 'hook_expire_cache');
            add_plugin_hook('after_delete_file', 'hook_expire_cache');
            add_plugin_hook('after_delete_item', 'hook_expire_cache');
            add_plugin_hook('after_delete_collection', 'hook_expire_cache');
            
            add_filter(array('Display', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_hide_element_display');
            add_filter(array('Display', 'Item', 'IIIF Item Metadata', 'Parent Collection'), 'filter_hide_element_display');
            add_filter(array('Display', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'), 'filter_hide_element_display');
            add_filter(array('ElementForm', 'Item', 'Item Type Metadata', 'On Canvas'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'Item Type Metadata', 'Selector'), 'filter_singular_form');
            add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'Original @id'), 'filter_singular_form');
            add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Original @id'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Parent Collection'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Original @id'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_singular_form');
            add_filter(array('ElementInput', 'Item', 'Item Type Metadata', 'Selector'), 'filter_minimal_input');
            add_filter(array('ElementInput', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_minimal_input');
            add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_minimal_input');
            add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_minimal_input');
        }
        
        public function hookDefineRoutes($args) {
            $args['router']->addConfig(new Zend_Config_Ini(dirname(__FILE__) . '/routes.ini', 'routes'));
        }
        
        public function hookConfigForm() {
            require dirname(__FILE__) . '/config_form.php';
        }
        
        public function hookConfig($args) {
            $csrfValidator = new Omeka_Form_SessionCsrf;
            if (!$csrfValidator->isValid($args['post'])) {
                throw Omeka_Validate_Exception(__("Invalid CSRF token."));
            }
            $data = $args['post'];
            set_option('iiifitems_bridge_prefix', rtrim($data['iiifitems_bridge_prefix'], '/'));
            set_option('iiifitems_mirador_path', rtrim($data['iiifitems_mirador_path'], '/'));
        }
        
        public function hookAdminFilesShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            $iiifUrl = public_full_url(array('things' => 'files', 'id' => $args['view']->file->id), 'iiifitems_manifest');
            echo '<div class="element-set">';
            echo '<h2>IIIF File Information</h2><p>Manifest URL: <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="allowfullscreen" src="' . html_escape(public_full_url(array('things' => 'files', 'id' => $args['view']->file->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';
        }
        
        public function hookPublicItemsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            $iiifUrl = absolute_url(array('things' => 'items', 'id' => $args['view']->item->id), 'iiifitems_manifest');
            echo '<h2>IIIF Manifest</h2><p><a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="allowfullscreen" src="' . html_escape(absolute_url(array('things' => 'items', 'id' => $args['view']->item->id), 'iiifitems_mirador')) . '"></iframe>';
        }
        
        public function hookAdminItemsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            $iiifUrl = public_full_url(array('things' => 'items', 'id' => $args['view']->item->id), 'iiifitems_manifest');
            echo '<div class="element-set">';
            echo '<h2>IIIF Item Information</h2><p>Manifest URL: <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="allowfullscreen" src="' . html_escape(public_full_url(array('things' => 'items', 'id' => $args['view']->item->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';
        }
        
        public function hookPublicCollectionsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            $iiifUrl = absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
            echo '<div class="element-set">';
            echo '<h2>IIIF Manifest</h2><p><a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="allowfullscreen" src="' . html_escape(absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';    
        }
        
        public function hookAdminCollectionsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            $iiifUrl = public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
            echo '<div class="element-set">';
            echo '<h2>IIIF Collection Information</h2><p>Manifest URL: <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="allowfullscreen" src="' . html_escape(public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';
        }
        
        public function filterAdminNavigationMain($nav) {
            $nav[] = array(
                'label' => __('IIIF Import'),
                'uri' => url('iiif-items/import'),
            );
            return $nav;
        }
        
        public function filterDisplayElements($elementsBySet) {
            unset($elementsBySet['Annotation Item Type Metadata']['Selector']);
            unset($elementsBySet['IIIF File Metadata']['JSON Data']);
            unset($elementsBySet['IIIF Item Metadata']['JSON Data']);
            unset($elementsBySet['IIIF Collection Metadata']['JSON Data']);
            return $elementsBySet;
        }
        
        /* Annotation Type Metadata */
        
        public function inputForAnnotationOnCanvas($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            return filter_minimal_input($comps, $args);
        }
        
        /* File Metadata */
        
        public function inputForFileOriginalId($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            return filter_minimal_input($comps, $args);
        }
        
        /* Item Metadata */
        
        public function inputForItemDisplay($comps, $args) {
            $comps['input'] = get_view()->formRadio($args['input_name_stem'] . '[text]', $args['value'], array(), array('No', 'Yes'));
            return filter_minimal_input($comps, $args);
        }
        
        public function inputForItemOriginalId($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            return filter_minimal_input($comps, $args);
        }
        
        public function displayForItemParent($text, $args) {
            $collection = get_db()->getTable('Collection')->find($args['element_text']->text);
            return '<a href="' . url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">' . metadata($collection, array('Dublin Core', 'Title')) . '</a>';

        }
        
        public function inputForItemParent($comps, $args) {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), get_table_options('Collection'));
            return filter_minimal_input($comps, $args);
        }
        
        public function inputForCollectionOriginalId($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            return filter_minimal_input($comps, $args);
        }
        
        /* Collection metadata */
        
        public function inputForCollectionIiifType($comps, $args) {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), array(''=>'None','Manifest'=>'Manifest','Collection'=>'Collection'));
            return filter_minimal_input($comps, $args);
        }
        
        public function displayForCollectionParent($text, $args) {
            $collection = get_db()->getTable('Collection')->find($args['element_text']->text);
            return '<a href="' . url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">' . metadata($collection, array('Dublin Core', 'Title')) . '</a>';

        }
        
        public function inputForCollectionParent($comps, $args) {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), get_table_options('Collection'));
            return filter_minimal_input($comps, $args);
        }
}
?>
