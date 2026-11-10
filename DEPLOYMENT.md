---
contract_version: 2
kind: tool
service: terminus-pegtest-plugin    # a Terminus plugin (`peg:*` commands) that tests Pantheon Enterprise Gateway connectivity. Runs on users' machines; nothing runs on Pantheon infrastructure

trigger: merge-to-main              # default branch is `master`. Not published anywhere: users install from the git URL, a clone, or Composer's VCS route, all of which read the repo directly
merged_implies_deployed: true       # for everyone who installs from the git URL, the head of master is the release
other_paths_to_prod:
  - "tags (1.0 from 2019-05-23, 1.1 from 2019-06-05) are not used by the documented install methods: `terminus self:plugin:install git@github.com:...git` runs `git clone --depth 1` with no ref (src/Commands/Self/Plugin/InstallCommand.php on Terminus 4.x), so it gets master HEAD"
  - "the README's 'Manual Method' (`composer create-project pantheon-systems/terminus-pegtest-plugin:~1`) cannot work today: the package is not on Packagist, under either its repo name or its composer name (`pantheon-systems/terminus-peg-test-plugin`)"   # verified 2026-11-10: repo.packagist.org returns 404 for both, and Packagist search finds nothing
  - "force-pushing master changes what every new install gets"

pipeline:
  system: null                      # no CI workflow in the repo; only Dependabot's own workflow shows in the Actions list
  workflow: null
  jobs_in_order: []
  typical_minutes: null             # n/a: merge is the release
  minutes_by_change: {}
  watch: "none needed: `git ls-remote https://github.com/pantheon-systems/terminus-pegtest-plugin.git refs/heads/master` shows the merged commit"
  concurrency_group: null
  cancel_in_progress: null
  blocked_by_waiting_run: null
  approval_gate: null

release:
  mechanism: "merge to master. That is the end of it. Nothing tags, builds or publishes"
  tag_scheme: "none in use. Two lightweight tags exist (1.0, 1.1; 2019); everything since, including the 2026 PHP 8.2 fix, is untagged. No GitHub releases"   # README's `:~1` constraint would resolve to 1.1 if it were on Packagist
  never: "force-push master"
  procedure: "merge the PR"

consumers:                          # `gh search code --owner pantheon-systems` for the repo and composer names, observed 2026-11-10: only the repo's own files match
  - {repo: "Terminus users who ran `terminus self:plugin:install` with the git URL, or cloned it", pin: "none: HEAD of master at install time", picked_up_by: "a fresh install, or reinstalling. Whether `terminus self:plugin:update` refreshes a git-URL install: TODO(verify-on-deploy)"}
  - {repo: "support and Enterprise Gateway troubleshooting", pin: "none", picked_up_by: "people running `terminus peg:test:curl|ldap|smtp|ssh` against a gateway; no repo in the org references the plugin"}

repo_properties:
  observed_at: 2026-11-10
  trust_level: TL4
  ccb_status: in-ccb
  owner: developer-experience
  repo_type: plugin

gating_checks:                      # `gh api repos/pantheon-systems/terminus-pegtest-plugin/rules/branches/master`
  required: ["Wiz IaC Scanner", "Wiz Vulnerability Scanner", "Wiz Data Scanner"]   # org ruleset 'TL4 - Low'; also CodeQL code scanning (errors / critical)
  approvals: {required: 1, code_owner: true, last_push_approval: false, strict_up_to_date: false, merge_queue: false}   # org ruleset only; the repo has none of its own
  run_but_not_required: []          # the repo has no CI workflow, so no tests or lint run at all
  conditional: []
  release_gate: "none: the PR review is the only gate"

environments:
  - {name: github, target: "github.com/pantheon-systems/terminus-pegtest-plugin master", gates_prod: true}

artifact:
  image: null
  file: "the repo at master HEAD"
  tag_scheme: none
  tag_mutable: true                 # master moves
  retention: "git history"
  version_signal: "`git -C ~/.terminus/plugins/terminus-pegtest-plugin rev-parse HEAD` on an installed copy"   # inferred
  tag_to_sha: "n/a: no tags in use"

runtime: []                         # runs on users' machines

deployed_when:                      # ALL must hold
  - "the PR is merged and `git ls-remote https://github.com/pantheon-systems/terminus-pegtest-plugin.git refs/heads/master` returns the merge commit"

health:
  soak_minutes: 0
  baseline: "none: no CI, no telemetry"
  checks:
    - name: master has the merged commit
      kind: command
      run: "git ls-remote https://github.com/pantheon-systems/terminus-pegtest-plugin.git refs/heads/master"
      healthy: "returns the merge commit"   # run 2026-11-10: returns 31f11d7 (the github-terraform dependabot.yml commit)
    - name: plugin installs and lists its commands
      kind: command
      run: "export TERMINUS_PLUGINS_DIR=$(mktemp -d) && terminus self:plugin:install https://github.com/pantheon-systems/terminus-pegtest-plugin.git && terminus list peg"
      healthy: "lists peg:constants:list, peg:showcerts, peg:test:curl, peg:test:ldap, peg:test:smtp, peg:test:ssh"   # TODO(verify-on-deploy): not run for this draft
    - name: users
      kind: delegate
      run: "issues on the repo, and #ask-hosting"
      healthy: "no new report"
  known_baseline_errors: []
  dashboards: []
  alerts: []

