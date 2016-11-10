<?php

class IiifItems_Job_Import extends Omeka_Job_AbstractJob {
    private $_importType, $_importSource, $_importSourceBody, $_importPreviewSize, $_isPublic, $_isFeatured;
    private $_statusId;
    
    public function __construct(array $options) {
        parent::__construct($options);
        $this->_importType = $options['importType'];
        $this->_importSource = $options['importSource'];
        $this->_importSourceBody = $options['importSourceBody'];
        $this->_importPreviewSize = $options['importPreviewSize'];
        $this->_isPublic = $options['isPublic'];
        $this->_isFeatured = $options['isFeatured'];
    }
    
    public function perform() {
        try {
            // Get import task ready
            switch ($this->_importSource) {
                case 'File': $importSourceLabel = 'Upload: ' . $this->_importSourceBody; break;
                case 'Url': $importSourceLabel = $this->_importSourceBody; break;
                case 'Paste': $importSourceLabel = 'Pasted JSON'; break; 
                default: $importSourceLabel = 'Unknown'; break;
            }
            $this->_statusId = get_db()->insert('IiifItems_JobStatus', array(
                'source' => $importSourceLabel,
                'dones' => 0, 
                'skips' => 0,
                'fails' => 0,
                'status' => 'Queued', 
                'progress' => 0,
                'total' => 0,
                'added' => date('Y-m-d H:i:s'),
            ));
            debug($this->_statusId);
            $js = get_db()->getTable('IiifItems_JobStatus')->find($this->_statusId);
            debug("Starting Import Job " . $this->_statusId);
            
            // (Download and) Decode
            switch ($this->_importSource) {
                case 'File':
                    $jsonSource = file_get_contents($this->_importSourceBody);
                    debug("Got JSON from uploaded file: " . $this->_importSourceBody);
                break;
                case 'Url':
                    $jsonSource = file_get_contents($this->_importSourceBody);
                    debug("Downloaded from " . $this->_importSourceBody);
                break;
                case 'Paste':
                    $jsonSource = $this->_importSourceBody;
                    debug("Got JSON from submitted string");
                break;
                default:
                    throw new Exception("Invalid import source.");
                break;
            }
            $jsonData = json_decode($jsonSource, true);
            debug("Top level structure parsed for Import Job " . $this->_statusId);
            
            // Get number of import tasks
            debug("Enumerating download items for Import Job " . $this->_statusId);
            $js->total = $this->_generateTasks($jsonData);
            $js->save();
            debug("Found " . $js->total . " download items for Import Job " . $this->_statusId);
            
            // Process the submission
            switch ($this->_importType) {
                case 'Collection':
                    $this->_processCollection($jsonData, $js);
                break;
                case 'Manifest':
                    $this->_processManifest($jsonData, $js);
                break;
                case 'Canvas':
                    $this->_processCanvas($jsonData, $js);
                break;
                default:
                    throw new Exception("Invalid import source.");
                break;
            }
            
            // Done
            $js->progress = $js->total;
            $js->status = 'Completed';
            $js->modified = date('Y-m-d H:i:s');
            $js->save();
            debug("Import done");
        }
        catch (Exception $e) {
            debug($e->getMessage());
            debug($e->getTraceAsString());
            $js->status = 'Failed';
            $js->modified = date('Y-m-d H:i:s');
            $js->save();
        }
    }
    
    protected function _generateTasks($jsonData, $parentCollection=null) {
        $taskCount = 0;
        switch ($jsonData['@type']) {
            case 'sc:Collection':
                if (isset($jsonData['collections'])) {
                    foreach ($jsonData['collections'] as $collection) {
                        try {
                            $downloadedData = file_get_contents($collection['@id']);
                            $subJsonData = json_decode($downloadedData, true);
                            $taskCount += $this->_generateTasks($subJsonData);
                        }
                        catch (Exception $e) {
                        }
                    }
                }
                if (isset($jsonData['manifests'])) {
                    foreach ($jsonData['manifests'] as $manifest) {
                        try {
                            $downloadedData = file_get_contents($manifest['@id']);
                            $subJsonData = json_decode($downloadedData, true);
                            $taskCount += $this->_generateTasks($subJsonData);
                        }
                        catch (Exception $e) {
                        }
                    }
                }
                if (isset($jsonData['members'])) {
                    foreach ($jsonData['members'] as $member) {
                        try {
                            $downloadedData = file_get_contents($member['@id']);
                            $subJsonData = json_decode($downloadedData, true);
                            $taskCount += $this->_generateTasks($subJsonData);
                        }
                        catch (Exception $e) {
                        }
                    }
                }
            break;
            case 'sc:Manifest':
                try {
                    $subCanvases = $jsonData['sequences'][0]['canvases'];
                    foreach ($subCanvases as $subCanvas) {
                        $taskCount += $this->_generateTasks($subCanvas);
                    }
                }
                catch (Exception $e) {
                }
            break;
            case 'sc:Canvas':
                try {
                    $subImages = count($jsonData['images']);
                    $taskCount += $subImages;
                }
                catch (Exception $e) {
                }
            break;
        }
        return $taskCount;
    }
    
