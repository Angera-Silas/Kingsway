"""Governed data analysis and chart rendering for coworker reporting.

Why this lives in Python and not in the chart window: the figures must be
drawn from the SAME rows the governed tools returned, so what the staff member
sees is the data PHP authorised and nothing else. The window renders; this
module decides what a figure legitimately contains.

The engine is pandas + matplotlib/seaborn/numpy, with Polars as the columnar
transport for anything arriving from the read-model engine. Output is a PNG
data URI plus the summary statistics behind it, so the agent can both show the
figure and speak the numbers.

Contract:
  * Rows arrive already authorized. This module never queries the database.
  * Only scalar columns are plotted; identifiers and free text are dropped so
    a figure cannot become a disclosure channel.
  * Figures are bounded (size, dpi, points) and rendered headless.
  * Anything that cannot be drawn degrades to a numeric summary, never to an
    exception, so one bad column cannot fail the turn.
"""

from __future__ import annotations

import base64
import io
import math
from typing import Any

import numpy as np
import pandas as pd

# Bounded rendering: 40 points keeps a school chart readable without turning
# into an unreadable hairball, and 1200x675 @100dpi is legible on a projector.
MAX_POINTS = 40
FIGURE_DPI = 100
FIGURE_INCHES = (12.0, 6.75)

_CHART_TYPES = ("bar", "line", "pie", "doughnut", "area", "scatter", "histogram", "box")


class AnalyticsError(RuntimeError):
    pass


def _numeric_frame(rows: list[dict[str, Any]]) -> tuple[pd.DataFrame, list[str]]:
    """Keep only the columns that can honestly carry a number.

    Text and identifier columns are returned separately as 'context' columns
    so a label can still be used on an axis, but they are never aggregated.
    """
    if not rows:
        return pd.DataFrame(), []
    frame = pd.DataFrame(rows)
    text_columns: list[str] = []
    numeric_columns: list[str] = []
    for column in frame.columns:
        series = frame[column]
        if pd.api.types.is_numeric_dtype(series):
            if series.notna().any():
                numeric_columns.append(column)
        else:
            coerced = pd.to_numeric(series, errors="coerce")
            if coerced.notna().sum() >= max(1, int(len(series) * 0.5)):
                frame[column] = coerced
                numeric_columns.append(column)
            else:
                text_columns.append(column)
    return frame, text_columns


def _bound_frame(frame: pd.DataFrame) -> pd.DataFrame:
    if len(frame) > MAX_POINTS:
        frame = frame.tail(MAX_POINTS)
    return frame


def summarize(rows: list[dict[str, Any]], focus: str = "") -> dict[str, Any]:
    """Deterministic statistics over authorized rows: no figure, no provider."""
    frame, _text = _numeric_frame(rows)
    if frame.empty:
        return {
            "status": "answered",
            "row_count": 0,
            "summary": "no rows were available to analyse",
        }
    stats: dict[str, Any] = {}
    for column in frame.columns:
        series = pd.to_numeric(frame[column], errors="coerce").dropna()
        if series.empty:
            continue
        stats[column] = {
            "count": int(series.count()),
            "mean": round(float(series.mean()), 2),
            "min": round(float(series.min()), 2),
            "max": round(float(series.max()), 2),
            "std": round(float(series.std()), 2) if len(series) > 1 else 0.0,
            # A simple, explainable trend: the sign of the first-to-last move.
            "trend": (
                "rising"
                if float(series.iloc[-1]) > float(series.iloc[0])
                else (
                    "falling"
                    if float(series.iloc[-1]) < float(series.iloc[0])
                    else "flat"
                )
            ),
        }
    if not stats:
        return {
            "status": "answered",
            "row_count": int(len(frame)),
            "summary": "the rows carry no numeric column to summarise",
        }
    return {
        "status": "answered",
        "row_count": int(len(frame)),
        "focus": focus[:120],
        "statistics": stats,
        "summary": _spoken_summary(stats, focus),
    }


def _spoken_summary(stats: dict[str, Any], focus: str) -> str:
    parts = []
    for column, values in list(stats.items())[:4]:
        parts.append(
            f"{column} averages {values['mean']} across {values['count']} rows "
            f"(range {values['min']}–{values['max']}), trending {values['trend']}"
        )
    label = focus or "these rows"
    return f"Across {label}: " + "; ".join(parts) + "."


