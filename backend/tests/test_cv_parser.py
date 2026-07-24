from app.services import cv_parser


def test_extract_text_from_pdf_can_preserve_page_layout(monkeypatch):
    calls: list[bool] = []

    class FakePage:
        def extract_text(self, *, layout=False):
            calls.append(layout)
            return "Contact                  Experience\nmail@example.com         Built products"

    class FakePdf:
        pages = [FakePage()]

        def __enter__(self):
            return self

        def __exit__(self, *_args):
            return None

    monkeypatch.setattr(cv_parser.pdfplumber, "open", lambda _stream: FakePdf())

    text = cv_parser.extract_text_from_pdf(b"%PDF", anonymize=False, layout=True)

    assert calls == [True]
    assert "Contact                  Experience" in text
