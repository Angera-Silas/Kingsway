"""
Kingsway AI Platform - WSGI entry point with auto-starting projection ticker.

DirectAdmin Python Selector (Passenger) deployment:
  Application root:      /home/<user>/python_apps/kingsway_ai
  Application startup:   passenger_wsgi.py
  WSGI callable:         application
  Environment variables: KINGSWAY_AI_SECRET, plus any AI_* provider vars.

Optional read-model worker: KINGSWAY_PYTHON_READ_MODELS_ENABLED,
KINGSWAY_DB_HOST/PORT/USER/PASSWORD/MASTER_SCHEMA/READS_SCHEMA, and
KINGSWAY_DB_SSL_CA for remote database TLS. The database account should be
limited to SELECT on the master sources and read-model DDL/DML on the reads
schema. Read-model settings are independent from the AI provider settings.

Auto-starting Ticker:
  When TICKER_ENABLED=true, the ticker subprocess starts automatically
  and provides 1-2 second projection refresh latency without cron jobs.
"""

import os
import subprocess
import sys

# Track ticker process for potential cleanup
_ticker_process = None


def _start_ticker():
    """Start the projection refresh ticker as a background subprocess."""
    if os.environ.get("TICKER_ENABLED", "true").lower() != "true":
        return None

    venv_python = os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
        ".venv",
        "bin",
        "python",
    )
    if not os.path.exists(venv_python):
        venv_python = sys.executable

    ticker_script = os.path.join(
        os.path.dirname(os.path.abspath(__file__)), "scripts", "ticker.py"
    )

    try:
        _ticker_process = subprocess.Popen(
            [venv_python, ticker_script],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            start_new_session=True,
            env=os.environ.copy(),
        )
        print(f"[PASSENGER] Started projection ticker (PID: {_ticker_process.pid})")
        return _ticker_process
    except Exception as e:
        print(f"[PASSENGER] Could not start ticker: {e}")
        return None


from app import create_app

application = create_app()

# Auto-start ticker for Passenger deployment
if os.environ.get("PASSENGER_BASE_URI"):
    _start_ticker()
