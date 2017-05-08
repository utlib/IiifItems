<!DOCTYPE html>
<html>
<head>
    <title><?php echo metadata($collection, array('Dublin Core', 'Title')) ?></title>
    <meta charset="utf-8">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsviews/0.9.76/jsviews.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/es6-promise/3.2.2/es6-promise.min.js"></script>
    <?php
        echo '<link rel="stylesheet" href="' . src('iiif-explorer-component', 'js/iiif-explorer-component/css', 'css') . '">';
        echo '<script src="' . src('base-component.bundle', 'js/iiif-explorer-component/js', 'js') . '"></script>';
        echo '<script src="' . src('iiif-explorer-component', 'js/iiif-explorer-component/js', 'js') . '"></script>';
        echo '<script src="' . src('manifold.bundle', 'js/iiif-explorer-component/js', 'js') . '"></script>';
    ?>
</head>
<body>
    <div id="explorer" class="iiif-explorer-component">Loading...</div>
    <script>
        var helper, component;

        $(function() {
            Manifold.loadManifest({
                iiifResourceUri: <?php echo js_escape(public_full_url(array('id' => $collection->id), 'iiifitems_collection')); ?>,
                collectionIndex: 0,
                manifestIndex: 0,
                sequenceIndex: 0,
                canvasIndex: 0
            }).then(function(h){

                helper = h;

                component = new IIIFComponents.ExplorerComponent({
                    target: document.querySelector('#explorer'),
                    data: {
                        helper: h
                    }
                });

                component.on('explorerNodeSelected', function(args) {
                    var node = args[0];
                    console.log('selected', node);
                    var _target = window.parent ? window.parent : window;
                    var urlcomps = node.id.split('/');
                    var prefix = <?php echo js_escape(public_full_url(array(), 'iiifitems_root') . (is_admin_theme() ? 'admin/' : '')); ?>;
                    _target.location = prefix + "collections/show/" + urlcomps[urlcomps.length-2];
                });

                component.set();

            }).catch(function(e) {
                console.error(e);
                console.error('failed to load manifest');
            });
        });
    </script>
</body>
</html>