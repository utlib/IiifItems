<?php

class IiifItems_AnnotationController extends IiifItems_BaseController {
    public function listAction() {
        // Check if the item exists and isn't itself an annotation
        $id = $this->getParam('id');
        $item = get_record_by_id('Item', $id);
        if (!$item || $item->item_type_id == get_option('iiifitems_annotation_item_type')) {
            throw new Omeka_Controller_Exception_404;
        }
        // Respond with JSON
        try {
            $jsonData = $this->__itemAnnotationList($item);
            $this->__respondWithJson($jsonData);
        } catch (Exception $e) {
            $this->__respondWithJson(array(
                'message' => $e->getMessage()
            ), 500);
        }
    }
    
    private function __itemAnnotationList($item) {
        $json = $this->__annotationListTemplate(public_full_url(array(
            'things' => 'items',
            'id' => $item->id,
            'typeext' => 'annolist.json',
        ), 'iiifitems_oa_uri'));
        $db = get_db();
        $elementTextTable = $db->getTable('ElementText');
        // Find annotations associated by item ID or original @id (if available)
        $originalId = raw_iiif_metadata($item, 'iiifitems_item_atid_element');
        $onCanvasMatches = $elementTextTable->findBySql("element_texts.element_id = ? AND (element_texts.text LIKE CONCAT(?, '%') OR element_texts.text = ?)", array(
            get_option('iiifitems_annotation_on_element'),
            $originalId,
            $item->id,
        ));
        $jsonDataElementId = get_option('iiifitems_item_json_element');
        $textElementId = get_option('iiifitems_annotation_text_element');
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
            $json['resources'][] = $currentAnnotationJson;
        }
        return $json;
    }
    
    private function __annotationListTemplate($atId, $resources=array()) {
        return array(
            '@context' => 'http://www.shared-canvas.org/ns/context.json',
            '@id' => $atId,
            '@type' => 'sc:AnnotationList',
            'resources' => $resources,
        );
    }
}
