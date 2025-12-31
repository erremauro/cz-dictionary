# CZ Dictionary

CZ Dictionary is a WordPress plugin that provides a live-search interface for tag-based dictionary entries. It searches across tag fields and ACF term meta, then renders results with a short definition and a link to the full lemma page.

## Features
- Live filtering as you type (client-side).
- Infinite scroll / lazy loading for results.
- Searches tag name, slug, description, and ACF fields: `show_as`, `synonyms_csv`, `readings`.
- Displays tag name, readings, short definition, and a button linking to `/lemma/<slug>?show_all=1`.
- Styles aligned to the site theme (dark/light via CSS variables).

## Requirements
- WordPress with the `post_tag` taxonomy.
- ACF (Advanced Custom Fields) if you want to use the custom fields.

## Installation
1. Copy the plugin folder to `wp-content/plugins/cz-dictionary`.
2. Activate **CZ Dictionary** from the WordPress admin.

## Usage
Add the shortcode where you want the search UI:
```
[cz_dictionary]
```

## Notes
- The UI loads term data into the page and filters in the browser.
- The first render shows a limited batch, then loads more on scroll.
- If ACF is not installed, the plugin still works but uses only core tag fields.

## Development
Main plugin file:
- `cz-dictionary.php`

## License
No license specified.
