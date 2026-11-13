#!/usr/bin/env python3
"""Report the number of EXECUTED PHPUnit test cases per acceptance criterion, from PHPUnit's own JUnit output.

Usage: phpunit-criteria-report.py JUNIT.xml [--label TEXT] [--baseline REGISTER.yml] [--summary FILE]

What it counts
  Every <testcase> element in the JUnit file, which is one executed test case (a data-provider data set is one case each).
  A case belongs to criterion NN when its name starts with test_CNN_. Nothing is estimated from test methods or source code.

Fail closed
  The exit status is 0 only when the file is a well-formed JUnit report with at least one test case, the number of cases
  equals the total the report itself declares, and no case failed, errored, was skipped or raised a warning. Anything else
  prints a controlled "phpunit-criteria-report:" message, says that no per-criterion evidence was produced, writes nothing to
  the summary file, and exits 1. A report is never printed for a red or doubtful run.

Output
  The table is printed to the log and, when --summary (or the GITHUB_STEP_SUMMARY environment variable) names a file, appended
  to it as Markdown. File paths and other environment details found in the JUnit file are never printed.
  --baseline compares the counts with the per-criterion counts recorded in the committed acceptance register. That comparison
  is informational: a difference is shown, it is not an error.
"""
import argparse
import collections
import os
import re
import sys
import xml.etree.ElementTree as ET

MAX_BYTES = 20 * 1024 * 1024
BAD_CHILDREN = ('failure', 'error', 'skipped', 'warning')
NAME_RE = re.compile(r'^test_C(\d{2})_')
BASELINE_RE = re.compile(r'PHPUnit: (\d+)/(\d+) tests named test_C(\d\d)_')
NO_EVIDENCE = 'No per-criterion evidence was produced.'


def die(message):
    sys.stderr.write(f'phpunit-criteria-report: {message}\n{NO_EVIDENCE}\n')
    sys.exit(1)


def _int_attr(element, name):
    value = element.get(name)
    if value is None:
        return None
    try:
        return int(value)
    except ValueError:
        return None


def load(path):
    """Parse the JUnit file and return the facts needed for the report; call die() on anything doubtful."""
    if not os.path.exists(path):
        die(f'the JUnit file does not exist: {os.path.basename(path)}')
    if not os.path.isfile(path):
        die(f'the JUnit path is not a regular file: {os.path.basename(path)}')
    size = os.path.getsize(path)
    if size == 0:
        die('the JUnit file is empty')
    if size > MAX_BYTES:
        die(f'the JUnit file is unexpectedly large ({size} bytes)')
    try:
        root = ET.parse(path).getroot()
    except ET.ParseError as exc:
        die(f'the JUnit file is not well-formed XML ({exc})')
    except OSError as exc:
        die(f'the JUnit file cannot be read ({exc.strerror or exc})')
    if root.tag not in ('testsuites', 'testsuite'):
        die(f'the JUnit root element is <{root.tag}>, expected <testsuites> or <testsuite>')

    outer = [root] if root.tag == 'testsuite' else [c for c in root if c.tag == 'testsuite']
    declared_parts = [_int_attr(s, 'tests') for s in outer]
    if not outer or any(p is None for p in declared_parts):
        die('the JUnit file does not declare its total number of tests, so the count cannot be cross-checked')
    declared = sum(declared_parts)
    assertions_parts = [_int_attr(s, 'assertions') for s in outer]
    assertions = None if any(p is None for p in assertions_parts) else sum(assertions_parts)

    counts, problems, other = collections.Counter(), [], 0
    cases = 0
    for case in root.iter('testcase'):
        cases += 1
        name = case.get('name')
        if not name:
            die('a <testcase> has no name attribute')
        for child in case:
            if child.tag in BAD_CHILDREN:
                problems.append(child.tag)
        match = NAME_RE.match(name)
        if match:
            counts[int(match.group(1))] += 1
        else:
            other += 1
    if cases == 0:
        die('the JUnit file contains no test cases')
    if cases != declared:
        die(f'the JUnit file contains {cases} test cases but declares {declared}')
    if problems:
        tally = ', '.join(f'{n} {kind}' for kind, n in sorted(collections.Counter(problems).items()))
        die(f'the run was not clean ({tally}); the table is not produced for a red run')
    return {'cases': cases, 'assertions': assertions, 'counts': counts, 'other': other}


