<?php
$formStem = $block->getFormStem();
$options = $block->getOptions();
?>
<div class="selected-items">
    <h4><?php echo __('Items'); ?></h4>
    <?php echo $this->exhibitFormAttachments($block); ?>
    <h4><?php echo __('Description'); ?></h4>
    <?php echo $this->exhibitFormText($block); ?>
</div>