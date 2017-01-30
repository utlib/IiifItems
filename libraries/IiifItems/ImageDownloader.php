<?php

class IiifItems_ImageDownloader {
    private $prefix, $suffix, $defaultXywh, $originalId, $json;
    public function __construct($imageJson) {
        $this->prefix = $imageJson['resource']['service']['@id'];
        $this->suffix = $this->__getIiifImageSuffix($imageJson);
        $this->defaultXywh = array(0, 0, $imageJson['resource']['width'], $imageJson['resource']['height']);
        $this->originalId = $imageJson['@id'];
        $this->json = $imageJson;
    }
    
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
                debug("Download with size " . $trySize . " failed, trying next...");
            }
        }
        return false;
    }
    
    public function downloadToItem($item, $xywh='full', $trySizes=array('full', 512, 96)) {
        foreach ($trySizes as $trySize) {
            try {
                $imageUrl = $this->__buildUrl($xywh, $trySize);
                debug("Downloading image " . $imageUrl);
                $downloadedFile = insert_files_for_item($item, 'Url', $imageUrl)[0];
                debug("Download OK: " . $imageUrl);
                $downloadedFile->addElementTextsByArray(array(
                    'IIIF File Metadata' => array(
                        'Original @id' => array(array('text' => $this->originalId, 'html' => false)),
                        'JSON Data' => array(array('text' => json_encode($this->json, JSON_UNESCAPED_SLASHES), 'html' => false)),
                    ),
                ));
                $downloadedFile->saveElementTexts();
                return $downloadedFile;
            } catch (Exception $e) {
                debug("Download with size " . $trySize . " failed, trying next...");
            }
        }
        return null;
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
