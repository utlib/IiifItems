<?php
class IiifItems_ManifestController extends IiifItems_BaseController {
    protected static $allowedThings = array('Collection', 'Item', 'File');
    
    public function manifestAction() {
        //Get and check the thing's existence
        $id = $this->getParam('id');
        $type = $this->getParam('things');
        $class = Inflector::titleize(Inflector::singularize($type));
        $thing = get_record_by_id($class, $id);
        if (empty($thing) || !in_array($class, self::$allowedThings)) {
            throw new Omeka_Controller_Exception_404;
        }

        //Respond with JSON
        try {
            $methodName = '__' . $type . 'ManifestJson';
            $jsonData = $this->$methodName($thing);
            $this->__respondWithJson($jsonData);
        } catch (Exception $e) {
            $this->__respondWithJson(array(
                'message' => $e->getMessage()
            ), 500);
        }
    }
    
    private function __filesManifestJson($file) {
        //Get metadata and current URL
        $elements = all_element_texts($file, array(
            'return_type' => 'array', 
            'show_empty_elements' => true,
            'show_element_set_headings' => true,
        ));
        $absoluteUrl = absolute_url(array('id' => $file->id));
        $webPath = $file->getWebPath('original');
        $iiifRoot = get_option('iiifitems_bridge_prefix');
        
        //Add information about file
        list($fileWidth, $fileHeight) = getimagesize(FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath());
        $fileJson = array(
            '@id' => $webPath . '/canvas',
            '@type' => 'sc:Canvas',
            'label' => metadata($file, 'display_title'),
            'width' => $fileWidth,
            'height' => $fileHeight,
            'images' => array(array(
                '@id' => $webPath . '/image',
                '@type' => 'oa:Annotation',
                'motivation' => 'sc:painting',
                'on' => $absoluteUrl . '/' . $file->id,
                'resource' => array(
                    '@id' => $webPath,
                    '@type' => 'dctypes:Image',
                    'format' => $file->mime_type,
                    'width' => $fileWidth,
                    'height' => $fileHeight,
                    'service' => array(
                        '@id' => $iiifRoot . '/' . $file->filename,
                        '@context' => 'http://iiif.io/api/image/2/context.json',
                        'profile' => 'http://iiif.io/api/image/2/level2.json',
                    ),
                ),
            )),
        );
        
        //Create IIIF Presentation 2.0 top-level template
        $json = array(
            '@context' => 'http://www.shared-canvas.org/ns/context.json',
            '@id' => $absoluteUrl,
            '@type' => 'sc:Manifest',
            'label' => metadata($file, 'display_title'),
            'sequences' => array(array(
                '@id' => $absoluteUrl . '/sequence',
                '@type' => 'sc:Canvas',
                'label' => '',
                'canvases' => array($fileJson),
            )),
        );
        
        //Add description if found
        if ($elements['Dublin Core']['Description']) {
            $json['description'] = $elements['Dublin Core']['Description'][0];
        }
        return $json;
    }
        
