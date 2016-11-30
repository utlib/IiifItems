<?php
    $title = metadata($thing, array('Dublin Core', 'Title'));
    echo head(array(
        'title' => $title,
    ));
?>
<?php
	$urlJs = src('mirador', 'js/mirador', 'js');
	$urlCss = src('mirador-combined', 'js/mirador/css', 'css');
?>
<iframe src="<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_annotator'); ?>" allowfullscreen="allowfullscreen" style="width:100%; height:600px;"></iframe>
<?php echo foot(); ?>
