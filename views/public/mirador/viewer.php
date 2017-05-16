<?php
    $mirador_path = get_option('iiifitems_mirador_path');
    $css_path = get_option('iiifitems_mirador_css');
    $urlJs = $mirador_path . '/' . get_option('iiifitems_mirador_js');
    $urlCss = (strpos($css_path, 'http://') === 0 || strpos($css_path, 'https://') === 0) ? $css_path : ($mirador_path . '/' . $css_path);
?>
<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link rel="stylesheet" type="text/css" href="<?php echo html_escape($urlCss); ?>">
    <style type="text/css">
        body { padding: 0; margin: 0; overflow: hidden; }
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
                { "manifestUri": "<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest'); ?>" }
            ],
            <?php 
                $defaultManifest = absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest');
            ?>
            "windowObjects": [{
                imageMode: "ImageView",
                loadedManifest: "<?php echo $defaultManifest; ?>",
                slotAddress: "row1.column1",
                viewType: "ImageView",
                displayLayout: false,
                sidePanel: true,
                annotationLayer: true
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
