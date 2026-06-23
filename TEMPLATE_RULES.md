# ChemHeaven — Template Rules & Style Guide

This document captures all the conventions, patterns, and rules found in the existing templates.
Use it as a prompt when generating new templates so they match the established style.

---

## 1. Template Engine Syntax

| Syntax | Purpose |
|---|---|
| `{{ $variable }}` | Echo a PHP variable |
| `{{ $object.property }}` | Echo a property of an object/array |
| `@include(path/to/partial.html)` | Include a partial (paths are relative to `templates/`) |
| `@foreach($items as $item) ... @endforeach` | Loop over a collection |
| `@if($condition) ... @else ... @endif` | Conditional rendering |

---

## 2. Shared Rules (Both Storefront & Admin)

1. **DOCTYPE + lang**: Every full-page template starts with `<!DOCTYPE html><html lang="en">`.
2. **Head via partial**: Never write a `<head>` block inline — always use `@include(...)`.
3. **CSRF token**: Every `<form method="post">` must include:
   ```html
   <input type="hidden" name="_csrf_token" value="{{ $csrf_token }}">
   ```
4. **Page title**: Passed to head partials as `{{ $title }}`.
5. **No external JS frameworks**: Only plain vanilla JavaScript (no jQuery, no React, etc.).
6. **Google Fonts**: Inter (400–800), Space Grotesk (400–700), Share Tech Mono — loaded in every head partial.
7. **Inline scripts only**: All JavaScript lives in a `<script>` tag just before `</body>`, always wrapped in an IIFE:
   ```js
   (function () {
       // ...
   })();
   ```
8. **Currency formatting**: Use `Intl.NumberFormat` with `en-US` locale and `USD`:
   ```js
   new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(price)
   ```
9. **HTML comments**: Use `<!-- Section Name -->` to label major blocks inside a template.
10. **No closing `</html>` indentation issues**: `</body>` and `</html>` are at the root level with no extra indentation.

---

## 3. Storefront Templates

### 3.1 Page Structure

Every storefront page follows this exact skeleton:

```html
<!DOCTYPE html>
<html lang="en">
@include(partials/head.html)

<body>
    @include(partials/site-header.html)

    <main class="page-main">
        <div class="site-shell">
            <!-- page content here -->
        </div>
    </main>

    @include(cart-officer/cart-sidebar.html)   <!-- include only on pages with a cart -->
    @include(partials/site-footer.html)

    <script>
    (function () {
        // page-specific JS
    })();
    </script>
</body>

</html>
```

> **Note:** Pages that have no cart interaction (e.g. `privacy.html`, `error.html`) omit `@include(cart-officer/cart-sidebar.html)`.

### 3.2 Storefront Partials

| Partial path | What it renders |
|---|---|
| `partials/head.html` | `<head>` with meta, fonts, CSS, cart JS config |
| `partials/site-header.html` | Top header with logo and cart button |
| `partials/site-footer.html` | Footer with `{{ $footer_text }}` and privacy link |
| `cart-officer/cart-button.html` | Cart icon + badge button (used inside header) |
| `cart-officer/cart-sidebar.html` | Sliding cart drawer / sidebar |

Variables consumed by partials:

| Variable | Partial | Meaning |
|---|---|---|
| `{{ $title }}` | `head.html` | Browser tab title |
| `{{ $csrf_token }}` | `head.html` | CSRF meta tag |
| `{{ $footer_text }}` | `site-footer.html` | Copyright / footer copy |

### 3.3 Storefront CSS Classes

#### Layout
| Class | Role |
|---|---|
| `page-main` | Top-level `<main>` element |
| `site-shell` | Centered content container |

#### Hero panel
| Class | Role |
|---|---|
| `hero-panel` | Full-width hero section wrapper |
| `hero-copy` | Left text column inside hero |
| `eyebrow` | Small label rendered above a heading |
| `hero-title` | Main `<h1>` inside the hero |
| `hero-text` | Paragraph text inside the hero |
| `hero-stats` | Stats row inside the hero |
| `stat-card` | Individual stat card |
| `stat-value` | Large number/value inside a stat card |
| `stat-label` | Label below the value in a stat card |
| `hero-art` | Right decorative area inside the hero |

#### Sections
| Class | Role |
|---|---|
| `store-section` | Generic content section |
| `section-head` | Row containing section title + optional badge/action |
| `section-title` | `<h2>` section heading |
| `section-copy` | Descriptive paragraph in a section |
| `catalog-badge` | Inline badge shown next to a section title |

