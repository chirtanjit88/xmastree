# XmasTree Custom Blocks

Centralized custom WordPress plugin foundation and automated discovery engine for Gutenberg and ACF blocks for the **Somerville & Peninsula Christmas Tree Farm** website.

---

## 1. Overview

**XmasTree Custom Blocks** establishes a scalable, decoupled, object-oriented plugin architecture for registering and managing custom Gutenberg blocks powered by Advanced Custom Fields (ACF Pro / Secure Custom Fields).

### Key Architectural Tenets:
- **Zero Placeholder / Demo Blocks:** Ships purely as an architectural foundation without sample or dummy blocks.
- **Automated Block Discovery:** Automatically scans and registers custom blocks placed in the future `blocks/` directory.
- **Modern `block.json` Standard:** Uses WordPress core and ACF 6+ `block.json` metadata specification.
- **Theme-Agnostic:** Completely decoupled from the active WordPress theme; custom blocks will continue working regardless of theme switches.
- **Programmatic Field Support:** Supports optional `fields.php` definitions per block for code-driven ACF local field groups.
- **Centralized Asset Management:** Scripts and stylesheets load conditionally and avoid premature or duplicate loading.
- **Admin Diagnostics:** Provides real-time status of ACF Pro dependencies and block discovery in **Settings → XmasTree Blocks**.

---

## 2. Prerequisites & Compatibility

- **WordPress:** Version 6.0 or higher.
- **PHP:** Version 8.1 or higher (strictly typed).
- **ACF Pro or Secure Custom Fields (SCF):** Version 6.0+ with Blocks API support.
  - *Note:* If ACF is inactive, the plugin remains active without throwing fatal errors and displays an administrative warning notice guiding the admin to activate ACF Pro.

---

## 3. Directory Layout

```text
xmastree-blocks/
│
├── xmastree-blocks.php      # Main bootstrap file, constants, and initialization
├── uninstall.php            # Clean uninstallation routine
├── README.md                # Plugin documentation and future block guide
│
├── includes/
│   ├── class-plugin.php     # Main orchestrator singleton
│   ├── class-block-loader.php # Automated block discovery and registration engine
│   ├── class-assets.php     # Centralized asset manager
│   ├── class-admin.php      # Admin diagnostics page and dependency notice
│   └── helpers.php          # Reusable helper functions and path resolvers
│
├── assets/
│   ├── css/                 # Global plugin stylesheets
│   ├── js/                  # Global plugin scripts
│   ├── images/              # Shared block icons or placeholder graphics
│   └── icons/               # SVG block icons
│
└── languages/               # Internationalization translation files (.pot/.mo/.po)
```

---

## 4. How the Automated Block Loader Works

When actual Gutenberg blocks are developed in subsequent phases, simply create a `blocks/` folder in the plugin root and add each block inside its own subdirectory.

```text
wp-content/plugins/xmastree-blocks/blocks/
└── {block-slug}/
    ├── block.json       (Required: standard WordPress & ACF block metadata)
    ├── render.php       (Optional: server-side PHP template for frontend rendering)
    ├── fields.php       (Optional: programmatic ACF local field group array)
    ├── style.css        (Optional: block-specific frontend styling)
    ├── editor.css       (Optional: block-specific block editor styling)
    └── script.js        (Optional: block-specific frontend JavaScript)
```

On every `init` hook (priority 20):
1. `Block_Loader::register_blocks()` scans `blocks/` if the directory exists.
2. It detects each subfolder containing a valid `block.json`.
3. If `fields.php` exists, it registers the programmatic field group via `acf_add_local_field_group()`.
4. It calls `register_block_type( $block_folder_path )`, letting ACF and WordPress handle server-side rendering, assets, and attributes.
5. If the `blocks/` directory does not exist or is empty, the loader exits silently with zero errors.

---

## 5. Guide: Adding Your First Gutenberg Block in Future Phases

To create a new block (e.g., `hero-banner` from `index.html`):

### Step 1: Create the Block Directory
```
wp-content/plugins/xmastree-blocks/blocks/hero-banner/
```

### Step 2: Create `block.json`
```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "xmastree/hero-banner",
  "title": "Hero Banner",
  "description": "Christmas Tree Farm homepage hero banner section.",
  "category": "xmastree-blocks",
  "icon": "palmtree",
  "keywords": ["hero", "banner", "christmas", "trees"],
  "acf": {
    "mode": "preview",
    "renderTemplate": "render.php"
  },
  "supports": {
    "align": ["full", "wide"],
    "anchor": true,
    "customClassName": true
  }
}
```

### Step 3: Create `render.php`
```php
<?php
/**
 * Block Template: Hero Banner
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block inner HTML (empty).
 * @param bool   $is_preview True during AJAX preview render.
 * @param int    $post_id    The post ID this block is saved to.
 */

$title    = get_field( 'hero_title' ) ?: 'Experience the Magic of Christmas';
$subtitle = get_field( 'hero_subtitle' ) ?: 'Somerville & Peninsula Christmas Tree Farm';
?>
<section class="hero-banner-section <?php echo esc_attr( $block['className'] ?? '' ); ?>">
    <div class="container text-center">
        <h1 class="hero-title"><?php echo esc_html( $title ); ?></h1>
        <p class="hero-subtitle"><?php echo esc_html( $subtitle ); ?></p>
    </div>
</section>
```

### Step 4: Optional `fields.php` (Code-Driven ACF Fields)
```php
<?php
return array(
    'key'    => 'group_xmastree_hero_banner',
    'title'  => 'Hero Banner Settings',
    'fields' => array(
        array(
            'key'   => 'field_hero_title',
            'label' => 'Hero Title',
            'name'  => 'hero_title',
            'type'  => 'text',
        ),
        array(
            'key'   => 'field_hero_subtitle',
            'label' => 'Hero Subtitle',
            'name'  => 'hero_subtitle',
            'type'  => 'textarea',
        ),
    ),
);
```

Once placed in `blocks/hero-banner/`, the block will immediately appear inside Gutenberg under the **XmasTree Blocks** category without any manual registration code!

