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
        $json = $this->__manifestTemplate($atId, $seqId, $label, array(
            $this->__itemCanvasJson($item)
        ));
        $this->__addDublinCoreMetadata($json, $item);
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
            if (!($json = $this->__fetchJsonData($collection))) {
                $json = $this->__manifestTemplate($atId, $seqId, $label);
            }
            $json['sequences'][0]['canvases'] = array();
            foreach ($this->_helper->db->getTable('Item')->findBy(array('collection_id' => $collection->id)) as $item) {
                $json['sequences'][0]['canvases'][] = $this->__itemCanvasJson($item);
            }
            $this->__addDublinCoreMetadata($json, $collection);
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
    
    private function __itemCanvasJson($item, $canvasId=null, $applyDublin=false) {
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
            $iiifJsonData['images'] = array();
            foreach ($itemFiles as $file) {
                $iiifJsonData['images'][] = $this->__fileImageJson($file, $canvasId);
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
            $db = get_db();
            $recordClass = get_class($record);
            switch ($recordClass) {
                case 'Collection':
                    $iiifMetadataSet = get_record_by_id('ElementSet', get_option('iiifitems_collection_element_set'));
                break;
                case 'Item':
                    $iiifMetadataSet = get_record_by_id('ElementSet', get_option('iiifitems_item_element_set'));
                break;
                case 'File':
                    $iiifMetadataSet = get_record_by_id('ElementSet', get_option('iiifitems_file_element_set'));
                break;
                default:
                    return null;
                break;
            }
            $iiifJsonDataElement = $db->getTable('Element')->findBySql('elements.element_set_id = ? AND elements.name = ?', array($iiifMetadataSet->id, 'JSON Data'))[0];
            $iiifJsonDataText = $db->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.record_type = ? AND element_texts.record_id = ?', array(
                $iiifJsonDataElement->id,
                $recordClass,
                $record->id,
            ));
            if ($iiifJsonDataText) {
                return json_decode($iiifJsonDataText[0]->text, true);
            }
        } catch (Exception $ex) {
        }
        return null;
    }

    private function __getCollectionIiifType($collection) {
        try {
            $db = get_db();
            $iiifMetadataSet = get_record_by_id('ElementSet', get_option('iiifitems_collection_element_set'));
            $iiifTypeElement = $db->getTable('Element')->findBySql('elements.element_set_id = ? AND elements.name = ?', array($iiifMetadataSet->id, 'IIIF Type'))[0];
            $iiifTypeText = $db->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.record_type = ? AND element_texts.record_id = ?', array(
                $iiifTypeElement->id,
                'Collection',
                $collection->id,
            ));
            if ($iiifTypeText) {
                return $iiifTypeText[0]->text;
            }
        } catch (Exception $ex) {
        }
        return 'Manifest';
    }
}
?>
