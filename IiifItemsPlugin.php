<?php
class IiifItemsPlugin extends Omeka_plugin_AbstractPlugin
{
	protected $_hooks = array(
	);
	
	protected $_filters = array(
            'display_elements',
            // Annotation Type Metadata
            'formForAnnotationOnCanvas' => array('ElementForm', 'Item', 'Item Type Metadata', 'On Canvas'),
            'inputForAnnotationOnCanvas' => array('ElementInput', 'Item', 'Item Type Metadata', 'On Canvas'),
            'formForAnnotationSelector' => array('ElementForm', 'Item', 'Item Type Metadata', 'Selector'),
            'inputForAnnotationSelector' => array('ElementInput', 'Item', 'Item Type Metadata', 'Selector'),
            // Item Metadata
            'formForItemDisplay' => array('ElementForm', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'),
            'inputForItemDisplay' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Display as IIIF?'),
            'formForItemOriginalId' => array('ElementForm', 'Item', 'IIIF Item Metadata', 'Original @id'),
            'inputForItemOriginalId' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Original @id'),
            'displayForItemParent' => array('Display', 'Item', 'IIIF Item Metadata', 'Parent Collection'),
            'formForItemParent' => array('ElementForm', 'Item', 'IIIF Item Metadata', 'Parent Collection'),
            'inputForItemParent' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'Parent Collection'),
            'displayForItemJson' => array('Display', 'Item', 'IIIF Item Metadata', 'JSON Data'),
            'formForItemJson' => array('ElementForm', 'Item', 'IIIF Item Metadata', 'JSON Data'),
            'inputForItemJson' => array('ElementInput', 'Item', 'IIIF Item Metadata', 'JSON Data'),
            // Collection Metadata
            'formForCollectionOriginalId' => array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Original @id'),
            'inputForCollectionOriginalId' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Original @id'),
            'formForCollectionIiifType' => array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'),
            'inputForCollectionIiifType' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'IIIF Type'),
            'displayForCollectionParent' => array('Display', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'),
            'formForCollectionParent' => array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'),
            'inputForCollectionParent' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'Parent Collection'),
            'displayForCollectionJson' => array('Display', 'Collection', 'IIIF Collection Metadata', 'JSON Data'),
            'formForCollectionJson' => array('ElementForm', 'Collection', 'IIIF Collection Metadata', 'JSON Data'),
            'inputForCollectionJson' => array('ElementInput', 'Collection', 'IIIF Collection Metadata', 'JSON Data'),
	);
        
        public function filterDisplayElements($elementsBySet) {
            unset($elementsBySet['IIIF Collection Metadata']['JSON Data']);
            return $elementsBySet;
        }
        
        /* Annotation Type Metadata */
        
        public function formForAnnotationOnCanvas($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForAnnotationOnCanvas($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        public function formForAnnotationSelector($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForAnnotationSelector($comps, $args) {
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        /* */
        
        public function formForItemDisplay($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForItemDisplay($comps, $args) {
            $comps['input'] = get_view()->formRadio($args['input_name_stem'] . '[text]', $args['value'], array(), array('No', 'Yes'));
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        public function formForItemOriginalId($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForItemOriginalId($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        public function displayForItemParent($text, $args) {
            $collection = get_db()->getTable('Collection')->find($args['element_text']->text);
            return '<a href="' . url($collection) . '">' . html_escape(metadata($collection, 'display_title')) . '</a>';

        }
        
        public function formForItemParent($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForItemParent($comps, $args) {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), get_table_options('Collection'));
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        public function formForCollectionOriginalId($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForCollectionOriginalId($comps, $args) {
            $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        public function displayForItemJson($text, $args) {
            return '';
        }
        
        public function formForItemJson($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForItemJson($comps, $args) {
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        /* Collection metadata */
        
        public function formForCollectionIiifType($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForCollectionIiifType($comps, $args) {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), array(''=>'None','Manifest'=>'Manifest','Collection'=>'Collection'));
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        public function displayForCollectionParent($text, $args) {
            $collection = get_db()->getTable('Collection')->find($args['element_text']->text);
            return '<a href="' . url($collection) . '">' . html_escape(metadata($collection, 'display_title')) . '</a>';

        }
        
        public function formForCollectionParent($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForCollectionParent($comps, $args) {
            $comps['input'] = get_view()->formSelect($args['input_name_stem'] . '[text]', $args['value'], array(), get_table_options('Collection'));
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
        
        public function displayForCollectionJson($text, $args) {
            return '';
        }
        
        public function formForCollectionJson($comps, $args) {
            $comps['add_input'] = false;
            return $comps;
        }
        
        public function inputForCollectionJson($comps, $args) {
            $comps['form_controls'] = '';
            $comps['html_checkbox'] = false;
            return $comps;
        }
}
?>
