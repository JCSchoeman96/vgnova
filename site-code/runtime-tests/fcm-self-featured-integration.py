#!/usr/bin/env python3
"""Run the local Self → Featured Content Manager → Bricks integration twice.

From the repository root:

    php site-code/bin/vgnova-runtime-mutate.php \
      --target=vgnova-local-10039 \
      --wp-root="/absolute/wordpress/root" \
      --purpose="Run the Self featured-content integration" \
      -- python3 site-code/runtime-tests/fcm-self-featured-integration.py

The test is pinned to the verified local site at http://localhost:10039/ and
uses local WP-CLI so each write process can inherit the coordinator's lock and
database-keeper lifetime descriptors. It creates one published QA post per
run, requests the normal public permalink, and removes the post in a `finally`
block before comparing runtime counts and protected values to the baseline.
"""

from __future__ import annotations

import html
import json
import os
import re
import signal
import subprocess
import sys
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
import uuid
from copy import deepcopy
from html.parser import HTMLParser
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[2]
CLI_WRAPPER = Path(__file__).with_name("fcm-self-featured-cli.php")
MUTATION_SESSION_KEYS = (
    "VGNOVA_MUTATION_SESSION_ID",
    "VGNOVA_MUTATION_TARGET",
    "VGNOVA_MUTATION_DB_LOCK",
    "VGNOVA_MUTATION_DB_CONNECTION_ID",
    "VGNOVA_MUTATION_LOCK_FILE",
)
EXPECTED_SITE = "http://localhost:10039/"
EXPECTED_QA_TITLE = "[QA][AUTOMATED] Self Featured Content Integration"
EXPECTED_CONTENT_CATEGORY_TREE = {"terms": 13, "roots": 8}
EXPECTED_CONTENT_TOPIC_TREE = {"terms": 55, "roots": 7}
EXPECTED_EDITORIAL_FORMAT = {
    "terms": 3,
    "roots": 3,
    "relationships": 0,
    "objects": 0,
}
EXPECTED_EDITORIAL_COLLECTION = {
    "terms": 0,
    "roots": 0,
    "relationships": 0,
    "objects": 0,
}
EXPECTED_MIGRATION_HASHES = {
    "record_sha256": "605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95",
    "marker_sha256": "915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3",
}
EXPECTED_FEATURED_OPTION_KEYS = {
    "featured_nuwe_jy",
    "featured_geloof",
    "featured_self",
    "featured_werk_geld",
    "featured_ouerskap",
    "featured_huwelik",
    "featured_mensstories",
    "featured_leefstyl",
}


def fail(message: str) -> None:
    raise RuntimeError(message)


def invoke_runtime(action: str, *, slug: str = "", run_id: str = "") -> dict[str, Any]:
    if action not in {"snapshot", "create", "cleanup"}:
        fail("Refusing an unsupported WordPress runtime action.")
    if action in {"create", "cleanup"} and (
        not re.fullmatch(r"qa-automated-self-featured-integration-[a-f0-9-]{20,64}", slug)
        or not re.fullmatch(r"[a-f0-9]{32}", run_id)
    ):
        fail("Create and cleanup actions require the exact QA slug and run identifier.")

    wp_root = os.environ.get("VGNOVA_MUTATION_WP_ROOT", "")
    if not wp_root or not Path(wp_root).is_absolute():
        fail("The local WordPress root is unavailable; run this tool under vgnova-runtime-mutate.php.")
    try:
        os.fstat(3)
        os.fstat(4)
    except OSError:
        fail("The coordinator lock descriptors are unavailable; run this tool under vgnova-runtime-mutate.php.")

    if action in {"create", "cleanup"}:
        proof = {key: os.environ.get(key, "") for key in MUTATION_SESSION_KEYS}
        if any(not value for value in proof.values()):
            fail("Create and cleanup actions require an active VGNova mutation session.")

    child_environment = os.environ.copy()
    child_environment.update({
        "VGNOVA_FCM_ACTION": action,
        "VGNOVA_FCM_SLUG": slug,
        "VGNOVA_FCM_RUN_ID": run_id,
    })
    try:
        completed = subprocess.run(
            [
                "wp",
                f"--path={wp_root}",
                "--quiet",
                "eval-file",
                str(CLI_WRAPPER),
            ],
            cwd=ROOT,
            env=child_environment,
            pass_fds=(3, 4),
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
            timeout=120,
        )
    except (OSError, ValueError) as error:
        fail(f"Could not start local WP-CLI with the coordinator lock descriptors: {error}")
    if completed.returncode != 0:
        fail(f"Local WP-CLI eval-file exited {completed.returncode}: {completed.stderr[-2000:]} {completed.stdout[-2000:]}")
    try:
        result = json.loads(completed.stdout)
    except (json.JSONDecodeError, TypeError):
        fail("The WordPress helper returned invalid JSON: " + repr(completed.stdout))
    if not isinstance(result, dict) or not result.get("ok"):
        fail("The WordPress runtime action failed: " + json.dumps(result, ensure_ascii=False))
    return result


