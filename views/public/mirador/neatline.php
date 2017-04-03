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
        .window-manifest-navigation { width: 30%; }
        .window-manifest-title { width: 63%; max-width: 63%; text-overflow: ellipsis; max-height: 1.4em; }
    </style>
</head>
<body>
    <div id="viewer"></div>
    <script src="<?php echo $urlJs; ?>"></script>
    <script type="text/javascript">
    $(function() {
        var default_manifest = <?php echo js_escape(absolute_url(array('things' => 'items', 'id' => $itemId), 'iiifitems_manifest')); ?>;
        Mirador({
            "id": "viewer",
            "buildPath": "<?php echo html_escape($mirador_path) . '/'; ?>",
            "layout": "1",
            "data": [
                { "manifestUri": default_manifest } 
            ],
            "windowObjects": [{
                imageMode: "ImageView",
                loadedManifest: default_manifest,
                slotAddress: "row1.column1",
                viewType: "ImageView",
                displayLayout: false,
                bottomPanel: false,
                bottomPanelAvailable: false,
                bottomPanelVisible: false,
                sidePanel: false,
                annotationLayer: true
            }],
            "openManifestsPage": true,
            "windowSettings": {
                availableViews: [],
                canvasControls: {
                    annotations: {
                        annotationLayer: true,
                        annotationState: "on",
                        annotationRefresh: true
                    }
                },
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
