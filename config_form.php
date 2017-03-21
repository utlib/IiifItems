<?php
    $form = new IiifItems_Form_Config();
    $form->removeDecorator('Form');
    $form->removeDecorator('Zend_Form_Decorator_Form');
    echo $form;
?>