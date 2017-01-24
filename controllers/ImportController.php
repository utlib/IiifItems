<?php
class IiifItems_ImportController extends IiifItems_BaseController {
    public function formAction() {
        // Admins only
        if (!is_admin_theme()) {
            throw new Omeka_Controller_Exception_404;
        }
        
        // Render the form
        $form = $this->_getImportForm();
        $this->view->form = $form;
        
        // Process the form instead if POSTed
        if ($this->getRequest()->isPost()) {
            if ($this->processSubmission()) {
                $this->_helper->redirector->goto(array(), 'status');
            }
        }
    }
    
    protected function processSubmission() {
        try {
            $form = $this->_getImportForm();
            // Check CSRF token
            if (!$form->isValid($this->getRequest()->getPost())) {
                $this->_helper->flashMessenger(__('Invalid CSRF token. Please refresh the form and retry.'), 'error');
                return false;
            }
            // Grab and verify the submitted source
            switch ($form->getValue('items_import_type')) {
                case 0:
                    $importType = 'Collection';
                break;
                case 1:
                    $importType = 'Manifest';
                break;
                case 2:
                    $importType = 'Canvas';
                break;
                default:
                    $this->_helper->flashMessenger(__('Invalid import type.'), 'error');
                break;
            }
            switch ($form->getValue('items_import_source')) {
                case 0:
                    $importSource = 'File';
                    if (!$form->items_import_source_file->receive()) {
                        $this->_helper->flashMessenger(__('Invalid source file upload.'), 'error');
                        return false;
                    }
                    $importSourceBody = $form->items_import_source_file->getFileName();
                break;
                case 1:
                    $importSource = 'Url';
                    $importSourceBody = $this->_extractResourceUrl($form->getValue('items_import_source_url'));
                    
                break;
                case 2:
                    $importSource = 'Paste';
                    $importSourceBody = $form->getValue('items_import_source_json');
                break;
                default:
                    $this->_helper->flashMessenger(__('Invalid import source.'), 'error');
                break;
            }
            switch ($form->getValue('items_preview_size')) {
                case 0:
                    $importPreviewSize = 96;
                break;
                case 1:
                    $importPreviewSize = 512;
                break;
                case 2:
                    $importPreviewSize = 'full';
                break;
                default:
                    $this->_helper->flashMessenger(__('Invalid import source.'), 'error');
                break;
            }
            Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('IiifItems_Job_Import', array(
                'isPublic' => $form->getValue('items_are_public') ? 1 : 0,
                'isFeatured' => $form->getValue('items_are_featured') ? 1 : 0,
                'importType' => $importType,
                'importSource' => $importSource,
                'importSourceBody' => $importSourceBody,
                'importPreviewSize' => $importPreviewSize,
                'isReversed' => $form->getValue('items_are_reversed') ? 1 : 0,
            ));
            // OK
            return true;
        }
        catch (Exception $e) {
            debug($e->getTraceAsString());
            $this->_helper->flashMessenger(__('An unexpected error occurred during submission. Please retry later.'));
            return false;
        }
    }
    
    protected function _extractResourceUrl($url) {
        // Return URL as is if there are no queries
        $queryPos = strpos($url, '?');
        if ($queryPos === false) {
            return $url;
        }
        // Parse the query portion (i.e. after ?) and return any manifest found
        $querySection = substr($url, $queryPos+1);
        parse_str($querySection, $parsed);
        if (isset($parsed['manifest'])) {
            return $parsed['manifest'];
        }
        // Still nothing, just give back the URL
        return $url;
    }
    
    public function statusAction() {
        // Admins only
        if (!is_admin_theme()) {
            throw new Omeka_Controller_Exception_404();
        }
        // Select all jobs
        $table= get_db()->getTable('IiifItems_JobStatus');
        $this->view->statuses = $table->fetchObjects('SELECT * FROM ' . $table->getTableName() . ' ORDER BY added DESC');
        $this->view->t = date_timestamp_get(new DateTime());
    }
    
    public function statusUpdateAction() {
        if (!isset($_GET['t'])) {
            throw new Omeka_Controller_Exception_404();
        }
        $jsonData = array(
            't' => date_timestamp_get(new DateTime()),
        );
        // Select all import tasks whose last update is greater than $_GET['t']
        $table= get_db()->getTable('IiifItems_JobStatus');
        $updatesSelect = $table->getSelect()->where("modified >= ?")->order('added ASC');
        $jsonData['updates'] = $table->fetchAll($updatesSelect, array(date('Y-m-d H:i:s', $_GET['t'])));
        $this->__respondWithJson($jsonData);
    }
    
