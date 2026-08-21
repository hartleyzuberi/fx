#!/usr/bin/env python3
"""Extract deterministic, page-level provenance and outline data from the course PDFs.

The script intentionally does not decide that extracted text is curriculum coverage.
It creates an audit substrate: every physical page, text hash, outline destination,
and a conservative set of content markers that later ingestion can map explicitly.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from collections import Counter
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any, Iterable

from pypdf import PdfReader


DOCUMENTS = {
    "complete_course": "Forex_Trading_Complete_Course_Kenya.pdf",
    "tutor_led": "Forex_Trading_Tutor_Led_Guided_Study_Edition.pdf",
}

MARKERS = {
    "learning_objective": re.compile(r"\blearning objectives?\b", re.I),
    "definition": re.compile(r"\bdefinition(?:s)?\b|\bmeans\b|\bis defined as\b", re.I),
    "first_principles": re.compile(r"first[ -]principles?|first principle", re.I),
    "example": re.compile(r"\b(?:worked )?examples?\b", re.I),
    "formula": re.compile(r"\bformula\b|\bequation\b|\bcalculate\b|\bcalculation\b|=", re.I),
    "table": re.compile(r"\btable\b", re.I),
    "diagram": re.compile(r"\bdiagram\b|\bfigure\b|\bchart\b", re.I),
    "notebook_instruction": re.compile(r"notebook|note (?:this|down)|write (?:this|it|down|in)", re.I),
    "warning": re.compile(r"\bwarning\b|\bcaution\b|\bnever\b|\bdo not\b|\btrap\b", re.I),
    "misconception": re.compile(r"misconception|common (?:beginner )?mistakes?|confus", re.I),
    "exercise": re.compile(r"\bexercises?\b|\bdrill\b|\bassignment\b", re.I),
    "reflection": re.compile(r"\breflection\b|reflect on", re.I),
    "comprehension_check": re.compile(r"mastery check|teach it back|retention test|before you read", re.I),
    "quiz": re.compile(r"\bquiz(?:zes)?\b", re.I),
    "answer_key": re.compile(r"answer key|model answer|correct answer", re.I),
    "assessment": re.compile(r"\bassessment\b|\bexam\b", re.I),
    "gate": re.compile(r"\bgate\b|graduation criteria|progression standard", re.I),
    "resource": re.compile(r"\bresources?\b|companion reading|bibliography|official source", re.I),
    "url": re.compile(r"https?://|www\.", re.I),
    "regulator": re.compile(r"\bCMA\b|Capital Markets Authority|\bCFTC\b|\bNFA\b|regulator", re.I),
    "central_bank": re.compile(r"central bank|Federal Reserve|\bECB\b|Bank of England|Bank of Japan|\bCBK\b", re.I),
    "software_platform": re.compile(r"MetaTrader|TradingView|Bar Replay|platform", re.I),
    "broker": re.compile(r"\bbroker\b|dealer due diligence", re.I),
    "practice": re.compile(r"\bpractice\b|\breplay\b|historical test|backtest", re.I),
    "strategy": re.compile(r"\bstrategy\b", re.I),
    "demo": re.compile(r"\bdemo\b|forward test", re.I),
    "journal": re.compile(r"\bjournal\b|trade log", re.I),
    "integrity_challenge": re.compile(r"30-trade integrity", re.I),
    "hundred_trade": re.compile(r"100-trade|100 legitimate|100 trades", re.I),
    "micro_live": re.compile(r"micro-live", re.I),
    "appendix": re.compile(r"\bappendi(?:x|ces)\b", re.I),
    "glossary": re.compile(r"\bglossary\b", re.I),
    "worksheet": re.compile(r"\bworksheet\b|working template", re.I),
}


@dataclass(frozen=True)
class OutlineEntry:
    order: int
    depth: int
    title: str
    physical_page: int | None
    parent_order: int | None


def sha256_bytes(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def normalize_text(text: str) -> str:
    return "\n".join(line.rstrip() for line in text.replace("\x00", "").splitlines()).strip()


def flatten_outline(reader: PdfReader) -> list[OutlineEntry]:
    entries: list[OutlineEntry] = []
    order = 0

    def walk(items: Iterable[Any], depth: int, parent_order: int | None) -> None:
        nonlocal order
        most_recent: int | None = None
        for item in items:
            if isinstance(item, list):
                walk(item, depth + 1, most_recent if most_recent is not None else parent_order)
                continue
            try:
                physical_page = reader.get_destination_page_number(item) + 1
            except Exception:
                physical_page = None
            current = order
            entries.append(
                OutlineEntry(
                    order=current,
                    depth=depth,
                    title=getattr(item, "title", str(item)).strip(),
                    physical_page=physical_page,
                    parent_order=parent_order,
                )
            )
            most_recent = current
            order += 1

    walk(reader.outline or [], 0, None)
    return entries


def classify_text(text: str) -> list[str]:
    return [name for name, pattern in MARKERS.items() if pattern.search(text)]


def logical_page_number(text: str) -> int | None:
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    if not lines:
        return None
    for value in (lines[0], lines[-1]):
        if re.fullmatch(r"\d{1,4}", value):
            return int(value)
    return None


def page_links(page: Any) -> list[str]:
    links: list[str] = []
    for annotation_ref in page.get("/Annots", []):
        try:
            annotation = annotation_ref.get_object()
            action_ref = annotation.get("/A")
            action = action_ref.get_object() if action_ref else None
            uri = action.get("/URI") if action else None
            if uri:
                links.append(str(uri))
        except Exception:
            continue
    return links


def page_record(page: Any, physical_page: int) -> dict[str, Any]:
    text = normalize_text(page.extract_text() or "")
    return {
        "physical_page": physical_page,
        "logical_page": logical_page_number(text),
        "character_count": len(text),
        "line_count": len(text.splitlines()),
        "text_sha256": hashlib.sha256(text.encode("utf-8")).hexdigest(),
        "markers": classify_text(text),
        "external_links": page_links(page),
        "text": text,
    }


def metadata_dict(reader: PdfReader) -> dict[str, str | None]:
    result: dict[str, str | None] = {}
    for key, value in (reader.metadata or {}).items():
        result[str(key).lstrip("/")] = None if value is None else str(value)
    return result


def outline_stats(entries: list[OutlineEntry]) -> dict[str, Any]:
    titles = [entry.title for entry in entries]
    chapter_numbers = sorted(
        {
            int(match.group(1))
            for title in titles
            for match in [re.search(r"\bChapter (\d+)\b", title, re.I)]
            if match
        }
    )
    return {
        "entries": len(entries),
        "depth_counts": dict(sorted(Counter(entry.depth for entry in entries).items())),
        "chapter_numbers": chapter_numbers,
        "chapter_count": len(chapter_numbers),
        "part_count": sum(bool(re.search(r"\bPart [IVX]+\b", title)) for title in titles),
        "quiz_headings": sum(bool(re.search(r"\bQuiz(?:zes)?\b", title, re.I)) for title in titles),
        "gate_headings": sum(bool(re.search(r"\bGate\b", title, re.I)) for title in titles),
        "appendix_headings": sum(bool(re.search(r"\bAppendix\b", title, re.I)) for title in titles),
        "mastery_check_headings": sum("Mastery Check" in title for title in titles),
        "guided_unit_headings": sum(entry.depth == 0 and bool(re.search(r"\bChapter \d+", entry.title)) for entry in entries),
    }


def extract_document(document_id: str, path: Path, output_dir: Path) -> dict[str, Any]:
    reader = PdfReader(str(path))
    outline = flatten_outline(reader)
    page_path = output_dir / f"{document_id}_pages.jsonl"
    marker_pages: Counter[str] = Counter()
    all_links: list[str] = []
    empty_pages: list[int] = []
    total_characters = 0

    with page_path.open("w", encoding="utf-8", newline="\n") as output:
        for index, page in enumerate(reader.pages, start=1):
            record = page_record(page, index)
            if not record["text"]:
                empty_pages.append(index)
            total_characters += record["character_count"]
            marker_pages.update(record["markers"])
            all_links.extend(record["external_links"])
            output.write(json.dumps(record, ensure_ascii=False, sort_keys=True) + "\n")

    outline_path = output_dir / f"{document_id}_outline.json"
    outline_path.write_text(
        json.dumps([asdict(entry) for entry in outline], ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    return {
        "document_id": document_id,
        "filename": path.name,
        "absolute_source_path": str(path.resolve()),
        "file_sha256": sha256_bytes(path),
        "file_size_bytes": path.stat().st_size,
        "physical_pages": len(reader.pages),
        "empty_text_pages": empty_pages,
        "total_extracted_characters": total_characters,
        "metadata": metadata_dict(reader),
        "outline": outline_stats(outline),
        "marker_page_counts": dict(sorted(marker_pages.items())),
        "external_link_annotations": len(all_links),
        "unique_external_links": len(set(all_links)),
        "page_records": str(page_path.resolve()),
        "outline_records": str(outline_path.resolve()),
    }


def read_page_hashes(path: Path) -> dict[str, list[int]]:
    hashes: dict[str, list[int]] = {}
    with path.open(encoding="utf-8") as stream:
        for line in stream:
            record = json.loads(line)
            hashes.setdefault(record["text_sha256"], []).append(record["physical_page"])
    return hashes


def compare_embedded_pages(output_dir: Path) -> dict[str, Any]:
    canonical_path = output_dir / "complete_course_pages.jsonl"
    guided_path = output_dir / "tutor_led_pages.jsonl"
    guided_hashes = read_page_hashes(guided_path)
    matches: list[dict[str, int]] = []
    unmatched: list[int] = []

    with canonical_path.open(encoding="utf-8") as stream:
        for line in stream:
            record = json.loads(line)
            guided_pages = guided_hashes.get(record["text_sha256"], [])
            if len(guided_pages) == 1:
                matches.append(
                    {
                        "canonical_physical_page": record["physical_page"],
                        "guided_physical_page": guided_pages[0],
                    }
                )
            else:
                unmatched.append(record["physical_page"])

    mapping_path = output_dir / "canonical_to_guided_page_map.json"
    mapping_path.write_text(json.dumps(matches, indent=2) + "\n", encoding="utf-8")
    matched_guided_pages = {item["guided_physical_page"] for item in matches}
    guided_page_count = sum(1 for _ in guided_path.open(encoding="utf-8"))
    return {
        "comparison": "normalized extracted-text SHA-256",
        "canonical_pages": len(matches) + len(unmatched),
        "exact_one_to_one_matches": len(matches),
        "unmatched_or_ambiguous_canonical_pages": unmatched,
        "guided_pages_matching_canonical": len(matched_guided_pages),
        "guided_additional_pages": guided_page_count - len(matched_guided_pages),
        "page_map": str(mapping_path.resolve()),
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-dir", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, default=Path("tmp/pdfs/source-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    args.output_dir.mkdir(parents=True, exist_ok=True)
    missing = [name for name in DOCUMENTS.values() if not (args.source_dir / name).is_file()]
    if missing:
        print(f"Missing source PDFs: {', '.join(missing)}", file=sys.stderr)
        return 2

    documents = [
        extract_document(document_id, args.source_dir / filename, args.output_dir)
        for document_id, filename in DOCUMENTS.items()
    ]
    manifest = {
        "schema_version": 1,
        "coverage_claim": "extraction-only; curriculum mapping is audited separately",
        "documents": documents,
        "cross_document_relationship": compare_embedded_pages(args.output_dir),
        "totals": {
            "documents": len(documents),
            "physical_pages": sum(item["physical_pages"] for item in documents),
            "extracted_characters": sum(item["total_extracted_characters"] for item in documents),
            "empty_text_pages": sum(len(item["empty_text_pages"]) for item in documents),
        },
    }
    manifest_path = args.output_dir / "manifest.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
