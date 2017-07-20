<?php

/**
 * Controller for endpoints used by the annotator
 * @package controllers
 */
class IiifItems_AnnotatorController extends IiifItems_BaseController {
    
    /**
     * Renders a JSON array of annotations filed under the given manifest-type collection or non-annotation item.
     * A "uri" GET parameter must be passed to indicate the canvas ID to list annotations from.
     * GET iiif-items/annotator/:things/:id/index?uri=...
     * 
     * [{ANNOTATION}, {ANNOTATION}, ...]
     */
    public function indexAction() {
        // Sanity checks
        $this->__blockPublic();
        $this->__restrictVerb('GET');
        $uri = $this->getParam('uri');
        if (empty($uri)) {
            $this->__respondWithJson(null, 400);
            return;
        }
        $contextRecordType = $this->getParam('things');
        $contextRecordId = $this->getParam('id');
        if (!($contextThing = $this->__getThing($contextRecordType, $contextRecordId))) {
            $this->__respondWithJson(null, 400);
            return;
        }
        if (!($thing = IiifItems_Util_Annotation::findAttachmentInContextByUri($contextThing, $uri))) {
            $this->__respondWithJson(null, 400);
            return;
        }
        
        // Pull all annotations that belong to $thing
        // Include access permissions
        $json = IiifItems_Util_Annotation::findAnnotationsFor($thing, true);
        
        // Respond [<anno1>...<annon>]
        $this->__respondWithJson($json);
        return;
    }
    
    /**
     * Processes a POSTed annotation from Mirador and responds with the annotation added to the given manifest-type collection or non-annotation item.
     * POST iiif-items/annotator/:things/:id
     * 
     * {CREATED ANNOTATION}
     */
    public function createAction() {
        // Sanity check
        $this->__blockPublic();
        $this->__restrictVerb('POST');
        $contextRecordType = $this->getParam('things');
        $contextRecordId = $this->getParam('id');
        if (!($contextThing = $this->__getThing($contextRecordType, $contextRecordId))) {
            $this->__respondWithJson(null, 400);
            return;
        }
        //Decode request params from JSON
        $paramStr = file_get_contents('php://input');
        $params = json_decode($paramStr, true);
        unset($params['@id']);
        // Extract on canvas
        $on = $this->__extractOn($params);
        // Extract selector
        $svgs = $this->__extractSvg($params);
        $svgMetas = array();
        foreach ($svgs as $svg) {
            $svgMetas[] = array('text' => $svg, 'html' => false);
        }
        // Extract preview dimensions (xywh tuple)
        $xywhs = $this->__extractXywh($params);
        $xywhMetas = array();
        foreach ($xywhs as $xywh) {
            $xywhMetas[] = array('text' => join(',', $xywh), 'html' => false);
        }
        // Extract main text and tags
        $body = "";
        $tags = array();
        foreach ($params['resource'] as $resource) {
            switch ($resource['@type']) {
                case 'dctypes:Text': $body = $resource['chars']; break;
                case 'oa:Tag': $tags[] = $resource['chars']; break;
            }
        }
        // Strip proprietary _dims attribute from rigged endpoint
        // This comes from Mirador 2.2 and below
        if (isset($params['_dims'])) {
            unset($params['_dims']);
        }
        // Read and strip proprietary _iiifitems_access attribute
        if (isset($params['_iiifitems_access']) && in_array(current_user()->role, array('super', 'admin'))) {
            $isPublic = !!$params['_iiifitems_access']['public'];
            $isFeatured = !!$params['_iiifitems_access']['featured'];
        } else {
            $isPublic = false;
            $isFeatured = false;
        }
        unset($params['_iiifitems_access']);
        // Trace back to the target Item and remember its UUID
        $originalItem = IiifItems_Util_Annotation::findAttachmentInContextByUri($contextThing, $on);
        if (!$originalItem) {
            $this->__respondWithJson(null, 400);
            return;
        }
        $uuid = raw_iiif_metadata($originalItem, 'iiifitems_item_uuid_element');
        if (!$uuid) {
            $uuid = generate_uuid();
            $originalItem->addElementTextsByArray(array(
                'IIIF Item Metadata' => array(
                    'UUID' => array(array('text' => $uuid, 'html' => false)),
                ),
            ));
            $originalItem->save();
        }
        // Save
        $newItem = insert_item(array(
            'public' => $isPublic,
            'featured' => $isFeatured,
            'item_type_id' => get_option('iiifitems_annotation_item_type'),
            'tags' => join(',', $tags),
        ), array(
            'Dublin Core' => array(
                'Title' => array(array('text' => 'Annotation: "' . html_entity_decode(snippet_by_word_count($body)) . '"', 'html' => false)),
            ),
            'Item Type Metadata' => array(
                'On Canvas' => array(array('text' => $uuid, 'html' => false)),
                'Selector' => $svgMetas,
                'Annotated Region' => $xywhMetas,
                'Text' => array(array('text' => $body, 'html' => true)),
            ),
        ));
        $newItemId = $newItem->id;
        // Add @id and re-save, then respond with @id attached
        $params['@id'] = public_full_url(array('things' => 'items', 'id' => $newItemId, 'typeext' => 'anno.json'), 'iiifitems_oa_uri');
        // Attach new original ID
        get_db()->insert('ElementText', array(
            'record_id' => $newItemId,
            'record_type' => 'Item',
            'html' => 0,
            'element_id' => get_option('iiifitems_item_atid_element'),
            'text' => $params['@id'],
        ));
        // Attach new JSON Data
        get_db()->insert('ElementText', array(
            'record_id' => $newItemId,
            'record_type' => 'Item',
            'html' => 0,
            'element_id' => get_option('iiifitems_item_json_element'),
            'text' => json_encode($params, JSON_UNESCAPED_SLASHES),
        ));
        // Attach preview images based on first image
        if (isset($xywhs[0]) && !IiifItems_Util_Canvas::isNonIiifItem($originalItem)) {
            foreach ($xywhs as $xywh) {
                Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('IiifItems_Job_AddAnnotationThumbnail', array(
                    'originalItemId' => $originalItem->id,
                    'annotationItemId' => $newItem->id,
                    'dims' => $xywh,
                ));
            }
        }
        // Tack back the proprietary _iiifitems_access attribute
        $params['_iiifitems_access'] = array(
            'public' => $newItem->public,
            'featured' => $newItem->featured,
            'owner' => $newItem->owner_id,
        );
        $this->__respondWithJson($params);
    }
    
