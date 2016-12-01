<?php
class IiifItems_ManifestController extends IiifItems_BaseController {
    protected static $allowedThings = array('Collection', 'Item', 'File');
    
    public function manifestAction() {
        //Get and check the thing's existence
        $id = $this->getParam('id');
        $type = $this->getParam('things');
        $class = Inflector::titleize(Inflector::singularize($type));
        $thing = get_record_by_id($class, $id);
        if (empty($thing) || !in_array($class, self::$allowedThings)) {
            throw new Omeka_Controller_Exception_404;
        }

        //Respond with JSON
        try {
            $methodName = '__' . $type . 'ManifestJson';
            $jsonData = $this->$methodName($thing);
            $this->__respondWithJson($jsonData);
        } catch (Exception $e) {
            $this->__respondWithJson(array(
                'message' => $e->getMessage()
            ), 500);
        }
    }
    
    private function __filesManifestJson($file) {
        $atId = public_full_url(array(
            'things' => 'files',
            'id' => $file->id,
            'typeext' => 'manifest.json',
        ), 'iiifitems_oa_uri');
        $seqId = public_full_url(array(
            'things' => 'files',
            'id' => $file->id,
            'typeext' => 'sequence.json',
        ), 'iiifitems_oa_uri');
        $label = metadata($file, 'display_title');
        $json = $this->__manifestTemplate($atId, $seqId, $label, array(
            $this->__fileCanvasJson($file)
        ));
        $this->__addDublinCoreMetadata($json, $file);
        return $json;
    }
        
    private function __itemsManifestJson($item) {
        $atId = public_full_url(array(
            'things' => 'items',
            'id' => $item->id,
            'typeext' => 'manifest.json',
        ), 'iiifitems_oa_uri');
        $seqId = public_full_url(array(
            'things' => 'items',
            'id' => $item->id,
            'typeext' => 'sequence.json',
        ), 'iiifitems_oa_uri');
        $label = metadata($item, array('Dublin Core', 'Title'));
        if ("{$item->item_type_id}" === get_option('iiifitems_annotation_item_type')) {
            $canvasId = raw_iiif_metadata($item, 'iiifitems_annotation_on_element');
            if (strpos($canvasId, '#xywh=') !== false) {
                $canvasId = strstr($canvasId, '#xywh=', true);
            }
            $theItemCanvasIdText = get_db()->getTable('ElementText')->findBySql("((element_texts.element_id = ? AND element_texts.text = ?) OR (element_texts.element_id = ? AND element_texts.text = ?)) AND element_texts.record_type = 'Item' ", array(
                get_option('iiifitems_item_atid_element'),
                $canvasId,
                get_option('iiifitems_item_uuid_element'),
                $canvasId,
            ))[0];
            $theItem = get_record_by_id('Item', $theItemCanvasIdText->record_id);
            $itemCanvas = $this->__itemCanvasJson($theItem);
            if (!is_admin_theme()) {
                $itemCanvas['otherContent'] = array(array(
                    '@id' => public_full_url(array(
                        'things' => 'items',
                        'id' => $item->id,
                        'typeext' => 'annolist.json',
                    ), 'iiifitems_oa_uri'),
                    '@type' => 'sc:AnnotationList',
                ));
            }
        } else {
            $theItem = $item;
            $itemCanvas = $this->__itemCanvasJson($item);
        }
        $json = $this->__manifestTemplate($atId, $seqId, $label, array(
            $itemCanvas,
        ));
        $this->__addDublinCoreMetadata($json, $theItem);
        return $json;
    }

