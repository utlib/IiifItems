<?php

/**
 * @package IiifItems
 */

defined('IIIF_ITEMS_DIRECTORY') or define('IIIF_ITEMS_DIRECTORY', dirname(__FILE__));
require_once dirname(__FILE__) . '/helpers/IiifItemsFunctions.php';

class IiifItemsPlugin extends Omeka_Plugin_AbstractPlugin {

    protected $_hooks = array(
        'install',
        'uninstall',
        'upgrade',
        'initialize',
        'define_acl',
        'define_routes',
        'config_form',
        'config',
    );
    protected $_filters = array(
    );
    protected $_integrations = array(
        'ExhibitBuilder',
        'SimplePages',
        'Files',
        'Items',
        'Collections',
        'Annotations',
        'System',
    );

    public function hookInstall() {
        // Populate entire library (needed because the plugin isn't loaded)
        foreach (glob(__DIR__ . "/libraries/IiifItems/**/*.php") as $libUnit) {
            require_once $libUnit;
        }
        // Trigger integrations
        foreach ($this->_integrations as $integrationName) {
            $integrationClass = 'IiifItems_Integration_' . $integrationName;
            $integration = new $integrationClass();
            $integration->install();
        }
    }

    public function hookUninstall() {
        // Populate entire library (needed because the plugin isn't loaded)
        foreach (glob(__DIR__ . "/libraries/IiifItems/**/*.php") as $libUnit) {
            require_once $libUnit;
        }
        // Trigger integrations
        foreach ($this->_integrations as $integrationName) {
            $integrationClass = 'IiifItems_Integration_' . $integrationName;
            $integration = new $integrationClass();
            $integration->uninstall();
        }
    }

    public function hookUpgrade($args) {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];
        $doMigrate = false;

        $versions = array();
        foreach (glob(dirname(__FILE__) . '/libraries/IiifItems/Migration/*.php') as $migrationFile) {
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
        // Add integrations
        foreach ($this->_integrations as $integrationName) {
            $integrationClass = 'IiifItems_Integration_' . $integrationName;
            $integration = new $integrationClass();
            $integration->integrate();
        }
    }
    
    /**
     * Hook for adding ACL entries.
     * Allow public users to traverse the collection tree and the top-level collection.
     * 
     * @param array $args
     */
    public function hookDefineAcl($args) {
        $acl = $args['acl'];
        // Solve login redirect when viewing submembers or collection.json as public user
        $acl->allow(null, 'Collections', 'members');
        $acl->allow(null, 'Collections', 'collection');
        $acl->allow(null, 'Collections', 'explorer');
        $acl->allow(null, 'Collections', 'tree-ajax');
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
        set_option('iiifitems_mirador_css', ltrim($data['iiifitems_mirador_css'], '/'));
        set_option('iiifitems_mirador_js', ltrim($data['iiifitems_mirador_js'], '/'));
    }

}
