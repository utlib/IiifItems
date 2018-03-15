<?php

/**
 * Utilities for IIIF manifests.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_Manifest extends IiifItems_IiifUtil {
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
     * @param boolean $bare Whether to exclude the annotation list references
     * @return array
     */
    public static function buildManifest($collection, $bare=false) {
        // Set default IDs and titles
        $atId = public_full_url(array('things' => 'collections', 'id' => $collection->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $seqId = public_full_url(array('things' => 'collections', 'id' => $collection->id, 'typeext' => 'sequence.json'), 'iiifitems_oa_uri');
        $label = metadata($collection, array('Dublin Core', 'Title'), array('no_escape' => true));
        // Do it only for manifests with appropriate authorization
        if (self::isManifest($collection)) {
            // Decide which cache entry to consider
            $cacheEntryName = $bare ? 'private_bare_manifest' : (
                current_user() ? 'private_manifest' : 'public_manifest'
            );
            if ($json = get_cached_iiifitems_value_for($collection, $cacheEntryName)) {
                return $json;
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
            cache_iiifitems_value_for($collection, $json, $cacheEntryName);
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
                IiifItems_Util_Canvas::buildAnnotationCanvas($item)
            ));
        }
        // Otherwise, use the standard item-to-canvas utility
        else {
            $json = self::blankTemplate($atId, $seqId, $label, array(
                IiifItems_Util_Canvas::buildCanvas($item)
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
            IiifItems_Util_Canvas::fileCanvasJson($file)
        ));
        // Override DC metadata
        parent::addDublinCoreMetadata($json, $file);
        // Done
        return $json;
    }
    
    /**
     * Return the IIIF Presentation API manifest representation of the exhibit block's attached items
     * @param ExhibitPageBlock $block
     * @return array
     */
    public static function buildExhibitPageBlockManifest($block) {
        // Set default IDs and titles
        $atId = public_full_url(array('things' => 'exhibit_page_blocks', 'id' => $block->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $seqId = public_full_url(array('things' => 'exhibit_page_blocks', 'id' => $block->id, 'typeext' => 'sequence.json'), 'iiifitems_oa_uri');
        $label = $block->getPage()->title;
        // Find attached items in order
        $canvases = array();
        foreach ($block->getAttachments() as $attachment) {
            if ($item = $attachment->getItem()) {
                // If it is an annotation, use the special annotation canvas utility
                if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
                    $canvases[] = IiifItems_Util_Canvas::buildAnnotationCanvas($item);
                }
                // Otherwise, use the standard item-to-canvas utility
                else {
                    $canvases[] = IiifItems_Util_Canvas::buildCanvas($item);
                }
            }
        }
        // Generate from template
        $json = self::blankTemplate($atId, $seqId, $label, $canvases);
        if ($block->text) {
            $json['description'] = $block->text;
        }
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
            if (raw_iiif_metadata($item, 'iiifitems_item_display_element') != 'Never') {
                $canvases[] = IiifItems_Util_Canvas::buildCanvas($item);
            }
        }
        return $canvases;
    }
    
    /**
     * Return the number of annotations among items within this manifest.
     * @param Collection $manifest
     * @return integer
     */
    public static function countAnnotationsFor($manifest) {
        $db = get_db();
        $itemsTable = $db->getTable('Item');
        $itemsSelect = $itemsTable->getSelectForCount();
        $annotationTypeId = get_option('iiifitems_annotation_item_type');
        $itemsSelect->where('items.item_type_id = ?', array($annotationTypeId));
        $uuidElementId = get_option('iiifitems_item_uuid_element');
        $onCanvasElementId = get_option('iiifitems_annotation_on_element');
        $itemsSelect->joinLeft(array('element_texts' => $db->ElementText), "element_texts.record_id = items.id AND element_texts.element_id = {$onCanvasElementId} AND element_texts.record_type = 'Item'", array('text'));
        $itemsSelect->join(array('element_texts2' => $db->ElementText), "element_texts.text = element_texts2.text AND element_texts2.element_id = {$uuidElementId} AND element_texts2.record_type = 'Item'", array('text'));
        $itemsSelect->join(array('items2' => $db->Item), "element_texts2.record_type = 'Item' AND element_texts2.record_id = items2.id");
        $itemsSelect->where('items2.collection_id = ?', $manifest->id);
        return $db->fetchOne($itemsSelect);
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
                return $iiifTypeText != 'Collection' && $iiifTypeText != 'Hidden';
            }
        } catch (Exception $ex) {
        }
        return true;
    }
}