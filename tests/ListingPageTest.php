<?php

namespace Symbiote\ListingPage\Tests;

use Page;
use DNADesign\Elemental\Tests\Src\TestPage;
use Symbiote\Multisites\Multisites;
use Symbiote\ListingPage\ListingPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DB;

class ListingPageTest extends SapphireTest
{
    /**
     * The elemental extension may be applied, meaning we need to ensure this is loaded.
     *
     * @return array
     */
    public static function getExtraDataObjects()
    {
        $objects = static::$extra_dataobjects;
        if (class_exists(TestPage::class)) {
            $objects[] = TestPage::class;
        }
        return $objects;
    }

    public function testPublish()
    {
        $this->logInWithPermission('ADMIN');

        $parentId = 0;
        if (class_exists(Multisites::class)) {
            $parentId = Multisites::inst()->getCurrentSiteId();
        }

        $record           = ListingPage::create();
        $record->Title    = "Listing Page Test";
        $record->ParentID = $parentId;
        $record->write();

        $this->assertTrue($record->publishRecursive());
        $this->assertEquals(
            'Listing Page Test',
            DB::query("SELECT \"Title\" FROM \"SiteTree_Live\" WHERE \"ID\" = '$record->ID'")->value()
        );
    }

    public function testCustomisedSort()
    {
        $this->logInWithPermission('ADMIN');

        $parentId = 0;
        if (class_exists(Multisites::class)) {
            $parentId = Multisites::inst()->getCurrentSiteId();
        }

        $record           = ListingPage::create();
        $record->Title    = "Listing Page sort test";
        $record->ParentID = $parentId;
        $record->SortBy   = 'Title';
        $record->SortDir  = 'Ascending';
        $record->write();

        $record->ListingSourceID = $record->ID;
        $record->CustomSort      = 'sort';
        $record->write();

        $items = $record->ListingItems();

        $this->assertEquals('Title', $record->CurrentSort);
        $this->assertEquals('ASC', $record->CurrentDir);

        $controller = new Controller;

        $params = [
            'sort' => 'ID',
            'sort_dir' => 'DESC',
        ];

        $req   = new HTTPRequest('GET', 'dummy/url', $params);
        $req->setSession(Controller::curr()->getRequest()->getSession());
        $controller->setRequest($req);
        $controller->pushCurrent();
        $items = $record->ListingItems();

        $this->assertEquals('ID', $record->CurrentSort);
        $this->assertEquals('DESC', $record->CurrentDir);

        $controller->popCurrent();
    }
}
