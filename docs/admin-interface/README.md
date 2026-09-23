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

## Usage

### Meta Box Fields

1. **SEO Title** (optional)
   - Defaults to post/page title
   - Recommended: 50-60 characters
   - Appears in browser tab and search results

2. **Meta Description** (optional)
   - Defaults to excerpt or auto-generated from content
   - Recommended: 150-160 characters
   - Appears in search result snippets

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

- **Titles**: Keep under 60 characters to avoid truncation
- **Descriptions**: Aim for 150-160 characters for optimal snippets
- **Preview**: Always check the live preview before publishing
- **Testing**: Use tools like Google Search Console to test appearance