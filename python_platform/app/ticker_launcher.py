"""Launch the job ticker as a detached, single-instance subprocess.

Shared by the production Passenger entry point (passenger_wsgi.py) and the
local development runner (local_run.py), so both start the scheduler the same
way. There is no cron: the ticker runs python_platform/schedules.yaml every
TICK_INTERVAL_SECONDS (default 2 s). See docs/OPERATIONS_SCHEDULER.md.

Restart semantics
-----------------
The ticker runs in its own session, so it survives Passenger worker restarts
and respawns. On every app load this launcher compares a fingerprint of the
ticker's code and environment with the one recorded for the running ticker:

  * ticker alive, fingerprint unchanged -> leave it running (no churn when
    Passenger recycles idle workers);
  * ticker alive, fingerprint changed   -> SIGTERM it (SIGKILL after
    TICKER_STOP_TIMEOUT seconds) and start a fresh one;
  * ticker not running                  -> start one.

So "restart Passenger after a deploy or env change" restarts the ticker.
schedules.yaml is NOT part of the fingerprint: the ticker hot-reloads it
every tick, so job edits need no restart at all.

Proof of life, all under the application's logs directory:
  ticker.pid          PID of the live ticker (absent means it is not running)
  ticker.fingerprint  code/env fingerprint the live ticker was started with
  ticker.status.json  heartbeat refreshed every ~5 s (pid, state, tick_count)
  ticker.out          ticker stdout/stderr, including tracebacks
  ticker.log          JSON-lines decisions: started/replaced/refused/errors
"""

from __future__ import annotations

import hashlib
import os
import signal
import subprocess
import sys
import time
from pathlib import Path

# python_platform/ (the application root): this file lives in app/.
APP_ROOT = Path(__file__).resolve().parent.parent

# Environment that changes ticker behaviour. Only a digest is stored, never
# the values themselves.
_ENV_PREFIXES = ("KINGSWAY_", "TICK_", "TICKER_", "MAX_TICK", "MAX_JOBS", "AI_")


def log_dir() -> Path:
    """Log directory shared with ticker.py (matches Config.log_dir)."""
    configured = os.environ.get("KINGSWAY_AI_LOG_DIR")
    if configured:
        return Path(configured)
    return APP_ROOT / "logs"


def _journal(entry: dict) -> None:
    """Record a ticker decision without ever blocking startup."""
    try:
        from app.journal import Journal

        Journal(log_dir()).write("ticker", entry)
    except Exception:  # pragma: no cover - journaling must never fail startup
        pass


def _pid_alive(pid: int) -> bool:
    """Running (not exited). A zombie - exited but not yet reaped by the
    Passenger worker that spawned it - counts as gone."""
    try:
        os.kill(pid, 0)
    except ProcessLookupError:
        return False
    except PermissionError:
        return True
    try:
        stat = Path(f"/proc/{pid}/stat").read_text()
        return stat.rsplit(")", 1)[1].split()[0] != "Z"
    except (OSError, IndexError):
        return True


def _is_ticker(pid: int) -> bool:
    """True when pid is a live ticker (guards against PID reuse)."""
    if not _pid_alive(pid):
        return False
    try:
        cmdline = Path(f"/proc/{pid}/cmdline").read_bytes()
    except OSError:
        return True  # no /proc: trust the PID file
    return b"ticker.py" in cmdline


def fingerprint(root: Path = APP_ROOT, environ: dict | None = None) -> str:
    """Digest of the ticker's code and behaviour-relevant environment."""
    environ = os.environ if environ is None else environ
    digest = hashlib.sha256()
    files = [root / "scripts" / "ticker.py", root / "requirements.txt"]
    files += sorted((root / "app").rglob("*.py"))
    for path in files:
        if "__pycache__" in path.parts:
            continue
        try:
            stat = path.stat()
        except OSError:
            continue
        digest.update(
            f"{path.relative_to(root)}|{stat.st_mtime_ns}|{stat.st_size}\n".encode()
        )
    for key in sorted(environ):
        if key.startswith(_ENV_PREFIXES):
            digest.update(f"{key}={environ[key]}\n".encode())
    return digest.hexdigest()


