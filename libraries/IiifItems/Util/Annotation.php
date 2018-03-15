<?php

/**
 * Utilities for IIIF annotations.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_Annotation extends IiifItems_IiifUtil {
    /**
     * Return a bare template for an annotation list
     * @param string $atId The JSON-LD ID to use
     * @param array $annotations List of annotation arrays
     * @return array
     */
    public static function blankListTemplate($atId, $annotations=array()) {
        return array(
            '@id' => $atId,
            '@context' => 'http://www.shared-canvas.org/ns/context.json',
            '@type' => 'sc:AnnotationList',
            'resources' => $annotations,
        );
    }

    /**
     * Return a bare template for an annotation
     * @param string $atId The JSON-LD Id to use
     * @param string $text The annotation text
     * @param boolean $isHtml Whether the text is HTML
     * @param string $svgSelector The SVG selector of the annotation
     * @param string $onCanvas The URI of the canvas it is attached to
     * @param array $tags An array of tags
     * @return array
     */
    public static function blankAnnotationTemplate($atId, $text, $isHtml, $svgSelector, $onCanvas, $tags=array()) {
        // Build the resource fragment
        $resourceFragments = array(array(
            '@type' => 'dctypes:Text',
            'format' => $isHtml ? 'text/html' : 'text/plain',
            'chars' => $text,
        ));
        // Attach tags
        foreach ($tags as $tag) {
            $resourceFragments[] = array(
                '@type' => 'oa:Tag',
                'chars' => $tag,
            );
        }
        // Fill in the template
        return array(
            '@id' => $atId,
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@type' => 'oa:Annotation',
            'motivation' => $tags ? array('oa:commenting', 'oa:tagging') : array('oa:commenting'),
            'resource' => $resourceFragments,
            'on' => array(
                '@type' => 'oa:SpecificResource',
                'full' => $onCanvas,
                'selector' => array(
                    '@type' => 'oa:SvgSelector',
                    'value' => $svgSelector,
                ),
            ),
        );
    }
    
    /**
     * Return the annotation list for an item (regular or annotation)
     * @param Item $item The item to build an annotation list from
     * @return array
     */
    public static function buildList($item) {
        $atId = public_full_url(array('things' => 'items', 'id' => $item->id, 'typeext' => 'annolist.json'), 'iiifitems_oa_uri');
        if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
            return self::blankListTemplate($atId, array(self::buildAnnotation($item)));
        } else {
            return self::blankListTemplate($atId, self::findAnnotationsFor($item));
        }
    }

    /**
     * Convert an annotation item to JSON object form
     * @param Item $annoItem An annotation-type item
     * @param string $forcedXywhMode ""=Default behaviour, "skip"=Force 0,0,0,0 if annotation has no xywh, "fill"=Force 0,0,width,height if annotation has no xywh
     * @return array
     */
    public static function buildAnnotation($annoItem, $forcedXywhMode='') {
        $elementTextTable = get_db()->getTable('ElementText');
        $currentAnnotationJson = json_decode($elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
            get_option('iiifitems_item_json_element'),
            $annoItem->id,
        ), true), true);
        $currentText = $elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
            get_option('iiifitems_annotation_text_element'),
            $annoItem->id,
        ), true);
        $currentAnnotationJson['resource'] = array(
            array(
                '@type' => 'dctypes:Text',
                'format' => $currentText->html ? 'text/html' : 'text/plain',
                'chars' => $currentText->text,
            ),
        );
        foreach ($annoItem->getTags() as $tag) {
            $currentAnnotationJson['resource'][] = array(
                '@type' => 'oa:Tag',
                'chars' => $tag->name,
            );
        }
        if ($attachedItem = IiifItems_Util_Annotation::findAnnotatedItemFor($annoItem)) {
            if (!($canvasId = raw_iiif_metadata($attachedItem, 'iiifitems_item_atid_element'))) {
                $canvasId = public_full_url(array('things' => 'items', 'id' => $attachedItem->id, 'typeext' => 'canvas.json'), 'iiifitems_oa_uri');
            }
            $svgSelectors = self::getAnnotationSvg($annoItem);
            $xywhSelectors = self::getAnnotationXywh($annoItem);
            $svgSelector = empty($svgSelectors) ? null : $svgSelectors[0];
            $xywhSelector = empty($xywhSelectors) ? null : $xywhSelectors[0];
            if ($svgSelector || $xywhSelector) {
                unset($currentAnnotationJson['on']);
                // Forced xywh-only format
                if ($forcedXywhMode) {
                    if ($xywhSelector) {
                        $currentAnnotationJson['on'] = $canvasId . '#xywh=' . $xywhSelector;
                    } else {
                        $itemCanvas = IiifItems_Util_Canvas::buildCanvas($attachedItem, '', false);
                        if ($forcedXywhMode == 'fill') {
                            $currentAnnotationJson['on'] = $canvasId . '#xywh=0,0,' . $itemCanvas['width'] . ',' . $itemCanvas['height'];
                        } else {
                            $currentAnnotationJson['on'] = $canvasId . '#xywh=0,0,0,0';
                        }
                    }
                    $currentAnnotationJson['resource'] = array(
                        '@type' => 'cnt:ContentAsText',
                        'chars' => $currentText->text,
                    );
                }
                // Non-forced format
                else {
                    // Mirador 2.3+ format
                    if ($svgSelector && $xywhSelector) {
                        $currentAnnotationJson['on'] = array();
                        $areas = min(count($svgSelectors), count($xywhSelectors));
                        for ($i = 0; $i < $areas; $i++) {
                            $currentAnnotationJson['on'][] = array(
                                '@type' => 'oa:SpecificResource',
                                'full' => $canvasId,
                                'selector' => array(
                                    '@type' => 'oa:Choice',
                                    'default' => array(
                                        '@type' => 'oa:FragmentSelector',
                                        'value' => 'xywh=' . $xywhSelectors[$i],
                                    ),
                                    'item' => array(
                                        '@type' => 'oa:SvgSelector',
                                        'value' => $svgSelectors[$i],
                                    ),
                                ),
                            );
                        }
                    }
                    // xywh-only format
                    elseif ($xywhSelector) {
                        $currentAnnotationJson['on'] = $canvasId . '#xywh=' . $xywhSelector;
                    }
                    // Mirador 2.2- format
                    else {
                        $currentAnnotationJson['on'] = array(
                            '@type' => 'oa:SpecificResource',
                            'full' => $canvasId,
                            'selector' => array(
                                '@type' => 'oa:SvgSelector',
                                'value' => $svgSelector,
                            ),
                        );
                    }
                }
            }
        }
        return $currentAnnotationJson;
    }
    
    /**
     * Return the SVG selectors of the given annotation-type item.
     * @param Item $annoItem The annotation-type item
     * @return string[]
     */
    public static function getAnnotationSvg($annoItem) {
        $svgTexts = get_db()->getTable('ElementText')->findBySql("element_texts.record_id = ? AND element_texts.record_type = 'Item' AND element_texts.element_id = ?", array($annoItem->id, get_option('iiifitems_annotation_selector_element')));
        $svgs = array();
        foreach ($svgTexts as $svgText) {
            $svgs[] = $svgText->text;
        }
        return $svgs;
    }
    
    /**
     * Return the xywh regions of the given annotation-type item.
     * @param Item $annoItem The annotation-type item
     * @param boolean $arrayForm Whether to return results as an array of strings (false, default) or of 4-entry arrays (true)
     * @return string[]|array[] 
     */
    public static function getAnnotationXywh($annoItem, $arrayForm=false) {
        $xywhTexts = get_db()->getTable('ElementText')->findBySql("element_texts.record_id = ? AND element_texts.record_type = 'Item' AND element_texts.element_id = ?", array($annoItem->id, get_option('iiifitems_annotation_xywh_element')));
        $xywhs = array();
        foreach ($xywhTexts as $xywhText) {
            if ($arrayForm) {
                $xywhs[] = explode(',', $xywhText->text);
            } else {
                $xywhs[] = $xywhText->text;
            }
        }
        return $xywhs;
    }
    
    /**
     * Return an array of annotations for an item, as JSON objects.
     * @param Item $item
     * @param boolean $withAccess Whether to attach access permission info
     * @return array
     */
    public static function findAnnotationsFor($item, $withAccess=false) {
        if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
            return $item;
        }
        // TENTATIVE: Accept annotations on non-IIIF items for now, but seed it with download links annotation.
        if (IiifItems_Util_Canvas::isNonIiifItem($item) && !is_admin_theme()) {
            $annoItems = self::findAnnotationsForNonIiif($item);
        } else {
            $annoItems = array();
        }
        $elementTextTable = get_db()->getTable('ElementText');
        $uuid = raw_iiif_metadata($item, 'iiifitems_item_uuid_element');
        $onCanvasMatches = $elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.text = ?", array(
            get_option('iiifitems_annotation_on_element'),
            $uuid,
        ));
        foreach ($onCanvasMatches as $onCanvasMatch) {
            $currentAnnotationJson = json_decode($elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
                get_option('iiifitems_item_json_element'),
                $onCanvasMatch->record_id,
            ), true)->text, true);
            $currentText = $elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
                get_option('iiifitems_annotation_text_element'),
                $onCanvasMatch->record_id,
            ), true);
            if (!$currentText) {
                continue;
            }
            $currentAnnotationJson['resource'] = array(
                array(
                    '@type' => 'dctypes:Text',
                    'format' => $currentText->html ? 'text/html' : 'text/plain',
                    'chars' => $currentText->text,
                ),
            );
            if ($matchedItem = get_record_by_id('Item', $onCanvasMatch->record_id)) {
                foreach ($matchedItem->getTags() as $tag) {
                    $currentAnnotationJson['resource'][] = array(
                        '@type' => 'oa:Tag',
                        'chars' => $tag->name,
                    );
                }
                if ($withAccess) {
                    $currentAnnotationJson['_iiifitems_access'] = array(
                        'public' => $matchedItem->public,
                        'featured' => $matchedItem->featured,
                        'owner' => $matchedItem->owner_id,
                    );
                }
                $annoItems[] = $currentAnnotationJson;
            }
        }
        return $annoItems;
    }
    
    /**
     * Return an array of annotations for a non-IIIF item, as JSON objects.
     * @param Item $item
     * @return array
     */
    public static function findAnnotationsForNonIiif($item) {
        $fileLinks = array();
        foreach ($item->getFiles() as $file) {
            $fileLinks[] = '<a href="' . $file->getWebPath() . '" target="_blank">' . metadata($file, 'display_title') . '</a>';
        }
        return array(array(
            '@id' => public_full_url(array(
                'things' => 'files',
                'id' => $file->id,
                'typeext' => 'anno.json',
            ), 'iiifitems_oa_uri'),
            '@type' => 'oa:Annotation',
            'motivation' => 'oa:linking',
            'on' => public_full_url(array(
                'things' => 'items',
                'id' => $item->id,
                'typeext' => 'canvas.json',
            ), 'iiifitems_oa_uri') . '#xywh=60,60,180,180',
            'resource' => array(
                '@type' => 'dctypes:Text',
                'format' => 'text/html',
                'chars' => '<ul><li>' . join($fileLinks, '</li><li>') . '</li></ul>'
            ),
        ));
    }
    
    /**
     * Return an array of annotations for a non-annotation item, as Item records.
     * @param type $item
     * @return array
     */
    public static function findAnnotationItemsUnder($item) {
        $elementTextTable = get_db()->getTable('ElementText');
        $uuid = raw_iiif_metadata($item, 'iiifitems_item_uuid_element');
        $onCanvasMatches = $elementTextTable->findBySql("element_texts.record_type = ? AND element_texts.element_id = ? AND element_texts.text = ?", array(
            'Item',
            get_option('iiifitems_annotation_on_element'),
            $uuid,
        ));
        $annoItems = array();
        foreach ($onCanvasMatches as $onCanvasMatch) {
            $annoItems[] = get_record_by_id('Item', $onCanvasMatch->record_id);
        }
        return $annoItems;
    }

    /**
     * Return the correct annotation Item corresponding to $uri for the context being annotated
     * @param mixed $context The Item or Collection being annotated
     * @param string $uri
     * @return Item|null
     */
    public static function findAnnotationInContextByUri($context, $uri) {
        // Find all annotations with that URI
        $uriMatches = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ? AND element_texts.record_type = ?", array(
            get_option('iiifitems_item_atid_element'),
            $uri,
            get_class($context),
        ));
        switch (get_class($context)) {
            case 'Collection':
                // Return the first that is attached to the context
                foreach ($uriMatches as $uriMatch) {
                    $uriMatchAnnotation = get_record_by_id('Item', $uriMatch->record_id);
                    if ($uriMatchAnnotation->item_type_id != get_option('iiifitems_annotation_item_type')) {
                        continue;
                    }
                    $attachment = self::findAnnotatedItemFor($uriMatchAnnotation);
                    if (!$attachment) {
                        continue;
                    }
                    if ($context->id === $attachment->collection_id) {
                        return $uriMatchAnnotation;
                    }
                }
            break;
            case 'Item':        
                // Return the first that is attached to the context
                foreach ($uriMatches as $uriMatch) {
                    $uriMatchAnnotation = get_record_by_id('Item', $uriMatch->record_id);
                    if ($uriMatchAnnotation->item_type_id != get_option('iiifitems_annotation_item_type')) {
                        continue;
                    }
                    $attachment = self::findAnnotatedItemFor($uriMatchAnnotation);
                    if (!$attachment) {
                        continue;
                    }
                    if ($context->id === $attachment->id) {
                        return $uriMatchAnnotation;
                    }
                }
            break;
        }
        return null;
    }
    
    /**
     * Return the correct attachment Item corresponding to $uri for the context being annotated.
     * @param mixed $context The Item or Collection being annotated
     * @param string $uri
     * @return Item|null
     */
    public static function findAttachmentInContextByUri($context, $uri) {
        switch (get_class($context)) {
            case 'Collection':
                // Try 1: Exact canvas ID match
                $originalIdMatches = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ?", array(
                    get_option('iiifitems_item_atid_element'),
                    $uri,
                ));
                foreach ($originalIdMatches as $originalIdMatch) {
                    $candidateItem = get_record_by_id('Item', $originalIdMatch->record_id);
                    if ($candidateItem->collection_id === $context->id) {
                        return $candidateItem;
                    }
                }
                // Try 2: Get from plugin-generated canvas ID ... /items/xxx/canvas.json
                $root = public_full_url(array(), 'iiifitems_root');
                if (strpos($uri, $root) === 0 && ($rpos = strrpos($uri, '/canvas.json')) !== false) {
                    $uriComps = explode('/', substr($uri, 0, $rpos));
                    $candidateItem = get_record_by_id('Item', $uriComps[count($uriComps)-1]);
                    if ($candidateItem->collection_id === $context->id) {
                        return $candidateItem;
                    }
                } 
            break;
            case 'Item':
                return $context;
        }
        return null;
    }
    
    /**
     * Return the item to which this annotation is attached.
     * @param Item $annotation The annotation item
     * @return Item|null
     */
    public static function findAnnotatedItemFor($annotation) {
        $uuid = $annotation->getElementTextsByRecord(get_record_by_id('Element', get_option('iiifitems_annotation_on_element')))[0]->text;
        $theItemCanvasIdText = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ? AND element_texts.record_type = 'Item' ", array(
            get_option('iiifitems_item_uuid_element'),
            $uuid,
        ), true);
        $theItem = get_record_by_id('Item', $theItemCanvasIdText->record_id);
        return $theItem;
    }
}