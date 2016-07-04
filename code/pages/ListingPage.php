<?php
/**
 * A page that can be configured to create listings of other content
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ListingPage extends Page {

	private static $db = array(
		'PerPage'					=> 'Int',
		'Style'						=> "Enum('Standard,A to Z')",
		'SortBy'					=> "Varchar(64)",
		'CustomSort'				=> 'Varchar(64)',
		'SortDir'					=> "Enum('Ascending,Descending')",
		'ListType'					=> 'Varchar(64)',
		'ListingSourceID'			=> 'Int',
		'Depth'						=> 'Int',
		'ClearSource'				=> 'Boolean',
		'StrictType'				=> 'Boolean',
		
		'ContentType'				=> 'Varchar',
		'CustomContentType'			=> 'Varchar',

		'ComponentFilterName'		=> 'Varchar(64)',
		'ComponentFilterColumn'		=> 'Varchar(64)',
		'ComponentFilterWhere'		=> 'MultiValueField', // todo(Jake): move to get_extra_config or extension
	);

	private static $has_one = array(
		'ListingTemplate'			=> 'ListingTemplate',
	);

	/**
	 * A mapping between ListType selected and the type of items that should be shown in the "Source" 
	 * selection tree. If not specified in this mapping, it is assumed to be 'Page'.
	 *
	 * @var array
	 */
	private static $listing_type_source_map = array(
		'Folder'	=> 'Folder'
	);

	private static $icon = 'listingpage/images/listingpage.png';

	/**
	 * @return FieldSet
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		/* @var FieldSet $fields */

		$fields->replaceField('Content', new HtmlEditorField('Content', _t('ListingPage.CONTENT', 'Content (enter $Listing to display the listing)')));

		$templates = DataObject::get('ListingTemplate');
		if ($templates) {
			$templates = $templates->map();
		} else {
			$templates = array();
		}

		$fields->addFieldToTab('Root.ListingSettings', new DropdownField('ListingTemplateID', _t('ListingPage.CONTENT_TEMPLATE', 'Listing Template'), $templates));
		$fields->addFieldToTab('Root.ListingSettings', new NumericField('PerPage', _t('ListingPage.PER_PAGE', 'Items Per Page')));
		$fields->addFieldToTab('Root.ListingSettings', new DropdownField('SortDir', _t('ListingPage.SORT_DIR', 'Sort Direction'), $this->dbObject('SortDir')->enumValues()));

		$listType = $this->ListType ? $this->ListType : 'Page';
		$objFields = $this->getSelectableFields($listType);

		$fields->addFieldToTab('Root.ListingSettings', new DropdownField('SortBy', _t('ListingPage.SORT_BY', 'Sort By'), $objFields));
		// $fields->addFieldToTab('Root.Content.Main', new TextField('CustomSort', _t('ListingPage.CUSTOM_SORT', 'Custom sort field')));

		$types = ClassInfo::subclassesFor('DataObject');
		array_shift($types);
		$source = array_combine($types, $types);
		asort($source);

		$optionsetField = new DropdownField('ListType', _t('ListingPage.PAGE_TYPE', 'List items of type'), $source, 'Any');
		$fields->addFieldToTab('Root.ListingSettings', $optionsetField);
		$fields->addFieldToTab('Root.ListingSettings', new CheckboxField('StrictType', _t('ListingPage.STRICT_TYPE', 'List JUST this type, not descendents')));

		$sourceType = $this->effectiveSourceType();
		$parentType = $this->parentType($sourceType);
		if ($sourceType && $parentType) {
			$fields->addFieldToTab('Root.ListingSettings', new DropdownField('Depth', _t('ListingPage.DEPTH', 'Depth'), array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5)));
			$fields->addFieldToTab('Root.ListingSettings', new TreeDropdownField('ListingSourceID', _t('ListingPage.LISTING_SOURCE', 'Source of content for listing'), $parentType));
			$fields->addFieldToTab('Root.ListingSettings', new CheckboxField('ClearSource', _t('ListingPage.CLEAR_SOURCE', 'Clear listing source value')));
		}

		$contentTypes = array(
			''						=> 'In Theme',
			'text/html; charset=utf-8'				=> 'HTML Fragment',
			'text/xml; charset=utf-8'				=> 'XML',
			'application/rss+xml; charset=utf-8'	=> 'RSS (xml)',
			'application/rdf+xml; charset=utf-8'	=> 'RDF (xml)',
			'application/atom+xml; charset=utf-8'	=> 'ATOM (xml)',
		);
		$fields->addFieldToTab('Root.ListingSettings', new DropdownField('ContentType', _t('ListingPage.CONTENT_TYPE', 'Content Type'), $contentTypes));
		$fields->addFieldToTab('Root.ListingSettings', new TextField('CustomContentType', _t('ListingPage.CUSTOM_CONTENT_TYPE', 'Custom Content Type')));

		if ($listType) {
			$componentsManyMany = singleton($this->ListType)->config()->many_many;
			$componentNames = array();
			foreach ($componentsManyMany as $componentName => $className) {
				$componentNames[$componentName] = FormField::name_to_label($componentName) . ' ('.$className.')';
			}
			$fields->addFieldToTab('Root.ListingSettings', DropdownField::create('ComponentFilterName', _t('ListingPage.TAG_COMPONENT_NAME', 'Filter by Component'), $componentNames)
				->setEmptyString('(Select)')
				->setRightTitle('Will cause this page to list items based on the last URL part. (ie. '.$this->AbsoluteLink().'{$componentFieldName})'));
			$fields->addFieldToTab('Root.ListingSettings', $componentColumnField = DropdownField::create('ComponentFilterColumn', 'Filter by Component Field')->setEmptyString('(Must select a component and save)')); 
			if ($this->ComponentFilterName) {
				$componentClass = isset($componentsManyMany[$this->ComponentFilterName]) ? $componentsManyMany[$this->ComponentFilterName] : '';
				if ($componentClass) {
					$componentFields = array();
					foreach ($this->getSelectableFields($componentClass) as $columnName => $type) {
						$componentFields[$columnName] = $columnName;
					}
					$componentColumnField->setSource($componentFields);
					$componentColumnField->setEmptyString('(Select)');

					if (class_exists('KeyValueField'))
					{
						$fields->addFieldToTab('Root.ListingSettings', KeyValueField::create('ComponentFilterWhere', 'Constrain By', $componentFields)
							->setRightTitle("Filter '{$this->ComponentFilterName}' with these properties."));
					}
				}
			}
		}

		return $fields;
	}
	
	protected function parentType($type) {
		$has_one = Config::inst()->get($type, 'has_one');
		return isset($has_one['Parent']) ? $has_one['Parent'] : null;
	}

	protected function getSelectableFields($listType) {
		$objFields = singleton($listType)->inheritedDatabaseFields();
		$objFields = array_keys($objFields);
		$objFields = array_combine($objFields, $objFields);
		$objFields['LastEdited'] = 'LastEdited';
		$objFields['Created'] = 'Created';
		$objFields['ID'] = 'ID';

		ksort($objFields);
		return $objFields;
	}

	/**
	 * When saving, check to see whether we should delete the
	 * listing source ID
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if (!$this->ID) {
			$this->Content = '$Listing';
		}
		if ($this->ClearSource) {
			$this->ClearSource = false;
			$this->ListingSourceID = 0;
		}
	}
	
	/**
	 * Some subclasses will want to override this. 
	 *
	 * @return DataObject
	 */
	protected function getListingSource() {
		$sourceType = $this->effectiveSourceType();
		if ($sourceType && $this->ListingSourceID) {
			return DataObject::get_by_id($sourceType, $this->ListingSourceID);
		}
	}
	
	/**
	 * Sometimes the type of a listing source will be different from that of the item being listed (eg
	 * a news article might be beneath a news holder instead of another news article) so we need to 
	 * figure out what that is based on the settings for this page. 
	 *
	 * @return string
	 */
	protected function effectiveSourceType() {
		$listType = $this->ListType ? $this->ListType : 'Page';
		$listType = isset($this->config()->listing_type_source_map[$listType]) ? $this->config()->listing_type_source_map[$listType] : ClassInfo::baseDataClass($listType);
		return $listType;
	}

	/**
	 * Retrieves all the listing items within this source
	 * 
	 * @return DataObjectSource
	 */
	public function ListingItems() {
		// need to get the items being listed
		$source = $this->getListingSource();

		$listType = $this->ListType ? $this->ListType : 'Page';
		
		$filter = array();

		$objFields = $this->getSelectableFields($listType);
		
		if ($source) {
			$ids = $this->getIdsFrom($source, 1);
			$ids[] = $source->ID;

			if (isset($objFields['ParentID']) && count($ids)) {
				$filter['ParentID:ExactMatch'] = $ids;
			}

		}
		

		if ($this->StrictType) {
			$filter['ClassName'] = $listType;
		}


		$sortDir = $this->SortDir == 'Ascending' ? 'ASC' : 'DESC';
		$sort = $this->SortBy && isset($objFields[$this->SortBy]) ? $this->SortBy : 'Title';
		// $sort = $this->CustomSort ? $this->CustomSort : $sort;
		$sort .= ' ' . $sortDir;

		$limit = '';

		$pageUrlVar = 'page' . $this->ID;

		$items = DataList::create($listType)->filter($filter)->sort($sort);
		
		if ($this->PerPage) {
			$page = isset($_REQUEST[$pageUrlVar]) ? (int) $_REQUEST[$pageUrlVar] : 0;
			$items  = $items->limit($this->PerPage, $page);
		}


		if ($this->ComponentFilterName) {
			$controller = (Controller::has_curr()) ? Controller::curr() : null;
			$tags = array();
			if ($controller && $controller instanceof ListingPage_Controller)
			{
				$tagName = $controller->getRequest()->latestParam('Action');

				if ($tagName) {
					$tagClass = isset(singleton($this->ListType)->config()->many_many[$this->ComponentFilterName]) ? singleton($this->ListType)->config()->many_many[$this->ComponentFilterName] : null;
					$tags = DataList::create($tagClass)->filter(array($this->ComponentFilterColumn => $tagName));
					if ($this->ComponentFilterWhere && ($componentWhereFilters = $this->ComponentFilterWhere->getValue()))
					{
						$tags = $tags->filter($componentWhereFilters);
					}
					$tags = $tags->toArray();
					if (!$tags)
					{
						// Workaround cms/#1045
		                // - Stop infinite redirect
		                // @see: https://github.com/silverstripe/silverstripe-cms/issues/1045
						unset($controller->extension_instances['OldPageRedirector']);

						return $controller->httpError(404);
					}
				}
			}
			
			if ($tags) {
				if (count($tags) > 1) {
					return $controller->httpError(500, 'ComponentFilterColumn provided is not unique. '.count($tags).' matches found in query.');
				}
				$tag = reset($tags);

				list($parentClass, $componentClass, $pageIDColumnName, $tagIDColumnName, $tagManyManyTable) = singleton($this->ListType)->manyManyComponent($this->ComponentFilterName);
				$items = $items->innerJoin($tagManyManyTable, "\"{$pageIDColumnName}\" = \"$parentClass\".\"ID\" AND \"{$tagIDColumnName}\" = ".(int)$tag->ID);
			} else {
				$tags = new ArrayList();
			}
		}
		
		$this->extend('updateListingItems', $items);

		$newList = ArrayList::create();
		if ($items) {
			// note: Access control is no longer being enforced by the page type! You'll need to 
			// manually include canView checks in your listing templates...
//			foreach ($items as $result) {
//				if ($result->canView()) {
//					$newList->push($result);
//				}
//			}
//
			$newList = PaginatedList::create($items);
			$newList->setPaginationGetVar($pageUrlVar);
			if ($items instanceof DataList) {
				$newList->setPaginationFromQuery($items->dataQuery()->query());
			}
		}

		return $newList;
	}

	/**
	 * Recursively find all the child items that need to be listed
	 *
	 * @param DataObject $parent
	 * @param int $depth
	 */
	protected function getIdsFrom($parent, $depth) {
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

	public function Content() {
		$items = $this->ListingItems();
		$item = $this->customise(array('Items' => $items));
		$view = SSViewer::fromString($this->ListingTemplate()->ItemTemplate);
		$content = str_replace('<p>$Listing</p>', '$Listing', $this->Content);
		return str_replace('$Listing', $view->process($item), $content);
	}
}

class ListingPage_Controller extends Page_Controller {
	private static $url_handlers = array(
		'$Action' => 'index'
	);

	public function index() {
		$action = $this->request->latestParam('Action');
		if (($action && !$this->ComponentFilterName) || (!$action && $this->ComponentFilterName)) {
			// - If component filter name is set, but the action is missing, throw error
			// - If the component filter isnt set, but there is an action, throw error.
			return $this->httpError(404);
		}
		if (($this->data()->ContentType || $this->data()->CustomContentType)) {
			// k, not doing it in the theme...
			$contentType = $this->data()->ContentType ? $this->data()->ContentType : $this->data()->CustomContentType;
			$this->response->addHeader('Content-type', $contentType);
			
			return $this->data()->Content();
		}
		return array();
	}
}
