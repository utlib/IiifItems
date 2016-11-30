<?php

class IiifItems_AnnotatorController extends IiifItems_BaseController {
    public function indexAction() {
        // Sanity checks
        if (!$this->getRequest()->isGet()) {
            throw new Omeka_Controller_Exception_404;
        }
        if (empty($_GET['uri'])) {
            $this->__respondWithJson(null, 400);
            return;
        }
        // Get parameters
        $uri = $this->getParam('uri');
        $queryConditions = "element_texts.text LIKE CONCAT(?, '%')";
        $queryParams = array(get_option('iiifitems_annotation_on_element'), $uri);
        // Temporary: Extract item # from plugin-generated canvas IDs
        $root = public_full_url(array(), 'iiifitems_root');
        if (strpos($uri, $root) === 0 && ($rpos = strrpos($uri, '/canvas.json')) !== false) {
            $queryConditions .= "OR element_texts.text = ?";
            $uriComps = explode('/', substr($uri, 0, $rpos));
            $queryParams[] = $uriComps[count($uriComps)-1];
        }
        // Find all On Canvas annotation texts that match the given canvas URI
        $elementTextTable = get_db()->getTable('ElementText');
        $onCanvasMatches = $elementTextTable->findBySql("element_texts.element_id = ? AND ({$queryConditions})", $queryParams);
        // Find all JSON data annotation texts that belong to these items
        $itemTable = get_db()->getTable('Item');
        $jsonDataElementId = get_option('iiifitems_item_json_element');
        $textElementId = get_option('iiifitems_annotation_text_element');
        $json = array();
        foreach ($onCanvasMatches as $onCanvasMatch) {
            $currentAnnotationJson = json_decode($elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
                $jsonDataElementId,
                $onCanvasMatch->record_id,
            ))[0], true);
            $currentText = $elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
                $textElementId,
                $onCanvasMatch->record_id,
            ))[0];
            $currentAnnotationJson['resource'] = array(
                array(
                    '@type' => 'dctypes:Text',
                    'format' => $currentText->html ? 'text/html' : 'text/plain',
                    'chars' => $currentText->text,
                ),
            );      
            foreach (get_record_by_id('Item', $onCanvasMatch->record_id)->getTags() as $tag) {
                $currentAnnotationJson['resource'][] = array(
                    '@type' => 'oa:Tag',
                    'chars' => $tag->name,
                );
            }
            $json[] = $currentAnnotationJson;
        }
        // Respond [<anno1>...<annon>]
        $this->__respondWithJson($json);
    }
    
    public function createAction() {
        // Sanity check
        $request = $this->getRequest();
        if (!$request->isPost()) {
            throw new Omeka_Controller_Exception_404;
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
        // Save
        $newItem = insert_item(array(
            'public' => true,
            'item_type_id' => get_option('iiifitems_annotation_item_type'),
            'tags' => join(',', $tags),
        ), array(
            'Dublin Core' => array(
                'Title' => array(array('text' => snippet_by_word_count($body), 'html' => false)),
            ),
            'Item Type Metadata' => array(
                'On Canvas' => array(array('text' => $on, 'html' => false)),
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
        $this->__respondWithJson($params);
    }
    
    public function deleteAction() {
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
    
    public function updateAction() {
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
                        'Title' => array(array('text' => 'Annotation: "' . snippet_by_word_count($text) . '"', 'html' => false)),
                    ),
                    'Item Type Metadata' => array(
                        'On Canvas' => array(array('text' => $on, 'html' => false)),
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
    
    public function annotateAction() {
        $this->__passModelToView();
    }
    
    private function __blockPublic() {
        if (!is_admin_theme()) {
            throw new Omeka_Controller_Exception_404;
        }
    }
    
    private function __restrictVerb($verb) {
        $request = $this->getRequest();
        if (strtolower($request->getMethod()) != strtolower($verb)) {
            throw new Omeka_Controller_Exception_404;
        }
    }
}
