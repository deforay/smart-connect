"""Check backup input validation without running the installer or touching backups."""
import pathlib
import re
import subprocess
import unittest

SCRIPT = (pathlib.Path(__file__).resolve().parents[1] / 'bin/remote-backup.sh').read_text()
HELPER = re.search(r'^is_decimal_integer\(\) \{\n.*?^\}', SCRIPT, re.M | re.S).group()


def run_fragment(fragment, value):
    return subprocess.run(
        ['bash', '-c', 'set -eu\n' + HELPER + '\n' + fragment, 'test', value],
        capture_output=True, text=True,
    )


class BackupInputs(unittest.TestCase):
    def test_decimal_input(self):
        for value, valid in [('', False), ('0', True), ('08', True), ('00022', True),
                             ('1+1', False), ('-1', False), (' 22', False),
                             ('9223372036854775807', True),
                             ('9223372036854775808', False),
                             ('18446744073709551617', False)]:
            with self.subTest(value=value):
                result = run_fragment('is_decimal_integer "$1"', value)
                self.assertEqual(result.returncode == 0, valid)

    def test_port_bounds(self):
        condition = re.search(r'^    if (! is_decimal_integer "\$SSH_PORT".*); then$', SCRIPT, re.M).group(1)
        for value, valid in [('1', True), ('22', True), ('08', True), ('65535', True),
                             ('0', False), ('65536', False), ('', False), ('text', False),
                             ('18446744073709551617', False)]:
            with self.subTest(value=value):
                result = run_fragment('SSH_PORT=$1\nif ' + condition + '; then exit 1; fi', value)
                self.assertEqual(result.returncode == 0, valid)

    def test_adoption_selection(self):
        condition = re.search(r'^    if (is_decimal_integer "\$adopt_choice".*); then$', SCRIPT, re.M).group(1)
        selection = re.search(r'^      (DEST_FOLDER=.*)$', SCRIPT, re.M).group(1)
        for value, valid in [('1', True), ('08', True), ('9', False), ('0', False),
                             ('bad', False), ('18446744073709551617', False)]:
            with self.subTest(value=value):
                result = run_fragment('EXISTING=(a b c d e f g h)\nadopt_choice=$1\nif ' + condition
                                      + '; then ' + selection + '; printf "%s" "$DEST_FOLDER"; else exit 1; fi', value)
                self.assertEqual(result.returncode == 0, valid)
                if value == '08':
                    self.assertEqual(result.stdout, 'h')

    def test_retention(self):
        condition = re.search(r'^  (is_decimal_integer "\$RETENTION".*?) && break$', SCRIPT, re.M).group(1)
        for value, valid in [('1', True), ('08', True), ('14', True), ('0', False),
                             ('-1', False), ('bad', False), ('18446744073709551617', False)]:
            with self.subTest(value=value):
                result = run_fragment('RETENTION=$1\n' + condition, value)
                self.assertEqual(result.returncode == 0, valid)

    def test_destination_defaults(self):
        blocks = re.findall(r'^case "\$DEST_MODE" in\n.*?^esac', SCRIPT, re.M | re.S)
        self.assertEqual(len(blocks), 3)
        for value, choice in [('ssh', '1'), ('smb', '2'), ('local', '3'), ('', '1'), ('invalid', '1')]:
            result = run_fragment('DEST_MODE=$1\n' + blocks[0] + '\nprintf "%s" "$default_choice"', value)
            self.assertEqual(result.returncode, 0)
            self.assertEqual(result.stdout, choice)
        stubs = '\n'.join(name + '() { :; }' for name in ['print', 'configure_ssh', 'configure_smb', 'configure_local'])
        for value in ['ssh', 'smb', 'local', 'invalid']:
            result = run_fragment(stubs + '\nDEST_MODE=$1\n' + blocks[1], value)
            self.assertEqual(result.returncode, 1 if value == 'invalid' else 0)

    def test_schedule_defaults(self):
        block = re.search(r'^case "\$SCHEDULE_CRON" in\n.*?^esac', SCRIPT, re.M | re.S).group()
        for value, choice in [('0 */6 * * *', '1'), ('0 */12 * * *', '2'),
                              ('30 2 * * *', '3'), ('', '2'), ('invalid', '2')]:
            result = run_fragment('SCHEDULE_CRON=$1\n' + block + '\nprintf "%s" "$sched_default"', value)
            self.assertEqual(result.returncode, 0)
            self.assertEqual(result.stdout, choice)


if __name__ == '__main__':
    unittest.main()
