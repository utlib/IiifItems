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
                    $importSourceBody = $form->getValue('items_import_source_url');
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
}
