<?php

namespace Symbiote\ListingPage\Tests;

use Page;
use Symbiote\ListingPage\ListingTemplate;
use Symbiote\ListingPage\ListingPage;
use Symbiote\Multisites\Multisites;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use SilverStripe\Dev\FunctionalTest;

class FilterByRelationTest extends FunctionalTest
{
    protected $usesDatabase = true;

    /**
     * Test the behaviour
     * - Select 'Page' or 'File' for ListType
     * - Select 'Viewer Groups (SilverStripe\Security\Group)' for ComponentFilterName
     * - Select 'Code' for ComponentFilterColumn
     *
     * Make sure there's a page or file that should be listed has the 'Administrators' group as a viewers group.
     * Assuming other fields are valid, visit the page, with /administrators as the last part of the URL.
     * 
     * Source:
     * https://github.com/nyeholt/silverstripe-listingpage/issues/19
     *
     */
    public function testFilterByRelation()
    {
        $this->logInWithPermission('ADMIN');

        $parentId = 0;
        if (class_exists(Multisites::class)) {
            $parentId = Multisites::inst()->getCurrentSiteId();
        }

        // Create template
        $templateRecord = ListingTemplate::create();
        $templateRecord->Title = 'Filter By Relation Template';
        $templateRecord->ItemTemplate = <<<SSTEMPLATE
<div class="listing-template-test-zone">
    <% loop \$Items %>
        <p class="listing-template-test-zone__item">
            \$Title
        </p>
    <% end_loop %>
</div>
SSTEMPLATE;
        $templateRecord->write();
        
        $adminGroups = Permission::get_groups_by_permission('ADMIN')->toArray();
        $this->assertCount(1, $adminGroups, 'Expected 1 admin group');

        // Create Page
        $record           = ListingPage::create();
        $record->Title    = 'Filter By Relation Test Page';
        $record->Content = <<<HTML
This is listing page content

\$Listing
HTML;
        $record->URLSegment = 'listingpage-relation-test';
        $record->ParentID = $parentId;
        $record->ListingSourceID = $parentId; // List everything under 'Site' / root
        $record->SortBy   = 'Title';
        $record->SortDir  = 'Ascending';
        $record->ListType = Page::class;
        $record->ListingTemplateID = $templateRecord->ID;
        $record->ComponentListingTemplateID = $templateRecord->ID;
        $record->ComponentFilterName = 'ViewerGroups'; // Magic method on SiteTree, from "InheritedPermissionsExtension" many_many.
        $record->ComponentFilterColumn = 'Code';
        $record->ViewerGroups()->addMany($adminGroups);
        $record->write();
        $record->publishRecursive();

        $response = $this->get('listingpage-relation-test');
        $this->assertEquals('200',$response->getStatusCode(), 'Expected "listingpage-relation-test" to get 200 OK');
        $response = $this->get('listingpage-relation-test/'.$adminGroups[0]->Code, 'Expected "listingpage-relation-test/'.$adminGroups[0]->Code.'" to get 200 OK');
        $this->assertEquals('200',$response->getStatusCode());
        $expectedValue = <<<HTML
<div class="listing-page-template">
    <h1>Filter By Relation Test Page</h1>
    <div class="listing-page-template__content">
        <div class="listing-template-test-zone">
            <p class="listing-template-test-zone__item">
                Filter By Relation Test Page
            </p>
        </div>
    </div>
</div>
HTML;
        $this->assertEqualIgnoringWhitespace($expectedValue, $response->getBody());
    }

    /**
     * Taken from "framework\tests\view\SSViewerTest.php"
     */
    protected function assertEqualIgnoringWhitespace($a, $b, $message = '')
    {
        $this->assertEquals(preg_replace('/\s+/', '', $a), preg_replace('/\s+/', '', $b), $message);
    }
}
