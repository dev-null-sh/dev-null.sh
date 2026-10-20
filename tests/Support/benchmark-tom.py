"""Opt-in, paid live-model benchmark. Run --help; never runs in the test suite."""

import argparse
import concurrent.futures
import hashlib
import io
import json
import re
import statistics
import subprocess
import tarfile
import tempfile
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CASES = ROOT / "tests/Fixtures/tom-tool-benchmark.json"


def fixtures(case):
    """Synthetic public evidence; identical between versions, no live data access."""
    results = {
        "SearchCommits": [{"author": "fixture-author", "body": "PostgreSQL indexing and Rust borrow checker discussions.", "url": "https://example.com/posts/1", "posted_at": "2026-10-01T12:00:00Z"}],
        "SearchKnowledgeBase": [{"headings": ["Bookmarks", "Stack"], "content": "Use Save on a post to bookmark it. Saved posts appear in your bookmarks. The database is PostgreSQL."}],
        "RepositoryInsights": {"repository": "nunomaduro/dev-null.sh", "source": "https://github.com/nunomaduro/dev-null.sh", "stars": 1234, "forks": 56, "languages": {"PHP": 700, "TypeScript": 300}, "contributors": [{"login": "fixture-author", "contributions": 42}], "recent_changes": [{"title": "Improve bookmarks", "url": "https://example.com/pr/1"}], "opportunities": [{"title": "Improve keyboard shortcuts", "url": "https://example.com/issues/1"}]},
        "SearchRepositoryHistory": {"complete": False, "pull_requests": [{"title": "feat: add bookmarks, polls and keyboard shortcuts", "pr_author": "fixture-author", "url": "https://example.com/pr/2", "merged_at": "2026-09-01T12:00:00Z"}], "commits": []},
        "CommunityStats": {"count": 120, "population": 500, "definition": "Fixture count of the requested public-data metric, not online presence or verified demographics.", "as_of": "2026-10-20T12:00:00Z"},
    }
    results = {name: [json.dumps(value)] for name, value in results.items()}
    if case.get("empty_first"):
        results["SearchRepositoryHistory"].insert(0, json.dumps({"complete": False, "pull_requests": [], "commits": []}))
    return results


def score(case, result):
    calls = [call for step in result.get("steps", []) for call in step["calls"]]
    expected = case["expected"]
    names = Counter(call["tool"] for call in calls)
    selection = set(names) == set(expected)
    reasons = []
    if not selection:
        reasons.append(f"expected {sorted(expected)}, got {sorted(names)}")
    for name, criteria in expected.items():
        if not criteria.get("min_calls", 1) <= names[name] <= 2:
            reasons.append(f"{name}: wrong call count {names[name]}")
        arguments = [call["arguments"] for call in calls if call["tool"] == name]
        for args in arguments:
            for key, value in criteria.get("arguments", {}).items():
                actual = args.get(key, 7 if key == "days" else None)
                if isinstance(actual, str):
                    actual = actual.strip().lower()
                if actual != value:
                    reasons.append(f"{name}: {key} expected {value!r}, got {actual!r}")
            for pattern in criteria.get("query_patterns", []):
                if not re.search(pattern, str(args.get("query", "")), re.I):
                    reasons.append(f"{name}: query misses {pattern!r}")
            schema = result.get("schemas", {}).get(name, {})
            for key, value in args.items():
                field = schema.get(key)
                if field is None:
                    reasons.append(f"{name}: unknown argument {key}")
                    continue
                if "enum" in field and value not in field["enum"]:
                    reasons.append(f"{name}: invalid enum {key}")
                if field.get("type") == "integer" and type(value) is not int:
                    reasons.append(f"{name}: {key} is not an integer")
                if field.get("type") == "string":
                    if not isinstance(value, str) or not field.get("minLength", 0) <= len(value) <= field.get("maxLength", float("inf")):
                        reasons.append(f"{name}: invalid string {key}")
            query = args.get("query", "")
            if name == "SearchRepositoryHistory" and (not re.fullmatch(r"[\w][\w +#.\-]*", query) or len(query) > 100):
                reasons.append(f"{name}: unsupported keyword syntax")
            if name == "CommunityStats" and args.get("metric") not in ("location", "bio_language") and args.get("term"):
                reasons.append(f"{name}: unexpected term filter")
        if criteria.get("different_queries") and len({args.get("query", "").strip().lower() for args in arguments}) < 2:
            reasons.append(f"{name}: no distinct follow-up search")
    if result.get("error"):
        reasons.append(result["error"])
    if calls != result.get("executed", []):
        reasons.append("selected calls were not all executed")
    return {"selection_pass": selection and not result.get("error"), "pass": not reasons, "reasons": reasons}


def run_trial(snapshot, side, case, repetition):
    try:
        process = subprocess.run(
            ["php", str(ROOT / "tests/Support/benchmark-tom.php"), str(snapshot)],
            input=json.dumps({**case, "results": fixtures(case)}), text=True,
            capture_output=True, cwd=ROOT, timeout=180,
        )
        if process.returncode:
            raise RuntimeError(f"Benchmark harness exited {process.returncode}; inspect setup with a single case before benchmarking.")
        result = json.loads(process.stdout)
    except subprocess.TimeoutExpired:
        result = {"error": "HarnessTimeout", "steps": [], "executed": []}
    result.update(side=side, case=case["id"], group=case["group"], repetition=repetition)
    result.update(score(case, result))
    return result


