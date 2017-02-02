<?php

class IiifItems_Job_AddAnnotationThumbnail extends Omeka_Job_AbstractJob {
    private $_originalItem, $_annotationItem, $_dims;
    
    public function __construct(array $options) {
        parent::__construct($options);
        $this->_originalItem = get_record_by_id('Item', $options['originalItemId']);
        $this->_annotationItem = get_record_by_id('Item', $options['annotationItemId']);
        $this->_dims = $options['dims'];
    }
    
    public function perform() {
        if ($this->_originalItem && $this->_annotationItem && count($this->_dims) == 4) {
            $this->__attachAnnotationPreview($this->_originalItem, $this->_annotationItem, $this->_dims);
        }
    }
    
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
                $prefix = rtrim(IiifItems_CanvasUtil::fileIiifPrefix($originalFile), '/');
                $suffix = 'default.jpg';
            }
        } else {
            $prefix = rtrim(IiifItems_CanvasUtil::fileIiifPrefix($originalFile), '/');
            $suffix = 'default.jpg';
        }
        $this->__downloadIiifImageToItem($annotationItem, $prefix, $dims, $suffix);
    }
    
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
                debug("Downloading image " . $imageUrl);
                $downloadedFile = insert_files_for_item($item, 'Url', $imageUrl)[0];
                debug("Download OK: " . $imageUrl);
                return;
            } catch (Exception $e) {
                debug("Download with size " . $trySize . " failed, trying next...");
            }
        }
    }
    
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
