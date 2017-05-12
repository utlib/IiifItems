<?php

/**
 * Migration 0.0.1.11: Un-JSON-ify the SVG selector attribute in annotations
 * @package IiifItems
 * @subpackage Migration
 */
class IiifItems_Migration_0_0_1_11 extends IiifItems_BaseMigration {
    public static $version = '0.0.1.11';
    
    /**
     * Migrate up
     */
    public function up() {
        $db = get_db();
        $quoteOpen = '\"<';
        $quoteClose = '>\"';
        $quote = '\\\"';
        $newQuote = '\"';
        $selectorElementId = get_option('iiifitems_annotation_selector_element');
        $db->query("UPDATE `{$db->prefix}element_texts` SET `text` = REPLACE(REPLACE(REPLACE(`text`, '{$quoteOpen}', '<'), '{$quoteClose}', '>'), '{$quote}', '{$newQuote}') WHERE `element_id` = {$selectorElementId}");
    }
}
