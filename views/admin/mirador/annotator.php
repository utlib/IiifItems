<?php
	$urlJs = src('mirador', 'js/mirador', 'js');
	$endpointJs = src('mirador_endpoint', 'js', 'js');
        $tinymceJs = src('omeka_tinymce', 'js', 'js');
	$urlCss = src('mirador-combined', 'js/mirador/css', 'css');
?>
<!DOCTYPE html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<link rel="stylesheet" type="text/css" href="<?php echo $urlCss; ?>">
	<style type="text/css">
		body { padding: 0; margin: 0; overflow: hidden; }
		#viewer { background: #333; width: 100%; height: 100%; position: fixed; }
	</style>
        <title><?php echo __("Mirador Viewer"); ?></title>
</head>
<body>
	<div id="viewer"></div>
	<script src="<?php echo $urlJs; ?>"></script>
	<script src="<?php echo $endpointJs; ?>"></script>
        <script src="<?php echo $tinymceJs; ?>"></script>
	<script type="text/javascript">
	$(function() {
		var anno_token;
		Mirador({
			"id": "viewer",
			"buildPath": "<?php echo src('', 'js/mirador', '') . '/'; ?>",
            "language": "<?php echo str_replace('_', '-', Zend_Registry::get('bootstrap')->getResource('Locale')->toString()); ?>",
			"layout": "1",
			"data": [
				{ "manifestUri": "<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest'); ?>" }
			],
			annotationEndpoint: {
				name: 'IiifItemsAnnotations',
				module: 'IiifItemsAnnotations',
				options: {
					'prefix': '<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_annotator_create') ?>',
                                        admin: <?php echo in_array(current_user()->role, array('super', 'admin')) ? 'true' : 'false'; ?>,
                                        userId: <?php echo current_user()->id; ?>
				}
			},
                        'annotationBodyEditor': {
                            'module': 'OmekaAnnotationEditor',
                            'options': {
                                config: {
                                    plugins: "image link media lists directionality",
                                    toolbar: "bold italic underline | bullist numlist | link image | removeformat | ltr rtl",
                                    admin: <?php echo in_array(current_user()->role, array('super', 'admin')) ? 'true' : 'false'; ?>
                                }
                            }
                        },
			"windowObjects": [{
				imageMode: "ImageView",
				loadedManifest: "<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest'); ?>",
				slotAddress: "row1.column1",
				viewType: "ImageView",
				displayLayout: false,
				sidePanel: true,
				annotationLayer: true,
				annotationCreation: true,
				annotationState: 'annoOncreateOn'
			}],
                        "windowSettings": {
                            canvasControls: {
                                annotations: {
                                    annotationLayer: true,
                                    annotationState: "on",
                                    annotationRefresh: true
                                }
                            },
                            bottomPanelVisible: false,
                            sidePanelVisible: false
                        },
                        "autoHideControls": false,
			"mainMenuSettings": {
				show: false
			}
		});
	});
	</script>
</body>
</html>
