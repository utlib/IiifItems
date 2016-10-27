<?php
    require_once IIIF_ITEMS_DIRECTORY . '/forms/Config.php';
    $form = new IiifItems_Form_Config();
    $form->removeDecorator('Form');
    $form->removeDecorator('Zend_Form_Decorator_Form');
    echo $form;
?>