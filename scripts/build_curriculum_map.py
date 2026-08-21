#!/usr/bin/env python3
"""Generate the complete 90-chapter canonical/guided source crosswalk."""

from __future__ import annotations

import json
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
AUDIT = ROOT / "tmp" / "pdfs" / "source-audit"


def load(name: str):
    return json.loads((AUDIT / name).read_text(encoding="utf-8"))


def chapter_number(title: str) -> int | None:
    match = re.search(r"\bChapter (\d+)\b", title)
    return int(match.group(1)) if match else None


canonical_outline = load("complete_course_outline.json")
guided_outline = load("tutor_led_outline.json")
canonical_entries = [
    item
    for item in canonical_outline
    if item["depth"] == 1 and re.match(r"Chapter \d+ -", item["title"])
]


def next_structural_page(entry: dict) -> int:
    position = canonical_outline.index(entry)
    for candidate in canonical_outline[position + 1 :]:
        if candidate["depth"] <= 1 and candidate["physical_page"] > entry["physical_page"]:
            return candidate["physical_page"]
    return 329

chapters = []
current_part = ""
index = 0
for item in canonical_outline:
    if item["depth"] == 0 and "Part " in item["title"]:
        current_part = re.sub(r"^[IVX]+\s+", "", item["title"])
    if item in canonical_entries:
        number = chapter_number(item["title"])
        chapters.append(
            {
                "number": number,
                "title": re.sub(r"^Chapter \d+ -\s*", "", item["title"]),
                "part": current_part,
                "start": item["physical_page"],
                "end": next_structural_page(item) - 1,
            }
        )
        index += 1

guided = {}
top_indices = [i for i, item in enumerate(guided_outline) if item["depth"] == 0]
for position, start_index in enumerate(top_indices):
    item = guided_outline[start_index]
    number = chapter_number(item["title"])
    if number is None:
        continue
    end_index = top_indices[position + 1] if position + 1 < len(top_indices) else len(guided_outline)
    children = guided_outline[start_index + 1 : end_index]
    original = next(child for child in children if child["title"] == f"Original Chapter {number}")
    mastery = next(child for child in children if child["title"] == f"Chapter {number} Mastery Check")
    guided[number] = {
        "schedule": re.split(r"\s+-\s+Chapter \d+", item["title"], maxsplit=1)[0],
        "guide": item["physical_page"],
        "original": original["physical_page"],
        "mastery": mastery["physical_page"],
    }

if len(chapters) != 90 or len(guided) != 90:
    raise RuntimeError(f"Unexpected counts: canonical={len(chapters)}, guided={len(guided)}")

rows = []
for chapter in chapters:
    unit = guided[chapter["number"]]
    canonical_pages = (
        str(chapter["start"])
        if chapter["start"] == chapter["end"]
        else f"{chapter['start']}-{chapter['end']}"
    )
    rows.append(
        f"| {chapter['number']} | {chapter['part'].replace('Part ', '')} | "
        f"{unit['schedule']} | {chapter['title']} | {canonical_pages} | "
        f"{unit['guide']} / {unit['original']} / {unit['mastery']} |"
    )

header = """# Curriculum Map

## Mapping rule

The Complete Course chapter is the canonical lesson content. For every chapter, the Tutor-Led guide and mastery check are additional instructional blocks linked to that canonical lesson. The guided edition's Original Chapter pages are provenance aliases to matching Complete Course pages, not separate editable curriculum copies.

Page references use physical PDF pages because they remain stable when printed logical page numbers or automatic chapter counters are misleading.

## Progression model

| Stage | Canonical source | Main evidence/gate |
| --- | --- | --- |
| Orientation and market mechanics | Part I, Chapters 1-8 | Gate A; platform competence; 85% overall with critical risk/direction items corrected |
| Chart and technical literacy | Part II, Chapters 9-17 | 100 annotated charts; Gate B |
| Fundamental and macro literacy | Part III, Chapters 18-33 | 20 event studies; Gate C |
| Risk and probability | Part IV, Chapters 34-42 | Position-sizing accuracy; Gate D |
| Strategy and evidence | Part V, Chapters 43-58 | Frozen Strategy A; historical/OOS/robustness evidence; Gate E |
| Psychology and process | Part VI, Chapters 59-65 | Journal evidence; 30-trade integrity challenge; Gate F |
| Serious demo and micro-live progression | Part VII, Chapters 66-75 | At least 100 legitimate demo trades, at least 95% adherence, Gate G and broker due diligence |
| Advanced FX and graduation | Part VIII, Chapters 76-90 | Advanced literacy, professional plan, Chapter 90 evidence gate and final comprehensive exam |
| Reference and assessment support | Parts IX-X, Appendices A-Z | Calendar, readings, resources, formulas, policies, worksheets, glossary, answers and rubrics |

## Complete chapter/session crosswalk

Guided pages are shown as guide / embedded original start / mastery.

| Ch. | Canonical part | Guided schedule | Canonical title | Canonical physical pages | Guided pages |
| ---: | --- | --- | --- | ---: | --- |
"""

vertical_slice = """

## First vertical slice

Week 1, Session 1 maps to canonical Chapter 1 and guided Chapter 1:

1. Session rule: understand the product before seeking a setup.
2. Explain relative exchange rates, exchange versus speculation, principal FX instruments and the retail platform's limited role.
3. Set up the Concept Notebook and answer three prior-knowledge prompts.
4. Work through EUR/USD = 1.1700, relative strength, economic need versus speculation, hedging versus deliberately accepted risk, and market versus platform.
5. Complete all canonical sections, five exercises, the six-question quiz and linked resources.
6. Complete closed-book teach-back, notebook verification, the at-least-85% progression standard and retention sentence.

The implementation must preserve this source chain while splitting it into small interactive blocks and retaining bidirectional page provenance.
"""

output = ROOT / "docs" / "CURRICULUM_MAP.md"
output.parent.mkdir(parents=True, exist_ok=True)
output.write_text(header + "\n".join(rows) + vertical_slice, encoding="utf-8", newline="\n")
print(output)
