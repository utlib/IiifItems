<?php

// TODO: Validation 
//    - Type must be filled
//    - If From File is checked, items_import_source_file must be filled
//    - If From URL is filled, the URL must be correctly formatted and filled
//    - If From JSON is filled, the JSON must be correctly formatted and filled
//    - Import Depth must be selected
//    - If Import Depth is Link-Only and it is from File or JSON, @id must be given in the JSON data
//    - Local Preview Size must be selected

/**
 * The IIIF import form.
 * @package IiifItems
 * @subpackage Form
 */
class IiifItems_Form_Import extends Omeka_Form {
    /**
     * Sets up elements for this form.
     */
    public function init() {
        // Form top-level
        parent::init();
        $this->setAttrib('id', 'iiif-import');
        $this->setMethod('post');
        $this->applyOmekaStyles();
        $this->setAutoApplyOmekaStyles(false);
        // Type (Collection/Manifest/Image-Canvas)
        $this->addElement('radio', 'items_import_type', array(
            'label' => __('Type'),
            'multiOptions' => array('Collection', 'Manifest', 'Canvas'),
        ));
        // Source
        $this->addElement('radio', 'items_import_source', array(
            'label' => __('Source'),
            'multiOptions' => array('From File', 'From URL', 'From Paste'),
        ));
        $this->addElement('file', 'items_import_source_file', array(
            'label' => __('File'),
        ));
        $this->addElement('text', 'items_import_source_url', array(
            'label' => __('URL'),
        ));
        $this->addElement('textarea', 'items_import_source_json', array(
            'label' => __('JSON Data'),
        ));
        // Parent
        $this->addElement('select', 'items_import_to_parent', array(
            'label' => __('Parent'),
            'multiOptions' => IiifItems_Util_CollectionOptions::getFullOptions(null, (current_user()->role == 'contributor') ? current_user() : null),
        ));
        // Set to Public?
        $this->addElement('checkbox', 'items_are_public', array(
            'label' => __('Set as Public?'),
            'options' => array(
                'use_hidden_element' => false,
            ),
        ));
        // Set as Featured?
        $this->addElement('checkbox', 'items_are_featured', array(
            'label' => __('Set as Featured?'),
            'options' => array(
                'use_hidden_element' => false,
            ),
        ));
        // Import backwards?
        $this->addElement('checkbox', 'items_are_reversed', array(
            'label' => __('Import in Reverse?'),
            'description' => __('Select this if you have enabled any plugins or configurations that cause items to be listed latest-first.'),
            'options' => array(
                'use_hidden_element' => false,
            ),
        ));
        // Local Preview Size 
        $this->addElement('radio', 'items_preview_size', array(
            'label' => __('Local Preview Size'),
            'multiOptions' => array('None', '96x96', '512x512', 'Maximum'),
        ));
        // Annotation Preview Size
        $this->addElement('radio', 'items_annotation_size', array(
            'label' => __('Annotation Preview Size'),
            'multiOptions' => array('None', '96x96', '512x512', 'Maximum'),
        ));
        // Submit button
        $submit = $this->createElement('submit', 'submit', array(
            'label' => __('Import'),
            'class' => 'submit submit-medium',
        ));
        $submit->setDecorators(array(
            'ViewHelper', array(
                'HtmlTag', array(
                    'tag' => 'div', 
                    'class' => 'field'
                )
            )
        ));
        $this->addElement($submit);
    }
}
