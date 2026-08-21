import hashlib
import json
import re
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
AUDIT = ROOT / "tmp" / "pdfs" / "source-audit"
SOURCE_DIR = Path(r"C:\Users\Administrator\Downloads")


def load(name: str):
    return json.loads((AUDIT / name).read_text(encoding="utf-8"))


class SourceAuditTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.manifest = load("manifest.json")
        cls.complete_outline = load("complete_course_outline.json")
        cls.guided_outline = load("tutor_led_outline.json")

    def test_source_files_still_match_audited_hashes(self):
        for document in self.manifest["documents"]:
            path = SOURCE_DIR / document["filename"]
            self.assertTrue(path.is_file(), path)
            digest = hashlib.sha256(path.read_bytes()).hexdigest()
            self.assertEqual(document["file_sha256"], digest)

    def test_all_pages_have_extractable_text(self):
        self.assertEqual(928, self.manifest["totals"]["physical_pages"])
        self.assertEqual(0, self.manifest["totals"]["empty_text_pages"])
        for document in self.manifest["documents"]:
            self.assertEqual([], document["empty_text_pages"])

    def test_guided_edition_contains_every_canonical_page_once(self):
        relation = self.manifest["cross_document_relationship"]
        self.assertEqual(328, relation["canonical_pages"])
        self.assertEqual(328, relation["exact_one_to_one_matches"])
        self.assertEqual([], relation["unmatched_or_ambiguous_canonical_pages"])
        self.assertEqual(272, relation["guided_additional_pages"])

    def test_canonical_structure_is_complete(self):
        chapter_numbers = {
            int(match.group(1))
            for item in self.complete_outline
            for match in [re.match(r"Chapter (\d+) -", item["title"])]
            if item["depth"] == 1 and match
        }
        appendices = [
            item
            for item in self.complete_outline
            if item["depth"] == 1 and re.match(r"Appendix [A-Z] -", item["title"])
        ]
        self.assertEqual(set(range(1, 91)), chapter_numbers)
        self.assertEqual(26, len(appendices))

    def test_guided_structure_is_complete(self):
        guided_units = [
            item
            for item in self.guided_outline
            if item["depth"] == 0 and re.search(r"\bChapter \d+\b", item["title"])
        ]
        originals = [item for item in self.guided_outline if re.fullmatch(r"Original Chapter \d+", item["title"])]
        mastery = [item for item in self.guided_outline if re.fullmatch(r"Chapter \d+ Mastery Check", item["title"])]
        self.assertEqual(90, len(guided_units))
        self.assertEqual(90, len(originals))
        self.assertEqual(90, len(mastery))

    def test_external_link_inventory(self):
        for document in self.manifest["documents"]:
            self.assertEqual(227, document["external_link_annotations"])
            self.assertEqual(63, document["unique_external_links"])

    def test_generated_curriculum_map_has_all_chapters_and_clean_part_boundaries(self):
        text = (ROOT / "docs" / "CURRICULUM_MAP.md").read_text(encoding="utf-8")
        rows = [
            line
            for line in text.splitlines()
            if line.startswith("| ") and line.split("|")[1].strip().isdigit()
        ]
        self.assertEqual(90, len(rows))
        self.assertIn("| 8 | I - Orientation and Market Mechanics |", rows[7])
        self.assertIn("| 55-57 |", rows[7])
        self.assertIn("| 17 | II - Reading Price and Technical Analysis |", rows[16])
        self.assertIn("| 80-82 |", rows[16])
        self.assertIn("| 90 | VIII - Advanced Foreign-Exchange Literacy |", rows[89])
        self.assertIn("| 250-252 |", rows[89])


if __name__ == "__main__":
    unittest.main()
