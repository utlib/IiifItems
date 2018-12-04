<?php

/**
 * Utilities for IIIF Search API. Tenatively 0.9 for compatibility with Jeff Witt's search within.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_Search extends IiifItems_IiifUtil {
    /**
     * The basic template for a search API query result.
     * 
     * @param string $atId The IIIF ID of the annotation list
     * @param array $results Associative array with an array of resources in the "resources" key and an array of hits in the "hits" key
     * @return array
     */
    public static function blankListTemplate($atId, $results) {
        return array(
//            '@context' => array(
//                'http://iiif.io/api/presentation/2/context.json',
//                'http://iiif.io/api/search/1/context.json',
//            ),
            '@context' => "http://iiif.io/api/search/0/context.json", //0.9 for now
            '@id' => $atId,
            '@type' => 'sc:AnnotationList',
            'within' => array(
                '@type' => 'sc:Layer',
                'total' => count($results['hits']),
            ),
            'startIndex' => 0,
            'resources' => $results['resources'],
            'hits' => $results['hits'],
        );
    }
    
    /**
     * Returns the search result for annotations under the given subject, in IIIF Search API form.
     * 
     * @param Colleciton|Item $thing A manifest-type collection or non-annotation Item to search under
     * @param string $query The provided search query
     * @param string $atId The unique ID to attach to the result
     * @return array IIIF Search API annotation list containing found annotations and hits
     */
    public static function findResultsFor($thing, $query, $atId = '') {
        $results = self::queryResultsFor($thing, $query);
        return self::blankListTemplate($atId, $results);
    }
    
    /**
     * Provides a summary of annotations and word hits attached to the given manifest-type collection or non-annotation Item.
     * 
     * @param Collection|Item $thing A manifest-type collection or non-annotation Item to search under
     * @param string $query The provided query
     * @return array Query result in the format specified by IiifItems_Util_Search::blankListTemplate
     */
    public static function queryResultsFor($thing, $query) {
        $resources = array();
        $hits = array();
        $annotations = self::queryAnnotationsFor($thing, $query);
        $terms = preg_split('/\s+/', $query);
        $quotedTerms = array();
        foreach ($terms as $term) {
            $quotedTerms[] = preg_quote($term);
        }
        $pregQuery = '/(' . join('|', $quotedTerms) . ')/i';
        foreach ($annotations as $annotation) {
            $annotationJson = IiifItems_Util_Annotation::buildAnnotation($annotation, 'fill');
            $resources[] = $annotationJson;
            $hitSelectors = array();
            $text = strip_formatting(raw_iiif_metadata($annotation, 'iiifitems_annotation_text_element'), '', $query);
            $pregSplits = preg_split($pregQuery, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
            foreach ($pregSplits as $pregSplit) {
                if (preg_match($pregQuery, $pregSplit[0])) {
                    $hitSelectors[] = array(
                        '@type' => 'oa:TextQuoteSelector',
                        'exact' => $pregSplit[0],
                        'before' => substr($text, 0, $pregSplit[1]),
                        'after' => substr($text, $pregSplit[1]+strlen($pregSplit[0])),
                    );
                    break;
                }
            }
            $hits[] = array(
                '@type' => 'search:Hit',
                'annotations' => array($annotationJson['@id']),
                'selectors' => $hitSelectors,
                'match' => empty($hitSelectors) ? '' : $hitSelectors[0]['exact'],
                'before' => empty($hitSelectors) ? '' : $hitSelectors[0]['before'],
                'after' => empty($hitSelectors) ? $text : $hitSelectors[0]['after'],
            );
        }
        return array(
            'resources' => $resources,
            'hits' => $hits,
        );
    }
    
    /**
     * Search for annotations attached to the given manifest-type collection or non-annotation Item.
     * 
     * @param Collection|Item $thing A manifest-type collection or non-annotation Item to search under
     * @param string $query The provided query
     * @return Item[] A list of result annotation-type Items
     */
    public static function queryAnnotationsFor($thing, $query) {
        $db = get_db();
        $itemsTable = $db->getTable('Item');
        $itemsSelect = $itemsTable->getSelectForFindBy(array('search' => $query));
        $annotationTypeId = get_option('iiifitems_annotation_item_type');
        $itemsSelect->where('items.item_type_id = ?', array($annotationTypeId));
        $uuidElementId = get_option('iiifitems_item_uuid_element');
        $onCanvasElementId = get_option('iiifitems_annotation_on_element');
        $itemsSelect->joinLeft(array('element_texts' => $db->ElementText), "element_texts.record_id = items.id AND element_texts.element_id = {$onCanvasElementId} AND element_texts.record_type = 'Item'", array('text'));
        $itemsSelect->join(array('element_texts2' => $db->ElementText), "element_texts.text = element_texts2.text AND element_texts2.element_id = {$uuidElementId} AND element_texts2.record_type = 'Item'", array('text', 'record_id'));
        $itemsSelect->joinLeft(array('qq' => $db->Item), "element_texts2.record_type = 'Item' AND element_texts2.record_id = qq.id", array('collection_id'));
        switch (get_class($thing)) {
            case 'Collection':
                $itemsSelect->where('qq.collection_id = ?', $thing->id);
                break;
            case 'Item':
                $itemsSelect->where('element_texts2.record_id = ?', $thing->id);
                break;
            default:
                return array();
        }
        
        $results = $db->fetchAll($itemsSelect);
        $annos = array();
        foreach ($results as $result) {
            $annos[] = get_record_by_id('Item', $result['id']);
        }
        return $annos;
    }
    
    /**
     * Insert the service description into the given IIIF manifest JSON array data (in-place).
     * 
     * @param Collection|Item $thing The collection or item to search inside
     * @param array $json IIIF manifest JSON array data
     */
    public static function insertSearchApiFor($thing, &$json, $label = "Search this manifest with Omeka") {
        $serviceFragment = array(
            '@context' => 'http://iiif.io/api/search/1/context.json',
            '@id' => public_full_url(array('things' => Inflector::pluralize(strtolower(get_class($thing))), 'id' => $thing->id), 'iiifitems_search'),
            'label' => $label,
            'profile' => 'http://iiif.io/api/search/1/search',
        );
        if (isset($json['service'])) {
            $json['service'][] = array($serviceFragment);
        } else {
            $json['service'] = array($serviceFragment);
        }
    }
}