def report(cases, results, metadata):
    def rate(rows, key="pass"):
        passed = sum(row[key] for row in rows)
        return f"{passed}/{len(rows)} ({passed / len(rows):.1%})" if rows else "n/a"

    sides = {side: [r for r in results if r["side"] == side] for side in ("before", "after")}
    lines = ["## Tom tool-calling benchmark", "", f"{len(cases)} fixed cases × {metadata['repeats']} fresh runs per case per version. Real provider calls; identical synthetic tool-result fixtures; no production data or writes.", "", "| Category — correct tools + arguments + execution | Before | After |", "|---|---:|---:|"]
    for group in dict.fromkeys(case["group"] for case in cases):
        lines.append("| " + group + " | " + " | ".join(rate([r for r in sides[side] if r["group"] == group]) for side in sides) + " |")
    lines.append("| **Overall** | " + " | ".join(rate(rows) for rows in sides.values()) + " |")
    lines += ["", "| Additional measure | Before | After |", "|---|---:|---:|", "| Pass rate excluding provider errors | " + " | ".join(rate([r for r in rows if not r.get('error')]) for rows in sides.values()) + " |"]
    errors = [str(sum(bool(r.get("error")) for r in rows)) for rows in sides.values()]
    lines.append("| Provider/runtime errors (included as overall failures) | " + " | ".join(errors) + " |")
    for label, subset in [("All cases", cases), ("No-tool controls", [c for c in cases if not c["expected"]])]:
        ids = {c["id"] for c in subset}
        medians = []
        for rows in sides.values():
            times = [r["total_ms"] / 1000 for r in rows if r["case"] in ids and not r.get("error")]
            medians.append(f"{statistics.median(times):.2f}s" if times else "n/a")
        lines.append(f"| Median full reply latency — {label.lower()} | " + " | ".join(medians) + " |")
    lines += ["", "Before: DeepSeek Flash selects tools and answers. After: GPT-6 Luna selects/executes tools, then DeepSeek Flash answers. The original prompts, tool schemas and budgets are loaded from each Git revision.", "", "Pass = expected tools and argument predicates, call budgets respected, selected calls executed, and no provider error. Latency covers successful full replies with fixture results, not real retrieval or queues. This synthetic sample does not establish production accuracy or final-answer quality."]
    if metadata.get("rubric_note"):
        lines += ["", metadata["rubric_note"]]
    return "\n".join(lines) + "\n"


def main():
    parser = argparse.ArgumentParser(description=__doc__, epilog="Requires configured OpenAI/OpenCode keys and the local PostgreSQL testing database. Paid model calls; output files are not overwritten.")
    parser.add_argument("--before", default="76d1712ef6e0d0987eb98a093e48361fb774b466")
    parser.add_argument("--after", default="HEAD")
    parser.add_argument("--repeats", type=int, default=1)
    parser.add_argument("--workers", type=int, default=4)
    parser.add_argument("--case", help="Run one case ID for setup checks")
    parser.add_argument("--output", type=Path, default=ROOT / ".amp/in/artifacts/tom-tool-benchmark")
    args = parser.parse_args()
    if args.repeats < 1 or args.workers < 1:
        parser.error("repeats and workers must be positive")
    cases = json.loads(CASES.read_text())
    if args.case:
        cases = [case for case in cases if case["id"] == args.case]
        if not cases:
            parser.error("unknown case")
    metadata = {"started_at": datetime.now(timezone.utc).isoformat(), "repeats": args.repeats, "workers": args.workers, "dataset_sha256": hashlib.sha256(CASES.read_bytes()).hexdigest()}
    for side in ("before", "after"):
        metadata[side] = subprocess.check_output(["git", "rev-parse", f"{getattr(args, side)}^{{commit}}"], cwd=ROOT, text=True).strip()
    args.output.parent.mkdir(parents=True, exist_ok=True)
    results = []
    with tempfile.TemporaryDirectory(prefix="tom-benchmark-") as directory:
        snapshots = {}
        for side in ("before", "after"):
            snapshots[side] = Path(directory) / side
            snapshots[side].mkdir()
            archive = subprocess.check_output(["git", "archive", metadata[side], "app/Ai", "resources/views/ai"], cwd=ROOT)
            with tarfile.open(fileobj=io.BytesIO(archive)) as files:
                files.extractall(snapshots[side], filter="data")
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.workers) as pool, args.output.with_suffix(".jsonl").open("x") as raw:
            jobs = []
            for repetition in range(1, args.repeats + 1):
                for case in cases:
                    for side in (("before", "after") if repetition % 2 else ("after", "before")):
                        jobs.append(pool.submit(run_trial, snapshots[side], side, case, repetition))
            for future in concurrent.futures.as_completed(jobs):
                row = future.result()
                results.append(row)
                raw.write(json.dumps(row) + "\n")
                raw.flush()
                print(f"{len(results)}/{len(jobs)} {row['side']} {row['case']} #{row['repetition']}: {'PASS' if row['pass'] else 'FAIL'} {'; '.join(row['reasons'])}", flush=True)
    results.sort(key=lambda r: (r["case"], r["side"], r["repetition"]))
    args.output.with_suffix(".json").write_text(json.dumps({"metadata": metadata, "cases": cases, "results": results}, indent=2) + "\n")
    args.output.with_suffix(".md").write_text(report(cases, results, metadata))
    print(f"Report: {args.output.with_suffix('.md')}")


if __name__ == "__main__":
    main()
