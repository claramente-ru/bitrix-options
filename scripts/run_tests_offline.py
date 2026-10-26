#!/usr/bin/env python3
"""Run PHP contract tests without credentials, network or subprocess access."""

from pathlib import Path
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which('php')
DISABLED = ','.join((
    'exec', 'system', 'passthru', 'shell_exec', 'proc_open', 'popen', 'pcntl_exec',
    'pcntl_fork', 'mail', 'dl', 'fsockopen', 'pfsockopen', 'stream_socket_client',
    'stream_socket_server', 'stream_socket_pair', 'stream_socket_sendto',
    'socket_create', 'socket_connect', 'socket_sendto', 'gethostbyname',
    'gethostbynamel', 'gethostbyaddr', 'dns_get_record', 'checkdnsrr',
    'getmxrr', 'dns_check_record', 'dns_get_mx',
))


def main():
    if PHP is None:
        raise RuntimeError('PHP CLI is required')
    environment = {
        'CLARAMENTE_TEST_MODE': 'offline',
        'LANG': 'C',
        'TZ': 'UTC',
    }
    return subprocess.run(
        [PHP, '-n', '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
         '-d', f'open_basedir={ROOT}', '-d', f'disable_functions={DISABLED}',
         str(ROOT / 'tests' / 'hints.php')],
        cwd=ROOT,
        env=environment,
        check=False,
    ).returncode


if __name__ == '__main__':
    sys.exit(main())
