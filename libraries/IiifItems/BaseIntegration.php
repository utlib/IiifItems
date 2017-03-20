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
                add_plugin_hook($hook, array($this, 'hook' . Inflector::camelize($hook)));
            }
            foreach ($this->_filters as $filter) {
                add_filter($filter, array($this, 'filter' . Inflector::camelize($filter)));
            }
        }
    }
    
    protected function _adminElementTextPair($label, $id, $entry, $html) {
        echo '<div id="' . $id . '" class="element"><div class="field two columns alpha"><label>' . html_escape($label) . '</label></div><div class="element-text five columns omega">' . ($html ? $entry : ('<p>'. html_escape($entry) .'</p>')) . '</div></div>';
    }

    protected function _publicElementTextPair($label, $id, $entry, $html) {
        echo '<div id="' . $id . '" class="element"><h3>' . html_escape($label) . '</h3><div class="element-text">' . ($html ? $entry : html_escape($entry)) . '</div></div>';
    }
}
