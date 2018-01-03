<?php

/**
 * Utilities for IIIF collections.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_Collection extends IiifItems_IiifUtil {
    /**
     * Basic template for IIIF Presentation API Collection, in manifest-collection form.
     * 
     * @param string $atId The IIIF ID to attach
     * @param string $label The label to attach
     * @param array $manifests List of manifests in IIIF JSON form
     * @param array $collections List of collections in IIIF JSON form
     * @return array
     */
    public static function blankTemplate($atId, $label, $manifests=array(), $collections=array()) {
        return array(
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => $atId,
            '@type' => 'sc:Collection',
            'label' => $label,
            'manifests' => $manifests,
            'collections' => $collections,
        );
    }

    /**
     * Basic template for IIIF Presentation API Collection, in members form.
     * 
     * @param string $atId The IIIF ID to attach
     * @param string $label The label to attach
     * @param array $manifests List of manifests in IIIF JSON form
     * @param array $collections List of collections in IIIF JSON form
     * @return array
     */
    public static function blankMembersTemplate($atId, $label, $members=array()) {
        return array(
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => $atId,
            '@type' => 'sc:Collection',
            'label' => $label,
            'members' => $members,
        );
    }
    
    /**
     * Bare minimum template for a collection, for embedding in a collection listing
     * @param string $atId The unique URI ID for this collection
     * @param string $label The title of this collection
     * @return array
     */
    public static function bareTemplate($atId, $label) {
        return array(
            '@id' => $atId,
            '@type' => 'sc:Collection',
            'label' => $label,
        );
    }

    /**
     * Return the IIIF Presentation API collection representation of the Omeka collection, in collection-manifest form
     * @param Collection $collection
     * @return array
     */
    public static function buildCollection($collection, $cacheAs=null) {
        // Set default IDs and titles
        $atId = public_full_url(array('things' => 'collections', 'id' => $collection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
        $label = metadata($collection, array('Dublin Core', 'Title'), array('no_escape' => true));
        // Do it only for collections
        if (self::isCollection($collection)) {
            // Try to find cached copy
            if ($cacheAs !== null) {
                if ($json = get_cached_iiifitems_value_for($collection, $cacheAs)) {
                    return $json;
                }
            }
            // Try to find template; if it does not already exist, use the blank template
            if (!($json = parent::fetchJsonData($collection))) {
                $json = self::blankTemplate($atId,$label);
            }
            if (isset($json['members'])) {
                unset($json['members']);
            }
            // Override the entries
            $json['collections'] = array();
            $json['manifests'] = array();
            foreach (self::findSubcollectionsFor($collection) as $subcollection) {
                $subAtId = public_full_url(array('things' => 'collections', 'id' => $subcollection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
                $label = metadata($subcollection, array('Dublin Core', 'Title'), array('no_escape' => true));
                $json['collections'][] = IiifItems_Util_Collection::bareTemplate($subAtId, $label);
            }
            foreach (self::findSubmanifestsFor($collection) as $submanifest) {
                $subAtId = public_full_url(array('things' => 'collections', 'id' => $submanifest->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
                $label = metadata($submanifest, array('Dublin Core', 'Title'), array('no_escape' => true));
                $json['manifests'][] = IiifItems_Util_Manifest::bareTemplate($subAtId, $label);
            }
            // Override the IDs, titles and DC metadata
            $json['@id'] = $atId;
            parent::addDublinCoreMetadata($json, $collection);
            // Override within
            if ($parentCollection = IiifItems_Util_Collection::findParentFor($collection)) {
                $json['within'] = public_full_url(array('things' => 'collections', 'id' => $parentCollection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
            } else if (isset($json['within'])) {
                unset($json['within']);
            }
            // Cache accordingly
            if ($cacheAs !== null) {
                cache_iiifitems_value_for($collection, $json, $cacheAs);
            }
            // Done
            return $json;
        }
        return self::blankTemplate($atId, $label);
    }
    
    /**
     * Return the IIIF Presentation API collection representation of the Omeka collection, in members form
     * @param Collection $collection
     * @return array
     */
    public static function buildMembersCollection($collection, $cacheAs=null) {
        // Set default IDs and titles
        $atId = public_full_url(array('things' => 'collections', 'id' => $collection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
        $label = metadata($collection, array('Dublin Core', 'Title'), array('no_escape' => true));
        // Do it only for collections
        if (self::isCollection($collection)) {
            // Try to find cached copy
            if ($cacheAs !== null) {
                if ($json = get_cached_iiifitems_value_for($collection, $cacheAs)) {
                    return $json;
                }
            }
            // Try to find template; if it does not already exist, use the blank template
            if (!($json = parent::fetchJsonData($collection))) {
                $json = self::blankTemplate($atId,$label);
            }
            // Override the entries
            if (isset($json['collections'])) {
                unset($json['collections']);
            }
            if (isset($json['manifests'])) {
                unset($json['manifests']);
            }
            $json['members'] = array();
            foreach (self::findMembersFor($collection) as $member) {
                if (self::isCollection($member)) {
                    $atId = public_full_url(array('things' => 'collections', 'id' => $member->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
                    $label = metadata($member, array('Dublin Core', 'Title'), array('no_escape' => true));
                    $json['collections'][] = IiifItems_Util_Collection::bareTemplate($atId, $label);
                }
                else {
                    $atId = public_full_url(array('things' => 'collections', 'id' => $member->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
                    $label = metadata($member, array('Dublin Core', 'Title'), array('no_escape' => true));
                    $json['manifests'][] = IiifItems_Util_Manifest::bareTemplate($atId, $label);
                }
            }
            // Override the IDs, titles and DC metadata
            $json['@id'] = $atId;
            parent::addDublinCoreMetadata($json, $collection);
            // Cache accordingly
            if ($cacheAs !== null) {
                cache_iiifitems_value_for($collection, $json, $cacheAs);
            }
            // Done
            return $json;
        }
        return self::blankTemplate($atId, $label);
    }

    /**
     * Find the parent of the given collection.
     * 
     * @param Collection $collection
     * @return type
     */
    public static function findParentFor($collection) {
        $parentUuid = raw_iiif_metadata($collection, 'iiifitems_collection_parent_element');
        if (!$parentUuid) {
            return null;
        }
        return find_collection_by_uuid($parentUuid);
    }

    /**
     * Return a list of collection-type collections under the given collection-type collection.
     * 
     * @param Collection $collection
     * @return Collection[]
     */
    public static function findSubcollectionsFor($collection) {
        $myUuid = raw_iiif_metadata($collection, 'iiifitems_collection_uuid_element');
        if (!$myUuid) {
            return null;
        }
        $matches = get_db()->getTable('ElementText')->findBySql(
            'element_texts.element_id = ? AND element_texts.text = ?',
            array(get_option('iiifitems_collection_parent_element'), $myUuid)
        );
        $results = array();
        foreach ($matches as $match) {
            if ($candidate = get_record_by_id($match->record_type, $match->record_id)) {
                $type = raw_iiif_metadata($candidate, 'iiifitems_collection_type_element');
                if ($type == 'Collection') {
                    $results[] = $candidate;
                }
            }
        }
        return $results;
    }

    /**
     * Return a list of manifest-type collections under the given collection-type collection.
     * 
     * @param Collection $collection
     * @return Collection[]
     */
    public static function findSubmanifestsFor($collection) {
        $myUuid = raw_iiif_metadata($collection, 'iiifitems_collection_uuid_element');
        if (!$myUuid) {
            return null;
        }
        $matches = get_db()->getTable('ElementText')->findBySql(
            'element_texts.element_id = ? AND element_texts.text = ?',
            array(get_option('iiifitems_collection_parent_element'), $myUuid)
        );
        $results = array();
        foreach ($matches as $match) {
            if ($candidate = get_record_by_id($match->record_type, $match->record_id)) {
                $type = raw_iiif_metadata($candidate, 'iiifitems_collection_type_element');
                if ($type != 'Collection' && $type != 'None') {
                    $results[] = $candidate;
                }
            }
        }
        return $results;
    }

    /**
     * Returns a list of collections under the given collection-type collection, regardless of IIIF type.
     * 
     * @param Collection $collection
     * @return Collection[]
     */
    public static function findSubmembersFor($collection) {
        $myUuid = raw_iiif_metadata($collection, 'iiifitems_collection_uuid_element');
        if (!$myUuid) {
            return null;
        }
        $matches = get_db()->getTable('ElementText')->findBySql(
            'element_texts.element_id = ? AND element_texts.text = ?',
            array(get_option('iiifitems_collection_parent_element'), $myUuid)
        );
        $results = array();
        foreach ($matches as $match) {
            if ($candidate = get_record_by_id($match->record_type, $match->record_id)) {
                $type = raw_iiif_metadata($candidate, 'iiifitems_collection_type_element');
                if ($type != 'None') {
                    $results[] = $candidate;
                }
            }
        }
        return $results;
    }
    
    /**
     * Returns the number of collections under the given collection-type collection, regardless of IIIF type.
     * 
     * @param Collection $collection
     * @return integer
     */
    public static function countSubmembersFor($collection) {
        $myUuid = raw_iiif_metadata($collection, 'iiifitems_collection_uuid_element');
        if (!$myUuid) {
            return 0;
        }
        $db = get_db();
        $elementTextTable = $db->getTable('ElementText');
        $select = $elementTextTable->getSelectForCount()
                ->where('element_texts.element_id = ?', get_option('iiifitems_collection_parent_element'))
                ->where('element_texts.text = ?', $myUuid);
        return $db->fetchOne($select);
    }
    
    /**
     * Returns the number of annotations among submembers of the given collection-type collection.
     * 
     * @param Collection $collection
     */
    public static function countAnnotationsFor($collection) {
        $db = get_db();
        $itemTable = $db->getTable('Item');
        $select = $itemTable->getSelectForCount();
        $collectionIds = IiifItems_Util_CollectionOptions::getFullSubmemberIdArray($collection);
        $collectionIds[] = $collection->id;
        $attachedToElementId = (int) get_option('iiifitems_annotation_on_element');
        $uuidElementId = (int) get_option('iiifitems_item_uuid_element');
        $select->where('items.item_type_id = ?', array(get_option('iiifitems_annotation_item_type')));
        $select->joinLeft(array('iiif_catalogue_collections' => $db->Collection), 'items.collection_id = iiif_catalogue_collections.id', array());
        $select->joinLeft(array('iiif_anno_attachment1_metadata' => $db->ElementText), "iiif_anno_attachment1_metadata.element_id = ${attachedToElementId} AND iiif_anno_attachment1_metadata.record_type = 'Item' AND iiif_anno_attachment1_metadata.record_id = items.id", array('text'));
        $select->joinLeft(array('iiif_anno_attachment2_metadata' => $db->ElementText), "iiif_anno_attachment2_metadata.element_id = ${uuidElementId} AND iiif_anno_attachment2_metadata.record_type = 'Item' AND iiif_anno_attachment2_metadata.text = iiif_anno_attachment1_metadata.text", array('record_id'));
        $select->joinLeft(array('iiif_attached_items' => $db->Item), "iiif_attached_items.id = iiif_anno_attachment2_metadata.record_id", array('collection_id'));
        $select->where('iiif_catalogue_collections.id IN (?) OR iiif_attached_items.collection_id IN (?)', array($collectionIds, $collectionIds));
        return $db->fetchOne($select);
    }
    
    /**
     * Returns a list of top-level collections, regardless of IIIF type (i.e. those with no parents).
     * 
     * @return Collection[]
     */
    public static function findTopMembers() {
        $db = get_db();
        $collectionsTable = $db->getTable('Collection');
        $collectionsSelect = $collectionsTable->getSelectForFindBy();
        $parentUuidElement = get_option('iiifitems_collection_parent_element');
        $collectionsSelect->where("collections.id NOT IN (SELECT record_id FROM {$db->prefix}element_texts WHERE record_type = 'Collection' AND element_id = {$parentUuidElement})");
        $collectionsTable->applySorting($collectionsSelect, 'Dublin Core,Title', 'ASC');
        return $collectionsTable->fetchObjects($collectionsSelect);
    }
    
    /**
     * Returns a list of top-level collection-type collections (i.e. those with no parents).
     * 
     * @return Collection[]
     */
    public static function findTopCollections() {
        $db = get_db();
        $collectionsTable = $db->getTable('Collection');
        $collectionsSelect = $collectionsTable->getSelectForFindBy();
        $parentUuidElement = get_option('iiifitems_collection_parent_element');
        $typeElement = get_option('iiifitems_collection_type_element');
        $collectionsSelect->joinLeft(array('element_texts' => $db->ElementText), "element_texts.record_id = collections.id AND element_texts.element_id = {$typeElement} AND element_texts.record_type = 'Collection'", array('text'));
        $collectionsSelect->where("collections.id NOT IN (SELECT record_id FROM {$db->prefix}element_texts WHERE record_type = 'Collection' AND element_id = {$parentUuidElement})");
        $collectionsSelect->where("element_texts.text = 'Collection'");
        $collectionsTable->applySorting($collectionsSelect, 'Dublin Core,Title', 'ASC');
        return $collectionsTable->fetchObjects($collectionsSelect);
    }
    
    /**
     * Returns a list of top-level manifest-type collections (i.e. those with no parents).
     * 
     * @return Collection[]
     */
    public static function findTopManifests() {
        $db = get_db();
        $collectionsTable = $db->getTable('Collection');
        $collectionsSelect = $collectionsTable->getSelectForFindBy();
        $parentUuidElement = get_option('iiifitems_collection_parent_element');
        $typeElement = get_option('iiifitems_collection_type_element');
        $collectionsSelect->joinLeft(array('element_textsA' => $db->ElementText), "element_textsA.record_id = collections.id AND element_textsA.record_type = 'Collection' AND element_textsA.element_id = {$typeElement}", array('text'));
        $collectionsSelect->joinLeft(array('element_textsB' => $db->ElementText), "element_textsB.record_id = collections.id AND element_textsB.record_type = 'Collection' AND element_textsB.element_id = {$parentUuidElement}", array('text'));
        $collectionsSelect->where("element_textsA.text NOT IN ('Collection', 'None') OR element_textsA.text IS NULL");
        $collectionsSelect->where("element_textsB.text IS NULL OR element_textsB.text = ''");
        $collectionsTable->applySorting($collectionsSelect, 'Dublin Core,Title', 'ASC');
        return $collectionsTable->fetchObjects($collectionsSelect);
    }
    
    /**
     * Returns whether the given collection is set to the Collection type
     * @param Collection $collection
     * @return boolean
     */
    public static function isCollection($collection) {
        try {
            $iiifMetadataSlug = 'iiifitems_collection_type_element';
            $iiifTypeText = raw_iiif_metadata($collection, $iiifMetadataSlug);
            if ($iiifTypeText) {
                return $iiifTypeText == 'Collection';
            }
        } catch (Exception $ex) {
        }
        return false;
    }
}
