<?php

function public_full_url() {
    $serverUrlHelper = new Zend_View_Helper_ServerUrl;
    $args = func_get_args();
    return $serverUrlHelper->serverUrl() . call_user_func_array('public_url', $args);
}

function filter_hide_element_display($comps, $args) {
    return '';
}

function filter_singular_form($comps, $args) {
    $comps['add_input'] = false;
    return $comps;
}

function filter_minimal_input($comps, $args) {
    $comps['form_controls'] = '';
    $comps['html_checkbox'] = false;
    return $comps;
}