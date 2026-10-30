# FluentSnippets Bootstrap Output Remediation v1.0.0 — Stage A

**Captured:** 2026-10-30
**Status:** Stage A read-only evidence complete; Stage B remains blocked pending an exact supported rollback path and resolution of the helper-mode count discrepancy.

## Authority and scope

This evidence records the approved handoff artifact hashes and the hashes of their repository copies separately. The repository copies remove only the eight trailing ASCII spaces required by the repository whitespace check: six in the design and two in the plan. No other bytes or wording changed.

- **Approved handoff artifact — design:** `docs/superpowers/specs/2026-10-30-fluentsnippets-bootstrap-output-remediation-design_v0.1.0.md`; SHA-256 `a4a8bcd33601d1ac7f1ba6b68852949aa5bcfa8dcd775b1b098c3c6a9120c026`
- **Approved handoff artifact — plan:** `docs/superpowers/plans/2026-10-30-fluentsnippets-bootstrap-output-remediation_v0.1.1.md`; SHA-256 `bc841d378204c6304e8bcef248416ef0e8fb99621ead2b2da4ca24167581ed2f`
- **Repository copy — design:** `docs/superpowers/specs/2026-10-30-fluentsnippets-bootstrap-output-remediation-design_v0.1.0.md`; SHA-256 `b5aef279a0709bc183a370982bbfa08a53fa17319278e8bce540aa61c72ba5c8`
- **Repository copy — plan:** `docs/superpowers/plans/2026-10-30-fluentsnippets-bootstrap-output-remediation_v0.1.1.md`; SHA-256 `8a6536fd945e44777a189fecdd6404c8ae9827621cc3dd5d6a09e88da04566cb`

```text
REPOSITORY_COPY_NORMALIZATION=WHITESPACE_ONLY
SEMANTIC_DESIGN_CHANGE=NO
```

PR #69 was already merged. Its exact head was `e883386d1d5ac565814f05894660b90efdfe8d4b`; merge commit `d363373b3993c37da769862cb3ff13b5de7307ec` has parent 2 equal to that head. Exact-head checks were Dashboard checks **SUCCESS**, Canonical source checks **SUCCESS**, VG Nova Platform Gate **SUCCESS**, and artifact publication **SKIPPED**. The Stage A branch is based on that merge commit.

The Local browser, wp-admin, and editor freeze remained active. Runtime inspection used read-only Novamira/WP-CLI and PE-007 commands. No FluentSnippets change, ACF sync, Platform Core deploy, Person schema change, or Bricks change was made.

## Runtime baseline

The exact registered target was `vgnova-local-10039`, home/site `http://localhost:10039/`, database `local`, host `localhost`, prefix `wp_`. The freshly rediscovered active MySQL socket was `/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock` (active AF_UNIX socket; inode `106434737`). The Local WordPress root matched the registered root.

PE-008 status was `in_progress=false`, `recovery_options=[]`; `.vgnova-platform-core-deployments` was absent. Platform Core matched the v1.3.0 baseline: 54 files, content SHA-256 `a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760`, PE-008 tree SHA-256 `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`. The exact content-only digest was recomputed from the accepted 54-file path/SHA inventory; the PE-008 fingerprint independently matched its canonical tree hash.

The v1.3.0 protected snapshot collector and canonicalization produced SHA-256 `2d268408fd466e19bb9bbc62964b2d132fe26810865d15c0255d79b365488e4e`, equal to the final v1.3.0 snapshot. The four pending ACF `Create` groups were unchanged: `group_vg_article_people`, `group_vg_course_people`, `group_vgmp_perk_details`, and `group_vgmp_options`.

## Installed FluentSnippets ownership

- **Plugin:** Fluent Snippets 10.56; slug/directory `easy-code-manager`; entry `wp-content/plugins/easy-code-manager/easy-code-manager.php`.
- **Persistence:** file-backed `FluentSnippets\App\Model\Snippet`; there is no snippet database row or numeric record ID. The authoritative snippet document is `wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php`, keyed by that filename. `Snippet::get()` enumerates and parses storage PHP files; `findByFileName()` resolves this same path.
- **Index:** `fluent-snippet-storage/index.php` is a derived cache. `cacheSnippetIndex()` rebuilds its published/draft maps from the storage files and uses the filename as the map key. The snippet PHP file itself is the persisted authority; it is not generated from a separate database source.
- **Independent identity attributes:** filename/key `10-vg-bricks-medewerker-queries.php`; title/name `[Bricks] Medewerker — Query loop support`; type `PHP`; status `published`; plugin active; `run_at=all`; condition `{"status":"no","run_if":"assertive","items":[[]]}`; `load_as_file` and `load_in_block_editor` empty; source code SHA-256 `7134a1de4a5e8447314b1c3843a60349b8622beaad5de940c4d8c66cb019ae4e`. The index contains the same published filename and metadata.
- **Complete metadata:** description `Bricks Query Loop vars for Medewerker MVP (ACF relationships).`; tags `medewerker,bricks,acf,query-loop`; group `Medewerkers`; created/updated timestamps `2026-06-04 12:00:00`; priority `10`; created_by/updated_by `1`; is_valid `1`.
- **Captured bytes:** full authoritative record and same-path runtime file are 5,998 bytes, SHA-256 `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2`; owner/group `jcschoeman96:jcschoeman96`, mode `0664`. Parsed authoritative code field is 5,400 bytes, SHA-256 as above. Exact read-only copies and a metadata/hash manifest are retained locally under `/tmp/vgnova-stage-a-fluentsnippets-rollback/`; manifest SHA-256 `8f2056cf7847bd0d3b5b4f01ad67520b1e867fef583bb3fdb8344ebf621f9176`.
- **Sibling baseline:** nine other PHP files, including derived `index.php`, have deterministic per-file hashes in the details JSON. The sorted sibling inventory SHA-256 is `e0a135f9cc354e5a79ca91507c23e0e5e96d457a26d0c534f4a1a3db7dd7e5d0`.

