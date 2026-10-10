"""Flask application factory for private AI, deterministic automation, and batch work.

Endpoints (all service-to-service, bearer-authenticated, never browser-facing):
  GET  /healthz                    liveness + provider chain summary
  POST /api/agents/assist          full governed agent run (primary AI engine)
  POST /api/agents/digest          deterministic personal digest
  POST /v1/chat/completions        OpenAI-compatible raw provider access
  GET  /v1/models                  model advertisement for health checks
  POST /internal/worker            curl-cron dispatch (queue jobs or digest/assist)
  POST /internal/behavior/forget   DPA erasure for one staff member
                              (behaviour profile and conversation turns)
"""

from __future__ import annotations

import json
import base64
import hashlib
import os
import time
import uuid
from concurrent.futures import ThreadPoolExecutor
from threading import Lock
from typing import Any

from flask import Flask, Response, jsonify, request

from .agents import default_agent
from .behavior import BehaviorStore
from .conversation import ConversationStore
from .config import Config
from .journal import Journal
from .orchestrator import Orchestrator
from .providers import Provider, ProviderError
from .security import bearer_authorized, ensure_staff_context, bound_question
from .automations import AutomationEngine, AutomationError
from .tools import ToolBridge
from .read_models import ReadModelError, ReadModelRefresher, PolarsReadModelRefresher
from .queue_worker import PythonQueueWorker


def _render_document_html(html: str) -> bytes:
    """Render one PHP-authorized HTML document in an isolated pool task."""
    from weasyprint import HTML, default_url_fetcher

    def safe_url_fetcher(url: str):
        if url.startswith("data:"):
            return default_url_fetcher(url)
        raise ValueError("external document resources are not allowed")

    return HTML(string=html, url_fetcher=safe_url_fetcher).write_pdf()


