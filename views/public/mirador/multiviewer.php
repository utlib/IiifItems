<?php
    $mirador_path = get_option('iiifitems_mirador_path');
    $urlJs = $mirador_path . '/mirador.js';
    $urlCss = $mirador_path . '/css/mirador-combined.css';
?>
<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link rel="stylesheet" type="text/css" href="<?php echo html_escape($urlCss); ?>">
    <style type="text/css">
        body { padding: 0; margin: 0; overflow: hidden; font-size: 70%; }
        #viewer { background: #333; width: 100%; height: 100%; position: fixed; }
    </style>
</head>
<body>
    <div id="viewer"></div>
    <script src="<?php echo $urlJs; ?>"></script>
    <script type="text/javascript">
    $(function() {
        var anno_token;
        Mirador({
            "id": "viewer",
            "buildPath": "<?php echo html_escape($mirador_path) . '/'; ?>",
            "layout": "1",
            "data": [
                <?php foreach ($item_ids as $i => $item_id): ?>
                <?php if ($i > 0) echo ','; ?>{ "manifestUri": "<?php echo public_full_url(array('things' => 'items', 'id' => $item_id), 'iiifitems_manifest'); ?>" }
                <?php endforeach; ?>
            ],
            "windowObjects": [{
                imageMode: "ImageView",
                <?php if (!empty($item_ids)): ?>
                loadedManifest: "<?php echo public_full_url(array('things' => 'items', 'id' => $item_ids[0]), 'iiifitems_manifest'); ?>",
                <?php endif; ?>
                slotAddress: "row1.column1",
                viewType: "ImageView",
                annotationLayer: true
            }],
            "windowSettings": {
                canvasControls: {
                    annotations: {
                        annotationLayer: true,
                        annotationState: "on",
                        annotationRefresh: true
                    }
                }
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
