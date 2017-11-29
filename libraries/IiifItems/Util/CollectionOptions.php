<?php

/**
 * Utlities for generating UI elements related to nesting IIIF collections.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_CollectionOptions extends IiifItems_IiifUtil {
    /**
     * Generates the raw tree array for the collection hierarchy
     * @param boolean|null $isPublic
     * @param Collection $parent
     * @param numeric $startDepth The depth of the starting call
     * @return array Array of entries [collection, title, depth]
     */
    protected static function _collectionHierarchy($isPublic=null, $parent=null, $startDepth=0) {
        // Set up holding list
        $hierarchy = array();
        // Find all collection-type sub-collections with the specified parent
        $table = get_db()->getTable('Collection');
        $select = self::attachMetadataToSelect($table->getSelect(),
                array(
                    'parent' => 'iiifitems_collection_parent_element',
                    'title' => get_db()->getTable('Element')->findByElementSetNameAndElementName('Dublin Core', 'Title')->id,
                    'iiiftype' => 'iiifitems_collection_type_element',
                ),
                'Collection', 'collections')->where("iiiftype.text = 'Collection'")->order('title.text ASC');
        if ($isPublic !== null) {
            $table->filterByPublic($select, $isPublic);
        }
        if ($parent === null) {
            $select->where('parent.text IS NULL');
        } else {
            $select->where('parent.text = ?', raw_iiif_metadata($parent, 'iiifitems_collection_uuid_element'));
        }
        $subcollections = $table->fetchObjects($select);
        // For each sub-collection
        foreach ($subcollections as $subcollection) {
            // Add the current collection to the list
            $hierarchy[] = array($subcollection, metadata($subcollection, array('Dublin Core', 'Title'), array('no_filters' => true, 'no_escape' => true)), $startDepth);
            // And add its children too
            foreach (self::_collectionHierarchy($isPublic, $subcollection, $startDepth+1) as $newEntry) {
                $hierarchy[] = $newEntry;
            }
        // End: For each sub-collection
        }
        // Return holding list
        return $hierarchy;
    }
    
    /**
     * Generates the raw tree array for the collection hierarchy (including Manifests)
     * @param boolean|null $isPublic
     * @param Collection $parent
     * @param numeric $startDepth The depth of the starting call
     * @return array Array of entries [collection, title, depth]
     */
    protected static function _fullHierarchy($isPublic=null, $parent=null, $startDepth=0) {
        // Set up holding list
        $hierarchy = array();
        // Find all collection-type sub-collections with the specified parent
        $table = get_db()->getTable('Collection');
        $select = self::attachMetadataToSelect($table->getSelect(),
                array(
                    'parent' => 'iiifitems_collection_parent_element',
                    'title' => get_db()->getTable('Element')->findByElementSetNameAndElementName('Dublin Core', 'Title')->id,
                    'iiiftype' => 'iiifitems_collection_type_element',
                ),
                'Collection', 'collections')->order('title.text ASC');
        if ($isPublic !== null) {
            $table->filterByPublic($select, $isPublic);
        }
        if ($parent === null) {
            $select->where('parent.text IS NULL');
        } else {
            $select->where('parent.text = ?', raw_iiif_metadata($parent, 'iiifitems_collection_uuid_element'));
        }
        $subcollections = $table->fetchObjects($select);
        // For each sub-collection
        foreach ($subcollections as $subcollection) {
            // Add the current collection to the list
            $hierarchy[] = array($subcollection, metadata($subcollection, array('Dublin Core', 'Title'), array('no_filters' => true, 'no_escape' => true)), $startDepth);
            // Go deeper if it's a collection-type collection
            if (raw_iiif_metadata($subcollection, 'iiifitems_collection_type_element') == 'Collection') {
                foreach (self::_fullHierarchy($isPublic, $subcollection, $startDepth+1) as $newEntry) {
                    $hierarchy[] = $newEntry;
                }
            }
        // End: For each sub-collection
        }
        // Return holding list
        return $hierarchy;
    }
    
    /**
     * Generate a path array for the sequence of nodes leading to the given thing
     * @param Collection|Item $thing
     * @return array Full sequence of collections/manifests/items down to the given thing
     * @throws InvalidArgumentException
     */
    protected static function _pathToThing($thing) {
        $path = array($thing);
        switch (get_class($thing)) {
            case 'Item':
                if ($thing->item_type_id == get_option('iiifitems_annotation_item_type')) {
                    if ($annotatedItem = IiifItems_Util_Annotation::findAnnotatedItemFor($thing)) {
                        $path[] = $annotatedItem;
                        $currentCollection = get_record_by_id('Collection', $annotatedItem->collection_id);
                    } else {
                        return $path;
                    }
                } else {
                    if ($thing->collection_id === null) {
                        return $path;
                    } else {
                        $currentCollection = get_record_by_id('Collection', $thing->collection_id);
                    }
                }
                break;
            case 'Collection':
                $currentCollection = IiifItems_Util_Collection::findParentFor($thing);
                break;
            default:
                throw new InvalidArgumentException(__('Thing must be an Item or Collection.'));
        }
        while ($currentCollection) {
            $path[] = $currentCollection;
            $currentCollection = IiifItems_Util_Collection::findParentFor($currentCollection);
        }
        return array_reverse($path);
    }
    
    /**
     * Convert a raw tree array to an options array used in form helpers
     * @param array $hierarchy Raw tree array of entries [collection, title, depth]
     * @param User|null $contributor Return an option that applies to the given contributor User, if provided.
     * @param bool $idValue (optional) Return an option that uses ID numbers instead of UUIDs for values.
     * @return array Form helper options array
     */
    protected static function _hierarchyToOptions($hierarchy, $contributor=null, $idValue=false) {
        // Get flat hierarchy
        $options = array('' => __('No Parent'));
        $disables = array();
        // Repeat over flat hierarchy
        foreach ($hierarchy as $i => $entry) {
            // Add option with indent
            $label = str_repeat("----", $entry[2]) . $entry[1];
            $value = $idValue ? $entry[0]->id : raw_iiif_metadata($entry[0], 'iiifitems_collection_uuid_element');
            $options[$value] = $label;
            if ($contributor !== null && $entry[0]->owner_id != $contributor->id) {
                $disables[] = $i;
            }
        // End: Repeat over flat hierarchy
        }
        // Add disable options
        if ($disables) {
            $options[] = array('disable' => $disables);
        }
        // Return options
        return $options;
    }
    
    /**
     * Convert a raw tree array to a HTML string containing nested unordered lists
     * @param array $hierarchy Raw tree array of entries [collection, title, depth]
     * @return string Equivalent representation in UL form
     */
    protected static function _hierarchyToUl($hierarchy) {
        // Set up holder string
        $ulStr = '';
        // Repeat over flat hierarchy
        $lastDepth = 0;
        foreach ($hierarchy as $entry) {
            // If the indent is the same, just add another li
            if ($entry[2] == $lastDepth) {
                $ulStr .= "<li>" . html_escape($entry[1]) . "</li>";
            // If the indent has increased, add li with recursed ul
            } elseif ($entry[2] > $lastDepth) {
                $ulStr .= "<ul><li>" . html_escape($entry[1]) . "</li>";
            // If the indent has decreased, add closing ul tags before adding the li
            } else {
                $ulStr .= str_repeat("</ul>", $lastDepth-$entry[2]) . "<li>" . html_escape($entry[1]) . "</li>"; 
            // End: Indent change
            }
            $lastDepth = $entry[2];
        // End: Repeat over flat hierarchy
        }
        // Return options
        return "<ul>{$ulStr}</ul>";
    }
    
    /**
     * Return form helper options array for all collections, in tree-like form.
     * @param boolean|null $isPublic
     * @param User|null $contributor Return an option that applies to the given contributor User, if provided.
     * @return array
     */
    public static function getCollectionOptions($isPublic=null, $contributor=null) {
        return self::_hierarchyToOptions(self::_collectionHierarchy($isPublic, $contributor));
    }
    
    /**
     * Return form helper options array for all collections and manifests, in tree-like form.
     * @param boolean|null $isPublic
     * @param User|null $contributor Return an option that applies to the given contributor User, if provided.
     * @return array
     */
    public static function getFullOptions($isPublic=null, $contributor=null) {
        return self::_hierarchyToOptions(self::_fullHierarchy($isPublic, $contributor));
    }
    
    /**
     * Return form helper options array for all collections and manifests, in tree-like form. Uses ID-based values instead of UUID values.
     * @param boolean|null $isPublic
     * @param User|null $contributor Return an option that applies to the given contributor User, if provided.
     * @return array
     */
    public static function getFullIdOptions($isPublic=null, $contributor=null) {
        return self::_hierarchyToOptions(self::_fullHierarchy($isPublic, $contributor), $contributor, true);
    }
    
    /**
     * Return HTML representation for all collections, in nested unordered list form.
     * @param boolean|null $isPublic
     * @return string
     */
    public static function getCollectionUl($isPublic=null) {
        return self::_hierarchyToUl(self::_collectionHierarchy($isPublic));
    }
    
    /**
     * Return the HTML representation for all collections and manifests, in nested unordered list form.
     * @param boolean|null $isPublic
     * @return string
     */
    public static function getFullUl($isPublic=null) {
        return self::_hierarchyToUl(self::_fullHierarchy($isPublic));
    }
    
    /**
     * Return HTML string representing a path to the current collection/item, in nested list form
     * @param Collection|Item $thing
     * @param boolean $linked
     * @return string
     */
    public static function getPathUl($thing, $linked) {
        // Get current path
        $path = self::_pathToThing($thing);
        $ulStr = '';
        // Print nested list to thing
        $currentCount = 0;
        $pathLength = count($path);
        foreach ($path as $pathElement) {
            $isLast = ++$currentCount == $pathLength;
            switch (get_class($pathElement)) {
                case 'Collection':
                    $ulStr .= "<li>" 
                            . (($linked && !$isLast) ? ('<a href="' . url(array('id' => $pathElement->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">') : "") 
                            . metadata($pathElement, array('Dublin Core', 'Title')) 
                            . (($linked && !$isLast) ? "</a>" : "") 
                            . "</li><ul>";
                    break;
                case 'Item':
                    $ulStr .= "<li>" 
                            . (($linked && !$isLast) ? ('<a href="' . url(array('id' => $pathElement->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">') : "") 
                            . metadata($pathElement, array('Dublin Core', 'Title')) 
                            . (($linked && !$isLast) ? "</a>" : "") 
                            . "</li><ul>";
                    break;
            }
        }
        $ulStr .= str_repeat("</ul>", $pathLength);
        return "<ul>{$ulStr}</ul>";
    }
    
    /**
     * Return HTML string representing a pth to the current collection/item, in linear breadcrumb form
     * @param type $thing
     * @param boolean $linked
     * @param boolean $includeTop
     * @return string
     */
    public static function getPathBreadcrumb($thing, $linked, $includeTop=true) {
        // Get current path
        $path = self::_pathToThing($thing);
        $breadcrumbString = '';
        $currentCount = 0;
        $pathLength = count($path);
        foreach($path as $pathElement) {
            $isLast = ++$currentCount == $pathLength;
            switch (get_class($pathElement)) {
                case 'Collection':
                    $breadcrumbString .= (($linked && !$isLast) ? ('<a href="' . url(array('id' => $pathElement->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">') : "")
                        . metadata($pathElement, array('Dublin Core', 'Title'))
                        . (($linked && !$isLast) ? "</a>" : "")
                        . ($isLast ? '' : ' &raquo; ');
                    break;
                case 'Item':
                    $breadcrumbString .= (($linked && !$isLast) ? ('<a href="' . url(array('id' => $pathElement->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">') : "")
                        . metadata($pathElement, array('Dublin Core', 'Title'))
                        . (($linked && !$isLast) ? "</a>" : "")
                        . ($isLast ? '' : ' &raquo; ');
                    break;
            }
            $first = false;
        }
        if ($includeTop) {
            $breadcrumbString = '<a href="' . url(array('id' => $thing->id, 'controller' => 'collections', 'action' => 'show'), 'id') . '">' . __('Top') . '</a> &raquo; ' . $breadcrumbString;
        }
        return $breadcrumbString;
    }
    
    /**
     * Return an array of IDs. For use with submember search.
     * @param Collection $parent
     * @param bool $isPublic
     * @return int[]
     */
    public static function getFullSubmemberIdArray($parent=null, $isPublic=null) {
        $fullHierarchy = self::_fullHierarchy($isPublic, $parent);
        $idArray = array();
        foreach ($fullHierarchy as $entry) {
            $idArray[] = $entry[0]->id;
        }
        return $idArray;
    }
}
