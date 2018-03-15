<?php

/**
 * Background job for importing IIIF collection, manifest and canvas data.
 * @package IiifItems
 * @subpackage Job
 */
class IiifItems_Job_Import extends Omeka_Job_AbstractJob {
    private $_importType, $_importSource, $_importSourceBody, $_importPreviewSize, $_annoPreviewSize, $_isPublic, $_isFeatured, $_isReversed, $_parent;
    private $_statusId;
    
    /**
     * Create a new IiifItems_Job_Import.
     * @param array $options
     */
    public function __construct(array $options) {
        parent::__construct($options);
        $this->_importType = $options['importType'];
        $this->_importSource = $options['importSource'];
        $this->_importSourceBody = $options['importSourceBody'];
        $this->_importPreviewSize = $options['importPreviewSize'];
        $this->_annoPreviewSize = $options['importAnnoSize'];
        $this->_isPublic = $options['isPublic'];
        $this->_isFeatured = $options['isFeatured'];
        $this->_isReversed = $options['isReversed'];
        $this->_parent = $options['parent'];
    }
    
    /**
     * Main runnable method.
     */
    public function perform() {
        try {
            // Load routes
            fire_plugin_hook('define_routes', array('router' => Zend_Controller_Front::getInstance()->getRouter()));
            // Get import task ready
            switch ($this->_importSource) {
                case 'File': $importSourceLabel = __("Upload: %s", $this->_importSourceBody); break;
                case 'Url': $importSourceLabel = $this->_importSourceBody; break;
                case 'Paste': $importSourceLabel = __("Pasted JSON"); break; 
                default: $importSourceLabel = __("Unknown"); break;
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
            debug(__("Starting Import Job %s", $this->_statusId));
            
            // (Download and) Decode
            switch ($this->_importSource) {
                case 'File':
                    $jsonSource = file_get_contents($this->_importSourceBody);
                    debug(__("Got JSON from uploaded file: %s", $this->_importSourceBody));
                break;
                case 'Url':
                    $jsonSource = file_get_contents($this->_importSourceBody);
                    debug(__("Downloaded from %s", $this->_importSourceBody));
                break;
                case 'Paste':
                    $jsonSource = $this->_importSourceBody;
                    debug(__("Got JSON from submitted string"));
                break;
                default:
                    throw new Exception(__("Invalid import source."));
                break;
            }
            $jsonData = json_decode($jsonSource, true);
            debug(__("Top level structure parsed for Import Job %s", $this->_statusId));
            
            // Get number of import tasks
            debug(__("Enumerating download items for Import Job %s", $this->_statusId));
            $js->total = $this->_generateTasks($jsonData);
            $js->save();
            debug(__("Found %s download items for Import Job %s" , $js->total, $this->_statusId));
            
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
                    throw new Exception(__("Invalid import source."));
                break;
            }
            
            // Done
            $js->progress = $js->total;
            $js->status = 'Completed';
            $js->modified = date('Y-m-d H:i:s');
            $js->save();
            debug(__("Import done"));
        }
        catch (Exception $e) {
            debug($e->getMessage());
            debug($e->getTraceAsString());
            $js->status = 'Failed';
            $js->modified = date('Y-m-d H:i:s');
            $js->save();
        }
    }
    