    protected function _getImportForm() {
        require_once IIIF_ITEMS_DIRECTORY . '/forms/Import.php';
        $form = new IiifItems_Form_Import();
        return $form;
    }
    
    public function repairItemAction() {
        // Admins only via POST
        if (!is_admin_theme()) {
            throw new Omeka_Controller_Exception_404();
        }
        $request = $this->getRequest();
        if (strtoupper($request->getMethod()) != 'POST') {
            throw new Omeka_Controller_Exception_404;
        }
        // Get return URL
        $returnUrl = $request->getServer('HTTP_REFERER', WEB_ROOT . admin_url(array(
            'controller' => 'items',
            'action' => 'show',
            'id' => $this->getParam('id'),
        ), 'id'));
        // If item doesn't exist, redirect back with flash
        $item = get_record_by_id('Item', $this->getParam('id'));
        if (!$item) {
            $this->_helper->flashMessenger(__("This item does not exist."));
            Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->gotoUrl($returnUrl);
            return;
        }
        // If no JSON Data, redirect back
        if (!($jsonStr = raw_iiif_metadata($item, 'iiifitems_item_json_element'))) {
            $this->_helper->flashMessenger(__("This item does not seem to be imported using IIIF Items."));
            Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->gotoUrl($returnUrl);
            return;
        }
        // Try to repair item
        $originalFiles = $item->getFiles();
        try {
            $jsonData = json_decode($jsonStr, true);
            foreach ($jsonData['images'] as $image) { 
                $downloader = new IiifItems_ImageDownloader($image);
                $file = $downloader->downloadToItem($item, 'full');
            }
        } catch (Exception $ex) {
            $this->_helper->flashMessenger(__("Unable to repair item."));
            Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->gotoUrl($returnUrl);
            return;
        }
        // Done
        foreach ($originalFiles as $originalFile) {
            $originalFile->delete();
        }
        $this->_helper->flashMessenger(__("Item successfully repaired. Please recheck contents."));
        Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->gotoUrl($returnUrl);
    }
    
    public function maintenanceAction() {
        $this->__blockPublic();
    }
    
    public function cleanCacheAction() {
        // Block unwanted people
        $this->__blockPublic();
        $this->__restrictVerb('POST');
        
        // Set up request
        $request = $this->getRequest();
        $targetType = $this->getParam('type');
        $targetId = $this->getParam('id');
        $db = get_db();
        
        // Clear all
        if ($targetType == 'all' && $targetId == 'all') {
            $db->query("TRUNCATE `{$db->prefix}iiif_items_cached_json_data`;");
            $this->_helper->flashMessenger(__("JSON cache purged."));
            $targetUrl = $request->getServer('HTTP_REFERER', WEB_ROOT . admin_url(array(), 'iiifItemsMaintenance'));
            Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->gotoUrl($targetUrl);
            return;
        }
        // Clear for just one record
        else {
            try {
                $target = get_record_by_id($targetType, $targetId);
                if ($target) {
                    $db->query("DELETE FROM `{$db->prefix}iiif_items_cached_json_data` WHERE record_id = ? AND record_type = ?;", array($targetId, $targetType));
                    $this->_helper->flashMessenger(__("Cleaned JSON cached data."));
                    switch (get_class($target)) {
                        case 'Collection': 
                            $targetUrl = $request->getServer('HTTP_REFERER', WEB_ROOT . admin_url(array(
                                'controller' => 'collections',
                                'action' => 'show',
                                'id' => $targetId,
                            ), 'id'));
                        break;
                        case 'Item':
                            $targetUrl = $request->getServer('HTTP_REFERER', WEB_ROOT . admin_url(array(
                                'controller' => 'items',
                                'action' => 'show',
                                'id' => $targetId,
                            ), 'id'));
                        break;
                        default: 
                            $targetUrl = $request->getServer('HTTP_REFERER', WEB_ROOT . admin_url()); 
                        break;
                    }
                    Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->gotoUrl($targetUrl);
                    return;
                }
            }
            catch (Exception $e) {
            }
        }
        
        // Fail
        $this->_helper->flashMessenger("Failed to purge JSON cache");
    }
}
