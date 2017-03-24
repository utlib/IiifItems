<?php

/**
 * Template for all plugin integrations (mixins for a plugin).
 * @package IiifItems
 */
abstract class IiifItems_BaseIntegration {
    protected $_hooks = array();
    protected $_filters = array();
    
    /**
     * The initialize hook
     */
    public function initialize() {
        
    }
    
    /**
     * The install hook
     */
    public function install() {
        
    }
    
    /**
     * The uninstall hook
     */
    public function uninstall() {
        
    }
    
    /**
     * Returns whether this integration should be applied.
     * 
     * @return boolean
     */
    public function isActive() {
        return true;
    }
    
    /**
     * Apply all hooks and filters implemented in this integration.
     */
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
    
    /**
     * Echo an element-element text pair on admin-facing pages
     * 
     * @param string $label The label for the element
     * @param string $id The HTML id attribute
     * @param string $entry The text for the element
     * @param boolean $html Whether the text is already HTML
     */
    protected function _adminElementTextPair($label, $id, $entry, $html) {
        echo '<div id="' . $id . '" class="element"><div class="field two columns alpha"><label>' . html_escape($label) . '</label></div><div class="element-text five columns omega">' . ($html ? $entry : ('<p>'. html_escape($entry) .'</p>')) . '</div></div>';
    }

    /**
     * Echo an element-element text pair on public-facing pages
     * 
     * @param string $label The label for the element
     * @param string $id The HTML id attribute
     * @param string $entry The text for the element
     * @param boolean $html Whether the text is already HTML
     */
    protected function _publicElementTextPair($label, $id, $entry, $html) {
        echo '<div id="' . $id . '" class="element"><h3>' . html_escape($label) . '</h3><div class="element-text">' . ($html ? $entry : html_escape($entry)) . '</div></div>';
    }
}
