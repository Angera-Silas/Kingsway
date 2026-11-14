"""
Kingsway AI Platform - WSGI entry point with auto-starting projection ticker.

This is the file Passenger runs in production.

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

Auto-starting Ticker (replaces cron entirely):
  When TICKER_ENABLED=true (the default), the ticker subprocess starts as soon
  as this file is loaded by Passenger. No PASSENGER_BASE_URI condition: that
  variable is unset for DirectAdmin apps mounted at the domain root.
  It runs python_platform/schedules.yaml every TICK_INTERVAL_SECONDS (2 s).
  Launch logic and proof-of-life files: app/ticker_launcher.py.
"""

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app import create_app
from app.ticker_launcher import start_ticker

application = create_app()

# Auto-start ticker for Passenger deployment. The PASSENGER_BASE_URI gate was
# removed: DirectAdmin mounts apps at the domain root, where Passenger never
# sets it, so the ticker silently never started. TICKER_ENABLED is the switch.
_ticker_process = start_ticker("passenger")
