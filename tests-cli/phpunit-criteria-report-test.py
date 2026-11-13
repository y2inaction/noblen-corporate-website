#!/usr/bin/env python3
"""Regression tests for scripts/phpunit-criteria-report.py.

Needs only python3 (no WordPress, database, PHPUnit or network). Every scenario builds its own JUnit file in a temporary
directory in PHPUnit 9's real layout (testsuites > testsuite > testsuite > one testsuite per class > testcase, with a data-set
case per data set) and runs the script in a subprocess. The temporary directory is removed on every exit path.

What it proves:
  - counts are the executed <testcase> elements, one per data set, grouped by the test_CNN_ prefix of their name;
  - nested suites are not double counted, and several outer suites are summed;
  - the report never contains the file paths found in the JUnit file;
  - a missing file, a directory, an empty file, an oversized file, malformed XML, a wrong root element, zero cases, a missing or
    non-numeric declared total, a declared-total mismatch, a case without a name, and any failure, error, skip or warning
    all fail closed: exit status 1, a controlled message, the "no evidence" notice, no traceback, nothing on stdout and
    nothing written to the job summary;
  - the job summary is appended to (never truncated) and an unwritable summary fails closed without printing a table;
  - the comparison with the committed register is informational and handles a missing or empty register.
"""
import atexit
import html
import os
import re
import shutil
import subprocess
import sys
import tempfile

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SCRIPT = os.path.join(REPO, 'scripts', 'phpunit-criteria-report.py')
T = tempfile.mkdtemp(prefix='pcrt-')
atexit.register(shutil.rmtree, T, True)
pass_n = fail_n = 0
_n = [0]


def check(name, cond, why=''):
    global pass_n, fail_n
    if cond:
        pass_n += 1
        print(f'ok   {name}')
    else:
        fail_n += 1
        print(f'FAIL {name} :: {why}')


def junit(cases, declared='auto', wrapper=True, suites=1):
    """PHPUnit-9-shaped JUnit text. `cases` is a list of (name, [child tags]); `declared` overrides the declared total."""
    n = len(cases)
    declared_attr = '' if declared is None else f' tests="{n if declared == "auto" else declared}"'
    body = ''
    for name, children in cases:
        name = html.escape(name, quote=True)  # PHPUnit writes a data-set name's quotes as &quot;
        inner = ''.join(f'<{c}>detail</{c}>' for c in children)
        body += (f'<testcase name="{name}" class="FooTest" classname="FooTest" file="/home/secret-user/project/tests/FooTest.php" '
                 f'line="12" assertions="2" time="0.01">{inner}</testcase>' if inner else
                 f'<testcase name="{name}" class="FooTest" classname="FooTest" file="/home/secret-user/project/tests/FooTest.php" '
                 f'line="12" assertions="2" time="0.01"/>')
    cls = f'<testsuite name="FooTest" file="/home/secret-user/project/tests/FooTest.php"{declared_attr} assertions="{2 * n}">{body}</testsuite>'
    suite = (f'<testsuite name="noblen-core"{declared_attr} assertions="{2 * n}">{cls}</testsuite>')
    outer = f'<testsuite name=""{declared_attr} assertions="{2 * n}">{suite}</testsuite>'
    if suites == 2:  # two outer suites, each with the same cases: the declared totals must be summed
        outer = outer + outer
    return '<?xml version="1.0" encoding="UTF-8"?>\n' + (f'<testsuites>{outer}</testsuites>' if wrapper else outer)


def write(text, name=None, mode='w'):
    _n[0] += 1
    path = os.path.join(T, name or f'junit{_n[0]}.xml')
    with open(path, mode) as handle:
        handle.write(text)
    return path


def run(path, *extra, env=None):
    e = dict(os.environ)
    e.pop('GITHUB_STEP_SUMMARY', None)
    e.update(env or {})
    r = subprocess.run([sys.executable, '-I', SCRIPT, path, *extra], capture_output=True, text=True, env=e)
    return r.returncode, r.stdout, r.stderr


def table(out):
    return {m.group(1): int(m.group(2)) for m in re.finditer(r'^(C\d\d|other|total) +(\d+)$', out, re.M)}


GOOD = [('test_C01_a', []), ('test_C01_b', []), ('test_C05_each with data set "project"', []), ('test_C05_each with data set "product"', []),
        ('test_C05_each with data set "company"', []), ('test_C23_x', []), ('testOther', []), ('test_notacriterion', [])]

