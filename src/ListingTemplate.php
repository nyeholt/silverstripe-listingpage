<?php

/**
 * Description of ListingTemplate
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ListingTemplate extends DataObject
{
    public static $db = array(
    'Title'                => 'Varchar(127)',
    'ItemTemplate'        => 'Text',
    );
    
    public static $defaults = array(
    'ItemTemplate'        => "\t<% loop \$Items %>\n\t\t<p>\$Title</p>\n\t<% end_loop %>",
    );

    public function getCMSFields() 
    {
        $fields = parent::getCMSFields();
        $fields->replaceField('ItemTemplate', $ta = new TextareaField('ItemTemplate', _t('ListingTemplate.ITEM_TEMPLATE', 'Item Template (use the Items variable to iterate over)')));
        $ta->setRows(20);
        $ta->setColumns(120);
        return $fields;
    }
}
