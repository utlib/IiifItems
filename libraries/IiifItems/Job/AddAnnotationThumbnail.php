<?php

/**
 * Background job for adding the thumbnail back onto an annotation-type item
 * @package IiifItems
 * @subpackage Job
 */
class IiifItems_Job_AddAnnotationThumbnail extends Omeka_Job_AbstractJob {
    private $_originalItem, $_annotationItem, $_dims;
    
    /**
     * Create a new IiifItems_Job_AddAnnotationThumbnail.
     * @param array $options
     */
    public function __construct(array $options) {
        parent::__construct($options);
        $this->_originalItem = get_record_by_id('Item', $options['originalItemId']);
        $this->_annotationItem = get_record_by_id('Item', $options['annotationItemId']);
        $this->_dims = $options['dims'];
    }
    
    /**
     * Main runnable method.
     */
    public function perform() {
        if ($this->_originalItem && $this->_annotationItem && count($this->_dims) == 4) {
            $this->__attachAnnotationPreview($this->_originalItem, $this->_annotationItem, $this->_dims);
        }
    }
    
    /**
     * Attach a preview file to the given annotation item.
     * 
     * @param Item $originalItem The item referenced by the annotation item
     * @param Item $annotationItem The annotation item
     * @param array $dims 4-entry array with x, y, width and height
     */
    private function __attachAnnotationPreview($originalItem, $annotationItem, $dims) {
        // Find the first file as a representative
        $originalFile = $originalItem->getFile();
        // Get the prefix
        if ($fileJsonStr = raw_iiif_metadata($originalFile, 'iiifitems_file_json_element')) {
            $fileJsonData = json_decode($fileJsonStr, true);
            if (isset($fileJsonData['resource']) && isset($fileJsonData['resource']['service'])) {
                $prefix = $fileJsonData['resource']['service']['@id'];
                $suffix = $this->__getIiifImageSuffix($fileJsonData);
            } else {
                $prefix = rtrim(IiifItems_Util_Canvas::fileIiifPrefix($originalFile), '/');
                $suffix = 'default.jpg';
            }
        } else {
            $prefix = rtrim(IiifItems_Util_Canvas::fileIiifPrefix($originalFile), '/');
            $suffix = 'default.jpg';
        }
        $this->__downloadIiifImageToItem($annotationItem, $prefix, $dims, $suffix);
    }
    
    /**
     * Helper for downloading a IIIF image to an Omeka Item.
     * 
     * @param Item $item
     * @param string $prefix The part of the IIIF URL before transformations
     * @param array $dims 4-entry array with x, y, width, height
     * @param string $suffix The part of the IIIF URL after transformations (e.g. default.jpg)
     */
    private function __downloadIiifImageToItem($item, $prefix, $dims, $suffix) {
        $trySizes = array('full', 512, 96);
        foreach ($trySizes as $trySize) {
            try {
                if ($trySize === 'full') {
                    $theSize = 'full';
                } else {
                    $theSize = ($dims[2] >= $dims[3]) ? (','.$trySize) : ($trySize.',');
                }
                $imageUrl = $prefix . '/' . join(',', $dims) . '/' . $theSize . '/0/' . $suffix;
                debug(__("Downloading image %s", $imageUrl));
                $downloadedFile = insert_files_for_item($item, 'Url', $imageUrl)[0];
                debug(__("Download OK: %s", $imageUrl));
                return;
            } catch (Exception $e) {
                debug(__("Download with size %s failed, trying next...", $trySize));
            }
        }
    }
    
    /**
     * Helper for returning the correct suffix according to the IIIF version supported.
     * 
     * @param array $image IIIF Presentation API of an image
     * @return string
     */
    private function __getIiifImageSuffix($image) {
        try {
            switch ($image['resource']['service']['@context']) {
                case 'http://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                case 'http://iiif.io/api/image/1/context.json':
                case 'https://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                case 'https://iiif.io/api/image/1/context.json':
                    return 'native.jpg';
                case 'http://iiif.io/api/image/2/context.json':
                case 'https://iiif.io/api/image/2/context.json':
                    return 'default.jpg';
            }
            switch ($image['resource']['service']['profile']) {
                case 'http://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                case 'https://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                    return 'native.jpg';
            }
        }
        catch (Exception $e) {
            return 'native.jpg';
        }
        return 'native.jpg';
    }
}