    private function __itemsManifestJson($item) {
        //Get metadata and current URL
        $elements = all_element_texts($item, array(
            'return_type' => 'array', 
            'show_empty_elements' => true,
            'show_element_set_headings' => true,
        ));
        $absoluteUrl = absolute_url(array('id' => $item->id));
        $iiifRoot = get_option('iiifitems_bridge_prefix');

        //Fill sequence with information from files
        $files = array();
        foreach ($item->getFiles() as $file) {
            list($fileWidth, $fileHeight) = getimagesize(FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath());
            $webPath = $file->getWebPath('original');
            $newFile = array(
                '@id' => $webPath . '/canvas',
                '@type' => 'sc:Canvas',
                'label' => metadata($file, 'display_title'),
                'width' => $fileWidth,
                'height' => $fileHeight,
                'images' => array(array(
                    '@id' => $webPath . '/image',
                    '@type' => 'oa:Annotation',
                    'motivation' => 'sc:painting',
                    'on' => $absoluteUrl . '/' . $file->id,
                    'resource' => array(
                        '@id' => $webPath,
                        '@type' => 'dctypes:Image',
                        'format' => $file->mime_type,
                        'width' => $fileWidth,
                        'height' => $fileHeight,
                        'service' => array(
                            '@id' => $iiifRoot . '/' . $file->filename,
                            '@context' => 'http://iiif.io/api/image/2/context.json',
                            'profile' => 'http://iiif.io/api/image/2/level2.json',
                        ),
                    ),
                )),
            );
            $files[] = $newFile;
        }

        //Create IIIF Presentation 2.0 top-level template
        $json = array(
            '@context' => 'http://www.shared-canvas.org/ns/context.json',
            '@id' => $absoluteUrl,
            '@type' => 'sc:Manifest',
            'sequences' => array(array(
                '@id' => $absoluteUrl . '/sequence',
                '@type' => 'sc:Canvas',
                'label' => '',
                'canvases' => $files,
            )),
        );

        //Add title and description if found
        if ($elements['Dublin Core']['Title']) {
            $json['label'] = $elements['Dublin Core']['Title'][0];
        }
        if ($elements['Dublin Core']['Description']) {
            $json['description'] = $elements['Dublin Core']['Description'][0];
        }
        return $json;
    }

    private function __collectionsManifestJson($collection) {
        //Get metadata and current URL
        $elements = all_element_texts($collection, array(
            'return_type' => 'array', 
            'show_empty_elements' => true,
            'show_element_set_headings' => true,
        ));
        $absoluteUrl = absolute_url(array('id' => $collection->id));
        $iiifRoot = get_option('iiifitems_bridge_prefix');

        //Fill sequence with information from the files attached
        $files = array();
        foreach ($this->_helper->db->getTable('Item')->findBy(array('collection_id' => $collection->id)) as $item) {
            foreach ($item->getFiles() as $file) {
                list($fileWidth, $fileHeight) = getimagesize(FILES_DIR . DIRECTORY_SEPARATOR . $file->getStoragePath());
                $webPath = $file->getWebPath('original');
                $newFile = array(
                    '@id' => $webPath . '/canvas',
                    '@type' => 'sc:Canvas',
                    'label' => metadata($file, 'display_title'),
                    'width' => $fileWidth,
                    'height' => $fileHeight,
                    'images' => array(array(
                        '@id' => $webPath . '/image',
                        '@type' => 'oa:Annotation',
                        'motivation' => 'sc:painting',
                        'on' => $absoluteUrl . '/' . $file->id,
                        'resource' => array(
                            '@id' => $webPath,
                            '@type' => 'dctypes:Image',
                            'format' => $file->mime_type,
                            'width' => $fileWidth,
                            'height' => $fileHeight,
                            'service' => array(
                                '@id' => $iiifRoot . '/' . $file->filename,
                                '@context' => 'http://iiif.io/api/image/2/context.json',
                                'profile' => 'http://iiif.io/api/image/2/level2.json',
                            ),
                        ),
                    )),
                );
                $files[] = $newFile;
            }
        }

        //Create IIIF Presentation API 2.0 template
        $json = array(
            '@context' => 'http://www.shared-canvas.org/ns/context.json',
            '@id' => $absoluteUrl,
            '@type' => 'sc:Manifest',
            'sequences' => array(array(
                '@id' => $absoluteUrl . '/sequence',
                '@type' => 'sc:Canvas',
                'label' => '',
                'canvases' => $files,
            )),
        );

        //Add title and description if found
        if ($elements['Dublin Core']['Title']) {
            $json['label'] = $elements['Dublin Core']['Title'][0];
        }
        if ($elements['Dublin Core']['Description']) {
            $json['description'] = $elements['Dublin Core']['Description'][0];
        }

        return $json;
    }
}
?>
