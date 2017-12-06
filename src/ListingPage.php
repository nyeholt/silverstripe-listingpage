<?php

namespace Symbiote\ListingPage;

use Page;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\HTMLEditor\HtmlEditorField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FormField;
use Symbiote\MultiValueField\Fields\KeyValueField;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\SSViewer;

/**
 * A page that can be configured to create listings of other content
 *
 * @author  Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ListingPage extends Page
{
    private static $table_name = 'ListingPage';

    private static $db = array(
        'PerPage'                   => 'Int',
        'Style'                     => "Enum('Standard,A to Z')",
        'SortBy'                    => "Varchar(64)",
        'CustomSort'                => 'Varchar(64)',
        'SortDir'                   => "Enum('Ascending,Descending')",
        'ListType'                  => 'DBClassName(\'' . DataObject::class . '\', [\'index\' => false])',
        'ListingSourceID'           => 'Int',
        'Depth'                     => 'Int',
        'ClearSource'               => 'Boolean',
        'StrictType'                => 'Boolean',

        'ContentType'               => 'Varchar',
        'CustomContentType'         => 'Varchar',

        'ComponentFilterName'       => 'Varchar(64)',
        'ComponentFilterColumn'     => 'Varchar(64)',
        'ComponentFilterWhere'      => MultiValueField::class
    );

    private static $has_one = array(
        'ListingTemplate'           => ListingTemplate::class,
        'ComponentListingTemplate'  => ListingTemplate::class,
    );

    private static $defaults = array(
        'ListType'                  => Page::class,
        'PerPage'                   => 10
    );

    /**
     * A mapping between ListType selected and the type of items that should be shown in the "Source"
     * selection tree. If not specified in this mapping, it is assumed to be 'Page'.
     *
     * @var array
     */
    private static $listing_type_source_map = array(
        'Folder'    => Folder::class
    );

    private static $icon = 'symbiote/silverstripe-listingpage: client/images/listingpage.png';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        /* @var FieldSet $fields */

        $fields->replaceField('Content', HtmlEditorField::create('Content', _t('ListingPage.CONTENT', 'Content (enter $Listing to display the listing)')));

        $templates = DataObject::get(ListingTemplate::class);
        if ($templates) {
            $templates = $templates->map();
        } else {
            $templates = array();
        }

        $fields->addFieldToTab('Root.ListingSettings', DropdownField::create('ListingTemplateID', _t('ListingPage.CONTENT_TEMPLATE', 'Listing Template'), $templates));
        $fields->addFieldToTab('Root.ListingSettings', NumericField::create('PerPage', _t('ListingPage.PER_PAGE', 'Items Per Page')));
        $fields->addFieldToTab('Root.ListingSettings', DropdownField::create('SortDir', _t('ListingPage.SORT_DIR', 'Sort Direction'), $this->dbObject('SortDir')->enumValues()));

        $listType = $this->ListType ? $this->ListType : Page::class;
        $objFields = $this->getSelectableFields($listType);

        $fields->addFieldToTab('Root.ListingSettings', DropdownField::create('SortBy', _t('ListingPage.SORT_BY', 'Sort By'), $objFields));
        // $fields->addFieldToTab('Root.Content.Main', TextField::create('CustomSort', _t('ListingPage.CUSTOM_SORT', 'Custom sort field')));

        $types = ClassInfo::subclassesFor(DataObject::class);
        array_shift($types);
        $source = array_combine($types, $types);
        asort($source);

        $optionsetField = DropdownField::create('ListType', _t('ListingPage.PAGE_TYPE', 'List items of type'), $source, 'Any');
        $fields->addFieldToTab('Root.ListingSettings', $optionsetField);
        $fields->addFieldToTab('Root.ListingSettings', CheckboxField::create('StrictType', _t('ListingPage.STRICT_TYPE', 'List JUST this type, not descendents')));

        $sourceType = $this->effectiveSourceType();
        $parentType = $this->parentType($sourceType);
        if ($sourceType && $parentType) {
            $fields->addFieldToTab('Root.ListingSettings', DropdownField::create('Depth', _t('ListingPage.DEPTH', 'Depth'), array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5)));
            $fields->addFieldToTab('Root.ListingSettings', TreeDropdownField::create('ListingSourceID', _t('ListingPage.LISTING_SOURCE', 'Source of content for listing'), $parentType));
            $fields->addFieldToTab('Root.ListingSettings', CheckboxField::create('ClearSource', _t('ListingPage.CLEAR_SOURCE', 'Clear listing source value')));
        }

        $contentTypes = array(
            ''                      => 'In Theme',
            'text/html; charset=utf-8'              => 'HTML Fragment',
            'text/xml; charset=utf-8'               => 'XML',
            'application/rss+xml; charset=utf-8'    => 'RSS (xml)',
            'application/rdf+xml; charset=utf-8'    => 'RDF (xml)',
            'application/atom+xml; charset=utf-8'   => 'ATOM (xml)',
        );
        $fields->addFieldToTab('Root.ListingSettings', DropdownField::create('ContentType', _t('ListingPage.CONTENT_TYPE', 'Content Type'), $contentTypes));
        $fields->addFieldToTab('Root.ListingSettings', TextField::create('CustomContentType', _t('ListingPage.CUSTOM_CONTENT_TYPE', 'Custom Content Type')));

        if ($this->ListType) {
            $componentsManyMany = singleton($this->ListType)->config()->many_many;
            if (!is_array($componentsManyMany)) {
                $componentsManyMany = array();
            }
            $componentNames = array();
            foreach ($componentsManyMany as $componentName => $className) {
                $componentNames[$componentName] = FormField::name_to_label($componentName) . ' ('.$className.')';
            }
            $fields->addFieldToTab(
                'Root.ListingSettings',
                DropdownField::create('ComponentFilterName', _t('ListingPage.RELATION_COMPONENT_NAME', 'Filter by Relation'), $componentNames)
                ->setEmptyString('(Select)')
                ->setDescription('Will cause this page to list items based on the last URL part. (ie. '.$this->AbsoluteLink().'{$componentFieldName})')
            );
            $fields->addFieldToTab('Root.ListingSettings', $componentColumnField = DropdownField::create('ComponentFilterColumn', 'Filter by Relation Field')->setEmptyString('(Must select a relation and save)'));
            $fields->addFieldToTab('Root.ListingSettings', $componentListingField = DropdownField::create('ComponentListingTemplateID', _t('ListingPage.COMPONENT_CONTENT_TEMPLATE', 'Relation Listing Template'))->setEmptyString('(Must select a relation and save)'));
            if ($this->ComponentFilterName) {
                $componentClass = isset($componentsManyMany[$this->ComponentFilterName]) ? $componentsManyMany[$this->ComponentFilterName] : '';
                if ($componentClass) {
                    $componentFields = array();
                    foreach ($this->getSelectableFields($componentClass) as $columnName => $type) {
                        $componentFields[$columnName] = $columnName;
                    }
                    $componentColumnField->setSource($componentFields);
                    $componentColumnField->setEmptyString('(Select)');

                    $componentListingField->setSource($templates);
                    $componentListingField->setHasEmptyDefault(false);

                    if (class_exists('KeyValueField')) {
                        $fields->addFieldToTab(
                            'Root.ListingSettings',
                            KeyValueField::create('ComponentFilterWhere', 'Constrain Relation By', $componentFields)
                                ->setRightTitle("Filter '{$this->ComponentFilterName}' with these properties.")
                        );
                    }
                }
            }
        }

        return $fields;
    }

    protected function parentType($type)
    {
        $has_one = Config::inst()->get($type, 'has_one');
        return isset($has_one['Parent']) ? $has_one['Parent'] : null;
    }

    protected function getSelectableFields($listType)
    {
        $objFields = static::getSchema()->fieldSpecs($listType);
        $objFields = array_keys($objFields);
        $objFields = array_combine($objFields, $objFields);

        ksort($objFields);
        return $objFields;
    }

    /**
     * When saving, check to see whether we should delete the
     * listing source ID
     */
    public function onBeforeWrite()
    {
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
    protected function getListingSource()
    {
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
    protected function effectiveSourceType()
    {
        $listType = $this->ListType ? $this->ListType : Page::class;
        $listType = isset($this->config()->listing_type_source_map[$listType]) ? $this->config()->listing_type_source_map[$listType] : ClassInfo::baseDataClass($listType);
        return $listType;
    }

    /**
     * Retrieves all the component/relation listing items
     *
     * @return SS_List
     */
    public function ComponentListingItems()
    {
        $manyMany = singleton($this->ListType)->config()->many_many;
        $tagClass = isset($manyMany[$this->ComponentFilterName]) ? $manyMany[$this->ComponentFilterName] : '';
        if (!$tagClass) {
            return new ArrayList();
        }
        $result = DataList::create($tagClass);
        if ($this->ComponentFilterWhere
            && ($componentWhereFilters = $this->ComponentFilterWhere->getValue())
        ) {
            $result = $result->filter($componentWhereFilters);
        }
        return $result;
    }

    /**
     * Retrieves all the listing items within this source
     *
     * @return SS_List
     */
    public function ListingItems()
    {
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
            if ($controller && $controller instanceof ListingPageController) {
                $tagName = $controller->getRequest()->latestParam('Action');
                if ($tagName) {
                    $tags = $this->ComponentListingItems();
                    $tags = $tags->filter(array($this->ComponentFilterColumn => $tagName));
                    $tags = $tags->toArray();
                    if (!$tags) {
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
                $tagComponent = DataObject::getSchema()->manyManyComponent($this->ListType, $this->ComponentFilterName);
                $parentClass = ClassInfo::shortName($tagComponent['parentClass']);
                $items = $items->innerJoin(
                    $tagComponent['join'],
                    "\"{$tagComponent['parentField']}\" = \"$parentClass\".\"ID\" AND \"{$tagComponent['childField']}\" = ".(int)$tag->ID
                );
            } else {
                $tags = new ArrayList();
            }
        }

        $this->extend('updateListingItems', $items);

        $newList = ArrayList::create();
        if ($items) {
            $newList = PaginatedList::create($items);
            // ensure the 0 limit is applied if configured as such
            $newList->setPageLength($this->PerPage);
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
     * @param int        $depth
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
        if (!$this->ID) {
            return '';
        }
        $action = (Controller::has_curr()) ? Controller::curr()->getRequest()->latestParam('Action') : null;

        if ($this->ComponentFilterName && !$action) {
            // For a list of relations like tags/categories/etc
            $items = $this->ComponentListingItems();
            $item = $this->customise(array('Items' => $items));
            $view = SSViewer::fromString($this->ComponentListingTemplate()->ItemTemplate);
        } else {
            $items = $this->ListingItems();
            $item = $this->customise(array('Items' => $items));
            $view = SSViewer::fromString($this->ListingTemplate()->ItemTemplate);
        }
        $content = str_replace('<p>$Listing</p>', '$Listing', $this->Content);
        return str_replace('$Listing', $view->process($item), $content);
    }
}