    private function __collectionsManifestJson($collection) {
        $atId = public_full_url(array(
            'things' => 'collections',
            'id' => $collection->id,
            'typeext' => 'manifest.json',
        ), 'iiifitems_oa_uri');
        $seqId = public_full_url(array(
            'things' => 'collections',
            'id' => $collection->id,
            'typeext' => 'sequence.json',
        ), 'iiifitems_oa_uri');
        $label = metadata($collection, array('Dublin Core', 'Title'));
        if ($this->__getCollectionIiifType($collection) == 'Manifest') {
            if (!is_admin_theme()) {
                if ($json = get_cached_iiifitems_value_for($collection)) {
                    return $json;
                }
            }
            if (!($json = $this->__fetchJsonData($collection))) {
                $json = $this->__manifestTemplate($atId, $seqId, $label);
            }
            $json['@id'] = $atId;
            $json['sequences'][0]['@id'] = $seqId;
            $json['sequences'][0]['canvases'] = array();
            foreach ($this->_helper->db->getTable('Item')->findBy(array('collection' => $collection)) as $item) {
                $json['sequences'][0]['canvases'][] = $this->__itemCanvasJson($item);
            }
            $this->__addDublinCoreMetadata($json, $collection);
            if (!is_admin_theme()) {
                cache_iiifitems_value_for($collection, $json);
            }
            return $json;
        }
        return $this->__manifestTemplate($atId, $seqId, $label);
    }

