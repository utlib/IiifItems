<?php

class IiifItems_Integration_ExhibitBuilder extends IiifItems_BaseIntegration {
    protected $_filters = array(
        'exhibit_layouts'
    );
    
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
}
