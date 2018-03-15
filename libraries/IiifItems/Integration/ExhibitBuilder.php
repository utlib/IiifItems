<?php

/**
 * Mixins for integration with Exhibit Builder
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_ExhibitBuilder extends IiifItems_BaseIntegration {
    protected $_filters = array(
        'exhibit_layouts'
    );
    
    /**
     * Registers IIIF Toolkit exhibit block types.
     * 
     * @param array $layouts
     * @return array
     */
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
