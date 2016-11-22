<?php

class IiifItems_CachedJsonData extends Omeka_Record_AbstractRecord {
    public $id, $record_id, $record_type, $url, $data, $generated;
    
    public function getRecord() {
        return get_db()->getTable($this->record_type)->find($this->record_id);
    }
    
    public function getData() {
        return json_decode($this->data, true);
    }
}
