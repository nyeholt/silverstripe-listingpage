<?php

/**
 * Allow the retrieval of child items using a custom filter and sort by clause
 * 
 * This allows templates access to child items in a more structured manner. Note that
 * it does allow template authors to pass through raw SQL - SS templates are still a
 * little too naive in that regard :/
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class SortableChildrenExtension extends DataObjectDecorator {
	
	protected $_cache_children = array();
	
	/**
	 * Get the children for this DataObject.
	 * @return DataObjectSet
	 */
	public function FilteredChildren($filter = '', $sort = '"Sort" ASC') {
		if ($filter == "null") {
			$filter = '';
		}
		$key = $filter.$sort;
		
		if(!isset($this->_cache_children[$key])) { 
			$result = $this->owner->filteredStageChildren(false, $filter, $sort); 
		 	if(isset($result)) { 
		 		$this->_cache_children[$key] = new DataObjectSet(); 
		 		foreach($result as $child) { 
		 			if($child->canView()) { 
		 				$this->_cache_children[$key]->push($child); 
		 			} 
		 		} 
		 	} 
		}
		return $this->_cache_children[$key];
	}
	
	/**
	 * Return children from the stage site
	 * 
	 * @param showAll Inlcude all of the elements, even those not shown in the menus.
	 *   (only applicable when extension is applied to {@link SiteTree}).
	 * @return DataObjectSet
	 */
	public function filteredStageChildren($showAll = false, $filter = '', $sort = '"Sort" ASC') {
		$extraFilter = $filter;
		if($this->owner->db('ShowInMenus')) {
			$extraFilter .= ($showAll) ? '' : " AND \"ShowInMenus\"=1";
		} 

		$baseClass = ClassInfo::baseDataClass($this->owner->class);
		
		$sort = explode(' ', $sort);
		if (strpos($sort[0], '"') === false) {
			$sort[0] = '"' . Convert::raw2sql($sort[0]).'"';
		}
		
		$sort[1] = strtolower($sort[1]) == 'asc' ? 'ASC' : 'DESC';
		$sort = $sort[0] . ' ' . $sort[1];
		
		if (strlen($extraFilter)) {
			$extraFilter = ' AND ' . $extraFilter;
		}

		$staged = DataObject::get($baseClass, "\"{$baseClass}\".\"ParentID\" = " 
			. (int)$this->owner->ID . " AND \"{$baseClass}\".\"ID\" != " . (int)$this->owner->ID
			. $extraFilter, $sort);
			
		if(!$staged) $staged = new DataObjectSet();
		$this->owner->extend("augmentStageChildren", $staged, $showAll);
		return $staged;
	}
	
}
