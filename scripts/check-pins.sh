#!/usr/bin/env bash
# Dependency pin audit. Fails on an unpinned or inconsistently pinned dependency, and on any claim of verification
# that the Classic Editor pin file does not support. Run in CI (static job) and before any push.
set -u
cd "$(git rev-parse --show-toplevel)"
fail=0
bad() { echo "FAIL: $1"; shift; printf '  %s\n' "$@"; fail=1; }

# 1. GitHub Actions: full commit SHA with a version comment.
hits=$(grep -rnE '^\s*(- )?uses:' .github/workflows | grep -vE '@[0-9a-f]{40} # ' || true)
[ -n "$hits" ] && bad "GitHub Actions not pinned to a full commit SHA with a version comment" "$hits"

# 1b. Service containers and setup-php tools: digest-pinned images, exact tool versions.
hits=$(grep -rnE '^\s*image:' .github/workflows | grep -vE '@sha256:[0-9a-f]{64}' || true)
[ -n "$hits" ] && bad "container image not pinned by digest" "$hits"
hits=$(grep -rnE '^\s*tools:' .github/workflows | grep -E 'phpunit|phpcs' | grep -vE 'phpunit:[0-9]+\.[0-9]+\.[0-9]+' || true)
[ -n "$hits" ] && bad "setup-php tool without an exact version" "$hits"

# 2. npm: exact versions only; lockfile present.
node -e '
const p=require("./tests-e2e/package.json"); const bad=[];
for (const sec of ["dependencies","devDependencies"]) for (const [k,v] of Object.entries(p[sec]||{})) if(!/^\d+\.\d+\.\d+$/.test(v)) bad.push(k+"@"+v);
if(bad.length){console.log(bad.join("\n"));process.exit(1)}' >/tmp/pins-npm.txt || bad "npm dependencies not exact" "$(cat /tmp/pins-npm.txt)"
[ -f tests-e2e/package-lock.json ] || bad "npm lockfile missing" "tests-e2e/package-lock.json"
[ -f composer.lock ] || bad "composer lockfile missing" "composer.lock"

# 3. Classic Editor pin: exact version, exact official URL, consistent verification state.
PIN=tests-e2e/wp-env/classic-editor.pin.json
python3 - "$PIN" <<'PY' || fail=1
import json,re,sys
d=json.load(open(sys.argv[1])); errs=[]
slug,ver,src=d.get("slug"),d.get("version",""),d.get("source","")
if slug!="classic-editor": errs.append("slug must be classic-editor")
if not re.fullmatch(r"\d+\.\d+\.\d+",ver): errs.append("version must be an exact x.y.z, got %r"%ver)
if src!="https://downloads.wordpress.org/plugin/%s.%s.zip"%(slug,ver): errs.append("source must be the exact-version official URL, got %r"%src)
sha=d.get("zip_sha256","")
v=d.get("verification",{}); state=v.get("state")
if sha and not re.fullmatch(r"[0-9a-f]{64}",sha): errs.append("zip_sha256 must be 64 lowercase hex characters or blank")
if state not in ("unverified","verified"): errs.append("verification.state must be 'unverified' or 'verified'")
need=["zip_sha256_obtained_from","independent_download_sha256","source_parity_reviewed","reviewed_by","reviewed_utc","review_record"]
if state=="unverified":
    if sha: errs.append("a hash is present but the pin is still 'unverified': complete the review fields and set state to 'verified' in the same reviewed change")
elif state=="verified":
    if not sha: errs.append("state 'verified' requires zip_sha256")
    for k in need:
        if not v.get(k): errs.append("state 'verified' requires verification.%s"%k)
    if v.get("independent_download_sha256") and v.get("independent_download_sha256")!=sha: errs.append("independent_download_sha256 must equal zip_sha256")
if errs:
    print("FAIL: Classic Editor pin is inconsistent"); [print("  "+e) for e in errs]; sys.exit(1)
print("pin state: %s (%s %s)"%(state,slug,ver))
PY

# 4. Workflow consistency between pin state and the Configuration A job.
python3 - "$PIN" <<'PY' || fail=1
import json,sys,yaml
state=json.load(open(sys.argv[1]))["verification"]["state"]
wf=yaml.safe_load(open(".github/workflows/ci.yml")); jobs=wf["jobs"]; errs=[]
a=jobs.get("we-classic-editor-a")
if not a: errs.append("job we-classic-editor-a is missing")
else:
    coe=a.get("continue-on-error",False)
    if state=="unverified" and not coe: errs.append("pin is unverified but the Configuration A job is blocking; it must be continue-on-error until the pin is verified")
    if state=="verified" and coe: errs.append("pin is verified but the Configuration A job is still continue-on-error; remove it so Configuration A becomes a required check")
