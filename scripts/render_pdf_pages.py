#!/usr/bin/env python3
"""Render selected one-based PDF pages to PNG for visual source QA."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

workspace_tools = Path(__file__).resolve().parents[1] / ".tools"
if workspace_tools.is_dir():
    sys.path.insert(0, str(workspace_tools))

import fitz  # type: ignore


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf", type=Path)
    parser.add_argument("--pages", required=True, help="Comma-separated, one-based page numbers")
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--dpi", type=int, default=144)
    args = parser.parse_args()

    pages = sorted({int(value) for value in args.pages.split(",") if value.strip()})
    args.output_dir.mkdir(parents=True, exist_ok=True)
    document = fitz.open(args.pdf)
    matrix = fitz.Matrix(args.dpi / 72, args.dpi / 72)
    for page_number in pages:
        if not 1 <= page_number <= document.page_count:
            raise ValueError(f"Page {page_number} outside 1..{document.page_count}")
        page = document.load_page(page_number - 1)
        output = args.output_dir / f"{args.pdf.stem}-page-{page_number:03d}.png"
        page.get_pixmap(matrix=matrix, alpha=False).save(output)
        print(output)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
