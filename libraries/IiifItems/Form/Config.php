<?php

/**
 * The main plugin configuration form.
 */
class IiifItems_Form_Config extends Omeka_Form {
    /**
     * Sets up elements for this form.
     */
    public function init() {
        // Top-level parent
        parent::init();
        $this->applyOmekaStyles();
        $this->setAutoApplyOmekaStyles(false);
        // Enable automated HTTP-IIIF Bridge
        $this->addElement('text', 'iiifitems_bridge_prefix', array(
            'label' => __('IIIF Prefix'),
            'description' => __('The URL root of the HTTP-resolved IIIF server referencing this Omeka installation. Use {FILENAME} for a file name without the extension, {EXTENSION} for the file extension alone and {FULLNAME} for a file name with the extension.'),
            'value' => get_option('iiifitems_bridge_prefix'),
        ));
        // Mirador
        $this->addElement('text', 'iiifitems_mirador_path', array(
            'label' => __('Mirador Path'),
            'description' => __('URL to the directory holding the main mirador.js and supporting files.'),
            'value' => get_option('iiifitems_mirador_path'),
        ));
    }
}