    /**
     * Deletes the submitted annotation from Mirador as filed under the given manifest-type collection or non-annotation item.
     * Responds "OK" if successful
     * DELETE iiif-items/annotator/:things/:id/delete
     * 
     * OK
     * 
     * @throws Omeka_Controller_Exception_404
     */
    public function deleteAction() {
        // Sanity check
        $this->__blockPublic();
        $this->__restrictVerb('DELETE');
        $contextRecordType = $this->getParam('things');
        $contextRecordId = $this->getParam('id');
        if (!($contextThing = $this->__getThing($contextRecordType, $contextRecordId))) {
            $this->__respondWithJson(null, 400);
            return;
        }
        // Decode JSON
        $paramStr = file_get_contents('php://input');
        $params = json_decode($paramStr, true);
        $id = $params['id'];
        
        // Find the annotation by that ID and delete it
        if ($annoText = get_db()->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.text = ?', array(get_option('iiifitems_item_atid_element'), $id), true)) {
            if ($annoItem = get_record_by_id('Item', $annoText->record_id)) {
                // Check permissions
                $user = current_user();
                switch ($user->role) {
                    case 'contributor':
                        if ($user->id != $annoItem->owner_id) {
                            $this->__respondWithJson(null, 403);
                            return;
                        }
                    break;
                    case 'researcher':
                        $this->__respondWithJson(null, 403);
                        return;
                }
                // Delete the annotation
                $annoItem->delete();
                $this->__respondWithJson(array("status" => "OK"));
                return;
            }
        }
        throw new Omeka_Controller_Exception_404;
    }
    