def assert_infrastructure(snapshot: dict[str, Any]) -> None:
    posts = snapshot["posts"]
    statuses = posts["status_counts"]
    if not isinstance(statuses, dict) or not {"publish", "trash"}.issubset(statuses):
        fail("The standard-post status snapshot is unavailable.")
    if any(type(count) is not int or count < 0 for count in statuses.values()):
        fail("The standard-post status snapshot contains an invalid count.")

    taxonomies = snapshot["taxonomies"]
    required_taxonomies = {
        "content_category",
        "content_topic",
        "hf_cat_post",
        "editorial_format",
        "editorial_collection",
    }
    if not required_taxonomies.issubset(taxonomies):
        fail("One or more required taxonomy snapshots are unavailable.")
    for taxonomy, stats in taxonomies.items():
        if not isinstance(stats, dict) or any(
            type(stats.get(key)) is not int or stats[key] < 0
            for key in ("terms", "roots", "relationships", "objects")
        ):
            fail(f"The {taxonomy} statistics are incomplete or invalid.")

    category_tree = taxonomies["content_category"]
    if {key: category_tree[key] for key in EXPECTED_CONTENT_CATEGORY_TREE} != EXPECTED_CONTENT_CATEGORY_TREE:
        fail("The content_category taxonomy or Self classification tree differs from the required architecture.")
    if snapshot.get("content_category_self_exists") is not True:
        fail("The existing content_category=self term is unavailable.")

    topic_tree = taxonomies["content_topic"]
    if {key: topic_tree[key] for key in EXPECTED_CONTENT_TOPIC_TREE} != EXPECTED_CONTENT_TOPIC_TREE:
        fail("The canonical content_topic taxonomy tree differs from the required architecture.")

    if snapshot["taxonomies"]["editorial_format"] != EXPECTED_EDITORIAL_FORMAT:
        fail("editorial_format is not in the expected migration-safe, unassigned state.")
    if snapshot["taxonomies"]["editorial_collection"] != EXPECTED_EDITORIAL_COLLECTION:
        fail("editorial_collection baseline differs from the required empty state.")
    if snapshot["migration"] != EXPECTED_MIGRATION_HASHES:
        fail("The historical migration record or retirement marker differs from the locked baseline.")
    if snapshot["qa_residuals"]:
        fail("A post matching the automated QA identity already exists; clean it up before running this test.")

    options = snapshot["featured_options"]
    if set(options) != EXPECTED_FEATURED_OPTION_KEYS:
        fail("The Featured Content Manager options are unavailable or do not match the locked fields.")
    target = snapshot.get("featured_self_target")
    configured_id = options["featured_self"]["value"]
    if type(configured_id) is not int or configured_id <= 0:
        fail("The current featured_self option is empty or invalid.")
    if not target or target["id"] != configured_id or target["status"] != "publish":
        fail("The configured featured_self value does not resolve to a published object.")
    if target["post_type"] not in {"product", "mto-course", "tc_events"}:
        fail("The configured featured_self object has an unsupported post type.")


def fixture_free_snapshot_probe(snapshot: dict[str, Any]) -> dict[str, Any]:
    """Prove fixture absence and zeroed fixture relationships are not preconditions."""
    simulated = deepcopy(snapshot)
    presence = simulated.get("fixture_presence")
    if not isinstance(presence, dict) or not {"521", "523", "641"}.issubset(presence):
        fail("The snapshot lacks fixture-presence diagnostics needed for the fixture-free simulation.")

    published_fixtures_removed = sum(
        1 for object_id in ("521", "641") if presence[object_id] is True
    )
    simulated["posts"]["status_counts"]["publish"] -= published_fixtures_removed
    if simulated["posts"]["status_counts"]["publish"] < 0:
        fail("The fixture-free simulation produced a negative published-post count.")
    for object_id in ("521", "523", "641"):
        simulated["fixture_presence"][object_id] = False
    if any(simulated["fixture_presence"][object_id] is not False for object_id in ("521", "523", "641")):
        fail("The synthetic fixture-free state still reports a Task 4G object as present.")

    for taxonomy in ("content_category", "content_topic", "hf_cat_post"):
        simulated["taxonomies"][taxonomy]["relationships"] = 0
        simulated["taxonomies"][taxonomy]["objects"] = 0

    assert_infrastructure(simulated)
    return simulated


