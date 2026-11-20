# ARES — Automated RESume Generator

A single-page PHP app that renders a form and generates a tailored PDF résumé
from reusable content blocks.

## Requirements

- PHP 8.2 or later (tested on 8.5)
- Composer
- PHP extensions `curl`, `gd`, `zlib`, `mbstring` (plus the extensions required by
  `tecnickcom/tc-lib-pdf`)

## Setup

```sh
composer install
```

This installs the PDF dependency (`tecnickcom/tcpdf`, the compatibility facade
over `tecnickcom/tc-lib-pdf`) and generates the JSON font assets that the new
font stack requires into `vendor/tecnickcom/tc-lib-pdf-font/target/fonts/`.
`vendor/` is intentionally not committed; run `composer install` when deploying.

## Layout

- `index.php` — form rendering and PDF assembly
- `form.html`, `index.html`, `stylesheet.css`, `pdfstylesheet.css` — page/CSS shells
- `blocks/text/` — résumé text/template fragments (`content-*.html`, `content-*.txt`)
- `blocks/images/` — photos, ASCII art and flag images
- `ares-config.php` — TCPDF configuration constants
- `ARES-general-*.pdf` — sample output