    /**
     * Deletes the submitted annotation from Mirador as filed under the given manifest-type collection or non-annotation item.
     * Responds with the updated annotation if successful.
     * PUT iiif-items/annotator/:things/:id/update
     * 
     * {UPDATED ANNOTATION}
     * 
     * @throws Omeka_Controller_Exception_404
     */
    public function updateAction() {
        // Sanity check
        $this->__blockPublic();
        $this->__restrictVerb('PUT');
        $contextRecordType = $this->getParam('things');
        $contextRecordId = $this->getParam('id');
        if (!($contextThing = $this->__getThing($contextRecordType, $contextRecordId))) {
            $this->__respondWithJson(null, 400);
            return;
        }
        // Decode JSON
        $jsonStr = file_get_contents('php://input');
        $json = json_decode($jsonStr, true);
        $atid = $json['@id'];
        // Extract on canvas
//        $on = $this->__extractOn($json);
        // Extract selectors
        $svgs = $this->__extractSvg($json);
        $svgNewMetas = array();
        foreach ($svgs as $svg) {
            $svgNewMetas[] = array('text' => $svg, 'html' => false);
        }
        // Extract xywhs
        $xywhs = $this->__extractXywh($json);
        $xywhNewMetas = array();
        foreach ($xywhs as $xywh) {
            $xywhNewMetas[] = array('text' => join(',', $xywh), 'html' => false);
        }
        // Extract main text and tags
        $text = '';
        $textIsHtml = false;
        $tags = array();
        foreach ($json['resource'] as $resource) {
            switch ($resource['@type']) {
                case 'oa:Tag':
                    $tags[] = $resource['chars'];
                break;
                case 'dctypes:Text': case 'cnt:ContentAsText':
                    $text = $resource['chars'];
                    $textIsHtml = $resource['format'] == 'text/html';
                break;
            }
        }
        // Save
        if ($annoText = get_db()->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.text = ?', array(get_option('iiifitems_item_atid_element'), $atid), true)) {
            if ($annoItem = get_record_by_id('Item', $annoText->record_id)) {
                // Check whether xywh regions have changed
                $oldXywhTexts = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(get_option('iiifitems_annotation_xywh_element'), $annoItem->id));
                if (count($oldXywhTexts) != count($xywhNewMetas)) { // Not the same size = Changed
                    $xywhChanged = true;
                } else { // Same size = Changed if a new xywh isn't in the existing xywh set
                    $oldXywhSet = array();
                    foreach ($oldXywhTexts as $oldXywhText) {
                        $oldXywhSet[$oldXywhText->text] = true;
                    }
                    foreach ($xywhNewMetas as $xywhNewMeta) {
                        if (isset($oldXywhSet[$xywhNewMeta['text']])) {
                            unset($oldXywhSet[$xywhNewMeta['text']]);
                        }
                    }
                    $xywhChanged = !empty($oldXywhSet);
                }
                // Check permissions
                $user = current_user();
                switch ($user->role) {
                    case 'super': case 'admin':
                        $isPublic = !!$json['_iiifitems_access']['public'];
                        $isFeatured = !!$json['_iiifitems_access']['featured'];
                    break;
                    case 'contributor':
                        $isPublic = $annoItem->public;
                        $isFeatured = $annoItem->featured;
                        if ($user->id != $annoItem->owner_id) {
                            $this->__respondWithJson(null, 403);
                            return;
                        }
                    break;
                    case 'researcher':
                        $this->__respondWithJson(null, 403);
                        return;
                }
                unset($json['_iiifitems_access']);
                // Apply changes
                $annoItem->applyTagString(join(',', $tags));
                $newTextsArray = array(
                    'Item Type Metadata' => array(
                        'Text' => array(array('text' => $text, 'html' => true)),
                        'Selector' => $svgNewMetas,
                        'Annotated Region' => $xywhNewMetas,
                    ),
                    'IIIF Item Metadata' => array(
                        'JSON Data' => array(array('text' => $this->__json_encode($json), 'html' => false)),
                    ),
                );
                // Update title if unchanged from default
                $oldTitle = metadata($annoItem, array('Dublin Core', 'Title'), array('no_filter' => true, 'no_escape' => true));
                if (strpos($oldTitle, 'Annotation: "') === 0) {
                    $newTextsArray['Dublin Core'] = array('Title' => array(array('text' => 'Annotation: "' . html_entity_decode(snippet_by_word_count($text)) . '"', 'html' => true)));
                    $annoItem->deleteElementTextsByElementId(array(get_db()->getTable('Element')->findByElementSetNameAndElementName('Dublin Core', 'Title')->id));
                }
                // Replace old element texts
                $annoItem->deleteElementTextsByElementId(array(
                    get_option('iiifitems_annotation_text_element'),
                    get_option('iiifitems_item_json_element'),
                    get_option('iiifitems_annotation_selector_element'),
                    get_option('iiifitems_annotation_xywh_element'),
                ));
                $annoItem->addElementTextsByArray($newTextsArray);
                $annoItem->public = $isPublic;
                $annoItem->featured = $isFeatured;
                $annoItem->save();
                $this->__insertIiifItemsAccess($json, $annoItem);
                if ($xywhChanged) {
                    foreach ($annoItem->getFiles() as $file) {
                        $file->delete();
                    }
                    foreach ($xywhs as $xywh) {
                        $addAnnotationThumbnailJob = new IiifItems_Job_AddAnnotationThumbnail(array(
                            'originalItemId' => $contextThing->id,
                            'annotationItemId' => $annoItem->id,
                            'dims' => $xywh,
                        ));
                        $addAnnotationThumbnailJob->perform();
                    }
                }
                $this->__respondWithJson($json);
                return;
            }
        }
        // Respond with changes
        throw new Omeka_Controller_Exception_404;
    }
    
    /**
     * The annotator wrapper page for the given manifest-type collection or non-annotation item.
     * GET :things/:id/annotate
     */
    public function annotateAction() {
        $this->__passModelToView();
    }
    
    /**
     * Quick helper for retrieving a record by name and ID
     * @param string $type The type of record to retrieve
     * @param integer $id The ID to retrieve
     * @return Record
     */
    private function __getThing($type, $id) {
        $class = Inflector::titleize(Inflector::singularize($type));
        return get_record_by_id($class, $id);
    }
    
    /**
     * Return the canvas that the annotation is attached on.
     * @param array $params OA annotation JSON array data
     * @return string
     */
    private function __extractOn($params) {
        if (isset($params['on']['full'])) {
            return $params['on']['full'];
        }
        if (isset($params['on'][0]['full'])) {
            return $params['on'][0]['full'];
        }
        return null;
    }
    
    /**
     * Return xywh selectors from the annotation.
     * @param array $params OA annotation JSON array data
     * @return array[] 4-entry arrays of x, y, width, height
     */
    private function __extractXywh($params) {
        if (isset($params['_dims'])) {
            return array($params['_dims']);
        }
        if (isset($params['on'][0]['selector']['default']['value'])) {
            $xywhs = array();
            foreach ($params['on'] as $on) {
                $xywhs[] = explode(',', substr($on['selector']['default']['value'], 5));
            }
            return $xywhs;
        } 
        return null;
    }
    
    /**
     * Return SVG selectors of the annotation.
     * @param array $params OA annotation JSON array data
     * @return string[]
     */
    private function __extractSvg($params) {
        if (isset($params['on']['selector']['value'])) {
            return array($params['on']['selector']['value']);
        }
        if (isset($params['on'][0]['selector']['item']['value'])) {
            $svgs = array();
            foreach ($params['on'] as $on) {
                $svgs[] = $on['selector']['item']['value'];
            }
            return $svgs;
        } 
        return null;
    }
    
    /**
     * Inserts the proprietary _iiifitems_access property into the given JSON data.
     * @param array $json The JSON array data
     * @param Item $annoItem The annotation-type item that this is based on
     */
    private function __insertIiifItemsAccess(&$json, $annoItem) {
        // Tack back the proprietary _iiifitems_access attribute
        $json['_iiifitems_access'] = array(
            'public' => $annoItem->public,
            'featured' => $annoItem->featured,
            'owner' => $annoItem->owner_id,
        );
    }
}