    private function __manifestTemplate($atId, $seqId, $label, $canvases=array()) {
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
    
    private function __canvasTemplate($atId, $width, $height, $label, $images=array()) {
        return array(
            '@id' => $atId,
            'label' => $label,
            '@type' => 'sc:Canvas',
            'width' => $width,
            'height' => $height,
            'images' => $images,
        );
    }
    
    private function __fileCanvasJson($file, $canvasId=null, $applyDublin=false) {
        // Default IDs and display titles
        if (!$canvasId) {
            $canvasId = public_full_url(array(
                'things' => 'files',
                'id' => $file->id,
                'typeext' => 'canvas.json',
            ), 'iiifitems_oa_uri');
        }
        $displayTitle = metadata($file, 'display_title');
        // Get the file's Image JSON and force its ID to re-point here
        $fileImageJson = $this->__fileImageJson($file, $canvasId, true);
        // Create IIIF Presentation 2.0 top-level template
        $json = $this->__canvasTemplate($canvasId, $fileImageJson['resource']['width'], $fileImageJson['resource']['height'], $displayTitle, array($fileImageJson));
        // Apply Dublin Core metadata
        if ($applyDublin) {
            $this->__addDublinCoreMetadata($json, $file);
        }
        return $json;
    }
    
    private function __fileImageJson($file, $on, $force=false) {
        // Try to build JSON from imported IIIF metadata
        try {
            if ($iiifJsonData = $this->__fetchJsonData($file)) {
                if ($force || !isset($iiifJsonData['on'])) {
                    $iiifJsonData['on'] = $on;
                }
                return $iiifJsonData;
            }
        }
        catch (Exception $e) {
        }
        // If missing or failed, build from file data
        list($fileWidth, $fileHeight) = getimagesize(FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath());
        $iiifRoot = get_option('iiifitems_bridge_prefix');
        $fileJson = array(
            '@id' => public_full_url(array(
                'things' => 'files',
                'id' => $file->id,
                'typeext' => 'anno.json',
            ), 'iiifitems_oa_uri'),
            'motivation' => 'sc:painting',
            '@type' => 'oa:Annotation',
            'resource' => array(
                '@id' => $file->getWebPath('original'),
                '@type' => 'dctypes:Image',
                'format' => $file->mime_type,
                'width' => $fileWidth,
                'height' => $fileHeight,
                'service' => array(
                    '@id' => $iiifRoot . '/' . $file->filename,
                    '@context' => 'http://iiif.io/api/image/2/context.json',
                    'profile' => 'http://iiif.io/api/image/2/level2.json',
                ),
            ),
            'on' => $on,
        );
        return $fileJson;
    }
    
    private function __itemCanvasJson($item, $canvasId=null, $applyDublin=true) {
        // Fetch the canvas for the given item from IIIF metadata
        try {
            if ($iiifJsonData = $this->__fetchJsonData($item)) {
                $canvasId = $iiifJsonData['@id'];
            }
        } catch (Exception $e) {
        }
        // If unavailable or corrupted, generate a default
        if (!$canvasId) {
            $canvasId = public_full_url(array(
                'things' => 'items',
                'id' => $item->id,
                'typeext' => 'canvas.json',
            ), 'iiifitems_oa_uri');
        }
        if (!isset($iiifJsonData)) {
            $iiifJsonData = $this->__canvasTemplate($canvasId, 0, 0, metadata($item, array('Dublin Core', 'Title')));
        }
        // Add the Image for each file in order
        $itemFiles = $item->getFiles();
        if (!empty($itemFiles)) {
            if (!isset($iiifJsonData['images']) || empty($iiifJsonData['images']) || $this->__fetchJsonData($itemFiles[0])) {
                $iiifJsonData['images'] = array();
                foreach ($itemFiles as $file) {
                    $iiifJsonData['images'][] = $this->__fileImageJson($file, $canvasId);
                }
            }
            // If the default canvas template is used, set the width and height to the max among files
            if (!$iiifJsonData['width'] && !$iiifJsonData['height']) {
                foreach ($iiifJsonData['images'] as $fileJson) {
                    if ($fileJson['resource']['width'] > $iiifJsonData['width']) {
                        $iiifJsonData['width'] = $fileJson['resource']['width'];
                    }
                    if ($fileJson['resource']['height'] > $iiifJsonData['height']) {
                        $iiifJsonData['height'] = $fileJson['resource']['height'];
                    }
                }
            }
        }
        // Plug DC metadata
        if ($applyDublin) {
            $this->__addDublinCoreMetadata($iiifJsonData, $item);
        }
        // Plug otherContent for annotation lists
        if (is_admin_theme()) {
            $iiifJsonData['otherContent'] = array();
        } else {
            $iiifJsonData['otherContent'] = array(array(
                '@id' => public_full_url(array(
                    'things' => 'items',
                    'id' => $item->id,
                    'typeext' => 'annolist.json',
                ), 'iiifitems_oa_uri'),
                '@type' => 'sc:AnnotationList',
            ));
        }
        // Done
        return $iiifJsonData;
    }
    
    private function __addDublinCoreMetadata(&$jsonData, $record) {
        $elements = all_element_texts($record, array(
            'return_type' => 'array',
            'show_element_set_headings' => true,
        ));
        if (isset($elements['Dublin Core'])) {
            if (isset($elements['Dublin Core']['Title'])) {
                $jsonData['label'] = join($elements['Dublin Core']['Title'], '<br>');
                unset($elements['Dublin Core']['Title']);
            }
            if (isset($elements['Dublin Core']['Description'])) {
                $jsonData['description'] = join($elements['Dublin Core']['Description'], '<br>');
                unset($elements['Dublin Core']['Description']);
            }
            if (isset($elements['Dublin Core']['Publisher'])) {
                $jsonData['attribution'] = join($elements['Dublin Core']['Publisher'], '<br>');
                unset($elements['Dublin Core']['Publisher']);
            }
            if (isset($elements['Dublin Core']['Rights'])) {
                $jsonData['license'] = join($elements['Dublin Core']['Rights'], '<br>');
                unset($elements['Dublin Core']['Rights']);
            }
            if (!empty($elements['Dublin Core'])) {
                if (!isset($jsonData['metadata'])) {
                    $jsonData['metadata'] = array();
                }
                foreach ($elements['Dublin Core'] as $elementName => $elementContent) {
                    $jsonData['metadata'][] = array(
                        'label' => $elementName,
                        'value' => join($elementContent, '<br>')
                    );
                }
            }
        }
    }
    
    private function __fetchJsonData($record) {
        try {
            $recordClass = get_class($record);
            switch ($recordClass) {
                case 'Collection':
                    $iiifMetadataSlug = 'iiifitems_collection_json_element';
                break;
                case 'Item':
                    $iiifMetadataSlug = 'iiifitems_item_json_element';
                break;
                case 'File':
                    $iiifMetadataSlug = 'iiifitems_file_json_element';
                break;
                default:
                    return null;
                break;
            }
            $iiifJsonDataText = raw_iiif_metadata($record, $iiifMetadataSlug);
            if ($iiifJsonDataText) {
                return json_decode($iiifJsonDataText, true);
            }
        } catch (Exception $ex) {
        }
        return null;
    }

    private function __getCollectionIiifType($collection) {
        try {
            $iiifMetadataSlug = 'iiifitems_collection_type_element';
            $iiifTypeText = raw_iiif_metadata($collection, $iiifMetadataSlug);
            if ($iiifTypeText) {
                return $iiifTypeText;
            }
        } catch (Exception $ex) {
        }
        return 'Manifest';
    }
}
?>