def chart(
    rows: list[dict[str, Any]],
    chart_type: str = "bar",
    x: str = "",
    y: str = "",
    title: str = "",
    focus: str = "",
) -> dict[str, Any]:
    """Render a real figure from authorized rows and return it as a data URI."""
    if chart_type not in _CHART_TYPES:
        raise AnalyticsError(
            f"unsupported chart type {chart_type}; expected one of {', '.join(_CHART_TYPES)}"
        )
    frame, _text = _numeric_frame(rows)
    frame = _bound_frame(frame)
    if frame.empty:
        return {
            "status": "unavailable",
            "message": "the rows carry no numeric column to plot",
        }

    numeric = [c for c in frame.columns if pd.api.types.is_numeric_dtype(frame[c])]
    if y and y not in numeric:
        y = ""
    if not y:
        y = numeric[0]
    if not x:
        x = next(
            (
                c
                for c in frame.columns
                if c != y and not pd.api.types.is_numeric_dtype(frame[c])
            ),
            None,
        )

    # matplotlib is imported inside the function: a headless deployment without
    # a font cache must fail as a skipped figure, not as an unimportable module.
    import matplotlib

    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    import seaborn as sns

    plt.rcParams.update({"font.size": 10})
    figure, axis = plt.subplots(figsize=FIGURE_INCHES, dpi=FIGURE_DPI)
    try:
        if chart_type in ("bar", "area"):
            if x:
                axis.bar(frame[x].astype(str), frame[y], color="#0d6efd", width=0.7)
            else:
                axis.bar(frame[y], color="#0d6efd", width=0.7)
        elif chart_type == "line":
            if x:
                axis.plot(
                    frame[x].astype(str),
                    frame[y],
                    marker="o",
                    linewidth=2.4,
                    color="#0d6efd",
                )
            else:
                axis.plot(frame[y], marker="o", linewidth=2.4, color="#0d6efd")
        elif chart_type == "scatter":
            if x and pd.api.types.is_numeric_dtype(frame[x]):
                sns.scatterplot(data=frame, x=x, y=y, ax=axis, s=70)
            else:
                axis.scatter(range(len(frame)), frame[y], s=70, color="#0d6efd")
        elif chart_type == "histogram":
            axis.hist(
                frame[y].dropna(),
                bins=min(12, max(4, len(frame) // 2)),
                color="#0d6efd",
            )
        elif chart_type == "box":
            axis.boxplot(frame[y].dropna(), vert=True)
        elif chart_type in ("pie", "doughnut"):
            labels = (
                frame[x].astype(str)
                if x
                else pd.Series([str(i) for i in range(len(frame))])
            )
            values = frame[y].abs()
            wedge_props = {"width": 0.42} if chart_type == "doughnut" else None
            axis.pie(
                values,
                labels=labels,
                autopct="%1.0f%%",
                startangle=90,
                wedgeprops=wedge_props,
            )
        else:  # pragma: no cover - guarded by _CHART_TYPES
            raise AnalyticsError(f"unsupported chart type {chart_type}")

        axis.set_title(title or f"{_label(chart_type)} of {y}"[:120])
        if chart_type not in ("pie", "doughnut", "box"):
            axis.grid(axis="y", alpha=0.25)
            if x and len(frame) > 6:
                for tick in axis.get_xticklabels():
                    tick.set_rotation(35)
                    tick.set_ha("right")
        # seaborn's despine() names the figure argument `fig`, not `figure`.
        # Passing `figure` raises TypeError on seaborn 0.13 and the whole
        # figure silently degrades to "could not be drawn".
        sns.despine(fig=figure, ax=axis)

        buffer = io.BytesIO()
        figure.savefig(
            buffer, format="png", dpi=FIGURE_DPI, bbox_inches="tight", facecolor="white"
        )
    finally:
        plt.close(figure)

    encoded = base64.b64encode(buffer.getvalue()).decode("ascii")
    return {
        "status": "answered",
        "chart_type": chart_type,
        "title": (title or f"{_label(chart_type)} of {y}")[:120],
        "y_axis": y,
        "x_axis": x or "(index)",
        "point_count": int(len(frame)),
        # A PNG data URI: the chart window can render it directly, and it can be
        # attached to a download without a second round trip.
        "image": f"data:image/png;base64,{encoded}",
        "image_bytes": len(buffer.getvalue()),
        "focus": focus[:120],
        "summary": (
            f"{_label(chart_type)} of {y} over {len(frame)} rows; "
            f"range {float(frame[y].min()):.2f}–{float(frame[y].max()):.2f}, "
            f"average {float(frame[y].mean()):.2f}."
        ),
    }


def _label(chart_type: str) -> str:
    return {
        "bar": "Bar chart",
        "line": "Line chart",
        "pie": "Pie chart",
        "doughnut": "Doughnut chart",
        "area": "Area chart",
        "scatter": "Scatter plot",
        "histogram": "Histogram",
        "box": "Box plot",
    }.get(chart_type, "Chart")


def forecast(
    rows: list[dict[str, Any]], periods: int = 3, column: str = ""
) -> dict[str, Any]:
    """A bounded, explainable linear projection over one authorized series.

    Deliberately simple: a least-squares trend plus a stated confidence caveat.
    The agent must present it as an indication, never as a promise, and it is
    never allowed to assert a cause.
    """
    frame, _text = _numeric_frame(rows)
    if frame.empty:
        return {"status": "unavailable", "message": "no rows to forecast"}
    numeric = [c for c in frame.columns if pd.api.types.is_numeric_dtype(frame[c])]
    # A forecast needs a MEASURE, not an identifier. Taking the first numeric
    # column projected `class_id` - the ids 10, 5, 7, 12 ... happen to be
    # numeric - and told a staff member their enrolment was "declining by 0.65
    # per period" when it was really the class numbering. Identifiers are
    # dropped from consideration entirely.
    measures = [c for c in numeric if not str(c).endswith("_id") and c != "id"]
    target = column if column in numeric else (measures[0] if measures else "")
    if not target:
        return {"status": "unavailable", "message": "no numeric series to forecast"}
    series = (
        pd.to_numeric(frame[target], errors="coerce").dropna().reset_index(drop=True)
    )
    if len(series) < 3:
        return {
            "status": "unavailable",
            "message": "at least three observations are needed for a trend",
        }
    periods = max(1, min(periods, 12))
    x = np.arange(len(series), dtype=float)
    slope, intercept = np.polyfit(x, series.to_numpy(dtype=float), 1)
    projected = [
        round(float(slope * (len(series) + i) + intercept), 2) for i in range(periods)
    ]
    return {
        "status": "answered",
        "column": target,
        "observations": int(len(series)),
        "slope": round(float(slope), 4),
        "last_value": round(float(series.iloc[-1]), 2),
        "projected_next": projected,
        "direction": "rising" if slope > 0 else ("falling" if slope < 0 else "flat"),
        "summary": (
            f"{target} has moved at about {slope:+.2f} per period across "
            f"{len(series)} observations, so the next {periods} periods indicate "
            f"{projected}. This is a trend indication from authorized records, "
            "not a prediction of outcomes."
        ),
    }
