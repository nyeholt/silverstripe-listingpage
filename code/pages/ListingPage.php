<?php
/*

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
OF SUCH DAMAGE.
*/

/**
 * 
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ListingPage extends Page
{
    public static $db = array(
		'ItemTemplate' => 'HTMLText',
		'PerPage' => 'Int',
		'Style' => "Enum('Standard,A to Z')",
		'SortBy' => "Varchar(64)",
		'CustomSort' => 'Varchar(64)',
		'SortDir' => "Enum('Ascending,Descending')",
		'ListType' => 'Varchar(64)',
		'Depth' => 'Int',
	);

	public static $has_one = array(
		'ListingSource' => 'Page',
	);

	/**
	 * @return FieldSet
	 */
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		/* @var FieldSet $fields */

		$fields->removeFieldFromTab('Root.Content.Main', 'Content');

		$fields->addFieldToTab('Root.Content.Main', new TextAreaField('Content', _t('ListingPage.CONTENT_TEMPLATE', 'Content Template'), 10));
		$fields->addFieldToTab('Root.Content.Main', new NumericField('PerPage', _t('ListingPage.PER_PAGE', 'Items Per Page')));
		$fields->addFieldToTab('Root.Content.Main', new DropdownField('SortDir', _t('ListingPage.SORT_DIR', 'Sort Direction'), $this->dbObject('SortDir')->enumValues()));

		$fields->addFieldToTab('Root.Content.Main', new DropdownField('Depth', _t('ListingPage.DEPTH', 'Depth'), array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5)));

		$listType = $this->ListType ? $this->ListType : 'Page';
		$objFields = singleton($listType)->inheritedDatabaseFields();
		$objFields = array_keys($objFields);
		$objFields = array_combine($objFields, $objFields);

		$fields->addFieldToTab('Root.Content.Main', new DropdownField('SortBy', _t('ListingPage.SORT_BY', 'Sort By'), $objFields));
		// $fields->addFieldToTab('Root.Content.Main', new TextField('CustomSort', _t('ListingPage.CUSTOM_SORT', 'Custom sort field')));

		$types = SiteTree::page_type_classes(); 
		$source = array_combine($types, $types);
		asort($source);
		$optionsetField = new DropdownField('ListType', _t('ListingPage.PAGE_TYPE', 'List pages of type'), $source, 'Any');
		$fields->addFieldToTab('Root.Content.Main', $optionsetField);

		$fields->addFieldToTab('Root.Content.Main', new TreeDropdownField('ListingSourceID', _t('ListingPage.LISTING_SOURCE', 'Source of content for listing'), 'Page'));

		return $fields;
	}

	public function ListingItems()
	{
		// need to get the items being listed
		$source = $this->ListingSource();

		if (!$source) {
			$source = $this;
		}

		$listType = $this->ListType ? $this->ListType : 'Page';

		$ids = $this->getIdsFrom($source, 1);
		$ids[] = $source->ID;

		$filter = db_quote(array('ParentID IN ' => $ids));
		$sortDir = $this->SortDir == 'Ascending' ? 'ASC' : 'DESC';
		$sort = $this->SortBy ? $this->SortBy : 'Title';
		// $sort = $this->CustomSort ? $this->CustomSort : $sort;
		$sort .= ' '.$sortDir;

		$limit = '';

		$pageUrlVar = 'page'.$this->ID;

		if ($this->PerPage) {
			$page = isset($_REQUEST[$pageUrlVar]) ? $_REQUEST[$pageUrlVar] : 0;
			$limit = "$page,$this->PerPage";
		}

		$items = DataObject::get($listType, $filter, $sort, '', $limit);
		/* @var $items DataObjectSet */
//		$items->setPageLength($this->PerPage);
		$items->setPaginationGetVar($pageUrlVar);

		return $items;
	}

	/**
	 * Recursively find all the child items that need to be listed
	 *
	 * @param DataObject $parent
	 * @param int $depth
	 */
	protected function getIdsFrom($parent, $depth)
	{
		if ($depth >= $this->Depth) {
			return;
		}
		$ids = array();
		foreach ($parent->Children() as $kid) {
			$ids[] = $kid->ID;
			$childIds = $this->getIdsFrom($kid, $depth + 1);
			if ($childIds) {
				$ids = array_merge($ids, $childIds);
			}
		}

		return $ids;
	}

	public function Content()
	{
		$items = $this->ListingItems();
		$item = $this->customise(array('Items' => $items));
		$view = SSViewer::fromString($this->Content);
		return $view->process($item);
	}
}

class ListingPage_Controller extends Page_Controller
{
    
}
?>