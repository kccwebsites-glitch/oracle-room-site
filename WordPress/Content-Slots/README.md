# Oracle Room Content + Media Slots

Status: infrastructure contract for bespoke Elementor HTML objects.

## Why this comes first

Every custom object should declare editable copy and media positions from the beginning so we do not retrofit editor controls after the page design is finished.

GitHub remains the canonical source for component HTML/CSS/JS and coded fallback copy. WordPress stores editor-created overrides and Media Library assignments.

## Copy contract

Use:

```html
<h2
  data-or-content-slot="home-hero-heading"
  data-or-content-type="text"
  data-or-content-label="Home hero heading">
  Coded fallback heading
</h2>
```

Supported types for the first build:
- `text`
- `textarea`
- `richtext`

## Media contract

Use:

```html
<div
  data-or-media-slot="home-hero-image"
  data-or-media-type="image"
  data-or-media-label="Home hero image">
</div>
```

Supported types for the first build:
- `image`
- `gallery`
- `youtube`

## Naming

Use page/object/field names so slots stay globally understandable:

- `home-hero-heading`
- `home-hero-intro`
- `home-hero-image`
- `readings-online-heading`
- `about-portrait`

Do not use visual-position names such as `left-text` or `box-3` unless the content genuinely has no semantic role.

## Elementor workflow

1. Add one HTML widget for the object.
2. Paste the current GitHub component.
3. Save/update the Elementor page.
4. The Oracle Room slot engine discovers declared fields.
5. Copy/media can then be maintained from WordPress without exposing component code.
6. Approved permanent copy changes can later be folded into GitHub defaults.

The slot engine is site infrastructure and should live in the WordPress/ folder in this repository, separate from page objects.
