#!/usr/bin/env python3
"""Lightweight AI code review for pull requests, via OpenRouter.

Reads the PR diff and asks a model to flag real correctness, security or
Magento best-practice issues. Informational only: it prints its findings and
never fails the build, and it skips cleanly when no API key is configured.
"""
import json
import os
import subprocess
import sys
import urllib.request

API_KEY = os.environ.get("OPENROUTER_API_KEY")
MODEL = os.environ.get("REVIEW_MODEL", "anthropic/claude-sonnet-4.6")
EMPTY_TREE = "4b825dc642cb6eb9a060e54bf8d69288fbee4904"
MAX_DIFF_CHARS = 60000


def diff_base() -> str:
    """Base to diff HEAD against: the PR target on pull_request, else the
    previous commit on push (or the empty tree for the very first commit)."""
    base = os.environ.get("GITHUB_BASE_REF")
    if base:
        subprocess.run(["git", "fetch", "origin", base, "--depth", "50"], check=False)
        return f"origin/{base}"
    if subprocess.run(["git", "rev-parse", "HEAD~1"], capture_output=True).returncode == 0:
        return "HEAD~1"
    return EMPTY_TREE


def get_diff() -> str:
    result = subprocess.run(
        ["git", "diff", diff_base(), "HEAD", "--",
         "*.php", "*.js", "*.xml", "*.phtml", "*.less"],
        capture_output=True, text=True, check=False,
    )
    return result.stdout.strip()


def review(diff: str) -> str:
    prompt = (
        "You are reviewing a change to a Magento 2 module. Report only real "
        "correctness, security or Magento best-practice issues, as a short "
        "bulleted list with file references. If there are none, reply exactly "
        "'No issues found.'\n\n"
        f"Diff:\n{diff[:MAX_DIFF_CHARS]}"
    )
    payload = json.dumps({
        "model": MODEL,
        "messages": [{"role": "user", "content": prompt}],
    }).encode()

    request = urllib.request.Request(
        "https://openrouter.ai/api/v1/chat/completions",
        data=payload,
        headers={
            "Authorization": f"Bearer {API_KEY}",
            "Content-Type": "application/json",
        },
    )
    with urllib.request.urlopen(request, timeout=90) as response:
        body = json.load(response)
    return body["choices"][0]["message"]["content"]


def main() -> int:
    if not API_KEY:
        print("OPENROUTER_API_KEY not set; skipping AI review.")
        return 0

    diff = get_diff()
    if not diff:
        print("No reviewable changes.")
        return 0

    try:
        print("=== AI Code Review ===")
        print(review(diff))
    except Exception as exc:  # noqa: BLE001 - review is advisory, never blocking
        print(f"AI review skipped (request failed): {exc}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
