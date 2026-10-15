#!/usr/bin/env python3
"""Test the real PHP loader against a fake programmer, without hardware."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]


class LoaderTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='arcade-loader-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        shutil.copy(ROOT / 'server/fpgaloader.php', self.root / 'fpgaloader.php')
        (self.root / 'bitstreams').mkdir()
        self.name = "game with spaces & 'quotes'.bit"
        (self.root / 'bitstreams' / self.name).write_bytes(b'mock bitstream')
        self.log = self.root / 'arguments.json'
        program = self.root / 'openFPGALoader'
        program.write_text('#!/usr/bin/env python3\n'
                           'import json,os,sys\n'
                           'from pathlib import Path\n'
                           'Path(os.environ["MOCK_ARGUMENTS"]).write_text(json.dumps(sys.argv[1:]))\n'
                           'print("Simulated programmer output <not markup>")\n'
                           'sys.exit(int(os.environ.get("MOCK_EXIT", "0")))\n')
        program.chmod(0o755)

    def request(self, name, exit_code=0, json_mode=True, disable_exec=False):
        env = os.environ.copy()
        env.update(PATH=str(self.root) + os.pathsep + env['PATH'],
                   MOCK_ARGUMENTS=str(self.log), MOCK_EXIT=str(exit_code),
                   REQUEST_NAME=json.dumps(name))
        setup = '$_GET["name"] = json_decode(getenv("REQUEST_NAME"), true);'
        if json_mode:
            setup += '$_SERVER["HTTP_ACCEPT"] = "application/json";'
        command = ['php']
        if disable_exec:
            command += ['-d', 'disable_functions=exec']
        command += ['-r', setup + 'require "fpgaloader.php";']
        result = subprocess.run(command, cwd=self.root, env=env,
                                capture_output=True, text=True, check=True)
        self.assertEqual(result.stderr, '')
        return json.loads(result.stdout) if json_mode else result.stdout

    def test_success_and_path_quoting(self):
        result = self.request(self.name)
        self.assertTrue(result['success'])
        self.assertIn('switch the display', result['message'])
        self.assertEqual(json.loads(self.log.read_text()),
                         ['-b', 'nexysVideo', str(self.root / 'bitstreams' / self.name)])

    def test_nonzero_programmer_result_is_failure(self):
        result = self.request(self.name, exit_code=7)
        self.assertFalse(result['success'])
        self.assertIn('Simulated programmer output', result['output'])

    def test_missing_or_invalid_name_never_runs_programmer(self):
        for name in [None, '', [], 'missing.bit', 'script.php']:
            with self.subTest(name=name):
                self.assertFalse(self.request(name)['success'])
                self.assertFalse(self.log.exists())

    def test_disabled_exec_reports_failure(self):
        self.assertFalse(self.request(self.name, disable_exec=True)['success'])
        self.assertFalse(self.log.exists())

    def test_direct_html_escapes_programmer_output(self):
        markup = self.request(self.name, json_mode=False)
        self.assertIn('&lt;not markup&gt;', markup)
        self.assertNotIn('<not markup>', markup)
        self.assertIn('Return to the game menu', markup)


if __name__ == '__main__':
    unittest.main()
