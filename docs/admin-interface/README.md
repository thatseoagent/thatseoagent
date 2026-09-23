# Admin Interface

ThatSeoAgent adds a user-friendly meta box to the post and page editors for customizing SEO settings.

## What It Does

The `ThatSeoAgent_Meta_Box` class provides:

- **SEO Meta Box**: Added to post and page editors
- **Custom Title Field**: Override the default page title
- **Custom Description Field**: Set custom meta description
- **Live Preview**: Shows how the page will appear in search results
- **Character Counters**: Visual indicators for optimal title/description length
- **JavaScript Enhancement**: Real-time preview updates

## Code Structure

Located in `includes/admin/class-thatseoagent-meta-box.php`:

- `add()` - Registers the SEO meta box
- `render()` - Outputs the HTML form with fields and preview
- `save()` - Processes and saves the form data, through `ThatSeoAgent_Post_Seo`

The post types that get the fields are answered by `ThatSeoAgent_Post_Seo::post_types()`.

## Configuration Options

### Post Types

By default, the meta box appears on 'post' and 'page' post types. Customize this with:

```php
add_filter('thatseoagent_meta_box_post_types', function($post_types) {
    // Add to custom post types
    $post_types[] = 'product';
    $post_types[] = 'portfolio';
    
    // Remove from pages
    $post_types = array_diff($post_types, array('page'));
    
    return $post_types;
});
```

### Custom Validation

Add custom validation to the save process:

```php
add_action('save_post', function($post_id) {
    // Only for our post types
    if (!in_array(get_post_type($post_id), array('post', 'page'))) {
        return;
    }
    
    $title = get_post_meta($post_id, '_thatseoagent_title', true);
    if (strlen($title) > 70) {
        // Handle validation error
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>SEO title is too long!</p></div>';
        });
    }
}, 11); // After ThatSeoAgent saves (priority 10)
```

## Moving between views

The ThatSeoAgent screen behaves like a single-page app: links between its views are fetched with the `X-ThatSeoAgent-View` header, which makes `ThatSeoAgent_App::maybe_send_view()` answer with the screen alone as JSON (`html`, `title`, `bulletin`) instead of the whole admin page. In `assets/admin/app.js`, the `tsaScreen` component on `#thatseoagent-app` sends clicks on links to other views, and back and forward (`@popstate.window`), to `Alpine.store( 'router' ).go()`, which swaps the navigation and the main column and keeps history, the title and the sidebar in step. Alpine's own mutation observer stops the components that leave and starts the ones that arrive, so none is initialised twice; `$store.router.loading` sets `aria-busy` on the screen while the next view loads. A view with unsaved work sets `Alpine.store( 'router' ).leave` to be asked before it is replaced. Without JavaScript, or on any error, the link loads as a normal page.

## Usage

### Meta Box Fields

1. **SEO Title** (optional)
   - Defaults to post/page title
   - The counter turns red past 70 characters, where search results may cut it; they trim titles to the width of the screen, and Google publishes no limit
   - Appears in browser tab and search results

2. **Meta Description** (optional)
   - Defaults to excerpt or auto-generated from content
   - The counter turns red past 165 characters, where it may be cut; Google sets no limit, and the field does not cut the text
   - Appears in search result snippets

3. **Keep out of search results** (optional)
   - Adds `noindex, follow` to the page and prints no canonical
   - Leaves the post out of the sitemaps and llms.txt
   - The page stays public for anyone with the link

### Live Preview

The meta box includes a live preview that shows:
- How the title will appear in search results
- How the description will appear in search results
- The page URL
- Character counts for both fields

### Saving Data

Data is saved as post meta:
- `_thatseoagent_title` - Custom SEO title
- `_thatseoagent_description` - Custom meta description
- `_thatseoagent_noindex` - `1` when the post is kept out of search

Empty fields are automatically cleaned up (meta deleted).

## Technical Details

### Security

- Uses `wp_nonce_field()` for CSRF protection
- Sanitizes input with `sanitize_text_field()` and `sanitize_textarea_field()`
- Checks user capabilities with `current_user_can()`
- Prevents autosave interference

### JavaScript Features

- Real-time character counting
- Live preview updates
- Input validation (maxlength attributes)
- jQuery-based interactions

### Styling

Custom CSS is included inline for:
- Field layout and spacing
- Preview styling to match Google SERP appearance
- Counter styling with warnings for excessive length

## Integration

The admin interface integrates with the meta output system:
- Custom titles override `document_title_parts`
- Custom descriptions are used by `ThatSeoAgent_Meta::get_description()`

## Best Practices

- **Titles**: Distinct for each page and descriptive; past about 70 characters they may be cut
- **Descriptions**: Unique and relevant rather than long; past about 165 characters they may be cut
- **Preview**: Always check the live preview before publishing
- **Testing**: Use tools like Google Search Console to test appearance