class CardContent(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.text: list[str] = []
        self.links: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag.lower() == "a":
            values = dict(attrs)
            if values.get("href"):
                self.links.append(values["href"] or "")

    def handle_data(self, data: str) -> None:
        self.text.append(data)


def normalized_text(value: str) -> str:
    return " ".join(unicodedata.normalize("NFC", html.unescape(value)).split())


def normalized_url(value: str) -> tuple[str, str, str]:
    parsed = urllib.parse.urlsplit(html.unescape(value))
    return (
        parsed.scheme.lower(),
        parsed.netloc.lower(),
        urllib.parse.unquote(parsed.path).rstrip("/"),
    )


def request_frontend_and_assert_card(post: dict[str, Any], featured: dict[str, Any]) -> dict[str, Any]:
    url = post["permalink"]
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != "http" or parsed.hostname != "localhost" or parsed.port != 10039:
        fail("The temporary article permalink did not resolve to the guarded local vgnova host.")

    request = urllib.request.Request(url, headers={"User-Agent": "Voelgoed-Task-4F-Local-Integration-Test/1.0"})
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            status = response.status
            rendered = response.read().decode("utf-8", errors="replace")
            final_url = response.geturl()
    except urllib.error.URLError as error:
        fail(f"The temporary article front-end request failed: {error}")

    if not 200 <= status < 300:
        fail(f"The temporary article front end returned HTTP {status}.")
    final = urllib.parse.urlsplit(final_url)
    if final.hostname != "localhost" or final.port != 10039:
        fail("The front-end request left the guarded local host after a redirect.")
    if "fr-blog-hero-echo__heading" not in rendered:
        fail("The normal Bricks Posts template output was not present on the temporary article.")
    if 'data-vg-featured-content="true"' not in rendered:
        fail("The active Bricks Featured Content Block did not render on the temporary article.")

    fragments = re.findall(
        r'<article\b(?=[^>]*\bdata-vg-card=["\']featured-content["\'])[^>]*>(.*?)</article\s*>',
        rendered,
        flags=re.IGNORECASE | re.DOTALL,
    )
    target_title = normalized_text(featured["title"])
    target_url = normalized_url(featured["permalink"])
    matching_cards = 0
    for fragment in fragments:
        parsed_card = CardContent()
        parsed_card.feed(fragment)
        card_text = normalized_text(" ".join(parsed_card.text))
        card_links = {normalized_url(link) for link in parsed_card.links}
        if target_title in card_text and target_url in card_links:
            matching_cards += 1
    if matching_cards == 0:
        fail("No rendered Featured Content card contained both the current configured title and permalink.")

    return {
        "http_status": status,
        "final_url": final_url,
        "featured_card_matches": matching_cards,
        "bricks_posts_template_marker": "fr-blog-hero-echo__heading",
        "featured_block_marker": "data-vg-featured-content=true",
    }


def run_one(index: int, baseline: dict[str, Any]) -> dict[str, Any]:
    run_id = uuid.uuid4().hex
    slug = "qa-automated-self-featured-integration-" + run_id
    cleanup_required = False
    create_result: dict[str, Any] | None = None
    cleanup_result: dict[str, Any] | None = None
    request_result: dict[str, Any] | None = None
    after: dict[str, Any] | None = None
    failure: BaseException | None = None

    try:
        before = invoke_runtime("snapshot")["snapshot"]
        if before != baseline:
            fail(f"Run {index} did not start from the unchanged persistent baseline.")

        # Set this before the create request so cleanup still runs if the
        # runtime creates the post but its response is interrupted.
        cleanup_required = True
        create_result = invoke_runtime("create", slug=slug, run_id=run_id)
        post = create_result["post"]
        featured = create_result["featured"]
        if post["title"] != EXPECTED_QA_TITLE or post["post_type"] != "post" or post["status"] != "publish":
            fail("The temporary QA object did not match the required title, post type, and public status.")
        if post["content_category"] != ["self"] or post["happyfiles"] != ["self"]:
            fail("The temporary article did not receive the expected Self and HappyFiles assignments.")
        if post["content_topic"] or post["editorial_format"] or post["editorial_collection"] or post["primary_topic"]:
            fail("The temporary article received an unapproved editorial or topic classification.")
        if featured["id"] != baseline["featured_options"]["featured_self"]["value"]:
            fail("The current featured_self object changed between the pre-test snapshot and article creation.")
        request_result = request_frontend_and_assert_card(post, featured)
    except BaseException as error:
        failure = error
    finally:
        if cleanup_required:
            try:
                cleanup_result = invoke_runtime("cleanup", slug=slug, run_id=run_id)
                after = invoke_runtime("snapshot")["snapshot"]
                if after != baseline:
                    fail(f"Run {index} cleanup did not restore every persistent runtime value and count.")
                if after["qa_residuals"]:
                    fail(f"Run {index} left an automated QA post or relationship behind.")
            except BaseException as cleanup_error:
                if failure is None:
                    failure = cleanup_error
                else:
                    failure = RuntimeError(f"{failure}; cleanup also failed: {cleanup_error}")

    if failure is not None:
        raise failure
    if create_result is None or cleanup_result is None or after is None or request_result is None:
        fail(f"Run {index} did not produce complete creation, rendering, cleanup, and verification results.")

    return {
        "run": index,
        "status": "PASS",
        "temporary_post_id": create_result["post"]["id"],
        "temporary_slug": slug,
        "temporary_post_type": create_result["post"]["post_type"],
        "temporary_post_status": create_result["post"]["status"],
        "temporary_content_category": create_result["post"]["content_category"],
        "temporary_happyfiles": create_result["post"]["happyfiles"],
        "temporary_content_topic": create_result["post"]["content_topic"],
        "temporary_editorial_format": create_result["post"]["editorial_format"],
        "temporary_editorial_collection": create_result["post"]["editorial_collection"],
        "temporary_primary_topic": create_result["post"]["primary_topic"],
        "featured_self_before": baseline["featured_options"]["featured_self"]["value"],
        "featured_self_after": after["featured_options"]["featured_self"]["value"],
        "configured_featured_object": create_result["featured"],
        "front_end": request_result,
        "cleanup": cleanup_result,
        "post_count_restored": after["posts"]["status_counts"] == baseline["posts"]["status_counts"],
        "taxonomy_counts_restored": after["taxonomies"] == baseline["taxonomies"],
        "options_unchanged": after["featured_options"] == baseline["featured_options"],
        "migration_provenance_unchanged": after["migration"] == baseline["migration"],
        "temporary_objects_remaining": after["qa_residuals"],
    }


def exit_on_signal(signum: int, _frame: Any) -> None:
    raise SystemExit(f"Received signal {signum}; exiting through the test cleanup finally block.")


def main() -> int:
    signal.signal(signal.SIGTERM, exit_on_signal)
    signal.signal(signal.SIGINT, exit_on_signal)

    try:
        baseline = invoke_runtime("snapshot")["snapshot"]
        assert_infrastructure(baseline)
        fixture_free = fixture_free_snapshot_probe(baseline)
        results = [run_one(1, baseline), run_one(2, baseline)]
        print(json.dumps({
            "site": EXPECTED_SITE,
            "status": "PASS",
            "fixture_free_baseline_probe": "PASS",
            "fixture_free_simulation": {
                "fixture_presence": fixture_free["fixture_presence"],
                "published_standard_posts": fixture_free["posts"]["status_counts"]["publish"],
                "content_category": fixture_free["taxonomies"]["content_category"],
                "content_topic": fixture_free["taxonomies"]["content_topic"],
                "happyfiles": fixture_free["taxonomies"]["hf_cat_post"],
            },
            "runs": results,
            "baseline": {
                "published_standard_posts": baseline["posts"]["status_counts"]["publish"],
                "content_category": baseline["taxonomies"]["content_category"],
                "content_topic": baseline["taxonomies"]["content_topic"],
                "editorial_format": baseline["taxonomies"]["editorial_format"],
                "editorial_collection": baseline["taxonomies"]["editorial_collection"],
                "featured_self": baseline["featured_options"]["featured_self"]["value"],
            },
            "persistent_state_restored": True,
        }, ensure_ascii=False, indent=2))
        return 0
    except BaseException as error:
        print(json.dumps({"site": EXPECTED_SITE, "status": "FAIL", "error": str(error)}, ensure_ascii=False, indent=2), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
