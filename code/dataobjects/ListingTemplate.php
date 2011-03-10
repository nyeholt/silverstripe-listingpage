<?php

/**
 * Description of ListingTemplate
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ListingTemplate extends DataObject {
	public static $db = array(
		'Title'				=> 'Varchar(127)',
		'ItemTemplate'		=> 'Text',
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->replaceField('ItemTemplate', new TextareaField('ItemTemplate', _t('ListingTemplate.ITEM_TEMPLATE', 'Item Template (use the Item variable to iterate over)'), 20));
		return $fields;
	}
}
