<?php
$item_ids = array();
foreach ($attachments as $attachment) {
    $item_ids[] = $attachment->item_id;
}
?>

<div class="mirador-item">
    <iframe src="<?php echo public_full_url(array(), 'iiifitems_exhibit_mirador', array('items' => join(',', $item_ids))); ?>"></iframe>
</div>
