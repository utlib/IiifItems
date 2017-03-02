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
            'define_acl',
            'define_routes',
            'config_form',
            'config',
            'public_items_show',
            'admin_items_show',
            'public_collections_show',
            'admin_collections_show',
            'admin_files_show',
            'items_browse_sql',
            'collections_browse_sql',
            'admin_items_browse_simple_each',
            'admin_collections_browse_each',
            'admin_collections_browse',
            'public_collections_browse_each',
            'public_collections_browse',
            'before_save_collection',
            'before_save_item',
            'admin_items_show_sidebar',
            'admin_collections_show_sidebar',
            'before_delete_item',
            'before_delete_collection',
	);
	
	protected $_filters = array(
            'admin_navigation_main',
            'display_elements',
            // Annotation Type Metadata
            'displayForAnnotationOnCanvas' => array('Display', 'Item', 'Item Type Metadata', 'On Canvas'),
            'inputForAnnotationOnCanvas' => array('ElementInput', 'Item', 'Item Type Metadata', 'On Canvas'),
            // File Metadata
            'inputForFileOriginalId' => array('ElementInput', 'File', 'IIIF File Metadata', 'Original @id'),
            // Item Metadata
            'inputForItemDisplay' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'),
            'inputForItemOriginalId' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Original @id'),
            'inputForItemParent' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Parent Collection'),
            'inputForItemUuid' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'UUID'),
            // Collection Metadata
            'displayForCollectionParent' => array('Display', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'),
            'inputForCollectionOriginalId' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Original @id'),
            'inputForCollectionIiifType' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'),
            'inputForCollectionParent' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'),
            'inputForCollectionUuid' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'UUID'),
            // Exhibits extension
            'exhibit_layouts',
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
            // Add Annotation item type Text element
            include_once(dirname(__FILE__) . '/migrations/0_0_1_5.php');
            $addTextElementMigration = new IiifItems_Migration_0_0_1_5();
            $addTextElementMigration->up();
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
            $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}iiif_items_cached_json_data` (
                `id` int(10) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `record_id` int(11) NOT NULL,
                `record_type` varchar(50) NOT NULL,
                `url` varchar(255) NOT NULL,
                `data` mediumtext NOT NULL,
                `generated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
            // Add media placeholder files
            $addMediaPlaceholdersMigration = new IiifItems_Migration_0_0_1_7();
            $addMediaPlaceholdersMigration->up();
            // Add UUIDs via a job
            include_once(dirname(__FILE__) . '/migrations/0_0_1_6.php');
            $addUuidElementMigration = new IiifItems_Migration_0_0_1_6();
            $addUuidElementMigration->up();
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
            $db->query("DROP TABLE IF EXISTS `{$db->prefix}iiif_items_cached_json_data`;");
            // Remove media placeholder files
            $addMediaPlaceholdersMigration = new IiifItems_Migration_0_0_1_7();
            $addMediaPlaceholdersMigration->uninstall();
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
        
        public function hookDefineAcl($args) {
            $acl = $args['acl'];
            // Solve login redirect when viewing submembers or collection.json as public user
            $acl->allow(null, 'Collections', 'members');
            $acl->allow(null, 'Collections', 'collection');
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
            add_filter(array('ElementForm', 'Item', 'Item Type Metadata', 'On Canvas'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'Item Type Metadata', 'Selector'), 'filter_singular_form');
            add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'Original @id'), 'filter_singular_form');
            add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Original @id'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'Parent Collection'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Item', 'IIIF Item Metadata', 'UUID'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Original @id'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_singular_form');
            add_filter(array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'UUID'), 'filter_singular_form');
            add_filter(array('ElementInput', 'Item', 'Item Type Metadata', 'Selector'), 'filter_minimal_input');
            add_filter(array('ElementInput', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_minimal_input');
            add_filter(array('ElementInput', 'Item', 'IIIF Item Metadata', 'JSON Data'), 'filter_minimal_input');
            add_filter(array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'JSON Data'), 'filter_minimal_input');
            
            add_shortcode('mirador_file', array($this, 'shortcodeMiradorFile'));
            add_shortcode('mirador_items', array($this, 'shortcodeMiradorItems'));
            add_shortcode('mirador_collections', array($this, 'shortcodeMiradorCollections'));
            add_shortcode('mirador', array($this, 'shortcodeMirador'));
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
            if ($this->_isntIiifDisplayableFile($args['view']->file)) {
                return;
            }
            $iiifUrl = public_full_url(array('things' => 'files', 'id' => $args['view']->file->id), 'iiifitems_manifest');
            echo '<div class="element-set">';
            echo '<h2>IIIF File Information</h2><p>Manifest URL: <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'files', 'id' => $args['view']->file->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';
        }
        
        protected function _isntIiifDisplayableFile($file) {
            switch ($file->mime_type) {
                case 'image/jpeg': case 'image/png': case 'image/tiff': case 'image/jp2':
                    return false;
            }
            return true;
        }
        
        public function hookPublicItemsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            $item = $args['view']->item;
            if ($this->_isntIiifDisplayableItem($item)) {
                return;
            }
            $iiifUrl = absolute_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_manifest');
            echo '<h2>IIIF Manifest</h2><p>Manifest URL: <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(absolute_url(array('things' => 'items', 'id' => $args['view']->item->id), 'iiifitems_mirador')) . '"></iframe>';
        }
        
        public function hookAdminItemsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            $item = $args['view']->item;
            if ($this->_isntIiifDisplayableItem($item)) {
                return;
            }
            $iiifUrl = public_full_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_manifest');
            echo '<div class="element-set">';
            echo '<h2>IIIF Item Information</h2><p>Manifest URL: <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';
        }
        
        protected function _isntIiifDisplayableItem($item) {
            return ($item->fileCount() == 0 && !$item->hasElementText('IIIF Item Metadata', 'JSON Data')) || IiifItems_CanvasUtil::isNonIiifItem($item);
        }
        
        public function hookPublicCollectionsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            if ($this->_isntIiifDisplayableCollection($args['collection'])) {
                return;
            }
            switch (raw_iiif_metadata($args['view']->collection, 'iiifitems_collection_type_element')) {
                case 'None':
                    return;
                break;
                case 'Collection':
                    $iiifLabel = __('IIIF Collection');
                    $urlLabel = __('Collection URL');
                    $iiifUrl = absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_collection');
                break;
                case 'Manifest': default:
                    $iiifLabel = __('IIIF Manifest');
                    $urlLabel = __('Manifest URL');
                    $iiifUrl = absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
                break;
            }
            echo '<div class="element-set">';
            echo '<h2>' . $iiifLabel . '</h2><p>' . $urlLabel . ': <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(absolute_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';  
        }
        
        public function hookAdminCollectionsShow($args) {
            if (!isset($args['view'])) {
                $args['view'] = get_view();
            }
            if ($this->_isntIiifDisplayableCollection($args['collection'])) {
                return;
            }
            switch (raw_iiif_metadata($args['view']->collection, 'iiifitems_collection_type_element')) {
                case 'None':
                    return;
                break;
                case 'Collection':
                    $iiifLabel = __('IIIF Collection Information');
                    $urlLabel = __('Collection URL');
                    $iiifUrl = public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_collection');
                break;
                case 'Manifest': default:
                    $iiifLabel = __('IIIF Manifest Information');
                    $urlLabel = __('Manifest URL');
                    $iiifUrl = public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_manifest');
                break;
            }
            echo '<div class="element-set">';
            echo '<h2>' . $iiifLabel . '</h2><p>' . $urlLabel . ': <a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a></p>';
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'collections', 'id' => $args['view']->collection->id), 'iiifitems_mirador')) . '"></iframe>';
            echo '</div>';
        }
        
        protected function _isntIiifDisplayableCollection($collection) {
            return $collection->totalItems() == 0 && !$collection->hasElementText('IIIF Collection Metadata', 'JSON Data') && empty(IiifItems_CollectionUtil::findSubmembersFor($collection));
        }
        
        public function hookItemsBrowseSql($args) {
            $params = $args['params'];
            if (isset($params['controller']) && isset($params['action'])) {
                if (($params['controller'] == 'items') && ($params['action'] == 'index' || $params['action'] == 'browse') && !isset($params['search']) && !isset($params['tag']) && !isset($params['tags'])) {
                    $select = $args['select'];
                    $db = get_db();
                    $select->where("item_type_id != ? OR item_type_id IS NULL", get_option('iiifitems_annotation_item_type'));
                }
            }
        }
        
        public function hookCollectionsBrowseSql($args) {
            $params = $args['params'];
            if (isset($params['controller']) && isset($params['action'])) {
                $select = $args['select'];
                $select->joinLeft(array('element_textsA' => get_db()->ElementText), "element_textsA.element_id = " . get_option('iiifitems_collection_parent_element') . " AND element_textsA.record_type = 'Collection' AND element_textsA.record_id = collections.id", array('text'));
                $select->where("element_textsA.text IS NULL OR element_textsA.text = ''");
            }
        }
        
        public function hookAdminItemsBrowseSimpleEach($args) {
            if ($this->_isntIiifDisplayableItem($args['item'])) {
                return;
            }
            if ($args['item']->item_type_id != get_option('iiifitems_annotation_item_type')) {
                if ($uuid = raw_iiif_metadata($args['item'], 'iiifitems_item_uuid_element')) {
                    echo '<a href="' . html_escape(admin_url(array('things' => 'items', 'id' => $args['item']->id), 'iiifitems_annotate')) . '">Annotate</a>';
                    echo '<br />';
                    echo '<a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $uuid . '">List annotations</a>';
                }    
            } else {
                $on = raw_iiif_metadata($args['item'], 'iiifitems_annotation_on_element');
                if (($attachedItem = find_item_by_uuid($on)) || ($attachedItem = find_item_by_atid($on))) {
                    $text = 'Attached to: <a href="' . url(array('id' => $attachedItem->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">' . metadata($attachedItem, array('Dublin Core', 'Title')) . '</a>';
                    if ($attachedItem->collection_id !== null) {
                        $collection = get_record_by_id('Collection', $attachedItem->collection_id);
                        $collectionLink = url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id');
                        $collectionTitle = metadata($collection, array('Dublin Core', 'Title'));                                                                                                      
                        $text .= " (<a href=\"{$collectionLink}\">{$collectionTitle}</a>)";    
                    }
                    echo "<p>{$text}</p>";
                }
            }
        }

        public function hookAdminCollectionsBrowse($args) {
            $db = get_db();
            $itemsTable = $db->getTable('Item');
            $select = $itemsTable->getSelectForCount()->where('items.collection_id IS NULL AND items.item_type_id <> ?', array(get_option('iiifitems_annotation_item_type')));
            $totalItemsWithoutCollection = $db->fetchOne($select);
            if ($totalItemsWithoutCollection) {
                $withoutCollectionMessage = __(plural('%sOne item%s has no collection.', "%s%d items%s aren't in a collection.", $totalItemsWithoutCollection), '<a href="' . html_escape(url('items/browse?collection=0')) . '">', $totalItemsWithoutCollection, '</a>');
            } else {
                $withoutCollectionMessage = __('All items are in a collection.');
            }
            echo '<script>jQuery(document).ready(function() {'
                    . 'jQuery(".not-in-collections").html(' . js_escape($withoutCollectionMessage) . ');'
                    . 'jQuery(".iiifitems-replace-items-link").each(function() {'
                        . 'var _this = jQuery(this);'
                        . '_this.parent().parent().find("td:last a").attr("href", _this.data("newurl")).text(_this.data("newcount"));'
                        . '_this.remove();'
                    . '});'
                . '});</script>';
        }
        
        public function hookAdminCollectionsBrowseEach($args) {
            if ($this->_isntIiifDisplayableCollection($args['collection'])) {
                return;
            }
            if (raw_iiif_metadata($args['collection'], 'iiifitems_collection_type_element') == 'Collection') {
                if ($uuid = raw_iiif_metadata($args['collection'], 'iiifitems_collection_uuid_element')) {
                    $count = IiifItems_CollectionUtil::countSubmembersFor($args['collection']);
                    echo '<span class="iiifitems-replace-items-link" data-newcount="' . $count . '" data-newurl="' . admin_url(array('id' => $args['collection']->id), 'iiifitems_collection_members') . '"></span>';
                }    
            } else {
                echo '<a href="' . html_escape(admin_url(array('things' => 'collections', 'id' => $args['collection']->id), 'iiifitems_annotate')) . '">Annotate</a>';
            }
        }
        
        public function hookPublicCollectionsBrowseEach($args) {
            $collection = $args['collection'];
            if (IiifItems_CollectionUtil::isCollection($collection)) {
                echo '<p class="view-members-link"><a href="' . html_escape(public_url(array('id' => $collection->id), 'iiifitems_collection_members')) . '" data-hasmembers="' . $collection->id . '">View Submembers</a></p>';
            }
        }
        
        public function hookPublicCollectionsBrowse($args) {
            echo '<script>jQuery(document).ready(function() { jQuery("[data-hasmembers]").each(function() { jQuery(this).parent().parent().find(".view-items-link").remove(); }); });</script>';
        }
        
        public function hookBeforeSaveItem($args) {
            // Unset sensitive fields
            if (isset($args['post']['Elements'][get_option('iiifitems_item_uuid_element')])) {
                unset($args['post']['Elements'][get_option('iiifitems_item_uuid_element')]);
            }
            // Add UUID if it's new
            if ($args['insert']) {
                $args['record']->addTextForElement(get_record_by_id('Element', get_option('iiifitems_item_uuid_element')), generate_uuid());
            }
        }
        
        public function hookBeforeSaveCollection($args) {
            $uuidElementId = get_option('iiifitems_collection_uuid_element');
            $parentElementId = get_option('iiifitems_collection_parent_element');
            $uuidElement = get_record_by_id('Element', $uuidElementId);
            $parentElement = get_record_by_id('Element', $parentElementId);
            $record = $args['record'];
            // Unset sensitive fields
            if (isset($args['post']['Elements'][$uuidElementId])) {
                unset($args['post']['Elements'][$uuidElementId]);
            }
            // Add UUID if it's new
            if ($args['insert']) {
                $record->addTextForElement($uuidElement, generate_uuid());
            }
            // Check Parent Collection element text
            if (isset($args['post']['Elements'][$parentElementId])) {
                $parentUuid = $args['post']['Elements'][$parentElementId][0]['text'];
                if ($parentUuid) {
                    // Manifests can't be parents
                    $parent = find_collection_by_uuid($parentUuid);
                    if (raw_iiif_metadata($parent, 'iiifitems_collection_type_element') != 'Collection') {
                        $record->addError('Parent Collection', __('A collection can only have collection-type collections as its parent.'));
                    }
                    // Anti-loop check if is collection has a parent
                    $visitedUuids = array($record->getElementTextsByRecord($uuidElement)[0]->text => true);
                    $current = $parent;
                    while ($current) {
                        $currentUuid = raw_iiif_metadata($current, 'iiifitems_collection_uuid_element');
                        if (isset($visitedUuids[$currentUuid])) {
                            $record->addError('Parent Collection', __('A collection cannot have itself or a descendent as its parent.'));
                            break;
                        }
                        $visitedUuids[$currentUuid] = true;
                        $current = find_collection_by_uuid(raw_iiif_metadata($current, 'iiifitems_collection_parent_element'));
                    }
                }
            }
        }
        
        protected function _isCircularCollection($args) {
            if ($args['post']) {
                
            }
        }
        
        public function filterAdminNavigationMain($nav) {
            $nav[] = array(
                'label' => __('IIIF Items'),
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
        
        public function hookAdminItemsShowSidebar($args) {
            $item = $args['item'];
            if ($this->_isntIiifDisplayableItem($item)) {
                return;
            }
            if ($uuid = raw_iiif_metadata($item, 'iiifitems_item_uuid_element')) {
                if ($item->item_type_id != get_option('iiifitems_annotation_item_type')) {
                    $onCanvasMatches = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ?", array(
                        get_option('iiifitems_annotation_on_element'),
                        $uuid,
                    ));
                    echo '<div class="panel"><h4>Annotations</h4>'
                        . '<p>This item has '
                        . '<a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $uuid . '">'
                        . count($onCanvasMatches)
                        . '</a>'
                        . ' annotation(s).</p>'
                        // . '<a href="' . html_escape(admin_url(array('things' => 'items', 'id' => $item->id), 'iiifitems_annotate')) . '" class="big blue button">Annotate</a>'
                        . '</div>';
                    echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape(admin_url(array('things' => 'items', 'id' => $args['item']->id), 'iiifitems_annotate')) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
                    echo '<div class="panel"><h4>Repair</h4>'
                        . '<p>If this item is imported via IIIF Items and the files are '
                        . 'missing/corrupted, you can repair it below. All '
                        . 'files belonging to this item will be deleted and '
                        . 'then reloaded.</p>'
                        . '<form action="' . admin_url(array('id' => $item->id), 'iiifitems_repair_item') . '" method="POST">'
                        . '<input type="submit" value="Repair" class="big blue button" style="width:100%"/>'
                        . '</form>'
                        . '</div>';
                } else {
                    if ($onCanvasUuid = raw_iiif_metadata($item, 'iiifitems_annotation_on_element')) {
                        $onCanvasMatches = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ?", array(
                            get_option('iiifitems_annotation_on_element'),
                            $onCanvasUuid,
                        ));
                        $belongsTo = find_item_by_uuid($onCanvasUuid);
                        echo '<div class="panel"><h4>Annotations</h4>'
                            . '<p>This annotation is one of '
                            . '<a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $onCanvasUuid . '">'
                            . count($onCanvasMatches)
                            . '</a>'
                            . ' on the canvas "<a href="' . url(array('id' => $belongsTo->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">'
                            . metadata($belongsTo, array('Dublin Core', 'Title'))
                            . '</a>".</p>'
                            // . '<a href="' . html_escape(admin_url(array('things' => 'items', 'id' => $belongsTo->id), 'iiifitems_annotate')) . '" class="big blue button">Annotate</a>'
                            . '</div>';
                        echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape(admin_url(array('things' => 'items', 'id' => $belongsTo->id), 'iiifitems_annotate')) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
                    }
                }
            }
        }
        
        public function hookAdminCollectionsShowSidebar($args) {
            $collection = $args['collection'];
            if ($this->_isntIiifDisplayableCollection($collection)) {
                return;
            }
            $url = admin_url(array('things' => 'collections', 'id' => $collection->id), 'iiifitems_annotate');
            echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape($url) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
            echo '<div class="panel"><h4>Cache Management</h4>'
                . '<p>If the content shown in the viewer looks out of date, you can clear the cache to regenerate the manifest.</p>'
                . '<form action="' . admin_url(array(), 'iiifItemsCleanCache') . '" method="POST"><input type="hidden" name="type" value="Collection"><input type="hidden" name="id" value="' . $collection->id . '"><input type="submit" value="Clean" class="big blue button" style="width: 100%;"></form>'
                . '</div>';
        }
        
        public function hookBeforeDeleteItem($args) {
            $item = $args['record'];
            if ($item->item_type_id != get_option('iiifitems_annotation_item_type')) {
                Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('IiifItems_Job_RemoveSubannotations', array(
                    'item_uuid' => raw_iiif_metadata($item, 'iiifitems_item_uuid_element'),
                ));
            }
        }
        
        public function hookBeforeDeleteCollection($args) {
            $db = get_db();
            $collection = $args['record'];
            if (IiifItems_CollectionUtil::isCollection($collection) && $uuid = raw_iiif_metadata($collection, 'iiifitems_collection_uuid_element')) {
                $db->query("DELETE FROM `{$db->prefix}element_texts` WHERE element_id IN (?, ?) AND text = ?;", array(get_option('iiifitems_collection_parent_element'), get_option('iiifitems_manifest_parent_element'), $uuid));
            }
        }
        
        /* Annotation Type Metadata */
        
        public function displayForAnnotationOnCanvas($comps, $args) {
            $on = $args['element_text']->text;
            if (!($target = find_item_by_uuid($on))) {
                if (!($target = find_item_by_atid($on))) {
                    return $on;
                }
            }
            $link = url(array('id' => $target->id, 'controller' => 'items', 'action' => 'show'), 'id');
            $title = metadata($target, array('Dublin Core', 'Title'));
            $text = "<a href=\"{$link}\">{$title}</a> ({$on})";
            if ($target->collection_id !== null) {
                $collection = get_record_by_id('Collection', $target->collection_id);
                $collectionLink = url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id');
                $collectionTitle = metadata($collection, array('Dublin Core', 'Title'));
                $text .= "<p>From collection <a href=\"{$collectionLink}\">{$collectionTitle}</a></p>";
            }
            return $text;
        }
        
        public function inputForAnnotationOnCanvas($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            return filter_minimal_input($comps, $args);
        }
        
        /* File Metadata */
        
        public function inputForFileOriginalId($comps, $args) {
            $comps['input'] = $args['value'] ? $args['value'] : '';
            return filter_minimal_input($comps, $args);
        }
        
        /* Item Metadata */
        
        public function inputForItemDisplay($comps, $args) {
            $comps['input'] = get_view()->formRadio($args['input_name_stem'] . '[text]', $args['value'], array(), array('No', 'Yes'));
            return filter_minimal_input($comps, $args);
        }
        
        public function inputForItemOriginalId($comps, $args) {
            $comps['input'] = $args['value'] ? $args['value'] : '';
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
        
        public function inputForItemUuid($comps, $args) {
            $comps['input'] = $args['value'] ? $args['value'] : '&lt;TBD&gt;';
            return filter_minimal_input($comps, $args);
        }
        
        /* Collection metadata */
        
        public function inputForCollectionOriginalId($comps, $args) {
            $comps['input'] = $args['value'] ? $args['value'] : '';
            return filter_minimal_input($comps, $args);
        }
        
        public function inputForCollectionIiifType($comps, $args) {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), array(''=>'None','Manifest'=>'Manifest','Collection'=>'Collection'));
            return filter_minimal_input($comps, $args);
        }
        
        public function displayForCollectionParent($comps, $args) {
            $uuid = $args['element_text']->text;
            $collection = find_collection_by_uuid($uuid);
            if (!$collection) {
                return $uuid;
            }
            return '<a href="' . url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">' . metadata($collection, array('Dublin Core', 'Title')) . '</a> (' . $uuid. ')';
        }
        
        public function inputForCollectionParent($comps, $args) {
            $uuidOptions = IiifItems_CollectionOptions::getCollectionOptions();
            if (isset($_GET['parent']) && find_collection_by_uuid($_GET['parent'])) {
                $args['value'] = $_GET['parent'];
            }
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), $uuidOptions);
            return filter_minimal_input($comps, $args);
        }
        
        public function inputForCollectionUuid($comps, $args) {
            $comps['input'] = $args['value'] ? $args['value'] : '&lt;TBD&gt;';
            return filter_minimal_input($comps, $args);
        }
        
        /* Exhibit builder extension */
        
        public function filterExhibitLayouts($layouts) {
            $layouts['iiifitem'] = array(
                'name' => __('IIIF Items'),
                'description' => __('Embed a Mirador viewer for one or more items'),
            );
            $layouts['iiifmanifest'] = array(
                'name' => __('IIIF Manifests'),
                'description' => __('Embed a Mirador viewer for one or more manifests'),
            );
            return $layouts;
        }
        
        /* Simple Pages shortcodes */
        
        public function shortcodeMiradorFile($args, $view) {
            // Styles
            $styles = isset($args['style']) ? $args['style'] : '';
            // Single: View the file as-is
            if (isset($args['id'])) {
                $id = $args['id'];
                $file = get_record_by_id('File', $id);
                if ($file) {
                    return '<iframe src="' . public_full_url(array('things' => 'files', 'id' => $id), 'iiifitems_mirador') . '" style="width:100%;height:400px;' . $styles . '"></iframe>';
                }
            }
            // Fail
            return '';
        }
        
        public function shortcodeMiradorItems($args, $view) {
            // Styles
            $styles = isset($args['style']) ? $args['style'] : '';
            // Multiple: Rip arguments from existing [items] shortcode, for finding items
            if (isset($args['ids'])) {
                $params = array();
                if (isset($args['is_featured'])) {
                    $params['featured'] = $args['is_featured'];
                }
                if (isset($args['has_image'])) {
                    $params['hasImage'] = $args['has_image'];
                }
                if (isset($args['collection'])) {
                    $params['collection'] = $args['collection'];
                }
                if (isset($args['item_type'])) {
                    $params['item_type'] = $args['item_type'];
                }
                if (isset($args['tags'])) {
                    $params['tags'] = $args['tags'];
                }
                if (isset($args['user'])) {
                    $params['user'] = $args['user'];
                }
                if (isset($args['ids'])) {
                    $params['range'] = $args['ids'];
                }
                if (isset($args['sort'])) {
                    $params['sort_field'] = $args['sort'];
                }
                if (isset($args['order'])) {
                    $params['sort_dir'] = $args['order'];
                }
                if (isset($args['num'])) {
                    $limit = $args['num'];
                } else {
                    $limit = 10; 
                }
                $items = get_records('Item', $params, $limit);
                $item_ids = array();
                foreach ($items as $item) {
                    $item_ids[] = $item->id;
                }
                // Add iframe
                return '<iframe src="' . public_full_url(array(), 'iiifitems_exhibit_mirador', array('items' => join(',', $item_ids))) . '" style="width:100%;height:400px;' . $styles . '" allowfullscreen="true"></iframe>';
            }
            // Single: View quick-view manifest of the item
            if (isset($args['id'])) {
                $id = $args['id'];
                $item = get_record_by_id('Item', $id);
                if ($item) {
                    return '<iframe src="' . public_full_url(array('things' => 'items', 'id' => $id), 'iiifitems_mirador') . '" style="width:100%;height:400px;' . $styles . '" allowfullscreen="true"></iframe>';
                }
            }
            // Fail
            return '';
        }
        
        public function shortcodeMiradorCollections($args, $view) {
            // Styles
            $styles = isset($args['style']) ? $args['style'] : '';
            // Multiple: Rip arguments from existing [collections] shortcode, for finding collections
            if (isset($args['ids'])) {
                $params = array();
                if (isset($args['ids'])) {
                    $params['range'] = $args['ids'];
                }
                if (isset($args['sort'])) {
                    $params['sort_field'] = $args['sort'];
                }
                if (isset($args['order'])) {
                    $params['sort_dir'] = $args['order'];
                }
                if (isset($args['is_featured'])) {
                    $params['featured'] = $args['is_featured'];
                }
                if (isset($args['num'])) {
                    $limit = $args['num'];
                } else {
                    $limit = 10; 
                }
                $collections = get_records('Collection', $params, $limit);
                $collection_urls = array();
                foreach ($collections as $collection) {
                    $collection_urls[] = public_full_url(array('things' => 'collections', 'id' => $collection->id), 'iiifitems_manifest');
                }
                // Add iframe
                return '<iframe src="' . public_full_url(array(), 'iiifitems_exhibit_mirador', array('u' => $collection_urls)) . '" style="width:100%;height:400px;' . $styles . '" allowfullscreen="true"></iframe>';
            }
            // Single: View quick-view manifest of the collection
            if (isset($args['id'])) {
                $id = $args['id'];
                $collection = get_record_by_id('Collection', $id);
                if ($collection) {
                    return '<iframe src="' . public_full_url(array('things' => 'collections', 'id' => $id), 'iiifitems_mirador') . '" style="width:100%;height:400px;' . $styles . '" allowfullscreen="true"></iframe>';
                }
            }
            // Fail
            return '';
        }
        
        public function shortcodeMirador($args, $view) {
            // Styles
            $styles = isset($args['style']) ? $args['style'] : '';
            // Grab URL arguments
            $urls = isset($args['urls']) ? explode(';', $args['urls']) : array();
            // Add iframe
            return '<iframe src="' . public_full_url(array(), 'iiifitems_exhibit_mirador', array('u' => $urls)) . '" style="width:100%;height:400px;' . $styles . '" allowfullscreen="true"></iframe>';
        }
}
?>