# Isolation: Configuration A tooling only in its own job; Configuration B suites run in the main WE job.
for name,j in jobs.items():
    text=json.dumps(j)
    if "classic-editor-config-a.sh" in text and name!="we-classic-editor-a": errs.append("job %s uses the Classic Editor installer; only we-classic-editor-a may"%name)
if "CONFIG\": \"B\"" not in json.dumps(jobs.get("we",{})): errs.append("the main WE job must run the Configuration B suite")
if errs:
    print("FAIL: workflow and pin disagree"); [print("  "+e) for e in errs]; sys.exit(1)
print("workflow consistent with pin state")
PY

# 4b. Capture is separate from verification. The capture workflow may only capture, never write, never widen permissions,
# and only the two named jobs may use the installer.
python3 - "$PIN" <<'PY' || fail=1
import glob,json,re,sys,yaml
pin=json.load(open(sys.argv[1])); errs=[]
CAP=".github/workflows/classic-editor-capture.yml"; MARK="tests-e2e/wp-env/capture-request.txt"
allowed={".github/workflows/ci.yml":{"we-classic-editor-a"}, CAP:{"capture"}}
for f in sorted(glob.glob(".github/workflows/*.y*ml")):
    wf=yaml.safe_load(open(f))
    for name,j in (wf.get("jobs") or {}).items():
        if "classic-editor-config-a.sh" in json.dumps(j) and name not in allowed.get(f,set()):
            errs.append("%s job %s uses the Classic Editor installer; only %s may"%(f,name,", ".join("%s:%s"%(k,",".join(sorted(v))) for k,v in allowed.items())))
try: raw=open(CAP).read()
except FileNotFoundError: raw=None
if raw is not None:
    wf=yaml.safe_load(raw); on=wf.get("on",wf.get(True)) or {}
    if set(on.keys())-{"push","workflow_dispatch"}: errs.append("capture workflow may only trigger on push and workflow_dispatch, got %s"%sorted(on.keys()))
    push=on.get("push") or {}
    if push.get("branches")!=["feature/**"]: errs.append("capture push trigger must be exactly branches [feature/**], got %r"%push.get("branches"))
    if push.get("paths")!=[MARK]: errs.append("capture push trigger must be exactly paths [%s], got %r"%(MARK,push.get("paths")))
    if wf.get("permissions")!={"contents":"read"}: errs.append("capture workflow permissions must be exactly {contents: read}, got %r"%wf.get("permissions"))
    for jn,j in (wf.get("jobs") or {}).items():
        if "permissions" in j: errs.append("capture job %s must not set its own permissions"%jn)
        for st in j.get("steps",[]):
            r=st.get("run","") or ""
            for m in re.finditer(r"classic-editor-config-a\.sh\s+(\S+)(\s+\S+)?",r):
                if (m.group(1),(m.group(2) or "").strip())not in (("install","--capture"),("cleanup","")): errs.append("capture job may only run 'install --capture' or 'cleanup', found: %s"%m.group(0))
            if re.search(r"git\s+(add|commit|push|checkout|apply)|contents:\s*write|pin\.json\s*(>|\|)|sed\s+-i[^\n]*pin\.json|tee[^\n]*pin\.json",r): errs.append("capture job must not write to the repository or the pin")
    if "continue-on-error" in raw and "continue-on-error: false" not in raw: errs.append("capture workflow must not hide failures with continue-on-error")
    try: marker=open(MARK).read().splitlines()[0].strip()
    except FileNotFoundError: marker=None
    want="classic-editor %s capture request"%pin.get("version")
    if marker!=want: errs.append("%s must start with exactly %r, got %r"%(MARK,want,marker))
    for u in re.findall(r"uses:\s*(\S+)",raw):
        if not re.fullmatch(r"[^@\s]+@[0-9a-f]{40}",u): errs.append("capture workflow action not pinned to a full SHA: %s"%u)
if errs:
    print("FAIL: capture workflow breaks the capture/verification separation"); [print("  "+e) for e in errs]; sys.exit(1)
print("capture workflow isolated (push on feature/** with marker path only, read-only, capture mode only)")
PY

# 5. Neither package may contain the Classic Editor plugin.
hits=$(git ls-files wordpress-theme wordpress-plugin | grep -i "classic-editor" || true)
[ -n "$hits" ] && bad "Classic Editor files tracked inside a package directory" "$hits"

[ "$fail" -eq 0 ] && echo "PASS: pins consistent"
exit "$fail"
