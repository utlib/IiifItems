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
            $this->_statusId = get_db()->insert('IiifItems_JobStatus', array(
                'source' => $this->_importSourceBody,
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
            // Download the file
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
            $manifest = json_decode($jsonSource, true); // TODO: Remove this when done
            $jsonData = $manifest;
            
            // Create collection to place items in
            $collection = insert_collection(array(
                'public' => false,
                'featured' => false,
            ), array(
                'Dublin Core' => array(
                    'Title' => array(array('text' => $manifest['label'], 'html' => false)),
                    'Description' => array(array('text' => $manifest['description'], 'html' => false)),
                ),
                'IIIF Collection Metadata' => array(
                    'Original @id' => array(array('text' => $manifest['@id'], 'html' => false)),
                    'IIIF Type' => array(array('text' => 'Manifest', 'html' => false)),
                    'JSON Data' => array(array('text' => $jsonSource, 'html' => false)),
                ),
            ));
            debug("Created Collection " . $collection->id);
            // Add items
            $js->total = count($manifest['sequences'][0]['canvases']);
            debug(json_encode($manifest['sequences'][0]['canvases']));
            foreach ($manifest['sequences'][0]['canvases'] as $canvas) {
                debug("Processing canvas " . $canvas['@id']);
                $newItem = insert_item(array(
                    'public' => false,
                    'featured' => false,
                    'collection_id' => $collection->id,
                ), array(
                    'Dublin Core' => array(
                        'Title' => array(array('text' => $canvas['label'], 'html' => false)),
                    ),
                    'IIIF Item Metadata' => array(
                        'Display as IIIF?' => array(array('text' => 'Yes', 'html' => false)),
                        'Original @id' => array(array('text' => $canvas['@id'], 'html' => false)),
                        'JSON Data' => array(array('text' => json_encode($canvas), 'html' => false)),
                    ),
                ));
                $imageUrl = $canvas['images'][0]['resource']['service']['@id'] . '/full/' . $this->_importPreviewSize . '/0/' . $this->_getIiifImageSuffix($canvas);
                debug("Downloading image " . $imageUrl);
                $files = insert_files_for_item($newItem, 'Url', $imageUrl);
                debug("Image download OK.");
                update_item($newItem, array('collection_id' => $collection->id));
                $js->dones++;
                $js->progress++;
                $js->status = 'In Progress';
                $js->modified = date('Y-m-d H:i:s');
                $js->save();
                debug("Progress: " . ($js->progress/$js->total*100));
            }
            // Done
            $js->progress = $js->total;
            $js->status = 'Completed';
            $js->save();
            debug("Import done");
        }
        catch (Exception $e) {
            debug($e->getMessage());
            debug($e->getTraceAsString());
            $js->status = 'Failed';
            $js->progress = $js->total;
            $js->save();
        }
    }
    
    protected function _getIiifImageSuffix($canvas) {
        try {
            switch ($canvas['images'][0]['resource']['service']['@context']) {
                case 'http://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                case 'http://iiif.io/api/image/1/context.json':
                    return 'native.jpg';
                case 'http://iiif.io/api/image/2/context.json':
                    return 'default.jpg';
            }
            switch ($canvas['images'][0]['resource']['service']['profile']) {
                case 'http://library.stanford.edu/iiif/image-api/1.1/conformance.html#level1':
                    return 'native.jpg';
            }
        }
        catch (Exception $e) {
            return 'native.jpg';
        }
    }
}