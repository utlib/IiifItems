<?php

abstract class IiifItems_BaseIntegration {
    protected $_hooks = array();
    protected $_filters = array();
    
    public function initialize() {
        
    }
    
    public function isActive() {
        return true;
    }
    
    public function integrate() {
        if ($this->isActive()) {
            $this->initialize();
            $className = get_called_class();
            foreach ($this->_hooks as $hook) {
                add_plugin_hook($hook, array($className, 'hook' . Inflector::camelize($hook)));
            }
            foreach ($this->_filters as $filter) {
                add_filter($filter, array($className, 'filter' . Inflector::camelize($filter)));
            }
        }
    }
}
