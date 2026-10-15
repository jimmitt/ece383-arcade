#!/usr/bin/env python3
"""Exercise the real PHP menu renderer with isolated CSV catalogs (no FPGA)."""
import csv
from html.parser import HTMLParser
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]


class Menu(HTMLParser):
    def __init__(self, markup):
        super().__init__()
        self.games = []
        self.scripts = []
        self.feed(markup)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'a' and 'game-link' in attrs.get('class', '').split():
            self.games.append(attrs)
        if tag == 'script':
            self.scripts.append(attrs.get('src'))


class CatalogTests(unittest.TestCase):
    def render(self, rows=None):
        with tempfile.TemporaryDirectory(prefix='arcade-catalog-') as folder:
            folder = Path(folder)
            shutil.copy(ROOT / 'server/index.php', folder / 'index.php')
            if rows is not None:
                with (folder / 'bitstreams.csv').open('w', newline='') as catalog:
                    csv.writer(catalog).writerows([
                        ['Title', 'Bitstream File Name', 'Picture File Name', 'Description'],
                        *rows,
                    ])
            result = subprocess.run(['php', 'index.php'], cwd=folder,
                                    capture_output=True, text=True, check=True)
            self.assertEqual(result.stderr, '')
            return result.stdout, Menu(result.stdout)

    def test_quoted_titles_descriptions_and_safe_links(self):
        title = 'Game, "A" <script>alert(1)</script>'
        description = 'A <b>description</b> & "more"'
        markup, menu = self.render([
            [title, '../game & one.bit', '../missing.png', description],
            ['', 'skip.bit'], ['No filename', ''], [],
        ])
        self.assertEqual(len(menu.games), 1)
        game = menu.games[0]
        self.assertEqual(game['data-title'], title)
        self.assertEqual(game['data-description'], description)
        self.assertEqual(game['href'], 'fpgaloader.php?name=game%20%26%20one.bit')
        self.assertEqual(game['data-image'], '')
        self.assertNotIn('<script>alert(1)</script>', markup)
        self.assertEqual(menu.scripts, ['menu.js'])

    def test_empty_catalog_has_clear_state(self):
        markup, menu = self.render([])
        self.assertEqual(menu.games, [])
        self.assertIn('No games just yet', markup)

    def test_unreadable_catalog_has_clear_state(self):
        markup, menu = self.render()
        self.assertEqual(menu.games, [])
        self.assertIn('Game library unavailable', markup)
        self.assertNotIn('Warning:', markup)

    def test_short_rows_need_no_optional_columns(self):
        markup, menu = self.render([['Minimal game', 'minimal.bit']])
        self.assertEqual(len(menu.games), 1)
        self.assertEqual(menu.games[0]['data-description'], '')
        self.assertIn('data-empty="true"', markup)


if __name__ == '__main__':
    unittest.main()