    protected function _processCollection($collectionData, $jobStatus, $parentCollection=null) {
        // Check collection type marker
        if ($collectionData['@type'] !== 'sc:Collection') {
            throw new Exception(__('Not a valid collection.'));
        }
        // Set up metadata
        $collectionMetadata = array(
            'Dublin Core' => array(
                'Title' => array(array('text' => $collectionData['label'], 'html' => false)),
                'Description' => array(array('text' => $collectionData['description'], 'html' => false)),
            ),
            'IIIF Collection Metadata' => array(
                'Original @id' => array(array('text' => $collectionData['@id'], 'html' => false)),
                'IIIF Type' => array(array('text' => 'Collection', 'html' => false)),
                'JSON Data' => array(array('text' => json_encode($collectionData, JSON_UNESCAPED_SLASHES), 'html' => false)),
            ),
        );
        if ($parentCollection !== null) {
            $collectionMetadata['IIIF Collection Metadata']['Parent Collection'] = array(array('text' => $parentCollection->id, 'html' => false));
        }
        // Create collection
        debug("Creating collection for collection " . $collectionData['@id']);
        $collection = insert_collection(array(
            'public' => false,
            'featured' => false,
        ), $collectionMetadata);
        // Look for collections
        debug("Scanning subcollections for " . $collectionData['@id']);
        if (isset($collectionData['collections'])) {
            foreach ($collectionData['collections'] as $subcollectionSource) {
                $subcollectionRaw = file_get_contents($subcollectionSource['@id']);
                $subcollection = json_decode($subcollectionRaw, true);
                $this->_processCollection($subcollection, $jobStatus, $collection);
            }
        }
        // Look for manifests and import them too
        debug("Scanning submanifests for " . $collectionData['@id']);
        if (isset($collectionData['manifests'])) {
            foreach ($collectionData['manifests'] as $submanifestSource) {
                $submanifestRaw = file_get_contents($submanifestSource['@id']);
                $submanifest = json_decode($submanifestRaw, true);
                $this->_processManifest($submanifest, $jobStatus, $collection);
            }
        }
        // Look for members
        debug("Scanning submembers for " . $collectionData['@id']);
        if (isset($collectionData['members'])) {
            foreach ($collectionData['members'] as $submemberSource) {
                $submemberRaw = file_get_contents($submemberSource);
                $submember = json_decode($submemberRaw, true);
                switch ($submember['@type']) {
                    case 'sc:Collection': 
                        $this->_processCollection($submember, $jobStatus, $collection);
                    break;
                    case 'sc:Manifest':
                        $this->_processManifest($submember, $jobStatus, $collection);
                    break;
                }
            }
        }
        // Set public and featured here if applicable
        debug("Setting public/featured flags for collection...");
        $collection->public = $this->_isPublic;
        $collection->featured = $this->_isFeatured;
        $collection->save();
        debug("Collection OK.");
    }
    
