# Advanced Usage

## Template with Pagination Example

For pagination, the following might be useful

```html
<% loop $Items %>
    <p>$Title - $Link</p>
<% end_loop %>
```

```html
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
```
