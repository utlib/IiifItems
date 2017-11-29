<?php

/**
 * Integrations for files.
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_Files extends IiifItems_BaseIntegration {
    protected $_hooks = array(
        'admin_files_show',
    );
    
    /**
     * Install metadata elements for files.
     */
    public function install() {
        $elementTable = get_db()->getTable('Element');
        // Add File type metadata elements
        $file_metadata = insert_element_set_failsafe(array(
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
    }
    
    /**
     * Remove metadata elements for files.
     */
    public function uninstall() {
        $elementSetTable = get_db()->getTable('ElementSet');
        // Remove File Metadata element set
        $elementSetTable->find(get_option('iiifitems_file_element_set'))->delete();
        delete_option('iiifitems_file_atid_element');
        delete_option('iiifitems_file_json_element');
        delete_option('iiifitems_file_element_set');
    }
    
    /**
     * Add cache expiry hooks and element handling filters.
     */
    public function initialize() {
        add_plugin_hook('after_save_file', 'hook_expire_cache');
        add_plugin_hook('after_delete_file', 'hook_expire_cache');
        add_filter(array('Display', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_hide_element_display');
        add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'Original @id'), 'filter_singular_form');
        add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_singular_form');
        add_filter(array('ElementInput', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_minimal_input');
        add_filter(array('ElementInput', 'File', 'IIIF File Metadata', 'Original @id'), array($this, 'inputForFileOriginalId'));
    }
    
    /**
     * Hook for displaying single files in the admin view.
     * Adds Mirador viewer and IIIF info for IIIF-displayable files.
     * 
     * @param array $args
     */
    public function hookAdminFilesShow($args) {
        if (!isset($args['view'])) {
            $args['view'] = get_view();
        }
        if ($this->_isntIiifDisplayableFile($args['view']->file)) {
            return;
        }
        $iiifUrl = public_full_url(array('things' => 'files', 'id' => $args['view']->file->id), 'iiifitems_manifest');
        echo '<div class="element-set">';
        echo '<h2>' . __("IIIF File Information") . '</h2><p>' . __("Manifest URL: %s", '<a href="' . html_escape($iiifUrl). '">' . html_escape($iiifUrl) . '</a>') . '</p>';
        if (get_option('iiifitems_show_mirador_files')) {
            echo '<iframe style="width:100%;height:600px;" allowfullscreen="true" src="' . html_escape(public_full_url(array('things' => 'files', 'id' => $args['view']->file->id), 'iiifitems_mirador')) . '"></iframe>';
        }
        echo '</div>';
    }

    /**
     * Returns whether the given file can be displayed with IIIF.
     * 
     * @param File $file
     * @return boolean
     */
    protected function _isntIiifDisplayableFile($file) {
        switch ($file->mime_type) {
            case 'image/jpeg': case 'image/png': case 'image/tiff': case 'image/jp2':
                return false;
        }
        return true;
    }
    
    /**
     * Element input filter for the file's original IIIF ID.
     * Make it single and read-only.
     * 
     * @param array $comps
     * @param array $args
     * @return string
     */
    public function inputForFileOriginalId($comps, $args) {
        $comps['input'] = $args['value'] ? $args['value'] : '';
        return filter_minimal_input($comps, $args);
    }
}
