<?php
    $mirador_path = get_option('iiifitems_mirador_path');
    $css_path = get_option('iiifitems_mirador_css');
    $urlJs = $mirador_path . '/' . get_option('iiifitems_mirador_js');
    $urlCss = (strpos($css_path, 'http://') === 0 || strpos($css_path, 'https://') === 0) ? $css_path : ($mirador_path . '/' . $css_path);
    $urls = array();
    $types = array();
    if (!empty($item_ids)) {
        foreach ($item_ids as $item_id) {
            $types[] = 'Manifest';
            $urls[] = js_escape(public_full_url(array('things' => 'items', 'id' => $item_id), 'iiifitems_manifest'));
        }
    }
    if (!empty($manifests)) {
        foreach ($manifests as $manifest) {
            if (trim($manifest)) {
                $types[] = 'Manifest';
                $urls[] = js_escape($manifest);
            }
        }
    }
    if (!empty($collections)) {
        foreach ($collections as $collection) {
            if (trim($collection)) {
                $types[] = 'Collection';
                $urls[] = js_escape($collection);
            }
        }
    }
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
            "language": "<?php echo str_replace('_', '-', Zend_Registry::get('bootstrap')->getResource('Locale')->toString()); ?>",
            "layout": "1",
            "data": [
                <?php foreach ($urls as $i => $url): ?>
                <?php if ($i > 0) echo ','; ?>{ "<?php echo ($types[$i] == 'Collection') ? 'collectionUri' : 'manifestUri' ?>": <?php echo $url; ?> }
                <?php endforeach; ?>
            ],
            <?php if ($popup) : ?>
            "openManifestsPage": true,
            <?php else: ?>
            "windowObjects": [{
                imageMode: "ImageView",
                <?php if (!empty($urls) && !$popup): ?>
                loadedManifest: <?php echo $urls[0]; ?>,
                <?php endif; ?>
                sidePanel: true,
                slotAddress: "row1.column1",
                viewType: "ImageView",
                annotationLayer: true
            }],
            <?php endif; ?>
            "windowSettings": {
                canvasControls: {
                    annotations: {
                        annotationLayer: true,
                        annotationState: "on",
                        annotationRefresh: true
                    }
                },
                bottomPanelVisible: false,
                sidePanelVisible: false,
                sidePanelOptions: {
                    searchTabAvailable: true
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
