<?php

class IiifItems_JobStatus extends Omeka_Record_AbstractRecord {
    public $id, $source, $dones, $skips, $fails, $status, $progress, $total, $added, $modified;
}
