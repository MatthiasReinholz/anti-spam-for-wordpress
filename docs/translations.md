# Widget translations

## Design and scope

The widget was already internationalized: `ASFW_Widget_Renderer::get_translations()`
uses WordPress gettext functions and serializes the results into each widget's
escaped `strings` attribute. The custom element renders these strings using
`textContent`. Previously the plugin shipped only a POT template, without PO/MO
translations or registration of its bundled language directory.

Keep this existing per-widget boundary. PHP is the source of translated strings;
there is no parallel JavaScript language catalog, browser-language detection, or
translation download at runtime. English JavaScript defaults support standalone
custom elements and missing strings. WordPress-rendered widgets receive all ten
widget strings, including the extended introduction, privacy link, retry control,
and verification/error states. The catalogs also translate the JavaScript-disabled
notice and submit-delay countdown. Admin screens, server validation messages, and
site-authored privacy content are outside this initial translation scope.

`asfw_init()` registers `/languages` with `load_plugin_textdomain()`. WordPress
selects the active request locale, normally the site's **Settings → General → Site
Language**, with its usual locale filters and installed language-pack precedence.
Unsupported languages and untranslated messages fall back to English. Multilingual
plugins can set the request locale normally; page caches must vary by language as
configured for the rest of the site.

The shortcode's existing `language="fr_FR"` override uses WordPress's
`switch_to_locale()`. That locale must be available to WordPress (normally by
installing its core language pack); shipping a plugin catalog alone does not add
it to WordPress's available locales. Failed switches retain the current locale.
Successful switches restore the previous locale in `finally`, including nested
switches and throwing filters. The override applies to the widget's `strings`;
the surrounding form and no-JavaScript notice use the request locale.
The existing `asfw_translations` filter remains available for custom text.

Leave **Footer text** blank to translate the default at render time. Existing
saved English defaults also follow the current locale. Custom saved text remains
unchanged. A previously saved non-English default is indistinguishable from custom
text; clear that field once to restore automatic translation.

## Catalogs

PO files are the editable source; compiled MO files ship with the plugin so
translations work for self-hosted installations. The POT remains the full-plugin
extraction template. These initial PO files intentionally contain only the widget
messages. This does not claim full admin-interface translations.

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

Regional and formality catalogs are also included for German, French, Spanish,
Dutch and Portuguese (38 locale catalogs total). WordPress resolves exact locale
filenames; explicit catalogs avoid custom fallback hooks and work with the
plugin's minimum WordPress version. Regional wording can be maintained independently.

## Maintenance

1. Keep translatable PHP source strings literal and in the
   `anti-spam-for-wordpress` text domain. Add matching English fallback strings to
   the custom element when adding widget states.
2. Regenerate the POT with the repository's release tooling, or use WP-CLI:

   ```sh
   wp i18n make-pot . languages/anti-spam-for-wordpress.pot \
     --slug=anti-spam-for-wordpress \
     --exclude=.git,.github,.wp-plugin-base,.wp-plugin-base-quality-pack,.wp-plugin-base-security-pack,dist,node_modules,tests,packages,routes
   ```

3. Edit the PO catalogs using a gettext editor. Preserve placeholders, UTF-8,
   locale headers, and plural rules. Update related regional catalogs when changing
   shared wording. New widget messages must be translated in every shipped catalog.
4. Compile and validate:

   ```sh
   wp i18n make-mo languages
   python3 -m unittest discover -s tests/i18n
   npm run test:browser
   ```

Commit PO and MO files together. Tests compare compiled strings to PO sources,
check requested locale coverage and placeholders, and exercise every shipped
catalog through real browser verification states. The PHP suite separately checks
current-locale rendering, custom footer preservation, and locale-stack restoration.
