<?php

class IiifItems_MiradorController extends IiifItems_BaseController {
    protected static $allowedThings = array('Collection', 'Item', 'File');
    
    public function viewerAction() {
        $type = $this->getParam('things');
        $class = Inflector::titleize(Inflector::singularize($type));
        if (!in_array($class, self::$allowedThings)) {
            throw new Omeka_Controller_Exception_404;
        }
        $this->__passModelToView();
    }
}
?>
