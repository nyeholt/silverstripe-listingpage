# Listing Page Module

## Maintainer Contact

Marcus Nyeholt
<marcus (at) silverstripe (dot) com (dot) au>

## Requirements

## Documentation

[GitHub Wiki](http://wiki.github.com/nyeholt/silverstripe-listingpage)


## Quick Usage Overview

* Extract to silverstripe/listingpage and run dev/build
* Navigate to the Listing CMS section and create a listing template
* Create a new listing page, setting appropriate values
* Add the $Listing keyword to the page's Content block

## Template Options

For pagination, the following might be useful

	<% loop $Items %>
		<p>$Title - $Link</p>
	<% end_loop %>

	<% if Items.MoreThanOnePage %>
	    <div id="PageNumbers">
	      <% if Items.NotLastPage %>
	        <a class="next" href="$Items.NextLink" title="View the next page">Next</a>
	      <% end_if %>
	      <% if Items.NotFirstPage %>
	        <a class="prev" href="$Items.PrevLink" title="View the previous page">Prev</a>
	      <% end_if %>
	      <span>
	        <% loop $Items.PaginationSummary %>
	          <% if CurrentBool %>
	            $PageNum
	          <% else %>
	            <a href="$Link" title="View page number $PageNum">$PageNum</a>
	          <% end_if %>
	        <% end_loop %>
	      </span>
      
	    </div>
	 <% end_if %>


## API

[GitHub Wiki](http://wiki.github.com/nyeholt/silverstripe-listingpage)


## Troubleshooting

Make sure you have the $Listing variable in your page content for the listing 
to be inserted correctly. 
