<?php
class IiifItems_CollectionUtil extends IiifItems_IiifUtil {
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

    public static function blankMembersTemplate($atId, $label, $members=array()) {
        return array(
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => $atId,
            '@type' => 'sc:Collection',
            'label' => $label,
            'members' => $members,
        );
    }

    //    public static function buildCollection($collection, $cacheAs=null) {
    //        // TODO: Implement when collection support arrives
    //    }
    //    
    //    public static function buildMembersCollection($collection, $cacheAs=null) {
    //        // TODO: Implement when collection support arrives
    //    }

    public static function findParentFor($collection) {
        $parentUuid = raw_iiif_metadata($collection, 'iiifitems_collection_parent_element');
        if (!$parentUuid) {
            return null;
        }
        return find_collection_by_uuid($parentUuid);
    }

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
            $candidate = get_record_by_id($match->type, $match->id);
            $type = raw_iiif_metadata($candidate, 'iiifitems_collection_type_element');
            if ($type == 'Collection') {
                $results[] = $candidate;
            }
        }
        return $results;
    }

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
            $candidate = get_record_by_id($match->type, $match->id);
            $type = raw_iiif_metadata($candidate, 'iiifitems_collection_type_element');
            if ($type != 'Collection' && $type != 'None') {
                $results[] = $candidate;
            }
        }
        return $results;
    }

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
            $candidate = get_record_by_id($match->type, $match->id);
            $results[] = $candidate;
        }
        return $results;
    }
}
