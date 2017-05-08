<?php

/**
 * A record for the status of background running jobs.
 * @package models
 */
class IiifItems_JobStatus extends Omeka_Record_AbstractRecord {
    /**
     * The database primary key.
     * @var integer
     */
    public $id;
    
    /**
     * The description of the job that this job status describes.
     * @var string
     */
    public $source;
    
    /**
     * Number of subtasks successfully done.
     * @var integer
     */
    public $dones;
    
    /**
     * Number of subtasks skipped.
     * @var integer
     */
    public $skips;
    
    /**
     * Number of subtasks failed.
     * @var integer
     */
    public $fails;
    
    /**
     * Description of the status of the job.
     * @var string
     */
    public $status;
    
    /**
     * Number of subtasks completed so far.
     * @var integer
     */
    public $progress;
    
    /**
     * Total number of subtasks forecasted for this job.
     * @var integer
     */
    public $total;
    
    /**
     * When the job was started.
     * @var DateTime
     */
    public $added;
    
    /**
     * When the job status was last updated.
     * @var DateTime
     */
    public $modified;
}
