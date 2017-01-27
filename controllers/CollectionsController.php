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
    
    public function collectionAction() {
        // Get and check the collection's existence
        $collection = get_record_by_id('Collection', $this->getParam('id'));
        if (empty($collection) || raw_iiif_metadata($collection, 'iiifitems_collection_type_element') != 'Collection') {
            throw new Omeka_Controller_Exception_404;
        }
        //Respond with JSON
        try {
            $jsonData = IiifItems_CollectionUtil::buildCollection($collection);
            $this->__respondWithJson($jsonData);
        } catch (Exception $e) {
            $this->__respondWithJson(array(
                'message' => $e->getMessage(),
            ), 500);
        }
    }
    
    public function topAction() {
        $db = get_db();
        $parentUuidElementId = get_option('iiifitems_collection_parent_element');
        $iiifTypeElementId = get_option('iiifitems_collection_type_element');
        // Get parent-less collections
        $collections = array();
        foreach (IiifItems_CollectionUtil::findTopCollections() as $collection) {
            $atId = public_full_url(array('things' => 'collections', 'id' => $collection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
            $label = metadata($collection, array('Dublin Core', 'Title'), array('no_escape' => true));
            $collections[] = IiifItems_CollectionUtil::bareTemplate($atId, $label);
        }
        // Get parent-less manifests
        $manifests = array();
        foreach (IiifItems_CollectionUtil::findTopManifests() as $manifest) {
            $atId = public_full_url(array('things' => 'collections', 'id' => $manifest->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
            $label = metadata($manifest, array('Dublin Core', 'Title'), array('no_escape' => true));
            $manifests[] = IiifItems_CollectionUtil::bareTemplate($atId, $label);
        }
        // Merge and serve
        $atId = public_url();
        $this->__respondWithJson(IiifItems_CollectionUtil::blankTemplate($atId, get_option('site_title'), $manifests, $collections));
    }
}