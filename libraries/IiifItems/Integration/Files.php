<?php

class IiifItems_Integration_Files extends IiifItems_BaseIntegration {
    protected $_hooks = array(
        'admin_files_show',
    );
    
    public function initialize() {
        add_plugin_hook('after_save_file', 'hook_expire_cache');
        add_plugin_hook('after_delete_file', 'hook_expire_cache');
        add_filter(array('Display', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_hide_element_display');
        add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'Original @id'), 'filter_singular_form');
        add_filter(array('ElementForm', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_singular_form');
        add_filter(array('ElementInput', 'File', 'IIIF File Metadata', 'JSON Data'), 'filter_minimal_input');
        add_filter(array('ElementInput', 'File', 'IIIF File Metadata', 'Original @id'), array($this, 'inputForFileOriginalId'));
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
    
    public function inputForFileOriginalId($comps, $args) {
        $comps['input'] = $args['value'] ? $args['value'] : '';
        return filter_minimal_input($comps, $args);
    }
}
