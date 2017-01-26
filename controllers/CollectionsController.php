<?php
class IiifItems_CollectionsController extends IiifItems_BaseController {
    public function init() {
        $this->_helper->db->setDefaultModelName('Collection');     
    }
    
    public function membersAction() {
        $this->__blockPublic();
        $db = get_db();
        $parentUuidElementId = get_option('iiifitems_collection_parent_element');
        $parentCollection = get_record_by_id('Collection', $this->getParam('id'));
        $parentUuid = raw_iiif_metadata($parentCollection, 'iiifitems_collection_uuid_element');
        $matches = $db->getTable('ElementText')->findBySql('element_texts.element_id = ? AND element_texts.record_type = ? AND element_texts.text = ?', array(
            $parentUuidElementId,
            'Collection',
            $parentUuid,
        ));
        $matchIds = array();
        foreach ($matches as $match) {
            $matchIds[] = $match->record_id;
        }
        if (empty($matchIds)) {
            $this->view->collections = array();
            $this->view->total_results = 0;
        } else {
            $query = 'collections.id IN (' . implode(',', array_fill(0, count($matches), '?')) . ')';
            $table = $db->getTable('Collection');
            $sortField = $this->_getParam('sort_field') ? $_GET['sort_field'] : 'added';
            $sortOrder = ($this->_getParam('sort_dir') ? (($_GET['sort_dir'] == 'd') ? 'DESC' : 'ASC') : 'ASC');
            $select = $table->getSelectForFindBy()->where($query, $matchIds);
            $this->_helper->db->applySorting($select, $sortField, $sortOrder);
            $this->view->collections = $table->fetchObjects($select);
            $this->view->total_results = count($matches);
            $this->view->sort_field = $sortField;
            $this->view->sort_order = $sortOrder;
        }
    }
}