# ---- A clean run: the counts are the executed cases, one per data set.
rc, out, err = run(write(junit(GOOD)), '--label', 'PHP 9.9')
t = table(out)
check('clean run: exit 0 and nothing on stderr', rc == 0 and err == '', f'rc={rc} err={err}')
check('clean run: per-criterion counts equal the executed test cases (data sets counted one each)', t.get('C01') == 2 and t.get('C05') == 3 and t.get('C23') == 1, str(t))
check('clean run: cases without a test_CNN_ name are counted as "other" and the total adds up', t.get('other') == 2 and t.get('total') == 8, str(t))
check('clean run: the label, the executed total and the assertion total are printed', 'PHP 9.9' in out and 'Executed test cases: 8' in out and 'Assertions: 16' in out, out[:300])
check('clean run: no file path or user name from the JUnit file is printed', '/home/' not in out and 'secret-user' not in out and '.php' not in out, out)
check('clean run: nested suites are not double counted (three suite levels, 8 cases)', t.get('total') == 8)

# ---- Name matching is exact.
odd = [('test_c05_lower', []), ('test_C5_one_digit', []), ('testC05_nounderscore', []), ('test_C05', []), ('test_C05_ok', []), ('prefix_test_C05_x', [])]
rc, out, err = run(write(junit(odd)))
t = table(out)
check('only names that start with test_C and two digits and an underscore count towards a criterion', rc == 0 and t.get('C05') == 1 and t.get('other') == 5, str(t))

# ---- A root <testsuite> (no <testsuites> wrapper) works, and several outer suites are summed.
rc, out, _ = run(write(junit(GOOD, wrapper=False)))
check('a root <testsuite> without <testsuites> is accepted', rc == 0 and table(out).get('total') == 8, out[:200])
rc, out, _ = run(write(junit(GOOD, suites=2)))
check('several outer suites: the declared totals are summed and match the cases found', rc == 0 and table(out).get('total') == 16, out[:300])

# ---- Fail closed. Each case: exit 1, controlled message, notice, no traceback, nothing on stdout, summary untouched.
SUMMARY = os.path.join(T, 'summary-untouched.md')
open(SUMMARY, 'w').write('previous summary\n')
big = os.path.join(T, 'huge.xml')
with open(big, 'wb') as handle:
    handle.truncate(21 * 1024 * 1024)
bad = {
    'missing file': (os.path.join(T, 'no-such-file.xml'), 'does not exist'),
    'path is a directory': (T, 'not a regular file'),
    'empty file': (write('', 'empty.xml'), 'is empty'),
    'oversized file': (big, 'unexpectedly large'),
    'malformed XML (truncated)': (write(junit(GOOD)[:-40]), 'not well-formed'),
    'not XML at all': (write('this is not xml'), 'not well-formed'),
    'wrong root element': (write('<html><body/></html>'), 'root element is <html>'),
    'zero test cases (declared 0)': (write(junit([], declared=0)), 'contains no test cases'),
    'zero test cases (nothing declared either)': (write(junit([], declared=None)), 'does not declare'),
    'declared total missing': (write(junit(GOOD, declared=None)), 'does not declare'),
    'declared total not a number': (write(junit(GOOD, declared='many')), 'does not declare'),
    'declared total higher than the cases found': (write(junit(GOOD, declared=9)), 'contains 8 test cases but declares 9'),
    'declared total lower than the cases found': (write(junit(GOOD, declared=3)), 'contains 8 test cases but declares 3'),
    'a test case without a name': (write(junit(GOOD).replace('<testcase name="testOther"', '<testcase', 1)), 'no name attribute'),
    'a failed test': (write(junit(GOOD[:-1] + [('test_C09_f', ['failure'])])), 'not clean (1 failure)'),
    'an errored test': (write(junit(GOOD[:-1] + [('test_C09_e', ['error'])])), 'not clean (1 error)'),
    'a skipped test': (write(junit(GOOD[:-1] + [('test_C09_s', ['skipped'])])), 'not clean (1 skipped)'),
    'a test with a warning': (write(junit(GOOD[:-1] + [('test_C09_w', ['warning'])])), 'not clean (1 warning)'),
    'several problems at once': (write(junit(GOOD[:-2] + [('test_C09_f', ['failure']), ('test_C09_e', ['error'])])), 'not clean'),
}
for name, (path, needle) in bad.items():
    rc, out, err = run(path, '--summary', SUMMARY)
    check(f'fails closed: {name}', rc == 1 and needle in err and 'phpunit-criteria-report:' in err and 'No per-criterion evidence was produced.' in err
          and 'Traceback' not in err and out == '' and open(SUMMARY).read() == 'previous summary\n', f'rc={rc} out={out!r} err={err!r}')
check('a failure message does not echo the directory the JUnit file was in', all(T not in run(p, '--summary', SUMMARY)[2] for p, _ in bad.values()))