#### Product grid (home page)
| Class | Role |
|---|---|
| `product-grid` | CSS grid of product cards |
| `product-card` | Single product card (`<article>`) |
| `product-media` | Image container inside the card |
| `product-body` | Text/info column inside the card |
| `product-kicker` | Category label (small, above title) |
| `product-title` | Product name heading |
| `product-description` | Short product description |
| `product-meta` | Variant button area |
| `variant-buttons` | Container for variant toggle buttons on a card |
| `variant-button` | Individual variant toggle button |
| `product-card-footer` | Price + Add button row at the bottom of a card |
| `product-price` | Price display element |
| `product-card-add-btn` | Add-to-cart button on the card |

Product card `data-*` attributes required for cart integration:

```html
<button class="co-add-btn product-card-add-btn"
    data-product-id="{{ $product.id }}"
    data-product-name="{{ $product.name }}"
    data-product-variant=""
    data-price="0"
    data-quantity="1"
    data-id=""
    type="button">Add</button>
```

Variant button `data-*` attributes:

```html
<button class="variant-button"
    data-product-name="{{ $product.name }}"
    data-product-variant="{{ $variant.label }}"
    data-price="{{ $variant.price }}"
    data-quantity="1"
    data-id="{{ $variant.id }}"
    data-product-id="{{ $product.id }}"
    type="button">{{ $variant.label }}</button>
```

#### Product detail page
| Class | Role |
|---|---|
| `back-nav` | Wrapper for the back-to-catalog link |
| `back-link` | The anchor for going back |
| `back-icon` | SVG icon inside the back link |
| `product-detail-panel` | Two-column layout (media + info) |
| `product-detail-media` | Left media column |
| `detail-img` | Product image inside media column |
| `detail-glow-effect` | Decorative glow element |
| `product-detail-info` | Right info column |
| `detail-kicker` | Category label above the title |
| `detail-title` | Product `<h1>` |
| `price-row` | Price + stock badge row |
| `detail-price` | Price display |
| `stock-badge` | "In Stock" badge |
| `detail-description` | Full product description |
| `selection-section` | Variant selection wrapper |
| `selection-title` | Heading above variant pills |
| `variant-pills` | Container for pill-style variant buttons |
| `variant-pill co-variant-btn` | Individual pill variant button |
| `action-section` | Quantity selector + add-to-cart row |
| `qty-selector` | Quantity control wrapper |
| `qty-btn` | `+` / `−` quantity buttons |
| `qty-input` | Read-only quantity number input |
| `product-cart-btn co-add-btn` | Add-to-cart button on detail page |
| `specs-section` | Product specifications table wrapper |
| `specs-header` | Heading above specs table |
| `specs-table` | `<table>` of specification rows |

#### Checkout page
| Class | Role |
|---|---|
| `checkout-panel` | Two-column layout (`section` + `aside`) |
| `checkout-billing-card` | Left `<section>` — form column |
| `checkout-summary-card` | Right `<aside>` — order summary |
| `checkout-title` | Main heading inside each column |
| `checkout-subtitle` | Sub-heading |
| `checkout-description` | Description paragraph |
| `checkout-items-list` | List of order items in summary |
| `checkout-item` | Single order item row |
| `checkout-item-details` | Name + variant stack |
| `checkout-item-name` | Item product name |
| `checkout-item-variant` | Variant + quantity text |
| `checkout-item-total` | Line total for the item |
| `checkout-totals` | Subtotal/Shipping/Total block |
| `totals-row` | Single row in totals block |
| `grand-total` | Modifier for the grand-total row |
| `privacy-notice` | Icon + privacy assurance text |
| `checkout-btn` | Full-width form submit button |

#### Forms (shared between storefront + admin)
| Class | Role |
|---|---|
| `form-grid` | Two-column responsive form grid |
| `form-group` | One form field wrapper |
| `form-group full-width` | Field spanning both columns |
| `form-label` | `<label>` element |
| `form-input` | `<input>` or `<textarea>` |
| `form-select` | `<select>` element |
| `privacy-consent-label` | Label wrapping the privacy checkbox |
| `privacy-checkbox` | The consent checkbox input |
| `privacy-link` | Link to privacy policy inside consent label |

### 3.4 Storefront Accessibility Rules

- Sections use `aria-labelledby="<id>"` pointing to their heading element.
- All interactive icon-only buttons have `aria-label="..."`.
- Decorative SVGs and elements use `aria-hidden="true"`.
- Clickable product cards use `role="link"` and `tabindex="0"` to support keyboard navigation.
- Cart sidebar uses `role="dialog"`, `aria-modal="true"`, and `aria-label="Shopping cart"`.
- Cart badge uses `aria-live="polite"`.

---

## 4. Admin Templates

### 4.1 Admin Page Structure

Every authenticated admin page (except login) uses this skeleton:

