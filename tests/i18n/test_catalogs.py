"""Validate shipped gettext catalogs using Python's standard GNU MO reader."""
import ast
import gettext
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]


class CatalogTests(unittest.TestCase):
    def test_shipped_catalogs_match_sources_and_cover_the_widget(self):
        renderer = (ROOT / 'includes/class-asfw-widget-renderer.php').read_text(encoding='utf-8')
        messages = set(re.findall(r"__\( '((?:\\'|[^'])*)', 'anti-spam-for-wordpress' \)", renderer))
        messages = {message.replace("\\'", "'") for message in messages}
        messages.update(['Protected by Anti Spam for WordPress', 'This form requires JavaScript.', 'Please wait %s s...'])
        required = {'de_DE', 'fr_FR', 'it_IT', 'es_ES', 'bg_BG', 'pt_PT', 'pt_BR', 'pl_PL',
                    'hu_HU', 'sv_SE', 'da_DK', 'nl_NL', 'nb_NO', 'nn_NO', 'fi', 'cs_CZ', 'el'}
        catalogs = list((ROOT / 'languages').glob('*.po'))
        self.assertTrue(required <= {p.stem.removeprefix('anti-spam-for-wordpress-') for p in catalogs})
        self.assertEqual({p.with_suffix('.mo') for p in catalogs}, set((ROOT / 'languages').glob('*.mo')))
        for po in catalogs:
            with self.subTest(catalog=po.name):
                self.assertNotRegex(po.read_text(encoding='utf-8'), r'(?m)^#,.*\bfuzzy\b')
                entries = {}
                # Support both single-line and wrapped gettext strings.
                current = None
                for line in po.read_text(encoding='utf-8').splitlines():
                    if line.startswith('msgid '):
                        msgid = ast.literal_eval(line[6:])
                        current = 'id'
                    elif line.startswith('msgstr '):
                        self.assertNotIn(msgid, entries, 'Duplicate message ID')
                        entries[msgid] = ast.literal_eval(line[7:])
                        current = 'str'
                    elif line.startswith('"'):
                        if current == 'id':
                            msgid += ast.literal_eval(line)
                        elif current == 'str':
                            entries[msgid] += ast.literal_eval(line)
                self.assertTrue(messages <= entries.keys(), messages - entries.keys())
                with po.with_suffix('.mo').open('rb') as stream:
                    catalog = gettext.GNUTranslations(stream)
                locale = po.stem.removeprefix('anti-spam-for-wordpress-')
                self.assertEqual(locale, catalog.info()['language'])
                for msgid, translated in entries.items():
                    if not msgid:
                        continue
                    self.assertTrue(translated)
                    self.assertEqual(translated, catalog.gettext(msgid))
                    placeholders = r'%(?:[1-9][0-9]*\$)?[-+0 #]*(?:[0-9]+|\*)?(?:\.[0-9]+)?[bcdeEfFgGosuxX%]'
                    self.assertEqual(sorted(re.findall(placeholders, msgid)), sorted(re.findall(placeholders, translated)))
                self.assertNotEqual("I'm not a robot", catalog.gettext("I'm not a robot"))
                self.assertEqual('Unknown message', catalog.gettext('Unknown message'))


if __name__ == '__main__':
    unittest.main()