    /**
     * Return the number of items imported as part of this job.
     * 
     * @param array $jsonData
     * @return integer
     */
    protected function _generateTasks($jsonData) {
        $taskCount = 0;
        switch ($jsonData['@type']) {
            case 'sc:Collection':
                foreach (array('collections', 'manifests', 'members') as $subkey) {
                    if (isset($jsonData[$subkey])) {
                        foreach ($jsonData[$subkey] as $subthing) {
                            try {
                                $downloadedData = file_get_contents($subthing['@id']);
                                $subJsonData = json_decode($downloadedData, true);
                                $taskCount += $this->_generateTasks($subJsonData);
                            }
                            catch (Exception $e) {
                            }
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
    
    /**
     * Process a fragment of IIIF collection JSON data.
     * 
     * @param array $collectionData The IIIF collection JSON data.
     * @param IiifItems_JobStatus $jobStatus The job status record to update.
     * @param Collection|null $parentCollection The collection to place this collection under (if any)
     * @return Collection
     * @throws Exception
     */
    protected function _processCollection($collectionData, $jobStatus, $parentCollection=null) {
        // Check collection type marker
        if ($collectionData['@type'] !== 'sc:Collection') {
            throw new Exception(__('Not a valid collection.'));
        }
        // Set up metadata
        $collectionMetadata = $this->_buildMetadata('Collection', $collectionData, $parentCollection);
        // Create collection
        debug(__("Creating collection for collection %s", $collectionData['@id']));
        $collection = insert_collection(array(
            'public' => false,
            'featured' => false,
        ), $collectionMetadata);
        // Look for collections
        debug(__("Scanning subcollections for %s", $collectionData['@id']));
        if (isset($collectionData['collections'])) {
            foreach ($collectionData['collections'] as $subcollectionSource) {
                $subcollectionRaw = file_get_contents($subcollectionSource['@id']);
                $subcollection = json_decode($subcollectionRaw, true);
                $this->_processCollection($subcollection, $jobStatus, $collection);
            }
        }
        // Look for manifests and import them too
        debug(__("Scanning submanifests for %s", $collectionData['@id']));
        if (isset($collectionData['manifests'])) {
            foreach ($collectionData['manifests'] as $submanifestSource) {
                $submanifestRaw = file_get_contents($submanifestSource['@id']);
                $submanifest = json_decode($submanifestRaw, true);
                $this->_processManifest($submanifest, $jobStatus, $collection);
            }
        }
        // Look for members
        debug(__("Scanning submembers for %s", $collectionData['@id']));
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
        debug(__("Setting public/featured flags for collection..."));
        $collection->public = $this->_isPublic;
        $collection->featured = $this->_isFeatured;
        $collection->save();
        debug(__("Collection OK."));
        // Return the created Collection
        return $collection;
    }
    
    /**
     * Process a fragment of IIIF manifest JSON data.
     * 
     * @param array $manifestData The IIIF manifest JSON data.
     * @param IiifItems_JobStatus $jobStatus The job status record to update.
     * @param Collection|null $parentCollection The collection to place this manifest under (if any)
     * @return Collection
     * @throws Exception
     */
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
        debug(__("Creating collection for manifest %s", $manifestData['@id']));
        $manifest = insert_collection($manifestImportOptions, $manifestMetadata);
        // Look for canvases and import them too
        if (isset($manifestData['sequences']) && isset($manifestData['sequences'][0]) && isset($manifestData['sequences'][0]['canvases'])) {
            foreach ($this->_inOrder($manifestData['sequences'][0]['canvases']) as $canvas) {
                $this->_processCanvas($canvas, $jobStatus, $manifest);
            }
        }
        
        // Set public and featured here if applicable
        debug(__("Setting public/featured flags for manifest..."));
        $manifest->public = $this->_isPublic;
        $manifest->featured = $this->_isFeatured;
        if ($this->_isPublic || $this->_isFeatured) {
            $db = get_db();
            $db->query("UPDATE `" . $db->prefix . "items` SET `public` = " . ($manifest->public ? 1 : 0) . ", `featured` = " . ($manifest->featured ? 1 : 0) . " WHERE `collection_id` = {$manifest->id}");
        }
        $manifest->save();
        debug(__("Manifest OK."));
        // Return the created Collection
        return $manifest;
    }
    
    /**
     * Process a fragment of IIIF canvas JSON data.
     * 
     * @param array $canvasData The IIIF canvas JSON data.
     * @param IiifItems_JobStatus $jobStatus The job status record to update.
     * @param Collection|null $parentCollection The collection to place this canvas under (if any)
     * @return Item
     * @throws Exception
     */
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
        debug(__("Processing canvas %s", $canvasData['@id']));
        $newItem = insert_item($canvasImportOptions, $canvasMetadata);
        if ($this->_importPreviewSize) {
            foreach ($canvasData['images'] as $image) {
                $downloadResult = $this->_downloadIiifImageToItem($newItem, $image, $this->_importPreviewSize);
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
        } else {
            $jobStatus->dones++;
        }
        // Up progress
        $jobStatus->progress++;
        $jobStatus->status = 'In Progress';
        $jobStatus->modified = date('Y-m-d H:i:s');
        $jobStatus->save();
        debug(__("Canvas OK."));
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
    
    /**
     * Process a fragment of IIIF OA annotation data.
     * 
     * @param array $annotationData The IIIF OA annotation JSON data.
     * @param IiifItems_JobStatus $jobStatus The job status record to update.
     * @param array $image The IIIF image JSON data for the picture to cut out of.
     * @param string $source The source URL where the data cam efrom.
     * @param Collection|null $parentCollection The collection to place this annotation under (if any).
     * @param Item $attachItem The Item to attach to.
     * @return Item
     */
    protected function _processAnnotation($annotationData, $jobStatus, $image, $source, $parentCollection=null, $attachItem=null) {
        // Set up import options
        $annotationImportOptions = $this->_buildAnnotationImportOptions($annotationData, $parentCollection);
        $annotationMetadata = $this->_buildAnnotationMetadata($annotationData, $source, $parentCollection, $attachItem);
      
        // Process annotation
        debug(__("Processing annotation %s", $annotationData['@id']));
        $newItem = insert_item($annotationImportOptions, $annotationMetadata);
        
        // Add preview image if xywh selector is available
        if (isset($annotationMetadata['Item Type Metadata']['Annotated Region'][0]['text'])) {
            if ($this->_annoPreviewSize) {
                foreach ($annotationMetadata['Item Type Metadata']['Annotated Region'] as $xywhTextFrag) {
                    $xywhPosition = $xywhTextFrag['text'];
                    debug($xywhPosition);
                    $downloadResult = $this->_downloadIiifImageToItem($newItem, $image, $this->_annoPreviewSize, ($xywhPosition == 'full') ? 'full' : explode(',', $xywhPosition));
                }
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
            } else {
                $jobStatus->dones++;
            }
        }
        // Up progress
        $jobStatus->progress++;
        $jobStatus->modified = date('Y-m-d H:i:s');
        $jobStatus->save();
        debug(__("Annotation OK."));
        // Return the created Item
        return $newItem;
    }
    
    /**
     * Download a IIIF image crop to an item as a file.
     * 
     * @param Item $item
     * @param array $image The IIIF image JSON data.
     * @param string|integer $preferredSize The preferred size ('full' or number of pixels)
     * @param string|array $region The region to trim out ('full' or 4-entry array of x, y, width and height)
     * @return array ({status: 0} for invalid JSON data, {status: -1} for failed download and {status: 1, file: DOWNLOADEDFILE} for OK)
     */
    protected function _downloadIiifImageToItem($item, $image, $preferredSize='full', $region='full') {
        // Sanity check for image JSON faults
        if (!isset($image['resource']) || !isset($image['resource']['service']) || !isset($image['resource']['height']) || !isset($image['resource']['width'])) {
            debug(__("Missing stuff?"));
            return array('status' => 0);
        }
        // Download
        $downloader = new IiifItems_ImageDownloader($image);
        $downloadedFile = $downloader->downloadToItem($item, $region, array_unique(array($preferredSize, 'full', 512, 96)));
        // Download OK
        if ($downloadedFile) {
            return array('status' => 1, 'file' => $downloadedFile);
        }
        // Download failed
        return array('status' => -1);
    }
    
    /**
     * Build the nested metadata array of all elements for this specific IIIF type.
     * 
     * @param string $type The kind of Omeka record to work with (collection or items)
     * @param array $jsonData The JSON data to process.
     * @param Collection|null $parentCollection The collection to place this manifest under (if any)
     * @return array
     */
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
                'Title' => array(array('text' => $jsonData['label'], 'html' => $this->_hasHtml($jsonData['label']))),
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
            $metadata['Dublin Core']['Description'] = array(array('text' => $jsonData['description'], 'html' => $this->_hasHtml($jsonData['description'])));
        }
        if (isset($jsonData['attribution'])) {
            $metadata['Dublin Core']['Publisher'] = array(array('text' => $jsonData['attribution'], 'html' => $this->_hasHtml($jsonData['attribution'])));
        }
        if (isset($jsonData['license'])) {
            $metadata['Dublin Core']['Rights'] = array(array('text' => $jsonData['license'], 'html' => $this->_hasHtml($jsonData['license'])));
        }
        if ($parentCollection !== null && $type != 'Item') {
            $metadata[$metadataPrefix]['Parent Collection'] = array(array('text' => metadata($parentCollection, array($metadataPrefix, 'UUID'), array('no_escape' => true, 'no_filter' => true)), 'html' => false));
        }
        return $metadata;
    }

    /**
     * Build the public, featured, tags and text elements for this IIIF OA annotation data.
     * 
     * @param array $annotationData The IIIF OA annotation data for this annotation.
     * @param Collection|null $parentCollection The collection to place this manifest under (if any)
     * @return array
     */
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
    
    /**
     * Build the nested metadata array of all elements for this IIIF OA annotation data.
     * 
     * @param array $annotationData The IIIF OA annotation data for this annotation.
     * @param string $source The source URL where the data cam efrom.
     * @param Collection|null $parentCollection The collection to place this manifest under (if any)
     * @param Item $attachItem The Item to attach to.
     * @return array
     */
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
                'Annotated Region' => array(),
            ),
            'IIIF Item Metadata' => array(
                'Display as IIIF?' => array(array('text' => 0, 'html' => false)),
                'Original @id' => array(array('text' => $annotationData['@id'], 'html' => false)),
                'JSON Data' => array(array('text' => json_encode($annotationData, JSON_UNESCAPED_SLASHES), 'html' => false)),
            ),
        );
        // Determine type in 'on'
        if ($attachItem) {
            $metadata['Item Type Metadata']['On Canvas'][] = array('text' => raw_iiif_metadata($attachItem, 'iiifitems_item_uuid_element'), 'html' => false);
        } else {
            if (is_string($annotationData['on'])) {
                $metadata['Item Type Metadata']['On Canvas'][] = array('text' => $annotationData['on'], 'html' => false);
            } elseif (is_array($annotationData['on'])) {
                if (isset($annotationData['full'])) {
                    $metadata['Item Type Metadata']['On Canvas'][] = array('text' => $annotationData['on']['full'], 'html' => false);
                }
                if (isset($annotationData['selector'])) {
                    $metadata['Item Type Metadata']['Selector'][] = array('text' => $annotationData['selector'] , 'html' => false);
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
                        $metadata['Dublin Core']['Relation'][] = array('text' => __("Data Set: %s", '<a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>'), 'html' => true);
                    break;
                    case 'dctypes:Image':
                        $metadata['Dublin Core']['Relation'][] = array('text' => __("Image: %s", '<a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>'), 'html' => true);
                    break;
                    case 'dctypes:MovingImage':
                        $metadata['Dublin Core']['Relation'][] = array('text' => __("Moving Image: %s", '<a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>'), 'html' => true);
                    break;
                    case 'dctypes:Sound':
                        $metadata['Dublin Core']['Relation'][] = array('text' => __("Sound: %s", '<a href="' . $resource['@id'] . '"> ' . html_escape($resource['@id']) . '</a>'), 'html' => true);
                    break;
                    case 'cnt:ContentAsText': case 'dctypes:Text': default:
                        $metadata['Item Type Metadata']['Text'][] = array('text' => $resource['chars'], 'html' => isset($resource['@type']) && $resource['@type'] == 'text/html');
                    break;
                }
            }
        }
        // Set title based on snippet of first available text
        if (!empty($metadata['Item Type Metadata']['Text'])) {
            $metadata['Dublin Core']['Title'][] = array('text' => __("Annotation: \"%s\"", snippet_by_word_count($metadata['Item Type Metadata']['Text'][0]['text'])), 'html' => false);
        }
        // Set annotation region
        // Simple xywh case: Extract xywh from on
        if (is_string($annotationData['on'])) {
            $metadata['Item Type Metadata']['Annotated Region'][] = array('text' => substr($annotationData['on'], strpos($annotationData['on'], '#xywh=')+6), 'html' => false);
        }
        // Mirador 2.2- format: Extract SVG from value
        elseif (is_array($annotationData['on']) && isset($annotationData['on']['selector'])) {
            $selector = $annotationData['on']['selector'];
            if ($selector['@type'] === 'oa:SvgSelector') {
                $metadata['Item Type Metadata']['Selector'][] = array('text' => $selector['value'] , 'html' => false);
            }
        }
        // Mirador 2.3+ format: Extract xywhs and SVGs
        elseif (is_array($annotationData['on']) && isset($annotationData['on'][0]['selector'])) {
            foreach ($annotationData['on'] as $on) {
                $selector = $on['selector'];
                switch ($on['selector']['@type']) {
                    case 'oa:Choice':
                        $metadata['Item Type Metadata']['Selector'][] = array('text' => $selector['item']['value'], 'html' => false);
                        $metadata['Item Type Metadata']['Annotated Region'][] = array('text' => substr($selector['default']['value'], 5), 'html' => false);
                    break;
                    case 'oa:SvgSelector':
                        $metadata['Item Type Metadata']['Selector'][] = array('text' => $selector['item']['value'], 'html' => false);
                    break;
                    case 'oa:FragmentSelector':
                        $metadata['Item Type Metadata']['Annotated Region'][] = array('text' => substr($selector['default']['value'], 5), 'html' => false);
                    break;
                }
            }
        }
        return $metadata;
    }
    
    /**
     * Return the array in the original order if this job is not reversed, in reverse if it is.
     * @param array $array
     * @return array
     */
    protected function _inOrder($array) {
        return ($this->_isReversed) ? array_reverse($array) : $array;
    }
    
    /**
     * Return whether the given string contains HTML tags.
     * @param string $str
     * @return boolean
     */
    protected function _hasHtml($str) {
        return !!preg_match('#(</b>)|(</i>)|(</span>)|(</p>)|(<img)|(<hr)|(</li>)|(</ul>)|(</ol>)#i', $str);
    }
}