The installed source contains **six** `vg_query_type` dispatch branches: `event_medewerkers`, `event_products`, `medewerker_events`, `medewerker_products`, `product_medewerkers`, and `product_source_event`. The approved design/plan refer to five helper modes. This count discrepancy is recorded as an unresolved Stage B review item; all six installed branches and the pre-change hash are preserved. No caller audit or code change was made in Stage A.

## Update, regeneration, and rollback path

The normal editor update action is `wp_ajax_fluent_snippet_update`, handled by `AdminMenuHandler::saveSnippet()`, which calls public static `Helper::updateSnippet($data)`. Its payload is `meta`, `code`, `file_name`, and `reactivate`. `Helper::updateSnippet()` validates/prepares the code and passes the existing `file_name` to `Snippet::updateSnippet($fileName, $code, $metaData)`, preserving the file-key identity. The model writes the same file through `Helper::atomicPut()` and invalidates OPcache.

On success, `Helper::updateSnippet()` fires `fluent_snippets/snippet_updated`; `CodeHandler::rebuildCache()` then calls `Helper::cacheSnippetIndex()`, which regenerates the derived `index.php` from all snippet files. `Helper::syncSnippetIndex()` is the installed plugin's explicit stale-index check/rebuild entry point. No update or regeneration callable was invoked during Stage A.

**Exact rollback is not proven.** `Snippet::updateSnippet()` always replaces `updated_at` with the current time and serializes metadata into generated docblock format. That supported update cannot restore the exact pre-change bytes. `Helper::atomicPut()` can accept raw bytes but falls back to direct `file_put_contents()` when its atomic rename path is unavailable; the installed source exposes no rollback operation that guarantees exact, atomic restoration while preserving the full prior state. A fresh independent source review reached the same result. Therefore `ROLLBACK_INTERFACE_PROVEN=FAIL`; Stage B must remain blocked until a supported exact rollback mechanism is established and reviewed.

## PE-008 RED baseline

Through PE-007, the session helper passed. The health helper exited nonzero only because `no_unexpected_output=false`; `no_php_diagnostics=true`, `session_valid=true`, `wordpress_bootstrapped=true`, runtime identity, Platform Core activity/class/lifecycle, and site-name checks were all true. Both helpers had empty stderr.

The direct PE-007 buffer capture was exactly one LF: length `1`, hex `0a`, base64 `Cg==`, SHA-256 `01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b`. PHP tokenization found the matching `T_INLINE_HTML` LF in the parsed authoritative code field (line 1 of that field) and in the storage file (line 26). The authoritative file and runtime storage are the same file-backed record.

## Stage A result

```text
PR69_EVIDENCE_MERGED=PASS
FLUENTSNIPPETS_OWNERSHIP_PROVEN=PASS
AUTHORITATIVE_SNIPPET_IDENTITY=PASS
MUTATION_INTERFACE_PROVEN=PASS
REGENERATION_INTERFACE_PROVEN=PASS
ROLLBACK_INTERFACE_PROVEN=FAIL
PRE_MUTATION_PROTECTED_STATE=PASS
PE008_SESSION_RED_BASELINE=PASS
PE008_HEALTH_RED_BASELINE=PASS
BOOTSTRAP_CAPTURE_LENGTH=1
BOOTSTRAP_CAPTURE_SHA256=01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b
RUNTIME_MUTATIONS=0
ACF_SYNCS=0
PERSON_SCHEMA_MUTATIONS=0
BRICKS_MUTATIONS=0
MUTATION_AUTHORIZATION=REQUIRED
REPOSITORY_COPY_NORMALIZATION=WHITESPACE_ONLY
SEMANTIC_DESIGN_CHANGE=NO
```

Stage A evidence is ready for independent PR review. No snippet fix or other runtime mutation is authorized by this evidence.

See `docs/architecture/FLUENTSNIPPETS_BOOTSTRAP_OUTPUT_REMEDIATION_v1.0.0_DETAILS.json` for the structured identity, rollback capture, sibling inventory, and check results.
