<?php

/**
 * Controller for the IIIF search API back-end
 * @package controllers
 */
class IiifItems_SearchController extends IiifItems_BaseController {
    protected static $allowedThings = array('Collection', 'Item');
    
    public function searchAction() {
        //Get and check the thing's existence
        $id = $this->getParam('id');
        $type = $this->getParam('things');
        $class = Inflector::titleize(Inflector::singularize($type));
        $thing = get_record_by_id($class, $id);
        if (empty($thing) || !in_array($class, self::$allowedThings)) {
            throw new Omeka_Controller_Exception_404;
        }
        
        try {
            $results = IiifItems_Util_Search::findResultsFor($thing, $this->getParam('q'), public_full_url() . '?' . http_build_query($_GET));
            $this->__respondWithJson($results);
        } catch (Exception $ex) {
            $this->__respondWithJson(null, 400);
        }
        return;
    }
}
