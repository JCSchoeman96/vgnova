#!/usr/bin/env python3
"""Run the local Self → Featured Content Manager → Bricks integration twice.

From the repository root:

    python3 site-code/runtime-tests/fcm-self-featured-integration.py

The test is deliberately pinned to Novamira profile `vgnova` and the verified
local site at http://localhost:10039/. It creates one published QA post per
run, requests the normal public permalink, and removes the post in a `finally`
block before comparing runtime counts and protected values to the baseline.
"""

from __future__ import annotations

import html
import json
import re
import signal
import subprocess
import sys
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
import uuid
from html.parser import HTMLParser
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[2]
RUNTIME_HELPER = Path(__file__).with_name("fcm-self-featured-runtime.php")
EXPECTED_SITE = "http://localhost:10039/"
EXPECTED_QA_TITLE = "[QA][AUTOMATED] Self Featured Content Integration"
EXPECTED_POST_COUNTS = {"publish": 3, "trash": 0}
EXPECTED_CONTENT_CATEGORY = {
    "terms": 13,
    "roots": 8,
    "relationships": 4,
    "objects": 3,
}
EXPECTED_CONTENT_TOPIC = {
    "terms": 55,
    "roots": 7,
    "relationships": 3,
    "objects": 3,
}
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


def fail(message: str) -> None:
    raise RuntimeError(message)


def php_literal(value: str) -> str:
    # These supplied values are fixed action names, UUIDs, and UUID-derived
    # slugs. Restrict them before placing them in a PHP single-quoted literal.
    if not re.fullmatch(r"[a-z0-9_-]+", value):
        fail("Refusing an unsafe PHP action or run identifier.")
    return "'" + value + "'"


def decode_novamira_output(output: str) -> dict[str, Any]:
    decoder = json.JSONDecoder()
    for index, char in enumerate(output):
        if char != "{":
            continue
        try:
            wrapper, _ = decoder.raw_decode(output[index:])
        except json.JSONDecodeError:
            continue
        if isinstance(wrapper, dict) and "success" in wrapper:
            return wrapper
    fail("Novamira returned no decodable execute-php result. Output: " + output[-2000:])


def invoke_runtime(action: str, *, slug: str = "", run_id: str = "") -> dict[str, Any]:
    source = RUNTIME_HELPER.read_text(encoding="utf-8")
    if not source.startswith("<?php"):
        fail("The runtime PHP helper has no PHP opening tag.")
    php = [f"$task4f_action = {php_literal(action)};"]
    if slug:
        php.append(f"$task4f_slug = {php_literal(slug)};")
    if run_id:
        php.append(f"$task4f_run_id = {php_literal(run_id)};")
    php.append(source[len("<?php") :])

    request = json.dumps({"code": "\n".join(php)}, ensure_ascii=False)
    completed = subprocess.run(
        [
            "novamira",
            "--site",
            "vgnova",
            "--yes",
            "run",
            "novamira/execute-php",
            "--input",
            "-",
            "--fresh",
        ],
        cwd=ROOT,
        input=request,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        check=False,
        timeout=120,
    )
    if completed.returncode != 0:
        fail(f"Novamira execute-php exited {completed.returncode}: {completed.stdout[-2000:]}")

    wrapper = decode_novamira_output(completed.stdout)
    if not wrapper.get("success") or wrapper.get("errors"):
        fail("Novamira execute-php failed: " + json.dumps(wrapper, ensure_ascii=False))
    raw_result = wrapper.get("output", "")
    try:
        result = json.loads(raw_result)
    except (json.JSONDecodeError, TypeError):
        fail("The WordPress helper returned invalid JSON: " + repr(raw_result))
    if not isinstance(result, dict) or not result.get("ok"):
        fail("The WordPress runtime action failed: " + json.dumps(result, ensure_ascii=False))
    return result


def assert_baseline(snapshot: dict[str, Any]) -> None:
    posts = snapshot["posts"]
    statuses = posts["status_counts"]
    for status, expected in EXPECTED_POST_COUNTS.items():
        if statuses.get(status) != expected:
            fail(f"Unexpected standard post count for {status}: {statuses.get(status)} (expected {expected}).")

    for object_id, expected_type, expected_status in [
        ("375", "post", "publish"),
        ("521", "post", "publish"),
        ("641", "post", "publish"),
    ]:
        item = posts["objects"][object_id]
        if not item.get("exists") or item["post_type"] != expected_type or item["status"] != expected_status:
            fail(f"Fixture post {object_id} differs from the required pre-test state.")

    product = posts["product_523"]
    if not product.get("exists") or product["post_type"] != "product" or product["status"] != "publish" or product["slug"] != "welstand-pakket":
        fail("Protected product 523 differs from the required pre-test state.")

    if snapshot["taxonomies"]["content_category"] != EXPECTED_CONTENT_CATEGORY:
        fail("content_category baseline differs from the required 13 terms / 4 relationships / 3 objects.")
    if snapshot["taxonomies"]["content_topic"] != EXPECTED_CONTENT_TOPIC:
        fail("content_topic baseline differs from the required 55 terms / 3 relationships / 3 objects.")
    if snapshot["taxonomies"]["editorial_format"] != EXPECTED_EDITORIAL_FORMAT:
        fail("editorial_format baseline differs from the required 3 terms and 0 relationships.")
    if snapshot["taxonomies"]["editorial_collection"] != EXPECTED_EDITORIAL_COLLECTION:
        fail("editorial_collection baseline differs from the required empty state.")
    if snapshot["migration"] != EXPECTED_MIGRATION_HASHES:
        fail("The historical migration record or retirement marker differs from the locked baseline.")
    if snapshot["qa_residuals"]:
        fail("A post matching the automated QA identity already exists; cleanup it before running this test.")

    target = snapshot.get("featured_self_target")
    configured_id = snapshot["featured_options"]["featured_self"]["value"]
    if not target or target["id"] != configured_id or target["status"] != "publish":
        fail("The configured featured_self value does not resolve to a published object.")
    if target["post_type"] not in {"product", "mto-course", "tc_events"}:
        fail("The configured featured_self object has an unsupported post type.")


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
        assert_baseline(baseline)
        results = [run_one(1, baseline), run_one(2, baseline)]
        print(json.dumps({
            "site": EXPECTED_SITE,
            "status": "PASS",
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
