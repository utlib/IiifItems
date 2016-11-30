<?php
	$urlJs = src('mirador', 'js/mirador', 'js');
	$endpointJs = src('mirador_endpoint', 'js', 'js');
	$urlCss = src('mirador-combined', 'js/mirador/css', 'css');
?>
<!DOCTYPE html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<link rel="stylesheet" type="text/css" href="<?php echo $urlCss; ?>">
	<style type="text/css">
		body { padding: 0; margin: 0; overflow: hidden; font-size: 70%; }
		#viewer { background: #333; width: 100%; height: 100%; position: fixed; }
	</style>
        <title>Mirador Viewer</title>
</head>
<body>
	<div id="viewer"></div>
	<script src="<?php echo $urlJs; ?>"></script>
	<script src="<?php echo $endpointJs; ?>"></script>
	<script type="text/javascript">
	$(function() {
		var anno_token;
		Mirador({
			"id": "viewer",
			"buildPath": "<?php echo src('', 'js/mirador', '') . '/'; ?>",
			"layout": "1",
			"data": [
				{ "manifestUri": "<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest'); ?>" }
			],
			annotationEndpoint: {
				name: 'IiifItemsAnnotations',
				module: 'IiifItemsAnnotations',
				options: {
					'prefix': '<?php echo absolute_url(array(), 'iiifitems_annotator_create') ?>'
				}
			},
			"windowObjects": [{
				imageMode: "ImageView",
				loadedManifest: "<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest'); ?>",
				slotAddress: "row1.column1",
				viewType: "ImageView",
				displayLayout: false,
				sidePanel: false,
				annotationLayer: true,
				annotationCreation: true,
				annotationState: 'annoOncreateOn'
			}],
                        "autoHideControls": false,
			"mainMenuSettings": {
				show: false
			}
		});
	});
	</script>
</body>
</html>
