<?php

/**
 * Integration for Simple Pages shortcodes.
 * @package IiifItems
 * @subpackage Integration
 */
class IiifItems_Integration_SimplePages extends IiifItems_BaseIntegration {
    
    /**
     * Register the shortcodes.
     */
    public function initialize() {
        add_shortcode('mirador_file', array($this, 'shortcodeMiradorFile'));
        add_shortcode('mirador_items', array($this, 'shortcodeMiradorItems'));
        add_shortcode('mirador_collections', array($this, 'shortcodeMiradorCollections'));
        add_shortcode('mirador', array($this, 'shortcodeMirador'));
    }
    
    /**
     * Renders the mirador_file shortcode as a single-file Mirador viewer.
     * Supports the same arguments as the standard file shortcode, plus a "style" argument for the embedding iframe's CSS.
     * 
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
    public function shortcodeMiradorFile($args, $view) {
        // Styles
        $styles = isset($args['style']) ? $args['style'] : '';
        // Single: View the file as-is
        if (isset($args['id'])) {
            $id = $args['id'];
            $file = get_record_by_id('File', $id);
            if ($file) {
                return '<iframe src="' . public_full_url(array('things' => 'files', 'id' => $id), 'iiifitems_mirador') . '" style="width:100%;height:480px;' . $styles . '"></iframe>';
            }
        }
        // Fail
        return '';
    }

    
    /**
     * Renders the mirador_items shortcode as a Mirador viewer.
     * Supports the same arguments as the standard items shortcode, plus a "style" argument for the embedding iframe's CSS.
     * 
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
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
            return '<iframe src="' . public_full_url(array(), 'iiifitems_exhibit_mirador', array('items' => join(',', $item_ids))) . '" style="width:100%;height:480px;' . $styles . '" allowfullscreen="true"></iframe>';
        }
        // Single: View quick-view manifest of the item
        if (isset($args['id'])) {
            $id = $args['id'];
            $item = get_record_by_id('Item', $id);
            if ($item) {
                return '<iframe src="' . public_full_url(array('things' => 'items', 'id' => $id), 'iiifitems_mirador') . '" style="width:100%;height:480px;' . $styles . '" allowfullscreen="true"></iframe>';
            }
        }
        // Fail
        return '';
    }

    /**
     * Renders the mirador_collection shortcode as a Mirador viewer.
     * Supports the same arguments as the standard collections shortcode, plus a "style" argument for the embedding iframe's CSS.
     * 
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
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
            $manifest_urls = array();
            $collection_urls = array();
            foreach ($collections as $collection) {
                if (IiifItems_Util_Collection::isCollection($collection)) {
                    $collection_urls[] = public_full_url(array('things' => 'collections', 'id' => $collection->id), 'iiifitems_collection');
                } else {
                    $manifest_urls[] = public_full_url(array('things' => 'collections', 'id' => $collection->id), 'iiifitems_manifest');
                }
            }
            $popup = isset($args['popup']) || (empty($manifest_urls) && !empty($collection_urls));
            // Add iframe
            return '<iframe src="' . public_full_url(array(), 'iiifitems_exhibit_mirador', array('u' => $manifest_urls, 'c' => $collection_urls, 'p' => $popup)) . '" style="width:100%;height:480px;' . $styles . '" allowfullscreen="true"></iframe>';
        }
        // Single: View quick-view manifest of the collection
        if (isset($args['id'])) {
            $id = $args['id'];
            $collection = get_record_by_id('Collection', $id);
            if ($collection) {
                return '<iframe src="' . public_full_url(array('things' => 'collections', 'id' => $id), 'iiifitems_mirador') . '" style="width:100%;height:480px;' . $styles . '" allowfullscreen="true"></iframe>';
            }
        }
        // Fail
        return '';
    }

    /**
     * Renders a Mirador viewer with arbitrary manifest URLs.
     * The "urls" argument accepts a semicolon-delimited list of manifest URLs.
     * The "style" argument adds styles to the embedding iframe's CSS.
     * 
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
    public function shortcodeMirador($args, $view) {
        // Styles
        $styles = isset($args['style']) ? $args['style'] : '';
        // Grab URL arguments
        $urls = isset($args['urls']) ? explode(';', $args['urls']) : array();
        $collections = isset($args['collections']) ? explode(';', $args['collections']) : array();
        $popup = isset($args['popup']) || (empty($urls) && !empty($collections));
        // Add iframe
        return '<iframe src="' . public_full_url(array(), 'iiifitems_exhibit_mirador', array('u' => $urls, 'c' => $collections, 'p' => $popup)) . '" style="width:100%;height:480px;' . $styles . '" allowfullscreen="true"></iframe>';
    }
}
