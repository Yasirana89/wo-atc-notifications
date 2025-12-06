# WP Add to Cart Notifications

A WordPress plugin that displays beautiful add-to-cart notifications in a popup when products are added to the cart. Works with any WooCommerce theme.

## Features

- **Customizable Layout**: Choose between product image on the left or as a background
- **Flexible Positioning**: Display notifications at the top or bottom of the page
- **Auto-close Timer**: Configure automatic closing after a specified number of seconds
- **Display Conditions**: Control where notifications appear (All pages, Shop Archive, Categories, Tags, Attributes, Single Products)
- **Theme Compatible**: Works with any WooCommerce theme
- **Filter Support**: Customize the close timer using PHP filters

## Installation

1. Upload the `wp-atc-notifications` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > ATC Notifications to configure the plugin

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## Configuration

Navigate to **WooCommerce > ATC Notifications** in your WordPress admin to configure:

### Layout Options
- **Product image within the content on the left side**: Shows product image as a thumbnail on the left
- **Product image as a background**: Uses product image as the notification background

### Display Position
- **Top**: Notification appears at the top-right of the page
- **Bottom**: Notification appears at the bottom-right of the page

### Close After (Seconds)
Set the number of seconds before the notification automatically closes. Set to 0 to disable auto-close.

### Display Conditions
Select where the notification should appear:
- All pages
- Shop Archive
- Shop Archive Categories (product categories)
- Shop Archive Tags (product tags)
- Shop Archive Product Attributes
- Single Products

## Developer Hooks

### Filter: `wp_atc_notifications_close_after`

Modify the close timer value programmatically:

```php
add_filter('wp_atc_notifications_close_after', function($seconds) {
    // Change close timer to 5 seconds
    return 5;
});
```

## Support

For issues, questions, or feature requests, please contact the plugin author.

## Changelog

### 1.0.0
- Initial release
- Layout options (image left / image background)
- Position options (top / bottom)
- Auto-close timer
- Display conditions
- Filter support for close timer

