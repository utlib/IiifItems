<?php

class IiifItems_Job_Import extends Omeka_Job_AbstractJob {
    private $_importType, $_importSource, $_importSourceBody, $_importPreviewSize, $_isPublic, $_isFeatured, $_isReversed, $_parent;
    private $_statusId;
    
    public function __construct(array $options) {
        parent::__construct($options);
        $this->_importType = $options['importType'];
        $this->_importSource = $options['importSource'];
        $this->_importSourceBody = $options['importSourceBody'];
        $this->_importPreviewSize = $options['importPreviewSize'];
        $this->_isPublic = $options['isPublic'];
        $this->_isFeatured = $options['isFeatured'];
        $this->_isReversed = $options['isReversed'];
        $this->_parent = $options['parent'];
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
            $parentCollection = find_collection_by_uuid($this->_parent);
            switch ($this->_importType) {
                case 'Collection':
                    $this->_processCollection($jsonData, $js, $parentCollection);
                break;
                case 'Manifest':
                    $this->_processManifest($jsonData, $js, $parentCollection);
                break;
                case 'Canvas':
                    $this->_processCanvas($jsonData, $js, $parentCollection);
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
                    if (isset($jsonData['otherContent'])) {
                        foreach ($jsonData['otherContent'] as $otherContent) {
                            if (is_array($otherContent)) {
                                $downloadedData = file_get_contents($otherContent['@id']);
                                $subJsonData = json_decode($downloadedData, true);
                                $taskCount += $this->_generateTasks($subJsonData);
                            } elseif (is_string($otherContent)) {
                                $downloadedData = file_get_contents($otherContent);
                                $subJsonData = json_decode($downloadedData, true);
                                $taskCount += $this->_generateTasks($subJsonData);
                            }
                        }
                    }
                }
                catch (Exception $e) {
                }
            break;
            case 'sc:AnnotationList':
                try {
                    $previews = count($jsonData['resources']);
                    $taskCount += $previews;
                } catch (Exception $e) {
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
        $collectionMetadata = $this->_buildMetadata('Collection', $collectionData, $parentCollection);
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
        // Return the created Collection
        return $collection;
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
        $manifestMetadata = $this->_buildMetadata('Manifest', $manifestData, $parentCollection);
        // Create collection
        debug("Creating collection for manifest " . $manifestData['@id']);
        $manifest = insert_collection($manifestImportOptions, $manifestMetadata);
        // Look for canvases and import them too
        if (isset($manifestData['sequences']) && isset($manifestData['sequences'][0]) && isset($manifestData['sequences'][0]['canvases'])) {
            foreach ($this->_inOrder($manifestData['sequences'][0]['canvases']) as $canvas) {
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
        // Return the created Collection
        return $manifest;
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
        $canvasMetadata = $this->_buildMetadata('Item', $canvasData, $parentCollection);
        if ($parentCollection !== null) {
            $canvasImportOptions['collection_id'] = $parentCollection->id;
        }
        // Process canvases
        debug("Processing canvas " . $canvasData['@id']);
        $newItem = insert_item($canvasImportOptions, $canvasMetadata);
        foreach ($canvasData['images'] as $image) {
            $downloadResult = $this->_downloadIiifImageToItem($newItem, $image, $this->_importPreviewSize);
            switch ($downloadResult['status']) {
                case 1:
                    $jobStatus->dones++;
                    $downloadResult['file']->addElementTextsByArray(array(
                        'IIIF File Metadata' => array(
                            'Original @id' => array(array('text' => $image['@id'], 'html' => false)),
                            'JSON Data' => array(array('text' => json_encode($image, JSON_UNESCAPED_SLASHES), 'html' => false)),
                        ),
                    ));
                    $downloadResult['file']->saveElementTexts();
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
        // Process annotations
        if (isset($canvasData['otherContent'])) {
            foreach ($canvasData['otherContent'] as $otherContent) {
                if (is_array($otherContent)) {
                    $downloadedData = file_get_contents($otherContent['@id']);
                    $subJsonData = json_decode($downloadedData, true);
                    foreach ($subJsonData['resources'] as $annotationJson) {
                        $this->_processAnnotation($annotationJson, $jobStatus, $canvasData['images'][0], $otherContent['@id'], $parentCollection, $newItem);
                    }
                } elseif (is_string($otherContent)) {
                    $downloadedData = file_get_contents($otherContent);
                    $subJsonData = json_decode($downloadedData, true);
                    foreach ($subJsonData['resources'] as $annotationJson) {
                        $this->_processAnnotation($annotationJson, $jobStatus, $canvasData['images'][0], $otherContent, $parentCollection, $newItem);
                    }
                }
            }
        }
        // Return the created Item
        return $newItem;
    }
    
    protected function _processAnnotation($annotationData, $jobStatus, $image, $source, $parentCollection=null, $attachItem=null) {
        // Set up import options
        $annotationImportOptions = $this->_buildAnnotationImportOptions($annotationData, $parentCollection);
        $annotationMetadata = $this->_buildAnnotationMetadata($annotationData, $source, $parentCollection, $attachItem);
      
        // Process annotation
        debug("Processing annotation " . $annotationData['@id']);
        $newItem = insert_item($annotationImportOptions, $annotationMetadata);
        
        // Add preview image if xywh selector is available
        if (is_string($annotationData['on'])) {
            $xywhPosition = strstr($annotationData['on'], '#xywh=');
            if ($xywhPosition !== false) {
                $xywhPosition = substr($xywhPosition, 6);
                debug($xywhPosition);
                $downloadResult = $this->_downloadIiifImageToItem($newItem, $image, $this->_importPreviewSize, $xywhPosition);
                switch ($downloadResult['status']) {
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
        }
        // Up progress
        $jobStatus->progress++;
        $jobStatus->modified = date('Y-m-d H:i:s');
        $jobStatus->save();
        debug("Annotation OK.");
        // Return the created Item
        return $newItem;
    }
    
    protected function _downloadIiifImageToItem($item, $image, $preferredSize='full', $region='full') {
        $trySizes = array($preferredSize, 512, 96);
        if (!isset($image['resource']) || !isset($image['resource']['service']) || !isset($image['resource']['height']) || !isset($image['resource']['width'])) {
            debug("Missing stuff?");
            return array('status' => 0);
        }
        foreach ($trySizes as $trySize) {
            try {
                if ($trySize === 'full') {
                    $theSize = 'full';
                } else {
                    if ($region == 'full') {
                        $theSize = ($image['resource']['width'] >= $image['resource']['height']) ? (','.$trySize) : ($trySize.',');
                    } else {
                        $regionComps = split(',', $region);
                        $theSize = ($regionComps[2] >= $regionComps[3]) ? (','.$trySize) : ($trySize.',');
                    }
                }
                $imageUrl = rtrim($image['resource']['service']['@id'], '/') . '/' . $region . '/' . $theSize . '/0/' . $this->_getIiifImageSuffix($image);
                debug("Downloading image " . $imageUrl);
                $downloadedFile = insert_files_for_item($item, 'Url', $imageUrl)[0];
                debug("Download OK: " . $imageUrl);
                return array('status' => 1, 'file' => $downloadedFile);
            } catch (Exception $e) {
                debug("Download with size " . $trySize . " failed, trying next...");
            }
        }
        return array('status' => -1);
    }
    
    protected function _getIiifImageSuffix($image) {
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
    
    protected function _buildMetadata($type, $jsonData, $parentCollection=null) {
        switch ($type) {
            case 'Collection':
                $metadataPrefix = 'IIIF Collection Metadata';
            break;
            case 'Manifest':
                $metadataPrefix = 'IIIF Collection Metadata';
            break;
            case 'Item':
                $metadataPrefix = 'IIIF Item Metadata';
            break;
        }
        $metadata = array(
            'Dublin Core' => array(
                'Title' => array(array('text' => $jsonData['label'], 'html' => false)),
                'Source' => array(),
            ),
            $metadataPrefix => array(
                'Original @id' => array(array('text' => $jsonData['@id'], 'html' => false)),
                'JSON Data' => array(array('text' => json_encode($jsonData, JSON_UNESCAPED_SLASHES), 'html' => false)),
            ),
        );
        switch ($type) {
            case 'Collection': case 'Manifest':
                $metadata['Dublin Core']['Source'][] = array('text' => $jsonData['@id'], 'html' => false);
                $metadata['IIIF Collection Metadata']['IIIF Type'] = array(array('text' => $type, 'html' => false));
            break;
            case 'Item':
                if ($parentCollection) {
                    $metadata['Dublin Core']['Source'][] = array('text' => metadata($parentCollection, array('IIIF Collection Metadata', 'Original @id'), array('no_escape' => true, 'no_filter' => true)), 'html' => false);
                }
                $metadata['IIIF Item Metadata']['Display as IIIF?'] = array(array('text' => 'Yes', 'html' => false));
            break;
        }
        if (isset($jsonData['description'])) {
            $metadata['Dublin Core']['Description'] = array(array('text' => $jsonData['description'], 'html' => false));
        }
        if (isset($jsonData['attribution'])) {
            $metadata['Dublin Core']['Publisher'] = array(array('text' => $jsonData['attribution'], 'html' => false));
        }
        if (isset($jsonData['license'])) {
            $metadata['Dublin Core']['Rights'] = array(array('text' => $jsonData['license'], 'html' => false));
        }
        if ($parentCollection !== null && $type != 'Item') {
            $metadata[$metadataPrefix]['Parent Collection'] = array(array('text' => metadata($parentCollection, array($metadataPrefix, 'UUID'), array('no_escape' => true, 'no_filter' => true)), 'html' => false));
        }
        return $metadata;
    }

    protected function _buildAnnotationImportOptions($annotationData, $parentCollection=null) {
        $importOptions = array(
            'public' => $this->_isPublic,
            'featured' => false,
            'item_type_id' => get_option('iiifitems_annotation_item_type'),
        );
        $tags = array();
        if (isset($annotationData[0])) {
            $resources = $annotationData;
        } elseif (isset($annotationData['@type'])) {
            $resources = array($annotationData);
        }
        if (isset($resources)) {
            foreach ($resources as $resource) {
                switch ($resource['@type']) {
                    case 'oa:Tag':
                        $tags[] = $resource['chars'];
                    break;
                }
            }
        }
        if (!empty($tags)) {
            $importOptions['tags'] = join(',', $tags);
        }
        return $importOptions;
    }
    
    protected function _buildAnnotationMetadata($annotationData, $source, $parentCollection=null, $attachItem=null) {
        // Set up metadata
        $metadata = array(
            'Dublin Core' => array(
                'Title' => array(),
                'Source' => array(array('text' => $source, 'html' => false)),
                'Relation' => array(),
            ),
            'Item Type Metadata' => array(
                'Text' => array(),
                'On Canvas' => array(),
                'Selector' => array(),
            ),
            'IIIF Item Metadata' => array(
                'Display as IIIF?' => array(array('text' => 0, 'html' => false)),
                'Original @id' => array(array('text' => $annotationData['@id'], 'html' => false)),
                'JSON Data' => array(array('text' => json_encode($annotationData, JSON_UNESCAPED_SLASHES), 'html' => false)),
            ),
        );
        // Determine type in 'on'
        if ($attachItem) {
            $metadata['Item Type Metadata']['On Canvas'][] = array('text' => metadata($attachItem, array('IIIF Item Metadata', 'UUID')), 'html' => false);
        } else {
            if (is_string($annotationData['on'])) {
                $metadata['Item Type Metadata']['On Canvas'][] = array('text' => $annotationData['on'], 'html' => false);
            } elseif (is_array($annotationData['on'])) {
                if (isset($annotationData['full'])) {
                    $metadata['Item Type Metadata']['On Canvas'][] = array('text' => $annotationData['on']['full'], 'html' => false);
                }
                if (isset($annotationData['selector'])) {
                    $metadata['Item Type Metadata']['Selector'][] = array('text' => json_encode($annotationData['selector'], JSON_UNESCAPED_SLASHES) , 'html' => false);
                }
            }
        }
        // Grab resources
        if (isset($annotationData['resource'][0])) {
            $resources = $annotationData['resource'];
        } elseif (isset($annotationData['resource']['@type'])) {
            $resources = array($annotationData['resource']);
        }
        if (isset($resources)) {
            foreach ($resources as $resource) {
                switch ($resource['@type']) {
                    case 'dctypes:Dataset':
                        $metadata['Dublin Core']['Relation'][] = array('text' => 'Data Set: <a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>', 'html' => true);
                    break;
                    case 'dctypes:Image':
                        $metadata['Dublin Core']['Relation'][] = array('text' => 'Image: <a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>', 'html' => true);
                    break;
                    case 'dctypes:MovingImage':
                        $metadata['Dublin Core']['Relation'][] = array('text' => 'Moving Image: <a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>', 'html' => true);
                    break;
                    case 'dctypes:Sound':
                        $metadata['Dublin Core']['Relation'][] = array('text' => 'Sound: <a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>', 'html' => true);
                    break;
                    case 'cnt:ContentAsText': case 'dctypes:Text': default:
                        $metadata['Item Type Metadata']['Text'][] = array('text' => $resource['chars'], 'html' => isset($resource['@type']) && $resource['@type'] == 'text/html');
                    break;
                }
            }
        }
        // Set title based on snippet of first available text
        if (!empty($metadata['Item Type Metadata']['Text'])) {
            $metadata['Dublin Core']['Title'][] = array('text' => 'Annotation: "' . snippet_by_word_count($metadata['Item Type Metadata']['Text'][0]['text']) . '"', 'html' => false);
        }
        return $metadata;
    }
    
    protected function _inOrder($array) {
        return ($this->_isReversed) ? array_reverse($array) : $array;
    }
    
}
