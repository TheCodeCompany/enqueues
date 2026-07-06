# Enqueues — post-2.0.0 review findings (for Opus to action later)

_Fable review pass over the whole 2.0.0 wave (`6e7ca46..b9aa899` on `test-cache`)._

**Bottom line: no functional bugs.** All 20 changed files lint clean; the wave was probe-tested
(12 harnesses) and browser/CLI-validated live on the C2C ddev canary (home + /about-us/ + wp-admin all
200, 28 blocks register, `wp enqueues` CLI works, object-cache downgrade + manifest build/clear
confirmed). The items below are **design decisions / hardening / minor optimisations**, not defects —
the plugin is safe to use as-is. Do NOT treat these as blockers.

> Constraint reminder: do NOT merge `test-cache` → `main` and do NOT do wp.org release prep (semver tag
> / `^2.0` install docs) — those are deferred by the user (was task #8).

---

## 1. (MEDIUM — design/contract) The build-time manifest is consulted regardless of cache mode

- **Where:** `src/php/Library/EnqueueAssets.php:475-483` (`get_theme_template_files` M0 check) and
  `src/php/Controller/BlockEditorRegistrationController.php:263-275` (`get_block_asset_version` M0 check).
  The manifest check runs *before* the `is_cache_enabled()` / mode gates.
- **Issue:** If a `dist/enqueues-manifest.php` file exists, it is used even when the cache mode is
  **Off** — but Off is documented as "resolve assets on every request (the previous behaviour)". So a
  present manifest silently overrides the Off contract. Relatedly, `enqueues_manifest()`
  (`src/php/Function/Manifest.php:71-75`) computes `get_enqueues_build_signature()` for validation on
  every request when a manifest exists, even in Off mode — the `.asset.php` glob cost that Off mode
  otherwise avoids. (No-manifest sites, i.e. the default, pay nothing — `enqueues_manifest()` returns
  null before computing the signature.)
- **Decision for Opus (pick one):**
  - (a) Gate the manifest tier behind `is_request_memo_enabled()` so Off mode truly resolves every
    request; or
  - (b) **(recommended)** Keep it independent and document that the manifest is a deploy-artifact tier:
    "if a manifest exists it is always used (signature-validated), independent of cache mode." It is
    opt-in per deploy and always correct, so (b) is defensible — but it's a conscious call, so decide +
    document rather than leave implicit.

## 2. (LOW — latent coupling) `get_theme_template_files($theme_directory)` ignores its param for the manifest

- **Where:** `src/php/Library/EnqueueAssets.php:471-483`.
- **Issue:** The scan path honours the passed `$theme_directory`, but the M0 manifest
  (`enqueues_manifest_path()` keys off `get_template_directory()`) returns **main-theme** data
  regardless of the argument. Every current caller passes `get_template_directory()`, so it's correct
  today — but a future caller passing a child/other theme dir would get the wrong manifest data.
- **For Opus:** low priority. Either drop the now-effectively-fixed `$theme_directory` param, or key the
  manifest lookup by the passed dir if multi-dir support is ever intended.

## 3. (LOW — minor optimisation) `build_block_version_map()` includes blocks that use their block.json version

- **Where:** `src/php/Controller/BlockEditorRegistrationController.php` (`build_block_version_map`).
- **Issue:** The runtime callers (`set_block_metadata_version` / `set_block_asset_version`) skip
  `get_block_asset_version()` when `should_use_block_json_version()` is true, so those blocks never
  consult the manifest. The builder does not apply that gate, so the manifest carries a few **unused**
  entries. Harmless (never read) — just a marginally larger map.
- **For Opus:** optional — apply `should_use_block_json_version()` in the builder to trim. Very low.

## 4. (LOW — hardening) Manifest write is non-atomic

- **Where:** `src/php/Function/Manifest.php` (`enqueues_manifest_write`).
- **Issue:** `file_put_contents()` isn't atomic; a concurrent `wp enqueues manifest build` (rare — it's a
  deploy command) could tear the file. **Already mitigated on the read side:** `enqueues_read_asset_php`
  try/catches a `ParseError` → `[]` → `enqueues_manifest()` returns null → falls back to scanning. So the
  worst case is a one-request scan fallback, never a fatal.
- **For Opus:** optional — write to a temp file + `rename()` for atomicity. Low priority.

---

_None of the above affects correctness of the shipped behaviour. Items 2–4 are "nice to have"; item 1 is
the only one worth a real decision (recommend: document the manifest as mode-independent)._
