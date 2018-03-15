<?php

/**
 * Background job for removing dependent annotations after an item is removed.
 * @package IiifItems
 * @subpackage Job
 */
class IiifItems_Job_RemoveSubannotations extends Omeka_Job_AbstractJob {
    private $_item_uuid;
    
    /**
     * Create a new IiifItems_Job_RemoveSubannotations.
     * @param array $options
     */
    public function __construct(array $options) {
        parent::__construct($options);
        $this->_item_uuid = $options['item_uuid'];
    }
    
    /**
     * Main runnable method.
     */
    public function perform() {
        try {
            $onCanvasMatches = get_db()->getTable('ElementText')->findBySql("element_texts.record_type = ? AND element_texts.element_id = ? AND element_texts.text = ?", array(
                'Item',
                get_option('iiifitems_annotation_on_element'),
                $this->_item_uuid,
            ));
            $annoItems = array();
            foreach ($onCanvasMatches as $onCanvasMatch) {
                $annoItems[] = get_record_by_id('Item', $onCanvasMatch->record_id);
            }
            foreach ($annoItems as $annoItem) {
                $annoItem->delete();
            }
        } catch (Exception $e) {
            debug(__("Error in RemoveSubannotations job: %s", $e->getTraceAsString()));
        }
    }
}
