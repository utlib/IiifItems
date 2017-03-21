<?php

/**
 * Controller for endpoints used by the annotator
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
        if (empty($_GET['uri'])) {
            $this->__respondWithJson(null, 400);
            return;
        }
        $contextRecordType = $this->getParam('things');
        $contextRecordId = $this->getParam('id');
        if (!($contextThing = $this->__getThing($contextRecordType, $contextRecordId))) {
            $this->__respondWithJson(null, 400);
            return;
        }
        $uri = $this->getParam('uri');
        if (!($thing = IiifItems_Util_Annotation::findAttachmentInContextByUri($contextThing, $uri))) {
            $this->__respondWithJson(null, 400);
            return;
        }
        
        // Pull all annotations that belong to $thing
        $json = IiifItems_Util_Annotation::findAnnotationsFor($thing);
        
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
        $on = $params['on']['full'];
        // Extract selector
        $selector = $params['on']['selector']['value'];
        // Extract main text and tags
        $body = "";
        $tags = array();
        foreach ($params['resource'] as $resource) {
            switch ($resource['@type']) {
                case 'dctypes:Text': $body = $resource['chars']; break;
                case 'oa:Tag': $tags[] = $resource['chars']; break;
            }
        }
        // Extract and strip proprietary _dims attribute from rigged endpoint
        if (isset($params['_dims'])) {
            $previewDimensions = $params['_dims'];
            unset($params['_dims']);
        }
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
            'public' => true,
            'item_type_id' => get_option('iiifitems_annotation_item_type'),
            'tags' => join(',', $tags),
        ), array(
            'Dublin Core' => array(
                'Title' => array(array('text' => 'Annotation: "' . html_entity_decode(snippet_by_word_count($body)) . '"', 'html' => false)),
            ),
            'Item Type Metadata' => array(
                'On Canvas' => array(array('text' => $uuid, 'html' => false)),
                'Selector' => array(array('text' => json_encode($selector, JSON_UNESCAPED_SLASHES), 'html' => false)),
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
        // Attach preview image based on first image
        if (isset($previewDimensions) && !IiifItems_Util_Canvas::isNonIiifItem($originalItem)) {
            Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('IiifItems_Job_AddAnnotationThumbnail', array(
                'originalItemId' => $originalItem->id,
                'annotationItemId' => $newItem->id,
                'dims' => $previewDimensions,
            ));
        }
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
        if ($annoTexts = get_db()->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.text = ?', array(get_option('iiifitems_item_atid_element'), $id))) {
            if ($annoItem = get_record_by_id('Item', $annoTexts[0]->record_id)) {
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
        $on = $json['on']['full'];
        // Extract selector
        $selector = $json['on']['selector']['value'];
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
        // TODO: Find a way not to overwrite metadata
        if ($annoTexts = get_db()->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.text = ?', array(get_option('iiifitems_item_atid_element'), $atid))) {
            if ($annoItem = get_record_by_id('Item', $annoTexts[0]->record_id)) {
                $annoItem->applyTagString(join(',', $tags));
                $annoItem->setReplaceElementTexts(true);
                $annoItem->addElementTextsByArray(array(
                    'Dublin Core' => array(
                        'Title' => array(array('text' => 'Annotation: "' . html_entity_decode(snippet_by_word_count($text)) . '"', 'html' => false)),
                    ),
                    'Item Type Metadata' => array(
                        'On Canvas' => array(array('text' => raw_iiif_metadata($annoItem, 'iiifitems_annotation_on_element'), 'html' => false)),
                        'Selector' => array(array('text' => json_encode($selector, JSON_UNESCAPED_SLASHES), 'html' => false)),
                        'Text' => array(array('text' => $text, 'html' => true)), // Mirador bug?
                    ),
                    'IIIF Item Metadata' => array(
                        'Original @id' => array(array('text' => $atid, 'html' => false)),
                        'JSON Data' => array(array('text' => $jsonStr, 'html' => false)),
                    ),
                ));
                $annoItem->save();
                $this->__respondWithRaw($jsonStr);
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
}
