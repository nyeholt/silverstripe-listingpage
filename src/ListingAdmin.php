<?php

namespace Symbiote\ListingPage;

use SilverStripe\Admin\ModelAdmin;
use Symbiote\ListingPage\ListingTemplate;

/**
 * Description of ListingAdmin
 *
 * @author  marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ListingAdmin extends ModelAdmin
{
    private static $menu_title = 'Listings';

    private static $url_segment = 'listing';

    private static $managed_models = array(
        ListingTemplate::class
    );
    
    private static $menu_icon_class = 'font-icon-p-list';

}
