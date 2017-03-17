<?php
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
        $annotations = array();
        if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
            return self::blankListTemplate($atId, array(self::buildAnnotation($item)));
        } else {
            return self::blankListTemplate($atId, self::findAnnotationsFor($item));
        }
    }

    /**
     * Convert an annotation item to JSON object form
     * @param Item $annoItem An annotation-type item
     * @return array
     */
    public static function buildAnnotation($annoItem) {
        $elementTextTable = get_db()->getTable('ElementText');
        $currentAnnotationJson = json_decode($elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
            get_option('iiifitems_item_json_element'),
            $annoItem->id,
        ))[0], true);
        $currentText = $elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
            get_option('iiifitems_annotation_text_element'),
            $annoItem->id,
        ));
        if ($currentText) {
            $currentText = $currentText[0];
        } else {
            $currentText = "";
        }
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
        return $currentAnnotationJson;
    }
    
    /**
     * Return an array of annotations for an item, as JSON objects.
     * @param type $item
     * @return array
     */
    public static function findAnnotationsFor($item) {
        if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
            return $item;
        }
        // TENTATIVE: Accept annotations on non-IIIF items for now, but seed it with download links annotation.
        if (IiifItems_CanvasUtil::isNonIiifItem($item) && !is_admin_theme()) {
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
            ))[0], true);
            $currentText = $elementTextTable->findBySql("element_texts.element_id = ? AND element_texts.record_type = 'Item' AND element_texts.record_id = ?", array(
                get_option('iiifitems_annotation_text_element'),
                $onCanvasMatch->record_id,
            ));
            if ($currentText) {
                $currentText = $currentText[0];
            } else {
                continue;
            }
            $currentAnnotationJson['resource'] = array(
                array(
                    '@type' => 'dctypes:Text',
                    'format' => $currentText->html ? 'text/html' : 'text/plain',
                    'chars' => $currentText->text,
                ),
            );
            foreach (get_record_by_id('Item', $onCanvasMatch->record_id)->getTags() as $tag) {
                $currentAnnotationJson['resource'][] = array(
                    '@type' => 'oa:Tag',
                    'chars' => $tag->name,
                );
            }
            $annoItems[] = $currentAnnotationJson;
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
            break;
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
        ))[0];
        $theItem = get_record_by_id('Item', $theItemCanvasIdText->record_id);
        return $theItem;
    }
}