# ---- The job summary: appended, never truncated; GITHUB_STEP_SUMMARY works; an unwritable one fails closed without a table.
S2 = os.path.join(T, 'append.md')
open(S2, 'w').write('existing text\n')
rc, out, _ = run(write(junit(GOOD)), '--summary', S2, '--label', 'leg A')
rc2, out2, _ = run(write(junit(GOOD)), env={'GITHUB_STEP_SUMMARY': S2})
text = open(S2).read()
check('the summary is appended to and the existing text is kept', rc == 0 and text.startswith('existing text\n') and text.count('### PHPUnit per-criterion evidence') == 2, text[:200])
check('the summary is Markdown with a table and the same counts as the log', '| C05 | 3 |' in text and '| **total** | **8** |' in text, text)
check('GITHUB_STEP_SUMMARY is honoured when --summary is not given', rc2 == 0 and 'leg A' in text and 'PHPUnit)' in text)
rc, out, err = run(write(junit(GOOD)), '--summary', T)
check('an unwritable summary fails closed and nothing is printed', rc == 1 and out == '' and 'job summary file cannot be written' in err and 'Traceback' not in err, f'rc={rc} out={out!r} err={err!r}')

# ---- The comparison with the committed register is informational.
REG_OK = write('  - "PHPUnit: 2/2 tests named test_C01_* pass (all)"\n  - "PHPUnit: 3/3 tests named test_C05_* pass (all)"\n  - "PHPUnit: 1/1 tests named test_C23_* pass (all)"\n', 'reg-ok.yml')
REG_DIFF = write('  - "PHPUnit: 2/2 tests named test_C01_* pass (all)"\n  - "PHPUnit: 4/4 tests named test_C05_* pass (all)"\n  - "PHPUnit: 7/7 tests named test_C40_* pass (all)"\n', 'reg-diff.yml')
REG_EMPTY = write('nothing relevant here\n', 'reg-empty.yml')
J = write(junit(GOOD))
rc, out, _ = run(J, '--baseline', REG_OK)
check('baseline identical: reported as identical for the criteria with recorded counts', rc == 0 and 'identical to the committed register for all 3 criteria' in out, out[-300:])
rc, out, _ = run(J, '--baseline', REG_DIFF)
check('baseline different: the differences are listed and the exit status is still 0 (informational)',
      rc == 0 and 'C05: executed 3, register 4' in out and 'C23: executed 1, register 0' in out and 'C40: executed 0, register 7' in out, out[-400:])
rc, out, _ = run(J, '--baseline', os.path.join(T, 'no-register.yml'))
check('baseline file missing: reported as not available, exit status 0', rc == 0 and 'not available' in out, out[-200:])
rc, out, _ = run(J, '--baseline', REG_EMPTY)
check('baseline without per-criterion counts: reported as not available, exit status 0', rc == 0 and 'not available' in out, out[-200:])
rc, out, _ = run(J)
check('no baseline requested: said so', rc == 0 and 'Baseline comparison: not requested.' in out, out[-200:])
rc, out, _ = run(J, '--baseline', REG_DIFF)
check('the comparison never changes the per-criterion counts that are printed', table(out).get('C05') == 3 and table(out).get('C01') == 2)

# ---- Last-resort guard: an unexpected internal error is a controlled failure, never a traceback and never a report.
code = ('import runpy, sys, xml.etree.ElementTree as ET; '
        'ET.parse = lambda *a, **k: (_ for _ in ()).throw(KeyError("injected")); '
        f'sys.argv = [{SCRIPT!r}, {write(junit(GOOD))!r}]; runpy.run_path({SCRIPT!r}, run_name="__main__")')
r = subprocess.run([sys.executable, '-I', '-c', code], capture_output=True, text=True)
check('an unexpected internal error: controlled message with the error type, the no-evidence notice, exit 1, no traceback, nothing on stdout',
      r.returncode == 1 and 'unexpected problem (KeyError' in r.stderr and 'No per-criterion evidence was produced.' in r.stderr
      and 'Traceback' not in r.stderr and r.stdout == '', f'rc={r.returncode} out={r.stdout!r} err={r.stderr!r}')

# ---- The usage error is controlled as well (no JUnit argument).
r = subprocess.run([sys.executable, '-I', SCRIPT], capture_output=True, text=True)
check('no argument: argparse usage error, exit status 2, no output on stdout', r.returncode == 2 and r.stdout == '', f'rc={r.returncode}')

print(f'{pass_n} passed, {fail_n} failed')
sys.exit(0 if fail_n == 0 else 1)
