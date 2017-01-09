<?php

class IiifItems_AnnotationController extends IiifItems_BaseController {
    public function listAction() {
        // Check if the item exists
        $id = $this->getParam('id');
        $item = get_record_by_id('Item', $id);
        if (!$item) {
            throw new Omeka_Controller_Exception_404;
        }
        // Respond with JSON
        try {
            $jsonData = IiifItems_AnnotationUtil::buildList($item);
            $this->__respondWithJson($jsonData);
        } catch (Exception $e) {
            $this->__respondWithJson(array(
                'message' => $e->getMessage()
            ), 500);
        }
    }
}
