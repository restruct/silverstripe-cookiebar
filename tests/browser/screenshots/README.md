# README screenshots

`readme.shot.ts` writes the PNGs in `docs/images/` that the module README shows. It is not a test
suite: it runs with its own config, `playwright.screenshots.config.ts` (in this directory), which
the browser tests and CI never load, so a normal test run never rewrites the images. Never edit the
PNGs by hand; regenerate them.

What it needs:

- a Silverstripe 6 host with this module installed, `silverstripe/htmleditor-tinymce` added (so the
  content field shows its editor, as in a real project), and the browser fixtures from
  `tests/browser/fixtures/` copied into it, then the database built. `CkBPage` seeds the pages
  (`/cookie-home`, `/cookie-policy`) and switches the bar on; the shot script then enters neutral
  demo texts through *Settings > Cookie bar*, the way an editor would. The next database build puts
  the fixture's test texts back;
- `BROWSER_TARGET_URLS` pointing at that host (only the `ss6` target is used, else the first);
- network access for the front-end shot: the fixture page has no theme, so the script adds
  Bootstrap 5 from jsDelivr to the page, as the module's default stylesheet expects a Bootstrap
  theme.

The shared runner in the maintainers' workspace does all of that (the extra package goes in through
the runner's `SS6_EXTRA_REQUIRE` hook):

```bash
SS6_EXTRA_REQUIRE="silverstripe/htmleditor-tinymce:^1" BROWSER_ONLY=ss6 \
  tools/browser/run.sh silverstripe-cookiebar --config=screenshots/playwright.screenshots.config.ts
```

Against a host you started yourself, from `tests/browser/`:

```bash
npm ci
BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8868" npx playwright test --config=screenshots/playwright.screenshots.config.ts
```