def _read(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8").strip()
    except OSError:
        return ""


def _stop(pid: int, timeout: float) -> str:
    """SIGTERM, wait, then SIGKILL. Returns how the process ended."""
    try:
        os.kill(pid, signal.SIGTERM)
    except ProcessLookupError:
        return "already_gone"
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        if not _pid_alive(pid):
            return "terminated"
        time.sleep(0.1)
    try:
        os.kill(pid, signal.SIGKILL)
    except ProcessLookupError:
        return "terminated"
    for _ in range(50):
        if not _pid_alive(pid):
            break
        time.sleep(0.1)
    return "killed"


def _spawn(directory: Path, started_by: str) -> subprocess.Popen:
    venv_python = APP_ROOT / ".venv" / "bin" / "python"
    python = str(venv_python) if venv_python.exists() else sys.executable
    # Keep the ticker's output instead of discarding it: a crash on import
    # (missing dependency, bad path) is then visible in ticker.out.
    with open(directory / "ticker.out", "ab", buffering=0) as output:
        process = subprocess.Popen(
            [python, "-u", str(APP_ROOT / "scripts" / "ticker.py")],
            stdout=output,
            stderr=subprocess.STDOUT,
            start_new_session=True,
            env=os.environ.copy(),
        )
    print(f"[TICKER-LAUNCH] Started ticker (PID: {process.pid})", flush=True)
    _journal(
        {
            "event": f"started_by_{started_by}",
            "pid": process.pid,
            "python": python,
            "log_dir": str(directory),
        }
    )
    return process


def start_ticker(started_by: str = "passenger") -> subprocess.Popen | None:
    """Ensure exactly one up-to-date ticker is running.

    The child inherits os.environ, so callers must load their environment
    (DB, PHP base URL, worker secret) before calling this. Never raises.
    """
    if os.environ.get("TICKER_ENABLED", "true").lower() != "true":
        print("[TICKER-LAUNCH] Ticker disabled (TICKER_ENABLED != true)", flush=True)
        _journal({"event": "disabled", "reason": "TICKER_ENABLED is not true"})
        return None

    directory = log_dir()
    try:
        directory.mkdir(parents=True, exist_ok=True)
    except OSError as error:
        print(f"[TICKER-LAUNCH] Could not create log directory: {error}", flush=True)
        return None

    try:
        # Serialise launches: several Passenger workers loading at once must
        # not each replace or spawn a ticker.
        lock = None
        try:
            import fcntl

            lock = open(directory / "ticker.launch.lock", "w")
            fcntl.flock(lock, fcntl.LOCK_EX)
        except (ImportError, OSError):
            lock = None
        try:
            return _ensure(directory, started_by)
        finally:
            if lock is not None:
                lock.close()
    except Exception as e:  # noqa: BLE001 - never fail app startup over the ticker
        print(f"[TICKER-LAUNCH] Could not start ticker: {e}", flush=True)
        _journal({"event": "start_failed", "error": f"{type(e).__name__}: {e}"})
        return None


def _ensure(directory: Path, started_by: str) -> subprocess.Popen | None:
    pid_path = directory / "ticker.pid"
    fp_path = directory / "ticker.fingerprint"
    current = fingerprint()

    try:
        existing_pid = int(_read(pid_path))
    except ValueError:
        existing_pid = None

    if existing_pid and existing_pid != os.getpid() and _is_ticker(existing_pid):
        if _read(fp_path) == current:
            print(f"[TICKER-LAUNCH] Ticker up to date (PID: {existing_pid})", flush=True)
            _journal({"event": "already_running", "pid": existing_pid})
            return None
        timeout = float(os.environ.get("TICKER_STOP_TIMEOUT", "15"))
        outcome = _stop(existing_pid, timeout)
        print(
            f"[TICKER-LAUNCH] Replacing ticker {existing_pid} "
            f"(code/env changed): {outcome}",
            flush=True,
        )
        _journal(
            {
                "event": "replaced",
                "old_pid": existing_pid,
                "reason": "fingerprint_changed",
                "stop": outcome,
            }
        )

    process = _spawn(directory, started_by)
    pid_path.write_text(str(process.pid), encoding="utf-8")
    fp_path.write_text(current, encoding="utf-8")
    return process
