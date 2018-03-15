<?php if (!empty($attachments)): ?>

<div class="mirador-item">
    <iframe src="<?php echo public_full_url(array('things' => 'exhibit_page_blocks', 'id' => $attachments[0]->block_id), 'iiifitems_mirador'); ?>" allowfullscreen="allowfullscreen"></iframe>
    <p><?php echo $text; ?></p>
</div>

<?php endif; ?>