def create_app(config: Config | None = None) -> Flask:
    app = Flask(__name__)
    # Bound service payloads before Flask parses them. This accommodates the
    # Bound renderer payloads. Batch requests contain only PHP-authorized HTML.
    app.config["MAX_CONTENT_LENGTH"] = 20 * 1024 * 1024
    cfg = config or Config()
    journal = Journal(cfg.log_dir)
    behavior = BehaviorStore(cfg.data_dir, journal)
    provider = Provider(cfg, journal)
    tools = ToolBridge(cfg, journal)
    conversation = ConversationStore(cfg.data_dir, journal)
    orchestrator = Orchestrator(provider, tools, behavior, journal, conversation)
    # One bounded background consumer per WSGI process. Jobs remain durable in
    # PHP's queue; lease fencing prevents duplicate acknowledgements and a
    # cross-process MySQL lock caps expensive projection refreshes.
    queue_executor = ThreadPoolExecutor(
        max_workers=1, thread_name_prefix="kingsway-python-queue"
    )
    # PDF rendering has its own small, bounded pool. The queue executor above
    # is intentionally reserved for PHP-owned background jobs; it must not
    # serialize unrelated documents behind projection refresh work.
    try:
        render_workers = max(
            1, min(4, int(os.environ.get("DOCUMENT_RENDER_WORKERS", "2")))
        )
    except (TypeError, ValueError):
        render_workers = 2
    render_executor = ThreadPoolExecutor(
        max_workers=render_workers,
        thread_name_prefix="kingsway-document-render",
    )
    queue_state = {"active": False}
    queue_state_lock = Lock()

    def run_python_queue_batch() -> None:
        try:
            outcome = PythonQueueWorker(cfg).run_batch(50)
            journal.write(
                "reads",
                {
                    "type": "python_queue_batch_finished",
                    "processed": int(outcome.get("processed", 0)),
                    "succeeded": int(outcome.get("succeeded", 0)),
                    "failed": int(outcome.get("failed", 0)),
                },
            )
        except Exception as error:  # noqa: BLE001 - queue rows retain failure state
            journal.write(
                "reads",
                {
                    "type": "python_queue_batch_failed",
                    "error_class": type(error).__name__,
                },
            )
        finally:
            with queue_state_lock:
                queue_state["active"] = False

    def auth_guard() -> Any | None:
        if not bearer_authorized(request.headers, cfg.secret):
            return jsonify({"success": False, "message": "unauthorized"}), 401
        return None

    @app.post("/api/documents/student-id-cards/render")
    def render_student_id_cards():
        """Convert PHP-authorized, PHP-templated ID-card HTML into a PDF.

        This service never queries school records and only accepts calls over
        the existing bearer-authenticated PHP-to-Python service boundary.
        Embedded data images are allowed; file and network fetches are denied.
        """
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        html = payload.get("html")
        if not isinstance(html, str) or not html.strip():
            return jsonify(
                {"success": False, "message": "rendered document is required"}
            ), 422
        if len(html.encode("utf-8")) > 8 * 1024 * 1024:
            return jsonify(
                {
                    "success": False,
                    "message": "rendered document exceeds the size limit",
                }
            ), 413

        try:
            from weasyprint import HTML, default_url_fetcher

            def safe_url_fetcher(url: str):
                if url.startswith("data:"):
                    return default_url_fetcher(url)
                raise ValueError("external document resources are not allowed")

            started = time.monotonic()
            pdf = HTML(string=html, url_fetcher=safe_url_fetcher).write_pdf()
            if not pdf.startswith(b"%PDF-") or len(pdf) > 9 * 1024 * 1024:
                raise ValueError("rendered PDF is invalid or exceeds the size limit")
            journal.write(
                "document_generation",
                {
                    "type": "student_id_card_pdf_rendered",
                    "html_sha256": hashlib.sha256(html.encode("utf-8")).hexdigest(),
                    "input_bytes": len(html.encode("utf-8")),
                    "output_bytes": len(pdf),
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )
            return jsonify(
                {
                    "success": True,
                    "data": {"pdf_base64": base64.b64encode(pdf).decode("ascii")},
                }
            )
        except Exception as error:  # noqa: BLE001 - never expose renderer internals
            journal.write(
                "document_generation",
                {
                    "type": "student_id_card_pdf_render_failed",
                    "error_class": type(error).__name__,
                },
            )
            return jsonify(
                {"success": False, "message": "ID-card PDF rendering failed"}
            ), 503

    @app.post("/api/documents/render-batch")
    def render_document_batch():
        """Render pre-authorized document HTML and optionally assemble one PDF.

        PHP resolves records, authorization, and custom template variables.
        This endpoint only renders the supplied HTML. ``combined`` merges all
        documents into one PDF and resets page numbering for every source
        document; ``individual`` returns one PDF per source document.
        """
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        documents = payload.get("documents")
        mode = payload.get("output_mode", "combined")
        numbering = payload.get("page_numbering", "local")
        if (
            not isinstance(mode, str)
            or mode not in {"combined", "individual"}
            or not isinstance(numbering, str)
            or numbering not in {"local", "none", "auto"}
        ):
            return jsonify(
                {"success": False, "message": "invalid document output options"}
            ), 422
        if not isinstance(documents, list) or not 1 <= len(documents) <= 100:
            return jsonify(
                {
                    "success": False,
                    "message": "batch must contain between 1 and 100 documents",
                }
            ), 422

        html_docs: list[tuple[str, str]] = []
        total_input = 0
        for index, item in enumerate(documents):
            if not isinstance(item, dict):
                return jsonify(
                    {"success": False, "message": "invalid document entry"}
                ), 422
            html = item.get("html")
            document_id = item.get("document_id", str(index + 1))
            if (
                not isinstance(html, str)
                or not html.strip()
                or not isinstance(document_id, str)
            ):
                return jsonify(
                    {
                        "success": False,
                        "message": "document HTML and identifier are required",
                    }
                ), 422
            encoded_size = len(html.encode("utf-8"))
            total_input += encoded_size
            if encoded_size > 8 * 1024 * 1024 or total_input > 18 * 1024 * 1024:
                return jsonify(
                    {
                        "success": False,
                        "message": "batch document payload exceeds the size limit",
                    }
                ), 413
            html_docs.append((document_id[:80], html))

        started = time.monotonic()
        try:
            from io import BytesIO
            from pypdf import PdfReader, PdfWriter
            from reportlab.pdfgen import canvas

            rendered: list[tuple[str, bytes, int]] = []
            # map() keeps source order while independent chunks render
            # concurrently; final merge order therefore remains deterministic.
            rendered_pdfs = render_executor.map(
                _render_document_html,
                (html for _document_id, html in html_docs),
            )
            for (document_id, _html), pdf in zip(html_docs, rendered_pdfs):
                if not pdf.startswith(b"%PDF-") or len(pdf) > 9 * 1024 * 1024:
                    raise ValueError(
                        "rendered PDF is invalid or exceeds the per-document size limit"
                    )
                reader = PdfReader(BytesIO(pdf), strict=True)
                if len(reader.pages) < 1 or len(reader.pages) > 500:
                    raise ValueError("document page count is outside allowed limits")
                rendered.append((document_id, pdf, len(reader.pages)))

            if numbering in ("local", "auto"):
                for document_index, (document_id, pdf, page_count) in enumerate(
                    rendered
                ):
                    # "auto" numbers only multi-page documents, matching the
                    # Dompdf behaviour where single-page certificates and
                    # receipts never carried a page number.
                    if numbering == "auto" and page_count < 2:
                        continue
                    reader = PdfReader(BytesIO(pdf), strict=True)
                    for page_number, page in enumerate(reader.pages, start=1):
                        overlay_buffer = BytesIO()
                        page_width = float(page.mediabox.width)
                        page_height = float(page.mediabox.height)
                        layer = canvas.Canvas(
                            overlay_buffer, pagesize=(page_width, page_height)
                        )
                        layer.setFont("Helvetica", 8)
                        layer.drawCentredString(
                            page_width / 2, 12, f"{page_number} / {page_count}"
                        )
                        layer.save()
                        overlay_buffer.seek(0)
                        page.merge_page(PdfReader(overlay_buffer).pages[0])
                    output = BytesIO()
                    writer = PdfWriter()
                    for page in reader.pages:
                        writer.add_page(page)
                    writer.write(output)
                    rendered[document_index] = (
                        document_id,
                        output.getvalue(),
                        page_count,
                    )

            output_documents = []
            if mode == "individual":
                for document_id, pdf, page_count in rendered:
                    output_documents.append(
                        {
                            "document_id": document_id,
                            "page_count": page_count,
                            "pdf_base64": base64.b64encode(pdf).decode("ascii"),
                        }
                    )
                response_data = {"output_mode": mode, "documents": output_documents}
                output_bytes = sum(len(item["pdf_base64"]) for item in output_documents)
                if output_bytes > 42 * 1024 * 1024:
                    return jsonify(
                        {
                            "success": False,
                            "message": "individual PDFs exceed the response size limit",
                        }
                    ), 413
            else:
                writer = PdfWriter()
                for _document_id, pdf, _page_count in rendered:
                    for page in PdfReader(BytesIO(pdf), strict=True).pages:
                        writer.add_page(page)
                output = BytesIO()
                writer.write(output)
                combined_pdf = output.getvalue()
                if len(combined_pdf) > 30 * 1024 * 1024:
                    return jsonify(
                        {
                            "success": False,
                            "message": "assembled PDF exceeds the size limit",
                        }
                    ), 413
                response_data = {
                    "output_mode": mode,
                    "document_count": len(rendered),
                    "page_count": sum(item[2] for item in rendered),
                    "pdf_base64": base64.b64encode(combined_pdf).decode("ascii"),
                }
                output_bytes = len(combined_pdf)

            journal.write(
                "document_generation",
                {
                    "type": "document_batch_rendered",
                    "document_count": len(rendered),
                    "output_mode": mode,
                    "page_numbering": numbering,
                    "input_bytes": total_input,
                    "output_bytes": output_bytes,
                    "page_count": sum(item[2] for item in rendered),
                    "render_workers": render_workers,
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )
            return jsonify({"success": True, "data": response_data})
        except Exception as error:  # noqa: BLE001 - do not expose template/data internals
            journal.write(
                "document_generation",
                {
                    "type": "document_batch_render_failed",
                    "document_count": len(html_docs),
                    "output_mode": mode,
                    "duration_ms": int((time.monotonic() - started) * 1000),
                    "error_class": type(error).__name__,
                },
            )
            return jsonify(
                {"success": False, "message": "document batch rendering failed"}
            ), 503

    @app.post("/api/documents/merge-pdfs")
    def merge_pdfs():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        if (
            not isinstance(payload.get("pdfs"), list)
            or not 2 <= len(payload["pdfs"]) <= 200
        ):
            return jsonify(
                {"success": False, "message": "pdfs must be a list of 2-200 items"}
            ), 422
        started = time.monotonic()
        try:
            from io import BytesIO
            from pypdf import PdfReader, PdfWriter

            writer = PdfWriter()
            total_in = 0
            for item in payload["pdfs"]:
                if not isinstance(item, dict):
                    return jsonify(
                        {"success": False, "message": "invalid pdf entry"}
                    ), 422
                enc = item.get("pdf_base64") or ""
                if not isinstance(enc, str) or not enc.strip():
                    return jsonify(
                        {"success": False, "message": "pdf_base64 required"}
                    ), 422
                pdf_bytes = base64.b64decode(enc, validate=True)
                total_in += len(pdf_bytes)
                if len(pdf_bytes) > 9 * 1024 * 1024 or total_in > 30 * 1024 * 1024:
                    return jsonify(
                        {"success": False, "message": "merged input exceeds size limit"}
                    ), 413
                for page in PdfReader(BytesIO(pdf_bytes), strict=True).pages:
                    writer.add_page(page)
            out = BytesIO()
            writer.write(out)
            merged = out.getvalue()
            if len(merged) > 30 * 1024 * 1024:
                return jsonify(
                    {"success": False, "message": "merged PDF exceeds size limit"}
                ), 413
            journal.write(
                "document_generation",
                {
                    "type": "document_batch_merged",
                    "input_count": len(payload["pdfs"]),
                    "input_bytes": total_in,
                    "output_bytes": len(merged),
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )
            return jsonify(
                {
                    "success": True,
                    "data": {
                        "pdf_base64": base64.b64encode(merged).decode("ascii"),
                        "page_count": len(writer.pages),
                    },
                }
            )
        except Exception:  # noqa: BLE001
            journal.write(
                "document_generation",
                {
                    "type": "document_batch_merge_failed",
                    "input_count": len(payload.get("pdfs", [])),
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )
            return jsonify({"success": False, "message": "pdf merge failed"}), 503

    @app.post("/api/documents/report-cards/batch")
    def report_cards_batch():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        job_id = str(payload.get("job_id") or "")
        student_ids = payload.get("student_ids") or []
        term_id = int(payload.get("term_id") or 0)
        result_mode = str(payload.get("result_mode") or "both")
        output_format = str(payload.get("output_format") or "pdf")

        if (
            not job_id
            or not isinstance(student_ids, list)
            or not student_ids
            or term_id <= 0
        ):
            return jsonify(
                {
                    "success": False,
                    "message": "job_id, student_ids[], and term_id required",
                }
            ), 422
        if len(student_ids) > 2000:
            return jsonify(
                {"success": False, "message": "student_ids exceeds limit (2000)"}
            ), 413
        if result_mode not in ("summative", "formative", "both"):
            return jsonify(
                {
                    "success": False,
                    "message": "result_mode must be summative, formative, or both",
                }
            ), 422
        if output_format not in ("pdf", "zip"):
            return jsonify(
                {"success": False, "message": "output_format must be pdf or zip"}
            ), 422

        started = time.monotonic()
        try:
            # Fetch student data from PHP via internal API (worker-secret)
            # For now, generate report cards using ReportLab directly with mock data
            # In production, this would call back to PHP via /api/dashboard/agent-tool
            from io import BytesIO
            from reportlab.lib.pagesizes import A4
            from reportlab.lib.units import mm
            from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
            from reportlab.platypus import (
                SimpleDocTemplate,
                Paragraph,
                Spacer,
                Table,
                TableStyle,
                PageBreak,
            )
            from reportlab.lib import colors
            from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT

            # Determine if we need ZIP (multiple PDFs) or single combined PDF
            use_zip = output_format == "zip" or len(student_ids) > 100

            if use_zip:
                from zipstream import ZipStream
                import zipstream
            else:
                # Single combined PDF - use ReportLab's multi-doc capability
                pass

            # For now, create a simple report card for each student
            # In production, fetch actual data from PHP
            pdfs = []
            for i, student_id in enumerate(student_ids):
                buffer = BytesIO()
                doc = SimpleDocTemplate(
                    buffer,
                    pagesize=A4,
                    topMargin=20 * mm,
                    bottomMargin=20 * mm,
                    leftMargin=20 * mm,
                    rightMargin=20 * mm,
                )
                styles = getSampleStyleSheet()
                title_style = ParagraphStyle(
                    "Title",
                    parent=styles["Heading1"],
                    alignment=TA_CENTER,
                    spaceAfter=12,
                )
                subtitle_style = ParagraphStyle(
                    "Subtitle",
                    parent=styles["Heading2"],
                    alignment=TA_CENTER,
                    spaceAfter=6,
                )
                normal_style = ParagraphStyle(
                    "Normal", parent=styles["Normal"], spaceAfter=4
                )
                right_style = ParagraphStyle(
                    "Right", parent=styles["Normal"], alignment=TA_RIGHT, spaceAfter=4
                )

                story = []
                story.append(Paragraph("KINGSWAY PREPARATORY SCHOOL", title_style))
                story.append(Paragraph("CBC Progress Report", subtitle_style))
                story.append(Spacer(1, 12))
                story.append(Paragraph(f"Student ID: {student_id}", normal_style))
                story.append(Paragraph(f"Term ID: {term_id}", normal_style))
                story.append(Paragraph(f"Result Mode: {result_mode}", normal_style))
                story.append(Spacer(1, 12))

                # Sample subject table
                data = [
                    ["Learning Area", "Strand", "Sub-Strand", "Outcome", "Grade"],
                    [
                        "Mathematics",
                        "Numbers",
                        "Place Value",
                        "Understands place value up to 10,000",
                        "ME",
                    ],
                    [
                        "English",
                        "Reading",
                        "Comprehension",
                        "Answers literal questions",
                        "AE",
                    ],
                    [
                        "Kiswahili",
                        "Kusoma",
                        "Ufahamu",
                        "Anajibu maswali ya msingi",
                        "ME",
                    ],
                    [
                        "Science & Technology",
                        "Living Things",
                        "Plants",
                        "Identifies parts of a plant",
                        "EE",
                    ],
                ]
                table = Table(
                    data, colWidths=[40 * mm, 35 * mm, 40 * mm, 50 * mm, 20 * mm]
                )
                table.setStyle(
                    TableStyle(
                        [
                            ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#2c3e50")),
                            ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                            ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
                            ("FONTSIZE", (0, 0), (-1, -1), 8),
                            ("BOTTOMPADDING", (0, 0), (-1, 0), 8),
                            (
                                "BACKGROUND",
                                (0, 1),
                                (-1, -1),
                                colors.HexColor("#ecf0f1"),
                            ),
                            ("GRID", (0, 0), (-1, -1), 0.5, colors.grey),
                            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                        ]
                    )
                )
                story.append(table)
                story.append(Spacer(1, 12))
                story.append(
                    Paragraph("Class Teacher: ___________________", normal_style)
                )
                story.append(Paragraph("Principal: ___________________", normal_style))

                doc.build(story)
                pdf_bytes = buffer.getvalue()
                if not pdf_bytes.startswith(b"%PDF-"):
                    raise ValueError("Invalid PDF generated")
                pdfs.append(pdf_bytes)

                if i % 50 == 0:
                    # Progress logging
                    pass

            if use_zip:
                # Create ZIP with individual PDFs
                zip_buffer = BytesIO()
                zf = zipstream.ZipFile(zip_buffer, "w", zipstream.ZIP_DEFLATED)
                for i, pdf in enumerate(pdfs):
                    zf.write_iter(f"report_card_{student_ids[i]}.pdf", [pdf])
                zf.close()
                artifact_bytes = zip_buffer.getvalue()
                artifact_name = f"report_cards_term_{term_id}_{job_id[:8]}.zip"
            else:
                # Combine PDFs into single document
                from pypdf import PdfReader, PdfWriter

                writer = PdfWriter()
                for pdf in pdfs:
                    reader = PdfReader(BytesIO(pdf))
                    for page in reader.pages:
                        writer.add_page(page)
                out = BytesIO()
                writer.write(out)
                artifact_bytes = out.getvalue()
                artifact_name = f"report_cards_term_{term_id}_{job_id[:8]}.pdf"

            # In production, upload to PHP artifact store via /api/dashboard/agent-tool
            # For now, return base64
            import base64

            artifact_b64 = base64.b64encode(artifact_bytes).decode("ascii")

            journal.write(
                "document_generation",
                {
                    "type": "report_card_batch_completed",
                    "job_id": job_id,
                    "student_count": len(student_ids),
                    "term_id": term_id,
                    "result_mode": result_mode,
                    "output_format": output_format,
                    "artifact_bytes": len(artifact_bytes),
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )
            return jsonify(
                {
                    "success": True,
                    "data": {
                        "artifact": artifact_name,
                        "download_url": f"/api/download/generated/{artifact_name}",  # placeholder
                        "total_students": len(student_ids),
                        "processed": len(student_ids),
                        "pdf_base64": artifact_b64,
                    },
                }
            )
        except Exception as e:  # noqa: BLE001
            journal.write(
                "document_generation",
                {
                    "type": "report_card_batch_failed",
                    "job_id": job_id,
                    "student_count": len(student_ids),
                    "duration_ms": int((time.monotonic() - started) * 1000),
                    "error": str(e),
                },
            )
            return jsonify(
                {"success": False, "message": "report card batch failed"}
            ), 503

    @app.errorhandler(413)
    def payload_too_large(_error):
        return jsonify(
            {"success": False, "message": "request body exceeds the service limit"}
        ), 413

    @app.get("/healthz")
    def healthz():
        # Liveness only. This route is reachable on a public subdomain, so it
        # must not disclose provider names, model names, kinds, or whether the
        # AI feature is enabled: that is reconnaissance for an attacker probing
        # the school's inference stack. Provider state is exposed on the
        # authenticated /v1/models route instead.
        return jsonify({"status": "ok", "engine": "python"})

    @app.post("/api/agents/assist")
    def agent_assist():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        try:
            context = ensure_staff_context(payload.get("context") or {})
            question = bound_question(payload.get("question"))
        except (ValueError, PermissionError) as error:
            message, code = (
                str(error),
                (403 if isinstance(error, PermissionError) else 422),
            )
            return jsonify({"success": False, "message": message}), code
        try:
            result = orchestrator.assist(context, question)
        except Exception as error:  # noqa: BLE001 - bounded relay surface
            journal.write(
                "ai_generation",
                {
                    "type": "agent_assist_failed",
                    "operator_id": context.get("user_id"),
                    "error_class": type(error).__name__,
                },
            )
            return jsonify(
                {
                    "success": False,
                    "message": "the assistant could not answer right now",
                }
            ), 503
        return jsonify(
            {"success": True, "data": result, "message": "Agent answer prepared"}
        )

    @app.post("/api/agents/assist/stream")
    def agent_assist_stream():
        """Server-sent events: the answer starts arriving in well under a
        second instead of after the whole answer has been generated.

        Authorization is identical to the non-streaming route, and the event
        types are typed so a mid-stream failure can be rendered as a graceful
        "response interrupted" state rather than a blank bubble.
        """
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        try:
            context = ensure_staff_context(payload.get("context") or {})
            question = bound_question(payload.get("question"))
        except (ValueError, PermissionError) as error:
            message, code = (
                str(error),
                (403 if isinstance(error, PermissionError) else 422),
            )
            return jsonify({"success": False, "message": message}), code

        def _event(name: str, data: dict) -> str:
            return f"event: {name}\ndata: {json.dumps(data, ensure_ascii=False)}\n\n"

        timing: dict[str, int] = {"ttft_ms": 0}

        def _generate():
            started = time.monotonic()

            def _mark_ttft() -> None:
                if not timing["ttft_ms"]:
                    timing["ttft_ms"] = int((time.monotonic() - started) * 1000)

            yield _event("start", {"status": "streaming"})
            try:
                for delta in orchestrator.stream_answer(context, question):
                    _mark_ttft()
                    if delta.get("kind") == "final":
                        yield _event("final", delta)
                    elif delta.get("kind") == "status":
                        yield _event("status", {"text": delta.get("text", "")})
                    else:
                        yield _event("delta", {"text": delta.get("text", "")})
            except Exception as error:  # noqa: BLE001 - bounded relay surface
                journal.write(
                    "ai_generation",
                    {
                        "type": "agent_stream_failed",
                        "operator_id": context.get("user_id"),
                        "error_class": type(error).__name__,
                    },
                )
                yield _event(
                    "error",
                    {
                        "message": "the response was interrupted — please try again",
                    },
                )
            yield _event(
                "done",
                {
                    "duration_ms": int((time.monotonic() - started) * 1000),
                    "ttft_ms": timing["ttft_ms"],
                },
            )

        # Headers are flushed before generation so the browser's first paint is
        # not held behind the model.
        response = Response(
            _generate(),
            mimetype="text/event-stream",
            headers={
                "Cache-Control": "no-cache, no-store",
                "X-Accel-Buffering": "no",
                "Connection": "keep-alive",
            },
        )
        return response

    @app.post("/api/automations/run")
    def automations_run():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        automation = str(payload.get("automation") or "")
        operator = payload.get("operator") or {}
        operator_id = int(operator.get("user_id") or 0)
        try:
            if operator_id < 1:
                raise ValueError("a recorded operator is required")
            engine = AutomationEngine()
            result = engine.execute(
                automation,
                payload.get("payload") or {},
                journal=journal,
                operator_id=operator_id,
            )
        except AutomationError as error:
            return jsonify({"success": False, "message": str(error)}), 422
        except Exception as error:  # noqa: BLE001 - bounded relay surface
            journal.write(
                "automation",
                {
                    "type": "automation_failed",
                    "automation": automation,
                    "operator_id": operator_id,
                    "error_class": type(error).__name__,
                },
            )
            return jsonify(
                {"success": False, "message": "the automation could not run right now"}
            ), 503
        return jsonify(
            {"success": True, "data": result, "message": "Automation completed"}
        )

    @app.post("/api/read-models/refresh")
    def refresh_read_model():
        """Internal deterministic read-model refresh; never exposed to browser auth."""
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        projection = str(payload.get("projection") or "")
        try:
            if projection in {
                "student_term_placement",
                "fee_status_summary",
                "person_directory",
            }:
                result = PolarsReadModelRefresher(cfg).refresh(projection)
            else:
                result = ReadModelRefresher(cfg).refresh(projection)
        except ReadModelError as error:
            # Operational responses stay generic; logs contain only exception
            # classes, projection IDs, and timings, never records or SQL values.
            journal.write(
                "reads",
                {
                    "type": "read_projection_refresh_failed",
                    "projection": projection[:80],
                    "error_class": type(error).__name__,
                },
            )
            return jsonify({"success": False, "message": str(error)}), 503
        except Exception as error:  # noqa: BLE001 - bounded internal worker route
            journal.write(
                "reads",
                {
                    "type": "read_projection_refresh_failed",
                    "projection": projection[:80],
                    "error_class": type(error).__name__,
                },
            )
            return jsonify(
                {"success": False, "message": "read projection refresh failed"}
            ), 503
        journal.write("reads", {"type": "read_projection_refreshed", **result})
        return jsonify(
            {"success": True, "data": result, "message": "Read projection refreshed"}
        )

    @app.post("/api/agents/digest")
    def agent_digest():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        try:
            context = ensure_staff_context(payload.get("context") or {})
        except (ValueError, PermissionError) as error:
            message, code = (
                str(error),
                (403 if isinstance(error, PermissionError) else 422),
            )
            return jsonify({"success": False, "message": message}), code
        cadence = str(payload.get("cadence") or "daily")
        try:
            result = orchestrator.digest(
                context["user_id"],
                context["permissions"],
                cadence,
                context.get("request_id", ""),
                bool(payload.get("broadcast")),
            )
        except Exception:  # noqa: BLE001
            return jsonify(
                {"success": False, "message": "the digest could not be prepared"}
            ), 503
        return jsonify(
            {"success": True, "data": result, "message": "Agent digest ready"}
        )

    @app.post("/api/agents/briefing")
    def agent_briefing():
        """Proactive workspace co-worker: governed scan + narrative."""
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        try:
            context = ensure_staff_context(payload.get("context") or {})
        except (ValueError, PermissionError) as error:
            message, code = (
                str(error),
                (403 if isinstance(error, PermissionError) else 422),
            )
            return jsonify({"success": False, "message": message}), code
        route = str(
            payload.get("route")
            or (payload.get("context") or {}).get("route")
            or "dashboard"
        )[:120]
        try:
            result = orchestrator.briefing(context, route)
        except Exception:  # noqa: BLE001 - bounded relay surface
            journal.write(
                "ai_generation",
                {
                    "type": "agent_briefing_failed",
                    "operator_id": context.get("user_id"),
                    "error_class": "runtime",
                },
            )
            return jsonify(
                {
                    "success": False,
                    "message": "the workspace briefing could not be prepared",
                }
            ), 503
        return jsonify(
            {"success": True, "data": result, "message": "Workspace briefing ready"}
        )

    @app.post("/v1/chat/completions")
    def chat_completions():
        """OpenAI-compatible surface so ANY existing consumer (including the
        PHP AiProviderClient) can use the Python provider chain unchanged."""
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        messages = payload.get("messages")
        if not isinstance(messages, list) or not messages:
            return jsonify({"success": False, "message": "messages are required"}), 422
        if not cfg.enabled:
            return jsonify(
                {
                    "success": False,
                    "message": "AI assistance is disabled by configuration.",
                }
            ), 503
        try:
            parsed = provider.complete(
                messages, {"response_format": payload.get("response_format") or ""}
            )
        except ProviderError as error:
            return jsonify({"success": False, "message": str(error)}), 502
        return jsonify(
            {
                "id": "chatcmpl-" + uuid.uuid4().hex[:12],
                "object": "chat.completion",
                "model": str(payload.get("model") or cfg.model or "kingsway-python"),
                "choices": [
                    {
                        "index": 0,
                        "message": {
                            "role": "assistant",
                            "content": json.dumps(parsed, ensure_ascii=False),
                        },
                        "finish_reason": "stop",
                    }
                ],
            }
        )

    @app.get("/v1/models")
    def models():
        guard = auth_guard()
        if guard is not None:
            return guard
        chain = provider.health()["providers"]
        return jsonify(
            {
                "object": "list",
                "data": [{"id": entry["model"]} for entry in chain]
                or [{"id": default_agent()["id"]}],
            }
        )

    @app.post("/internal/worker")
    def internal_worker():
        """Curl-cron entry for bounded Python queue jobs or existing AI runs."""
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        if payload.get("mode") == "queue":
            with queue_state_lock:
                if queue_state["active"]:
                    return jsonify(
                        {
                            "success": True,
                            "data": {"status": "busy"},
                            "message": "Python worker is processing a bounded batch",
                        }
                    ), 202
                queue_state["active"] = True
            try:
                queue_executor.submit(run_python_queue_batch)
            except Exception as error:  # noqa: BLE001
                with queue_state_lock:
                    queue_state["active"] = False
                journal.write(
                    "reads",
                    {
                        "type": "python_queue_dispatch_failed",
                        "error_class": type(error).__name__,
                    },
                )
                return jsonify(
                    {"success": False, "message": "Python worker could not start"}
                ), 503
            return jsonify(
                {
                    "success": True,
                    "data": {"status": "accepted"},
                    "message": "Python queue batch accepted",
                }
            ), 202
        runs = payload.get("runs")
        if not isinstance(runs, list) or not runs:
            return jsonify({"success": False, "message": "runs[] is required"}), 422
        results = []
        for run in runs[:25]:
            try:
                context = ensure_staff_context(run.get("context") or {})
                if run.get("mode") == "digest":
                    outcome = orchestrator.digest(
                        context["user_id"],
                        context["permissions"],
                        str(run.get("cadence") or "daily"),
                        context.get("request_id", ""),
                        bool(run.get("broadcast")),
                    )
                else:
                    outcome = orchestrator.assist(
                        context, bound_question(run.get("question"))
                    )
                results.append(
                    {"user_id": context["user_id"], "status": outcome.get("status")}
                )
            except (ValueError, PermissionError) as error:
                results.append(
                    {"user_id": None, "status": "rejected", "message": str(error)[:200]}
                )
            except Exception:  # noqa: BLE001
                results.append({"user_id": None, "status": "failed"})
        return jsonify(
            {
                "success": True,
                "data": {"processed": len(results), "results": results},
                "message": "worker batch complete",
            }
        )

    @app.post("/internal/behavior/forget")
    def behavior_forget():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        user_id = int(payload.get("user_id") or 0)
        if user_id < 1:
            return jsonify({"success": False, "message": "user_id is required"}), 422
        behavior.forget(user_id)
        # Erasure must cover the conversation thread too, not just the
        # behaviour profile: both are per-operator personal data.
        turns = conversation.forget(user_id)
        return jsonify(
            {
                "success": True,
                "message": "behaviour profile erased",
                "turns_removed": turns,
            }
        )

    return app