    protected function _processManifest($manifestData, $jobStatus, $parentCollection=null) {
        // Check manifest type marker
        if ($manifestData['@type'] !== 'sc:Manifest') {
            throw new Exception(__('Not a valid manifest.'));
        }
        // Set up metadata
        $manifestImportOptions = array(
            'public' => false,
            'featured' => false,
        );
        $manifestMetadata = array(
            'Dublin Core' => array(
                'Title' => array(array('text' => $manifestData['label'], 'html' => false)),
                'Description' => array(array('text' => $manifestData['description'], 'html' => false)),
            ),
            'IIIF Collection Metadata' => array(
                'Original @id' => array(array('text' => $manifestData['@id'], 'html' => false)),
                'IIIF Type' => array(array('text' => 'Manifest', 'html' => false)),
                'JSON Data' => array(array('text' => json_encode($manifestData, JSON_UNESCAPED_SLASHES), 'html' => false)),
            ),
        );
        if ($parentCollection !== null) {
            $manifestMetadata['IIIF Collection Metadata']['Parent Collection'] = array(array('text' => $parentCollection->id, 'html' => false));
        }
        // Create collection
        debug("Creating collection for manifest " . $manifestData['@id']);
        $manifest = insert_collection($manifestImportOptions, $manifestMetadata);
        // Look for canvases and import them too
        if (isset($manifestData['sequences']) && isset($manifestData['sequences'][0]) && isset($manifestData['sequences'][0]['canvases'])) {
            foreach ($manifestData['sequences'][0]['canvases'] as $canvas) {
                $this->_processCanvas($canvas, $jobStatus, $manifest);
            }
        }
        
        // Set public and featured here if applicable
        debug("Setting public/featured flags for manifest...");
        $manifest->public = $this->_isPublic;
        $manifest->featured = $this->_isFeatured;
        if ($this->_isPublic || $this->_isFeatured) {
            $db = get_db();
            $db->query("UPDATE `" . $db->prefix . "items` SET `public` = " . ($manifest->public ? 1 : 0) . ", `featured` = " . ($manifest->featured ? 1 : 0) . " WHERE `collection_id` = {$manifest->id}");
        }
        $manifest->save();
        debug("Manifest OK.");
    }
    
    protected function _processCanvas($canvasData, $jobStatus, $parentCollection=null) {
        // Check canvas type marker
        if ($canvasData['@type'] !== 'sc:Canvas') {
            throw new Exception(__("Not a valid canvas."));
        }
        // Set up metadata
        $canvasImportOptions = array(
            'public' => false,
            'featured' => false,
        );
        $canvasMetadata = array(
            'Dublin Core' => array(
                'Title' => array(array('text' => $canvasData['label'], 'html' => false)),
            ),
            'IIIF Item Metadata' => array(
                'Display as IIIF?' => array(array('text' => 'Yes', 'html' => false)),
                'Original @id' => array(array('text' => $canvasData['@id'], 'html' => false)),
                'JSON Data' => array(array('text' => json_encode($canvasData, JSON_UNESCAPED_SLASHES), 'html' => false)),
            ),
        );
        if ($parentCollection !== null) {
            $canvasImportOptions['collection_id'] = $parentCollection->id;
            $canvasMetadata['IIIF Item Metadata']['Parent Collection'] = array(array('text' => $parentCollection->id, 'html' => false));
        }
        // Process canvases
        debug("Processing canvas " . $canvasData['@id']);
        $newItem = insert_item($canvasImportOptions, $canvasMetadata);
        foreach ($canvasData['images'] as $image) {
            switch ($this->_downloadIiifImageToItem($newItem, $image, $this->_importPreviewSize)) {
                case 1:
                    $jobStatus->dones++;
                break;
                case 0:
                    $jobStatus->skips++;
                break;
                default:
                    $jobStatus->fails++;
                break;
            }
        }
        
        // Up progress
        $jobStatus->progress++;
        $jobStatus->status = 'In Progress';
        $jobStatus->modified = date('Y-m-d H:i:s');
        $jobStatus->save();
        debug("Canvas OK.");
    }
    
    protected function _downloadIiifImageToItem($item, $image, $preferredSize='full') {
        $trySizes = array($preferredSize, 512, 96);
        if (!isset($image['resource']) || !isset($image['resource']['service']) || !isset($image['resource']['height']) || !isset($image['resource']['width'])) {
            debug("Missing stuff?");
            return 0;
        }
        foreach ($trySizes as $trySize) {
            try {
                if ($trySize === 'full') {
                    $theSize = 'full';
                } else {
                    $theSize = ($image['resource']['width'] >= $image['resource']['height']) ? (','.$trySize) : ($trySize.',');
                }
                $imageUrl = rtrim($image['resource']['service']['@id'], '/') . '/full/' . $theSize . '/0/' . $this->_getIiifImageSuffix($image);
                debug("Downloading image " . $imageUrl);
                insert_files_for_item($item, 'Url', $imageUrl);
                debug("Download OK: " . $imageUrl);
                return 1;
            } catch (Exception $e) {
                debug("Download with size " . $trySize . " failed, trying next...");
            }
        }
        return -1;
    }
    
    protected function _getIiifImageSuffix($image) {
        try {
            switch ($image['resource']['service']['@context']) {
                case 'http://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                case 'http://iiif.io/api/image/1/context.json':
                    return 'native.jpg';
                case 'http://iiif.io/api/image/2/context.json':
                    return 'default.jpg';
            }
            switch ($image['resource']['service']['profile']) {
                case 'http://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                    return 'native.jpg';
            }
        }
        catch (Exception $e) {
            return 'native.jpg';
        }
    }
}
