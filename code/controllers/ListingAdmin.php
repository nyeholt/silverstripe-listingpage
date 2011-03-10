<?php

/**
 * Description of ListingAdmin
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ListingAdmin extends ModelAdmin {
	public static $url_segment = 'listing';
	public static $menu_title = 'Listings';
	public static $managed_models = array('ListingTemplate');
}
