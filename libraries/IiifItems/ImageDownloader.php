<?php

/**
 * Utility for downloading a IIIF image
 * @package IiifItems
 */
class IiifItems_ImageDownloader {
    private $prefix, $suffix, $defaultXywh, $originalId, $json;
    
    /**
     * Create a new image downloader.
     * 
     * @param array $imageJson IIIF presentation API image JSON data
     */
    public function __construct($imageJson) {
        $this->prefix = $imageJson['resource']['service']['@id'];
        $this->suffix = $this->__getIiifImageSuffix($imageJson);
        $this->defaultXywh = array(0, 0, $imageJson['resource']['width'], $imageJson['resource']['height']);
        $this->originalId = $imageJson['@id'];
        $this->json = $imageJson;
    }
    
    /**
     * Download to a local file and return whether successful.
     * 
     * @param string $filename The full file name to download to
     * @param array $xywh 4-entry array listing region x, y, width and height in order
     * @param array $sizes List of sizes to try in order. Can include 'full' and/or integer dimensions
     * @return boolean Whether the download was successful
     */
    public function downloadToLocalFile($filename, $xywh='full', $sizes=array('full', 512, 96)) {
        foreach ($sizes as $trySize) {
            $imageUrl = $this->__buildUrl($xywh, $trySize);
            $client = new Zend_Http_Client($imageUrl, array(
                'useragent' => 'Omeka/' . OMEKA_VERSION,
            ));
            try {
                $client->setHeaders('Accept-encoding', 'identity');
                $client->setStream($filename);
                $client->request('GET');
                return true;
            } catch (Exception $e) {
                debug(__("Download with size %s failed, trying next...", $trySize));
            }
        }
        return false;
    }
    
    /**
     * Download as a file attachment to an Omeka Item and return whether successful.
     * 
     * @param Item $item The Omeka Item to attach to
     * @param array $xywh 4-entry array listing region x, y, width and height in order
     * @param array $trySizes List of sizes to try in order. Can include 'full' and/or integer dimensions
     * @return boolean Whether the download was successful
     */
    public function downloadToItem($item, $xywh='full', $trySizes=array('full', 512, 96)) {
        foreach ($trySizes as $trySize) {
            try {
                $imageUrl = $this->__buildUrl($xywh, $trySize);
                debug(__("Downloading image %s", $imageUrl));
                $downloadedFile = insert_files_for_item($item, 'Url', $imageUrl)[0];
                debug(__("Download OK: %s", $imageUrl));
                $downloadedFile->addElementTextsByArray(array(
                    'IIIF File Metadata' => array(
                        'Original @id' => array(array('text' => $this->originalId, 'html' => false)),
                        'JSON Data' => array(array('text' => json_encode($this->json, JSON_UNESCAPED_SLASHES), 'html' => false)),
                    ),
                ));
                $downloadedFile->saveElementTexts();
                return $downloadedFile;
            } catch (Exception $e) {
                debug(__("Download with size %s failed, trying next...", $trySize));
            }
        }
        return null;
    }
    
    /**
     * Return the appropriate image URL to use, depending on the version context specified in the image JSON data.
     * Uses native.jpg for IIIF 1.x, default.jpg for IIIF 2.x and up.
     * 
     * @param array $image IIIF presentation API image JSON data
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
    
    /**
     * Return the full URL to download from, given IIIF region and size parameters.
     * 
     * @param array $xywh 4-entry array listing region x, y, width and height in order
     * @param array $trySizes List of sizes to try in order. Can include 'full' and/or integer dimensions
     * @return string
     */
    private function __buildUrl($xywh, $trySize) {
        if (is_string($xywh)) {
            $region = $xywh;
            $realXywh = $this->defaultXywh;
        } else {
            $region = join(',', $xywh);
            $realXywh = $xywh;
        }
        if ($trySize === 'full') {
            $theSize = 'full';
        } else {
            $theSize = ($realXywh[2] >= $realXywh[3]) ? (','.$trySize) : ($trySize.',');
        }
        return $this->prefix . '/' . $region . '/' . $theSize . '/0/' . $this->suffix;
    }
}
