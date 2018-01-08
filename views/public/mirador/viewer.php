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
            "language": "<?php echo str_replace('_', '-', Zend_Registry::get('bootstrap')->getResource('Locale')->toString()); ?>",
            "layout": "1",
            "data": [
                <?php if ($type != 'collections' || !IiifItems_Util_Collection::isCollection($thing)) : ?>
                { "manifestUri": "<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest'); ?>" }
                <?php else : ?>
                <?php foreach (IiifItems_Util_Collection::findSubcollectionsFor($thing) as $subcollection) : ?>
                { "collectionUri": "<?php echo absolute_url(array('id' => $subcollection->id), 'iiifitems_collection'); ?>" },
                <?php endforeach; ?>
                <?php foreach (IiifItems_Util_Collection::findSubmanifestsFor($thing) as $submanifest) : ?>
                { "manifestUri": "<?php echo absolute_url(array('things' => $type, 'id' => $submanifest->id), 'iiifitems_manifest'); ?>" },
                <?php endforeach; ?>
                {}
                <?php endif; ?>
            ],
            <?php 
                $defaultManifest = '';
                if ($type != 'collections' || !IiifItems_Util_Collection::isCollection($thing)) {
                    $defaultManifest = absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest');
                }
//                else {
//                    $submanifests = IiifItems_Util_Collection::findSubmanifestsFor($thing);
//                    if (count($submanifests) > 0) {
//                        $defaultManifest = absolute_url(array('things' => $type, 'id' => $submanifests[0]->id), 'iiifitems_manifest');
//                    }
//                }
            ?>
            <?php if ($type == 'collections' && IiifItems_Util_Collection::isCollection($thing)) : ?>
            "openManifestsPage": true,
            <?php else: ?>
            "windowObjects": [{
                imageMode: "ImageView",
                loadedManifest: "<?php echo $defaultManifest; ?>",
                slotAddress: "row1.column1",
                viewType: "ImageView",
                displayLayout: <?php echo ($type != 'collections' || !IiifItems_Util_Collection::isCollection($thing)) ? 'false' : 'true'; ?>,
                sidePanel: true,
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
