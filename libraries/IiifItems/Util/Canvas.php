<?php
/**
 * Utilities for IIIF canvases.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_Canvas extends IiifItems_IiifUtil {
    /**
     * Basic template for IIIF Presentation API canvas
     * @param string $atId The unique URI ID for this canvas
     * @param integer $width
     * @param integer $height
     * @param string $label
     * @param array $images A list of images according to the IIIF Presentation API
     * @return array
     */
    public static function blankTemplate($atId, $width, $height, $label, $images=array()) {
        return array(
            '@id' => $atId,
            'label' => $label,
            '@type' => 'sc:Canvas',
            'width' => $width,
            'height' => $height,
            'images' => $images,
        );
    }

    /**
     * Return the IIIF Presentation API canvas representation of an ordinary item
     * @param Item $item Non-annotation-typed item
     * @param string $canvasId (optional) Replacement canvas URI
     * @param boolean $applyDublin (optional) Whether to apply Dublin Core metadata
     * @param array $images (optional) An array of IIIF Presentation API images
     * @return array
     */
    public static function buildCanvas($item, $canvasId=null, $applyDublin=true) {
        // Fetch the canvas for the given item from IIIF metadata
        try {
            $iiifJsonData = parent::fetchJsonData($item);
            if ($iiifJsonData) {
                $canvasId = $iiifJsonData['@id'];
            }
        } catch (Exception $e) {
        }
        // If unavailable or corrupted, generate a default
        if (!$canvasId) {
            $canvasId = public_full_url(array(
                'things' => 'items',
                'id' => $item->id,
                'typeext' => 'canvas.json',
            ), 'iiifitems_oa_uri');
        }
        if (!isset($iiifJsonData)) {
            $iiifJsonData = self::blankTemplate($canvasId, 0, 0, metadata($item, array('Dublin Core', 'Title'), array('no_escape' => true)));
        }
        // Add the Image for each file in order
        $itemFiles = $item->getFiles();
        if (self::_containsNonIiifFile($itemFiles)) {
            $representativeType = self::_getNonIiifType($itemFiles);
            $representativeUrlBase = str_replace(
                array('{FILENAME}', '{EXTENSION}', '{FULLNAME}'), 
                array('iiifitems_' . $representativeType, 'jpg', 'iiifitems_' . $representativeType . '.jpg'), 
                get_option('iiifitems_bridge_prefix')
            );
            $iiifJsonData['images'][] = array(
                '@id' => public_full_url(array(
                    'things' => 'item',
                    'id' => $item->id,
                    'typeext' => 'anno.json',
                ), 'iiifitems_oa_uri'),
                '@type' => 'oa:Annotation',
                'motivation' => 'sc:painting',
                'on' => $canvasId,
                'resource' => array(
                    '@id' => $representativeUrlBase . '/full/full/0/default.jpg',
                    '@type' => 'dctypes:Image',
                    'format' => 'image/jpeg',
                    'width' => 300,
                    'height' => 300,
                    'service' => array(
                        '@id' => $representativeUrlBase,
                        '@context' => 'http://iiif.io/api/image/2/context.json',
                        'profile' => 'http://iiif.io/api/image/2/level2.json',
                    ),
                ),
            );
            $iiifJsonData['width'] = 300;
            $iiifJsonData['height'] = 300;
        }
        else {
            if (!empty($itemFiles)) {
                if (!isset($iiifJsonData['images']) || empty($iiifJsonData['images']) || parent::fetchJsonData($itemFiles[0])) {
                    $iiifJsonData['images'] = array();
                    foreach ($itemFiles as $file) {
                        $iiifJsonData['images'][] = self::fileImageJson($file, $canvasId);
                    }
                }
                // If the default canvas template is used, set the width and height to the max among files
                if (!$iiifJsonData['width'] && !$iiifJsonData['height']) {
                    foreach ($iiifJsonData['images'] as $fileJson) {
                        if ($fileJson['resource']['width'] > $iiifJsonData['width']) {
                            $iiifJsonData['width'] = $fileJson['resource']['width'];
                        }
                        if ($fileJson['resource']['height'] > $iiifJsonData['height']) {
                            $iiifJsonData['height'] = $fileJson['resource']['height'];
                        }
                    }
                }
            }
        }
        // Plug DC metadata
        if ($applyDublin) {
            parent::addDublinCoreMetadata($iiifJsonData, $item);
        }
        // Plug otherContent for annotation lists
        if (is_admin_theme()) {
            $iiifJsonData['otherContent'] = array();
        } else {
            $iiifJsonData['otherContent'] = array(array(
                '@id' => public_full_url(array(
                    'things' => 'items',
                    'id' => $item->id,
                    'typeext' => 'annolist.json',
                ), 'iiifitems_oa_uri'),
                '@type' => 'sc:AnnotationList',
            ));
        }
        // Done
        return $iiifJsonData;
    }
    
    /**
     * Return the IIIF Presentation API canvas representation of a single annotation on its attached item
     * @param Item $annotation Annotation-typed item
     * @param string $canvasId (optional) Replacement canvas URI
     * @param boolean $applyDublin (optional) Whether to apply Dublin Core metadata
     * @return array
     */
    public static function buildAnnotationCanvas($annotation, $canvasId=null, $applyDublin=true) {
        $base = self::buildCanvas(IiifItems_Util_Annotation::findAnnotatedItemFor($annotation));
        $base['otherContent'] = array(array(
            '@id' => public_full_url(array(
                'things' => 'items',
                'id' => $annotation->id,
                'typeext' => 'annolist.json',
            ), 'iiifitems_oa_uri'),
            '@type' => 'sc:AnnotationList',
        ));
        return $base;
    }
    
    /**
     * Return the IIIF Presentation API image representation of a file
     * @param File $file
     * @param string $on The canvas URI this that the file is attached to
     * @param boolean $force (optional) Whether to override the canvas URI, if already specified from import
     * @return array
     */
    public static function fileImageJson($file, $on, $force=false) {
        // Try to build JSON from imported IIIF metadata
        try {
            if ($iiifJsonData = parent::fetchJsonData($file)) {
                if ($force || !isset($iiifJsonData['on'])) {
                    $iiifJsonData['on'] = $on;
                }
                return $iiifJsonData;
            }
        }
        catch (Exception $e) {
        }
        // If missing or failed, build from file data
        list($fileWidth, $fileHeight) = getimagesize(FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath());
        $fileJson = array(
            '@id' => public_full_url(array(
                'things' => 'files',
                'id' => $file->id,
                'typeext' => 'anno.json',
            ), 'iiifitems_oa_uri'),
            'motivation' => 'sc:painting',
            '@type' => 'oa:Annotation',
            'resource' => array(
                '@id' => $file->getWebPath('original'),
                '@type' => 'dctypes:Image',
                'format' => $file->mime_type,
                'width' => $fileWidth,
                'height' => $fileHeight,
                'service' => array(
                    '@id' => self::fileIiifPrefix($file),
                    '@context' => 'http://iiif.io/api/image/2/context.json',
                    'profile' => 'http://iiif.io/api/image/2/level2.json',
                ),
            ),
            'on' => $on,
        );
        return $fileJson;
    }
    
    /**
     * Return the IIIF prefix for the given local File record.
     * @param File $file
     * @return string
     */
    public static function fileIiifPrefix($file) {
        $bridgePrefix = get_option('iiifitems_bridge_prefix');
        $replacedBridgePrefix = str_replace(
            array('{FILENAME}', '{EXTENSION}', '{FULLNAME}'), 
            array(basename($file->filename), $file->DERIVATIVE_EXT, $file->filename), 
            $bridgePrefix
        );
        return $replacedBridgePrefix;
    }

    /**
     * Return a quick-display IIIF Presentation API canvas representation of a file
     * @param File $file
     * @param string $canvasId (optional) Replacement canvas URI
     * @param boolean $applyDublin (optional) Whether to apply Dublin Core metadata
     * @return array
     */
    public static function fileCanvasJson($file, $canvasId=null, $applyDublin=false) {
        // Default IDs and display titles
        if (!$canvasId) {
            $canvasId = public_full_url(array(
                'things' => 'files',
                'id' => $file->id,
                'typeext' => 'canvas.json',
            ), 'iiifitems_oa_uri');
        }
        $displayTitle = metadata($file, 'display_title', array('no_escape' => true));
        // Get the file's Image JSON and force its ID to re-point here
        $fileImageJson = self::fileImageJson($file, $canvasId, true);
        // Create IIIF Presentation 2.0 top-level template
        $json = self::blankTemplate($canvasId, $fileImageJson['resource']['width'], $fileImageJson['resource']['height'], $displayTitle, array($fileImageJson));
        // Apply Dublin Core metadata
        if ($applyDublin) {
            parent::addDublinCoreMetadata($json, $file);
        }
        return $json;
    }
    
    /**
     * Return whether this item is not presentable in IIIF
     * @param Item $item
     * @return boolean
     */
    public static function isNonIiifItem($item) {
        return ($item->item_type_id != get_option('iiifitems_annotation_item_type') && self::_containsNonIiifFile($item->getFiles())) || raw_iiif_metadata($item, 'iiifitems_item_display_element') == 'Never';
    }
    
    /**
     * Return whether the array contains non-IIIF File records
     * @param array $files
     * @return boolean
     */
    protected static function _containsNonIiifFile($files) {
        foreach ($files as $file) {
            $mime = $file->mime_type;
            switch ($mime) {
                case 'image/jpeg': case 'image/tiff': case 'image/png': case 'image/jp2':
                    break;
                default:
                    return true;
            }
        }
        return false;
    }
    
    /**
     * Return the multimedia type of the files given.
     * @param array $files
     * @return string "audio", "file", "image", "pdf", "text" or "video"
     */
    protected static function _getNonIiifType($files) {
        $candidateType = null;
        // Go through the list of files
        foreach ($files as $file) {
            // Sniff mime types
            $currentMime = $file->mime_type;
            if ($currentMime == 'application/pdf') {
                $currentType = 'pdf';
            }
            elseif ($currentMime == 'application/zip' || strpos($currentMime, 'compressed') !== false || $currentMime == 'application/x-gtar' || $currentMime == 'application/x-tar' || $currentMime == 'application/gzip') {
                $currentType = 'zip';
            }
            else {
                switch ($currentMimePrefix = substr($currentMime, 0, 5)) {
                    case 'audio': case 'image': case 'video':
                        $currentType = $currentMimePrefix;
                    break;
                    case 'text/':
                        $currentType = 'text';
                    break;
                    default:
                        $currentType = 'file';
                    break;
                }
            }
            // First file should determine the starting type
            if ($candidateType === null) {
                $candidateType = $currentType;
            }
            // Otherwise, if the file doesn't match the first file's type, return mixed
            else if ($candidateType != $currentType) {
                return 'mixed';
            }
        }
        return ($candidateType === null) ? 'none' : $candidateType;
    }
}