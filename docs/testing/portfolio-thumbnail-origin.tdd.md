# Portfolio Thumbnail Origin — TDD Evidence

## Source and user journey

This regression was derived from the reported broken thumbnail on
`/portofolio`.

As a visitor, I want uploaded portfolio thumbnails to load from the same origin
as the current page so that the site's `img-src 'self'` Content Security Policy
does not block them.

## RED

Test:
`Tests\Feature\PortfolioImageManagementTest::test_public_storage_images_use_the_current_request_origin`

Command:

```text
php artisan test tests\Feature\PortfolioImageManagementTest.php --filter=test_public_storage_images_use_the_current_request_origin
```

Observed result before the fix:

```text
FAIL  Tests\Feature\PortfolioImageManagementTest
Expected the thumbnail src to be /storage/portfolios/thumbnails/card.jpg.
The response contained http://localhost:8000/storage/portfolios/thumbnails/card.jpg.
```

The failing test used a request host of `127.0.0.1:8000` and a configured public
disk URL of `http://localhost:8000/storage`, reproducing the cross-origin CSP
failure.

## GREEN

The public image resolver now returns a same-origin `/storage/...` path after
the existing file-existence and MIME checks pass.

Commands and results:

```text
php artisan test tests\Feature\PortfolioImageManagementTest.php
PASS — 13 tests, 114 assertions

php artisan test
PASS — 151 tests, 926 assertions

npm.cmd run build
PASS — Vite production build completed
```

Live verification against the running local server confirmed that the uploaded
thumbnail rendered as:

```html
<img src='/storage/portfolios/thumbnails/b4eb6322-d29a-4d38-add3-2db3d78440f4.png' ...>
```

The corresponding image request returned `200 image/png`.

## Test specification

| # | Guarantee | Test or command | Type | Result |
|---|---|---|---|---|
| 1 | Managed portfolio images use a same-origin `/storage/...` URL even when `APP_URL` and the request host differ | `test_public_storage_images_use_the_current_request_origin` | Feature/regression | PASS |
| 2 | A custom configured public path is preserved while its cross-origin host is removed | `test_public_storage_images_preserve_the_configured_url_path` | Feature/regression | PASS |
| 3 | Valid thumbnails and galleries render while missing gallery files are filtered | `test_public_pages_render_storage_urls_and_filter_missing_gallery_images` | Feature | PASS |
| 4 | Missing or unsafe images do not render broken or dangerous paths | `test_missing_or_unsafe_images_use_fallback_and_never_render_broken_paths` | Feature/security | PASS |
| 5 | The complete PHP application suite remains green | `php artisan test` | Unit/integration/feature | PASS |
| 6 | Frontend assets still build for production | `npm.cmd run build` | Build | PASS |

## Coverage and known gaps

No PCOV or Xdebug coverage driver is installed in the active PHP runtime, so a
fresh percentage report could not be generated. The full existing test suite
passed with 151 tests and 926 assertions. Browser automation was unavailable
because the configured Chrome executable was not installed; live HTML and HTTP
responses were verified directly instead.

## Git checkpoints

- `85a0122` — initial RED reproducer
- `2ae587b` — refined same-origin regression guarantee
- `b68d1cb` — GREEN production fix
- `f77d705` — RED coverage for custom public URL paths
- `34ba24f` — GREEN portable same-origin URL fix