```html
<!DOCTYPE html>
<html lang="en">
@include(administration/partials/head.html)
<body>
@include(administration/partials/header.html)
<main class="page-main admin-page">
    <div class="site-shell">
        @include(administration/partials/flash.html)

        <!-- page content here -->

    </div>
</main>
</body>
</html>
```

> **Note:** No cart sidebar or storefront footer in admin pages.

### 4.2 Admin Partials

| Partial path | What it renders |
|---|---|
| `administration/partials/head.html` | `<head>` with admin-specific meta + both CSS files |
| `administration/partials/header.html` | Top nav bar with logo, nav pills, and sign-out |
| `administration/partials/flash.html` | Flash message alerts |
| `administration/partials/sidebar.html` | Sidebar nav (used in `layout.html`) |
| `administration/partials/layout.html` | Full sidebar layout (alternative to header-based layout) |

### 4.3 Admin Head Differences

The admin `head.html` adds these extra items compared to the storefront head:

```html
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="/assets/admin.css">
```

It does **not** include the CartOfficer JS configuration or cart CSS.

### 4.4 Admin Navigation Variables

These variables are required on every admin page to mark the active nav link:

| Variable | Active on |
|---|---|
| `{{ $admin_base }}` | Base URL (e.g. `/administration`) |
| `{{ $csrf_token }}` | Required for logout form |
| `{{ $admin_username }}` | Displayed in sidebar footer |
| `{{ $nav_dashboard_active }}` | Dashboard page |
| `{{ $nav_categories_active }}` | Categories pages |
| `{{ $nav_products_active }}` | Products pages |
| `{{ $nav_gateways_active }}` | Payment gateways pages |
| `{{ $nav_shipping_active }}` | Shipping pages |

### 4.5 Admin CSS Classes

#### Layout & panels
| Class | Role |
|---|---|
| `admin-page` | Modifier added to `page-main` on every admin page |
| `admin-page-header` | Header row: title on left, action button on right |
| `admin-panel` | Content card / panel background |
| `admin-panel table-wrap` | Panel specifically wrapping a data table |
| `admin-btn-row` | Row containing the form's submit button(s) |
| `admin-hint` | Helper/hint text shown beneath the page header |
| `admin-hero-compact` | Compact hero variant for the dashboard |
| `admin-stats-grid` | Stats grid in the dashboard hero |

#### Buttons
| Class | Role |
|---|---|
| `admin-btn` | Base admin button |
| `admin-btn--primary` | Primary (create/save) action |
| `admin-btn--secondary` | Secondary (back/cancel) action |
| `btn-link-danger` | Inline danger button (used for delete inside tables) |

#### Tables
| Class | Role |
|---|---|
| `admin-table` | Styled data table |
| `admin-actions` | Last `<td>` in a table row (Edit + Delete controls) |

#### Alerts / Flash
| Class | Role |
|---|---|
| `admin-alert` | Base alert element |
| `admin-alert--error` | Error variant |
| `admin-alert--success` | Success variant |

#### Navigation (header)
| Class | Role |
|---|---|
| `admin-nav-bar` | The `<nav>` element inside the header |
| `admin-nav-pill` | Individual nav link/button |
| `admin-nav-pill--muted` | Muted style (store link) |
| `admin-nav-pill--danger` | Danger style (sign-out button) |
| `admin-logout-form` | Inline logout `<form>` |

#### Navigation (sidebar)
| Class | Role |
|---|---|
| `admin-sidebar` | `<aside>` sidebar element |
| `admin-sidebar-header` | Logo + label row at top |
| `admin-logo-link`, `admin-logo` | Logo anchor and image |
| `admin-label` | "Admin" label next to logo |
| `admin-nav` | `<ul>` navigation list |
| `admin-nav-section` | Section heading `<li>` (non-link) |
| `admin-nav-link` | Navigation link `<a>` |
| `admin-sidebar-footer` | Username, storefront link, sign-out at the bottom |
| `admin-username` | Logged-in username display |
| `admin-shop-link` | "View storefront" link |
| `admin-logout-link` | Sign-out submit button |

#### Stat card variants (dashboard)
| Class | Role |
|---|---|
| `stat-card--accent` | Teal accent variant |
| `stat-card--gold` | Gold accent variant |

#### Login page
| Class | Role |
|---|---|
| `admin-login-page` | `page-main` modifier for login |
| `admin-login-panel` | Login card panel |

### 4.6 Admin List (Index) Page Pattern

Follow this structure for any resource listing page:

