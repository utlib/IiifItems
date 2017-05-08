<?php

/**
 * Controller for displaying annotation lists
 * @package controllers
 */
class IiifItems_AnnotationController extends IiifItems_BaseController {
    
    /**
     * Renders an OA-compliant annotation list.
     * GET oa/items/:id/annolist.json
     * @throws Omeka_Controller_Exception_404
     */
    public function listAction() {
        // Check if the item exists
        $id = $this->getParam('id');
        $item = get_record_by_id('Item', $id);
        if (!$item) {
            throw new Omeka_Controller_Exception_404;
        }
        // Respond with JSON
        try {
            $jsonData = IiifItems_Util_Annotation::buildList($item);
            $this->__respondWithJson($jsonData);
        } catch (Exception $e) {
            $this->__respondWithJson(array(
                'message' => $e->getMessage()
            ), 500);
        }
    }
    
}
