<div class="mirador-item">
    <?php
        $params = array(
            'u' => array(),
            'c' => array(),
            'p' => !empty($options['types']) && $options['types'][0] == 'Collection',
        );
        foreach ($options['manifests'] as $i => $url) {
            if ($options['types'][$i] == 'Collection') {
                $params['c'][] = $url;
            } else {
                $params['u'][] = $url;
            }
        }
    ?>
    <iframe src="<?php echo public_full_url(array(), 'iiifitems_exhibit_mirador', $params); ?>" allowfullscreen="allowfullscreen"></iframe>
</div>