def read_baseline(path):
    """Per-criterion test counts recorded in the committed register, or None when it cannot be read."""
    try:
        with open(path, encoding='utf-8') as handle:
            text = handle.read()
    except OSError:
        return None
    found = {int(m.group(3)): int(m.group(2)) for m in BASELINE_RE.finditer(text)}
    return found or None


def compare(counts, baseline):
    if baseline is None:
        return 'Baseline comparison: not available (the committed register could not be read or has no per-criterion counts).'
    diffs = [f'C{n:02d}: executed {counts.get(n, 0)}, register {baseline.get(n, 0)}' for n in sorted(set(counts) | set(baseline))
             if counts.get(n, 0) != baseline.get(n, 0)]
    if not diffs:
        return f'Baseline comparison (informational): identical to the committed register for all {len(baseline)} criteria with recorded counts.'
    return 'Baseline comparison (informational): differs from the committed register: ' + '; '.join(diffs) + '.'


def render(label, facts, comparison):
    counts = facts['counts']
    lines = [f'PHPUnit per-criterion evidence ({label})',
             f"Executed test cases: {facts['cases']} (equal to the total declared by the JUnit file). "
             f"Assertions: {facts['assertions'] if facts['assertions'] is not None else 'not reported'}. "
             'Failures, errors, skips and warnings: none.',
             f'Criteria with named tests: {len(counts)}. Cases named test_CNN_*: {sum(counts.values())}. '
             f"Other cases: {facts['other']}.",
             '', 'Criterion  Executed test cases']
    lines += [f'C{n:02d}        {counts[n]}' for n in sorted(counts)]
    lines += [f"other      {facts['other']}", f"total      {facts['cases']}", '', comparison]
    text = '\n'.join(lines)
    md = [f'### PHPUnit per-criterion evidence ({label})', '',
          f"Executed test cases: **{facts['cases']}** (equal to the total declared by the JUnit file); assertions: "
          f"**{facts['assertions'] if facts['assertions'] is not None else 'not reported'}**; failures, errors, skips and warnings: none.",
          '', '| Criterion | Executed test cases |', '|---|---|']
    md += [f'| C{n:02d} | {counts[n]} |' for n in sorted(counts)]
    md += [f"| other (name not `test_CNN_*`) | {facts['other']} |", f"| **total** | **{facts['cases']}** |", '', comparison, '']
    return text, '\n'.join(md)


def main():
    parser = argparse.ArgumentParser(description='Per-criterion executed PHPUnit test cases from a JUnit file.')
    parser.add_argument('junit')
    parser.add_argument('--label', default='PHPUnit')
    parser.add_argument('--baseline')
    parser.add_argument('--summary', default=os.environ.get('GITHUB_STEP_SUMMARY'))
    args = parser.parse_args()
    facts = load(args.junit)
    comparison = compare(facts['counts'], read_baseline(args.baseline)) if args.baseline else 'Baseline comparison: not requested.'
    text, markdown = render(args.label, facts, comparison)
    if args.summary:  # written before anything is printed, so a failure here leaves no half-reported evidence
        try:
            with open(args.summary, 'a', encoding='utf-8') as handle:
                handle.write(markdown + '\n')
        except OSError as exc:
            die(f'the job summary file cannot be written ({exc.strerror or exc})')
    print(text)


if __name__ == '__main__':
    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:  # never a traceback and never a half-trusted report
        die(f'unexpected problem ({type(exc).__name__}: {exc})')
