# Manual acceptance records: guide for the people who must produce them

**None of these records exists, and none may be written by the implementer or inferred from a passing test.** This guide tells the reviewer or owner exactly what to check and what to retain, so each record can be produced as signed evidence (E7 in the [CI acceptance test plan](CI-ACCEPTANCE-TEST-PLAN.md)). Three items (C21-021, C21-022, C21-M01) are decided only in [owner-acceptance-decisions.yml](owner-acceptance-decisions.yml); the others are separate signed records.

## Rules for every record

- It is produced by a person who is **not** the author of the work being checked. Items marked "owner" are produced by the owner.
- It names the **commit SHA** checked, the date, the environment (staging URL or build) and the person's role. Use role labels, not private names or certificate numbers.
- It states pass or fail for each step and attaches the evidence listed (screenshots, notes). A failed step is reported, not smoothed over.
- Use a staging copy built from the release packages, never production.

## Record template (copy for each item; leave blank until performed)

```text
Item:            <ID>
Checked commit:  <40-character SHA>
Environment:     <staging address, WordPress and PHP versions>
Performed by:    <role label>            Date (UTC): <date>
Steps and result: <one line per step: pass / fail, with notes>
Evidence kept:   <file names or links>
Outcome:         <accepted / not accepted>   Signature or reference: <...>
```

## The 17 items

| ID | Who | What to do | Evidence to keep |
|----|-----|------------|------------------|
| C01-M01 | Reviewer | Install and activate the theme on a clean site. Open the Dashboard, Appearance (including Customize, Site Identity and Brand Identity), and the front end. Confirm no errors or notices appear and the Primary, Footer and Legal menu locations exist. | Screenshots of the Dashboard, Appearance, Menus and the front end |
| C02-M01 | Reviewer | Activate the plugin on a clean site. Confirm the Noblen menu and the five content types (Business Units, Industries, Services, Products, Projects) appear and there are no errors. | Screenshots of the admin menu and each list |
| C03-M01 | Reviewer | With both active, create a draft record and confirm it is not public. Walk the front end and the admin together. Deactivate the plugin and confirm the front end still loads without errors. | Screenshots; note of the plugin-off behaviour |
| C06-M01 | Reviewer | Walk the admin as editor, clearance officer and approver: create, clear, submit, approve and publish a record of each type; revoke and unpublish one. Confirm the steps and messages are clear. | Notes per role; screenshots of the Publication box states |
| C10-M01 | Reviewer | With clearance incomplete, try every route in the UI (Publish button, Quick Edit, Bulk Edit, scheduling). Confirm none publishes the record and the messages are understandable. | Screenshots of each blocked attempt |
| C12-M01 | Reviewer | Read every public page and each archive for placeholder text, invented claims, wrong names, broken wording or sample content. Confirm only verified content is shown. | Page list with a tick per page; any findings |
| C13-M01 | Reviewer | View the home page, an archive and a record at about 360, 768 and 1280 pixels on a real phone, tablet and desktop, with the default palette and again with the dark appearance and a long logo. Confirm nothing overflows or overlaps and the menu works. | Screenshots at each size; device list |
| C14-M01 | Reviewer | Use only the keyboard: skip link, menu (including Escape to close on mobile width), links, search, and the editor screens. Confirm focus is always visible and the order is sensible. | Notes per page; a short screen recording if possible |
| C15-M01 | Reviewer | Check contrast, text zoom to 200 percent, and reduced-motion on the public pages, in light and dark appearance; try the Customizer contrast check with a deliberately poor colour; run a screen reader over the home page and one record (the logo should be announced with its alternative text). | Notes; screenshots at zoom |
| C17-M01 | Owner | Review screenshots of the whole site for any name or label that must not appear (run the owner naming scan first). | The scan output and the screenshot set reviewed |
| C17-S01 | Owner | Supply the owner naming-scan evidence so the status item reports "supplied". Run `NOBLEN_BLOCKLIST=/path/to/your/private/terms.txt scripts/naming-scan.sh` locally; the term list stays private and is never committed. | The scan's pass or fail output (no private paths in it) |
| C19-M01 | Reviewer | Install the built theme and plugin ZIPs on a clean site (Plugins and Themes, Add New, Upload) and activate. Confirm they install and the checksums in `SHA256SUMS` match. | Checksums; screenshots of install and activation |
| C20-M01 | Reviewer | Read the installation, deployment, recovery, administrator and customization documents as an operator and confirm each step is understandable and accurate. | Review notes; list of corrections |
| C20-M02 | Reviewer | Rehearse the recovery steps on a staging copy: rename the plugin folder, rename the theme folder, restore a backup. Confirm each outcome matches [RECOVERY.md](RECOVERY.md). | Timed notes per scenario |
| C25-M01 | Reviewer | Upgrade a staging copy that has a database backup: confirm the plugin refuses to migrate until backup is confirmed, then migrates, and nothing is lost. | Screenshots of the refusal, confirmation and result |
| C27-M01 | Reviewer | In the audit view, confirm entries show who, when, what changed and the snapshot, and that the documented limits are stated. | Screenshots of the audit view |
| C31-M01 | Reviewer | Publish, edit, unpublish and revoke a record on a site with any page cache; confirm the purge list notice appears and, after purging, the public page reflects the change. | Notes of the notice and the before and after pages |
| C34-M01 | Owner | Walk the audit-key custody procedure: missing key, rotation, and the documented recovery, with the required second person. Confirm the steps are understood and the key copy is held. | Signed note of the walk-through; location of the key custody copy (not the key) |

## Owner decisions (not records)

C21-021, C21-022 and C21-M01 are accepted or not **only** by the owner editing [owner-acceptance-decisions.yml](owner-acceptance-decisions.yml). All three are pending. The generator never marks anything accepted by itself.
