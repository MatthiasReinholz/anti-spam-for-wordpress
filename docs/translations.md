# Widget languages

## For site owners

The widget follows **Settings → General → Site Language**. No separate widget
language setting or external translation service is needed. The plugin includes
German, French, Italian, Spanish, Bulgarian, Portuguese, Polish, Hungarian,
Swedish, Danish, Dutch, Norwegian, Finnish, Czech and Greek.

Translations cover the checkbox label, short explanation, progress and success
messages, errors, retry button, privacy link, default footer, JavaScript-disabled
notice and submit-delay countdown. Other plugin messages, administration screens
and your own privacy-policy content are outside this initial translation scope.
Missing translations use English.

The wording is short and informal: German uses **du**, French uses **tu**, and
other languages use their natural informal equivalents. This wording also applies
to locale variants whose WordPress names include “formal”. Both Norwegian written
forms and European and Brazilian Portuguese have their own catalogs.

Leave **Footer text** blank to use the translated default. An existing saved
English default also follows the site language. Custom footer text stays as you
entered it. If an older installation saved a non-English default, clear the field
once to enable automatic translation; the plugin cannot distinguish that saved
text from an intentional custom footer.

For multilingual sites, use your multilingual plugin's usual locale and cache
configuration. Cached pages must be separated by language, just like the rest of
the site. After updating the plugin or translations, clear cached pages if they
still show the previous wording.

### Shortcode language override

```text
[anti_spam_widget language="fr_FR"]
```

An override changes that widget's text. The requested locale must be available to
WordPress, normally by installing its core language pack. A bundled plugin catalog
alone does not make a locale available to `switch_to_locale()`. If WordPress cannot
switch to the requested locale, the widget keeps the current request language.
The surrounding form, no-JavaScript notice and submit-delay countdown continue to
use the request language.

## For developers and translators

### How translations reach the widget

`asfw_init()` registers the plugin's `languages` directory through WordPress's
`load_plugin_textdomain()`. `ASFW_Widget_Renderer::get_translations()` uses the
`anti-spam-for-wordpress` text domain and passes the results in each widget's JSON
`strings` attribute. The renderer escapes that attribute; the custom element uses
`textContent`, so translated text is never interpreted as HTML.

The custom element accepts only known keys with nonempty string values. Missing,
blank or malformed values retain their English defaults. These defaults also
support standalone custom elements. There is no separate JavaScript catalog or
browser-language detection.

WordPress controls locale selection, installed language-pack precedence and
translation filters. Successful per-widget locale switches are always paired with
`restore_previous_locale()` in a `finally` block. Failed and same-locale switches
do not change the locale stack. The existing `asfw_translations` filter remains
available for custom wording; return plain text for the existing keys.

### Catalog layout

PO files are the editable source. Compiled MO files are included in the release
ZIP so self-hosted installations have translations immediately. The POT is the
full-plugin extraction template; the PO files currently contain only the widget
messages listed above.

| Language | Primary WordPress locales |
| --- | --- |
| German | `de_DE` |
| French | `fr_FR` |
| Italian | `it_IT` |
| Spanish | `es_ES` |
| Bulgarian | `bg_BG` |
| Portuguese | `pt_PT`, `pt_BR` |
| Polish | `pl_PL` |
| Hungarian | `hu_HU` |
| Swedish | `sv_SE` |
| Danish | `da_DK` |
| Dutch | `nl_NL` |
| Norwegian | `nb_NO`, `nn_NO` |
| Finnish | `fi` |
| Czech | `cs_CZ` |
| Greek | `el` |

There are 38 exact-locale catalogs, including regional variants of German, French,
Spanish, Dutch and Portuguese. Exact filenames work with WordPress's standard
loader and the plugin's minimum WordPress version; no custom fallback hook is
needed. Maintain related catalogs together when changing shared wording.

### Updating translations

Requirements: PHP, WP-CLI with its `i18n` commands, Python 3.9 or newer, and Node.js
22 or newer for browser tests. Run these commands from the plugin repository.

1. Keep PHP translation calls literal and use the plugin's text domain. When
   adding a widget string, add its English fallback to `public/asfw-widget.js`.
2. Refresh the extraction template:

   ```sh
   wp i18n make-pot . languages/anti-spam-for-wordpress.pot \
     --slug=anti-spam-for-wordpress \
     --exclude=.git,.github,.wp-plugin-base,.wp-plugin-base-quality-pack,.wp-plugin-base-security-pack,dist,node_modules,tests,packages,routes
   ```

3. Use a gettext editor to update the PO files. Translate every widget message
   in every shipped locale. Keep UTF-8, locale headers and format placeholders.
   Use natural, informal language and avoid claims that the check protects personal
   data. Countdown messages use abbreviated seconds to avoid singular/plural errors.
4. Compile and check the catalogs:

   ```sh
   wp i18n make-mo languages
   python3 -m unittest discover -s tests/i18n
   ```

5. Install browser-test dependencies and run the regressions:

   ```sh
   npm ci
   npx playwright install --with-deps chromium
   npm run test:browser
   ```

Commit PO and MO files together. Catalog tests check source/compiled consistency,
locale coverage, duplicate messages, fuzzy entries and placeholders. Browser tests
exercise every shipped catalog, verification states, retry text and malformed or
HTML-like translations. PHP tests cover request-language rendering, custom footer
preservation and locale-stack restoration. CI runs Chromium, Firefox and WebKit.
