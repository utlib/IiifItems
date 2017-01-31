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
                <?php if ($type != 'collections' || !IiifItems_CollectionUtil::isCollection($thing)) : ?>
                { "manifestUri": "<?php echo absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest'); ?>" }
                <?php else : ?>
                <?php foreach (IiifItems_CollectionUtil::findSubcollectionsFor($thing) as $subcollection) : ?>
                { "collectionUri": "<?php echo absolute_url(array('id' => $subcollection->id), 'iiifitems_collection'); ?>" },
                <?php endforeach; ?>
                <?php foreach (IiifItems_CollectionUtil::findSubmanifestsFor($thing) as $submanifest) : ?>
                { "manifestUri": "<?php echo absolute_url(array('things' => $type, 'id' => $submanifest->id), 'iiifitems_manifest'); ?>" },
                <?php endforeach; ?>
                {}
                <?php endif; ?>
            ],
            <?php 
                $defaultManifest = '';
                if ($type != 'collections' || !IiifItems_CollectionUtil::isCollection($thing)) {
                    $defaultManifest = absolute_url(array('things' => $type, 'id' => $thing->id), 'iiifitems_manifest');
                }
//                else {
//                    $submanifests = IiifItems_CollectionUtil::findSubmanifestsFor($thing);
//                    if (count($submanifests) > 0) {
//                        $defaultManifest = absolute_url(array('things' => $type, 'id' => $submanifests[0]->id), 'iiifitems_manifest');
//                    }
//                }
            ?>
            "windowObjects": [{
                imageMode: "ImageView",
                loadedManifest: "<?php echo $defaultManifest; ?>",
                slotAddress: "row1.column1",
                viewType: "ImageView",
                displayLayout: <?php echo ($type != 'collections' || !IiifItems_CollectionUtil::isCollection($thing)) ? 'false' : 'true'; ?>,
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
                sidePanelVisible: false
            },
            "autoHideControls": false,
            "mainMenuSettings": {
                show: false
            }
        });
        <?php if ($type == 'collections' && IiifItems_CollectionUtil::isCollection($thing)) : ?>
        var interval = setInterval(function() {
            if ($('.addItemLink:first').length > 0) {
                $('.addItemLink:first').click();
                clearInterval(interval);
            }
        }, 100);
        <?php endif; ?>
    });
    </script>
</body>
</html>