rollback:
  options:
    - when: "a merged change breaks the plugin"
      kind: revert-and-merge
      steps: |
        1. Open a revert PR on master, get the code-owner approval, merge.
        2. People who already installed the bad commit reinstall: `terminus self:plugin:uninstall <name>` then `terminus self:plugin:install <git url>`.   # inferred: not exercised
      time_to_rollback_minutes: 30  # inferred: PR review
  not_restored:
    - "installs made while the bad commit was on master keep it until reinstalled"
  one_way_changes: []               # checked: no server-side state; the plugin only reads from a gateway
  currently_blocked: null
  last_exercised: null
  capture_before_merge: "`git ls-remote https://github.com/pantheon-systems/terminus-pegtest-plugin.git refs/heads/master`"
  who_can: ["@pantheon-systems/developer-experience (code owners): merge a revert PR"]
  verify: "`git ls-remote ... refs/heads/master` shows the revert commit, and a fresh install lists the peg commands"

escalation:
  slack: "#ask-hosting"
  owner_team: developer-experience
  paged: false                      # inferred: no alerts exist
  issues: "https://github.com/pantheon-systems/terminus-pegtest-plugin/issues"

preflight:
  - "gh auth status (write to merge a revert)"
  - "terminus on PATH (for the install smoke check)"

automation_blockers:
  - blocker: "no CI at all: nothing tests or lints a change before it becomes the release, and there is no health signal"
    clears_when: "a workflow runs `composer lint` or phpunit, and a smoke install on PRs"
  - blocker: "no tags or releases in use, so a user can't pin a known-good version, and a rollback target is just a commit"
    clears_when: "the plugin is tagged and published (Packagist), or the README says master is the only supported install"

verified: null                      # TODO(verify-on-deploy): pass 2 fills it from a real merge
---

# Deployment

This repo is a Terminus plugin for testing Pantheon Enterprise Gateway connectivity (`peg:*` commands). It isn't published to Packagist. "Deploying" it means merging to `master`: users install it from the git URL, which clones the head of `master`. Nothing runs on Pantheon infrastructure.

## How a change reaches production

1. **PR to `master`.** Org rules for TL4 require the three Wiz scanners, CodeQL, and one code-owner approval (`@pantheon-systems/developer-experience`). The repo has no CI workflow, so nothing else runs on a PR.
2. **Merge.** That is the release. Terminus's installer runs `git clone --depth 1` on the git URL, so a new install gets the merged commit.
3. **Users** pick it up on a fresh install or reinstall. TODO(verify-on-deploy): whether `terminus self:plugin:update` refreshes a git-URL install.

The two tags (`1.0`, `1.1`, 2019) are not used by any documented install method, and there are no GitHub releases.

## How to confirm it deployed

```bash
git ls-remote https://github.com/pantheon-systems/terminus-pegtest-plugin.git refs/heads/master
```

It returns the merge commit.

## How to confirm it's healthy

There is no telemetry, dashboard, alert or CI. Health is a smoke install (TODO(verify-on-deploy): not run for this draft) and reports from users:

```bash
export TERMINUS_PLUGINS_DIR=$(mktemp -d)
terminus self:plugin:install https://github.com/pantheon-systems/terminus-pegtest-plugin.git
terminus list peg
```

## Rollback

Revert the change on `master` through a PR. Record the current head first (`git ls-remote https://github.com/pantheon-systems/terminus-pegtest-plugin.git refs/heads/master`). People who already installed the bad commit uninstall and reinstall. [inferred]

**Verify:** `refs/heads/master` shows the revert, and a fresh install lists the `peg` commands.

**What this doesn't restore:** existing installs until they reinstall.

Rollback has never been exercised.

## Escalation

- Owner: `developer-experience` (code owners `@pantheon-systems/developer-experience`).
- Nobody is paged and there are no alerts.
- Issues: https://github.com/pantheon-systems/terminus-pegtest-plugin/issues
- Slack: `#ask-hosting`.

## Open owner questions

- TODO(owner): is this plugin still wanted? Nothing in the org references it, but it was fixed for PHP 8.2 in February 2026.
- TODO(owner): should it be tagged and published to Packagist, or should the README say git-URL install is the only method?

## Suggested pipeline changes

- Fix or remove the README's "Manual Method" (`composer create-project pantheon-systems/terminus-pegtest-plugin:~1`): the package isn't on Packagist.
- Add a minimal CI workflow (lint plus a smoke install).
- `composer.json` declares Terminus `^1|^2|^3`; Terminus 4 is current.
