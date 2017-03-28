<?php
$formStem = $block->getFormStem();
$options  = $block->getOptions();
$template = '<div class="mirador-manifest-row">'
        . $this->formSelect("{$formStem}[options][types][]", 'Manifest', array('style' => 'width: 18%; margin-right: 2%; height: 30px; margin-bottom: 10px;', 'multiple' => false), array('Manifest' => 'Manifest', 'Collection' => 'Collection'))
        . $this->formText("{$formStem}[options][manifests][]", '', array('style' => 'width:80%;', 'placeholder' => 'URL'))
        . '<button class="add-manifest">Add Another</button>'
        . '<button class="red remove-manifest">Remove</button>'
        . '</div>';
?>
<div class="mirador-manifest-form" id="mirador-manifest-form-<?php $block->id ?>" data-template="<?php echo html_escape($template); ?>">
    <script>
        jQuery('#mirador-manifest-form-<?php $block->id ?>').ready(function() {
            var _this = jQuery('#mirador-manifest-form-<?php $block->id ?>'),
                hookRowElements = function(row) {
                    _this.find('.remove-manifest').on('click', function(ev) {
                        ev.preventDefault();
                        jQuery(this).parent().remove();
                        manageDeleteButton();
                        return false;
                    });
                    _this.find('.add-manifest').on('click', function(ev) {
                        ev.preventDefault();
                        _this.append(_this.data('template'));
                        manageDeleteButton();
                        return false;
                    });
                },
                manageDeleteButton = function() {
                    _this.find('.remove-manifest').toggle(
                        _this.find('.mirador-manifest-row').length > 1
                    );
                };
            _this.on('click', '.remove-manifest', function(ev) {
                ev.preventDefault();
                jQuery(this).parent().remove();
                manageDeleteButton();
                return false;
            });
            _this.on('click', '.add-manifest', function(ev) {
                ev.preventDefault();
                jQuery(this).parent().after(_this.data('template'));
                manageDeleteButton();
                return false;
            });
            manageDeleteButton();
        });
    </script>
    <style>
        
    </style>
    <?php
    if (empty(@$options['manifests'])) {
        @$options['manifests'] = array('');
    }
    if (empty(@$options['types'])) {
        @$options['types'] = array_fill(0, count(@$options['manifests']), 'Manifest');
    }
    foreach (@$options['manifests'] as $i => $manifest) {
        echo '<div class="mirador-manifest-row">';
        echo $this->formSelect("{$formStem}[options][types][]", @$options['types'][$i], array('style' => 'width: 18%; margin-right: 2%; height: 30px; margin-bottom: 10px;', 'multiple' => false), array('Manifest' => 'Manifest', 'Collection' => 'Collection'));
        echo $this->formText("{$formStem}[options][manifests][]", $manifest, array('style' => 'width:80%;', 'placeholder' => 'URL'));
        echo '<button class="add-manifest">Add Another</button>';
        echo '<button class="red remove-manifest">Remove</button>';
        echo '</div>';
    }
    ?>
</div>