<?php

/**
 * A record for cached IIIF JSON data.
 * @package models
 */
class IiifItems_CachedJsonData extends Omeka_Record_AbstractRecord {
    /**
     * The database primary key for this entry.
     * @var integer
     */
    public $id;
    
    /**
     * The ID of the record that this entry is attached to.
     * @var integer
     */
    public $record_id;
    
    /**
     * The name of the record type that this entry is attached to.
     * @var string
     */
    public $record_type;
    
    /**
     * Where this entry refers to (e.g. admin_manifest).
     * @var string
     */
    public $url;
    
    /**
     * The cached JSON data in string form.
     * @var string
     */
    public $data;
    
    /**
     * The date and time that this entry was generated.
     * @var datetime
     */
    public $generated;
    
    /**
     * Return the JSON data held by this entry in nested array form.
     * 
     * @return array
     */
    public function getData() {
        return json_decode($this->data, true);
    }
}
