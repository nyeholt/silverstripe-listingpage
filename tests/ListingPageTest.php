<?php
namespace Symbiote\ListingPage\Tests;

use Page;
use Symbiote\ListingPage\ListingPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class ListingPageTest extends SapphireTest
{
    public function testPublish()
    {
        $this->logInWithPermission('ADMIN');

        $record = ListingPage::create();
        $record->Title = "Listing Page Test";
        $record->write();
        $this->assertTrue($record->publish());
        $this->assertEquals('Listing Page Test', DB::query("SELECT \"Title\" FROM \"SiteTree_Live\" WHERE \"ID\" = '$obj->ID'")->value());
    }
}
