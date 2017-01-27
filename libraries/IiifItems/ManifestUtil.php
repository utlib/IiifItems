<?php
class IiifItems_ManifestUtil extends IiifItems_IiifUtil {
    /**
     * Basic template for IIIF Presentation API manifest
     * @param string $atId The unique URI ID for this manifest
     * @param string $seqId The sequence ID for the main sequence
     * @param string $label The title of this manifest
     * @param array $canvases (optional) An array of IIIF Presentation API canvases
     * @return array
     */
    public static function blankTemplate($atId, $seqId, $label, $canvases=array()) {
        return array(
            '@context' => 'http://www.shared-canvas.org/ns/context.json',
            '@id' => $atId,
            '@type' => 'sc:Manifest',
            'label' => $label,
            'sequences' => array(array(
                '@id' => $seqId,
                '@type' => 'sc:Sequence',
                'label' => '',
                'canvases' => $canvases,
            )),
        );
    }
    
    /**
     * Bare minimum template for a manifest, for embedding in a collection listing
     * @param string $atId The unique URI ID for this manifest
     * @param string $label The title of this manifest
     * @return array
     */
    public static function bareTemplate($atId, $label) {
        return array(
            '@id' => $atId,
            '@type' => 'sc:Manifest',
            'label' => $label,
        );
    }

    /**
     * Return the IIIF Presentation API manifest representation of the Omeka collection
     * @param Collection $collection
     * @return array
     */
    public static function buildManifest($collection) {
        // Set default IDs and titles
        $atId = public_full_url(array('things' => 'collections', 'id' => $collection->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $seqId = public_full_url(array('things' => 'collections', 'id' => $collection->id, 'typeext' => 'sequence.json'), 'iiifitems_oa_uri');
        $label = metadata($collection, array('Dublin Core', 'Title'), array('no_escape' => true));
        // Do it only for manifests
        if (self::isManifest($collection)) {
            // Admin-side: Try to find cached admin-side manifest for the annotator
            if (is_admin_theme()) {
                if ($json = get_cached_iiifitems_value_for($collection, 'admin_manifest')) {
                    return $json;
                }
            }
            // Public-side: Try to find cached public manifest for the viewer
            else {
                if ($json = get_cached_iiifitems_value_for($collection)) {
                    return $json;
                }
            }
            // Try to find template; if it does not already exist, use the blank template
            if (!($json = parent::fetchJsonData($collection))) {
                $json = self::blankTemplate($atId, $seqId, $label);
            }
            // Override the IDs, titles and DC metadata
            $json['@id'] = $atId;
            $json['sequences'][0]['@id'] = $seqId;
            $json['sequences'][0]['canvases'] = self::findCanvasesFor($collection);
            parent::addDublinCoreMetadata($json, $collection);
            // Cache accordingly
            if (is_admin_theme()) {
                cache_iiifitems_value_for($collection, $json, 'admin_manifest');
            } else {
                cache_iiifitems_value_for($collection, $json);
            }
            // Done
            return $json;
        }
        return self::blankTemplate($atId, $seqId, $label);
    }

    /**
     * Return the IIIF Presentation API manifest representation of the Omeka Item
     * @param Item $item
     * @return array
     */
    public static function buildItemManifest($item) {
        // Set default IDs and titles
        $atId = public_full_url(array('things' => 'items', 'id' => $item->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $seqId = public_full_url(array('things' => 'items', 'id' => $item->id, 'typeext' => 'sequence.json'), 'iiifitems_oa_uri');
        $label = metadata($item, array('Dublin Core', 'Title'), array('no_escape' => true));
        // If it is an annotation, use the special annotation canvas utility
        if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
            $json = self::blankTemplate($atId, $seqId, $label, array(
                IiifItems_CanvasUtil::buildAnnotationCanvas($item)
            ));
        }
        // Otherwise, use the standard item-to-canvas utility
        else {
            $json = self::blankTemplate($atId, $seqId, $label, array(
                IiifItems_CanvasUtil::buildCanvas($item)
            ));
        }
        // Override DC metadata
        parent::addDublinCoreMetadata($json, $item);
        if ($item->collection_id !== null) {
            $json['label'] = metadata(get_record_by_id('Collection', $item->collection_id), array('Dublin Core', 'Title'), array('no_escape' => true));
        }
        // Done
        return $json;
    }

    /**
     * Return the IIIF Presentation API manifest representation of the Omeka File
     * @param File $file
     * @return array
     */
    public static function buildFileManifest($file) {
        // Set default IDs and titles
        $atId = public_full_url(array('things' => 'files', 'id' => $file->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $seqId = public_full_url(array('things' => 'files', 'id' => $file->id, 'typeext' => 'sequence.json'), 'iiifitems_oa_uri');
        $label = metadata($file, 'display_title', array('no_escape' => true));
        // Use standard file-to-canvas utility
        $json = self::blankTemplate($atId, $seqId, $label, array(
            IiifItems_CanvasUtil::fileCanvasJson($file)
        ));
        // Override DC metadata
        parent::addDublinCoreMetadata($json, $file);
        // Done
        return $json;
    }

    /**
     * Return the parent collection of this collection (Collection or Manifest type)
     * @param Collection $collection
     * @return Collection|null
     */
    public static function findParentFor($collection) {
        $parentUuid = raw_iiif_metadata($collection, 'iiifitems_collection_parent_element');
        if (!$parentUuid) {
            return null;
        }
        return find_collection_by_uuid($parentUuid);
    }

    /**
     * Return a list of canvases for this collection
     * @param Collection $collection
     * @return array
     */
    public static function findCanvasesFor($collection) {
        $canvases = array();
        foreach (get_db()->getTable('Item')->findBy(array('collection' => $collection)) as $item) {
            $canvases[] = IiifItems_CanvasUtil::buildCanvas($item);
        }
        return $canvases;
    }
    
    /**
     * Return whether this collection is set to the Manifest type
     * @param Collection $collection
     * @return boolean
     */
    public static function isManifest($collection) {
        try {
            $iiifMetadataSlug = 'iiifitems_collection_type_element';
            $iiifTypeText = raw_iiif_metadata($collection, $iiifMetadataSlug);
            if ($iiifTypeText) {
                return $iiifTypeText == 'Manifest';
            }
        } catch (Exception $ex) {
        }
        return true;
    }
}