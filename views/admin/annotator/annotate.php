<?php
    $title = metadata($thing, array('Dublin Core', 'Title'));
    echo head(array(
        'title' => __('Annotating: "%s"', $title),
    ));
?>
<?php
	$urlJs = src('mirador', 'js/mirador', 'js');
	$urlCss = src('mirador-combined', 'js/mirador/css', 'css');
?>
<a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : admin_url(array('controller' => $type, 'id' => $thing->id, 'action' => 'show'), 'id'); ?>" class="green button"><?php echo __('Return to Previous Page'); ?></a>
<iframe src="<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_annotator'); ?>" allowfullscreen="true" style="width:100%; height:600px;"></iframe>
<?php echo foot(); ?>