```html
<!-- 1. Flash messages -->
@include(administration/partials/flash.html)

<!-- 2. Page header with title + Add button -->
<div class="section-head admin-page-header">
    <h1 class="section-title">{{ $page_title }}</h1>
    <a href="{{ $admin_base }}/resource/new" class="admin-btn admin-btn--primary">Add resource</a>
</div>

<!-- 3. Optional hint -->
<p class="admin-hint">Some contextual hint here.</p>

<!-- 4. Table panel -->
<div class="admin-panel table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Column A</th>
                <th>Column B</th>
                <th></th>  <!-- actions column — always empty header -->
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item.column_a }}</td>
                <td>{{ $item.column_b }}</td>
                <td class="admin-actions">
                    <a href="{{ $admin_base }}/resource/{{ $item.id }}/edit">Edit</a>
                    <form method="post" action="{{ $admin_base }}/resource/{{ $item.id }}/delete"
                          style="display:inline;"
                          onsubmit="return confirm('Delete this item?');">
                        <input type="hidden" name="_csrf_token" value="{{ $csrf_token }}">
                        <button type="submit" class="btn-link-danger">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
```

### 4.7 Admin Form Page Pattern

Follow this structure for any create/edit form page:

```html
<!-- 1. Flash messages -->
@include(administration/partials/flash.html)

<!-- 2. Page header with title + Back button -->
<div class="section-head admin-page-header">
    <h1 class="section-title">{{ $page_title }}</h1>
    <a href="{{ $admin_base }}/resource" class="admin-btn admin-btn--secondary">Back</a>
</div>

<!-- 3. Form panel -->
<div class="checkout-billing-card admin-panel">
    <form method="post" action="{{ $form_action }}" class="admin-form">
        <input type="hidden" name="_csrf_token" value="{{ $csrf_token }}">

        <div class="form-grid">
            <div class="form-group full-width">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ $name }}" required>
            </div>
            <!-- more form-group items -->
        </div>

        <div class="admin-btn-row">
            <button type="submit" class="admin-btn admin-btn--primary">Save resource</button>
        </div>
    </form>
</div>
```

---

## 5. Quick Reference — Variable Naming Conventions

| Convention | Example |
|---|---|
| Page title | `$title`, `$page_title` |
| CSRF token | `$csrf_token` |
| Form POST target | `$form_action` |
| Admin base URL | `$admin_base` |
| Logged-in user | `$admin_username` |
| Collection loop | `$products as $product`, `$items as $item` |
| Object property | `$product.name`, `$item.price` |
| Active nav marker | `$nav_dashboard_active`, `$nav_products_active` |
| Flash messages | `$flash_messages` → `$flash.type`, `$flash.text` |
| Error list | `$errors` → `$err.text` |
| Selected option marker | `$opt.selected`, `$cat.selected` |
| Readonly attribute | `$code_readonly` |
| Checkbox checked state | `$enabled_checked`, `$default_checked` |

---

## 6. Prompt Template for New Pages

Use this as a starting prompt when asking an AI to generate a new ChemHeaven template:

```
Generate a ChemHeaven HTML template following these rules:

TEMPLATE ENGINE:
- Variables: {{ $variable }} or {{ $object.property }}
- Includes: @include(path/to/partial.html)
- Loops: @foreach($items as $item) ... @endforeach
- Conditionals: @if($condition) ... @else ... @endif

[FOR STOREFRONT PAGES]
- Start with <!DOCTYPE html><html lang="en">
- Head: @include(partials/head.html)  — requires {{ $title }} and {{ $csrf_token }}
- Header: @include(partials/site-header.html)
- Wrap all content in <main class="page-main"><div class="site-shell"> ... </div></main>
- If the page has a cart: include @include(cart-officer/cart-sidebar.html) before footer
- Footer: @include(partials/site-footer.html)  — requires {{ $footer_text }}
- All JavaScript is vanilla, wrapped in an IIFE, placed just before </body>
- Use Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }) for prices
- Add aria-labelledby on sections, aria-label on icon buttons, aria-hidden on decorative SVGs

[FOR ADMIN PAGES]
- Start with <!DOCTYPE html><html lang="en">
- Head: @include(administration/partials/head.html)  — requires {{ $title }} and {{ $csrf_token }}
- Header: @include(administration/partials/header.html)  — requires $admin_base, $csrf_token,
  $admin_username, and the five $nav_*_active variables
- Wrap content in <main class="page-main admin-page"><div class="site-shell"> ... </div></main>
- Always start content with @include(administration/partials/flash.html)
- For list pages: use the admin-page-header + admin-panel table-wrap + admin-table pattern
- For form pages: use the admin-page-header + checkout-billing-card admin-panel + admin-form pattern
- Every POST form must include: <input type="hidden" name="_csrf_token" value="{{ $csrf_token }}">
- Delete forms need onsubmit="return confirm('...')" and style="display:inline;"
- No site footer element, no cart sidebar
```
