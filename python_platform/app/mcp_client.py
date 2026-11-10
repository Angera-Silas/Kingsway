"""Outbound Model Context Protocol client.

The school already *serves* MCP: ``api/controllers/McpController.php`` exposes
its governed aggregate tools to authenticated machine clients. This module is
the other half - a client that lets the assistant *consume* tools from an
external MCP server the school has chosen to integrate.

Why it is shaped the way it is:

  * **Dormant by default.** No ``AI_MCP_SERVERS`` configured means no MCP
    capability at all, exactly like the realtime publisher. Nothing here
    phones out on its own.
  * **Operator-configured, never model-configured.** The server URL, its
    credential and its allowlisted tool names all come from the deployment
    environment. A model cannot add a server, cannot point the client at a new
    host and cannot widen the tool list, so the client can never be turned into
    an open proxy for the school's network.
  * **JSON-RPC 2.0 only, bounded.** ``initialize``, ``tools/list`` and
    ``tools/call``; a bounded timeout, a bounded response, and an allowlisted
    method set. Anything else is refused rather than attempted.
  * **The result is data, never instructions.** Tool output is returned as
    plain text for the model to reason over; it is never executed and never
    treated as an instruction.

Every call is journalled with the server name and a hash of the arguments, so
an external integration is auditable without recording what was sent.
"""

from __future__ import annotations

import json
import threading
import time
import urllib.error
import urllib.request
from typing import Any

PROTOCOL_VERSION = "2024-11-05"
CLIENT_INFO = {"name": "Kingsway AI Co-worker", "version": "1.0.0"}

# Only these JSON-RPC methods are ever sent. A server that answers something
# else has its answer read, but the client never initiates anything else.
METHODS = ("initialize", "tools/list", "tools/call", "notifications/initialized")

MAX_ARGUMENT_CHARS = 20_000
MAX_RESPONSE_BYTES = 2_000_000
MAX_TOOLS_PER_SERVER = 40
MAX_TOOL_NAME = 100
TOOL_LIST_TTL_SECONDS = 300
DEFAULT_TIMEOUT = 30


class McpError(RuntimeError):
    """A bounded, explainable failure talking to an external MCP server."""


def _as_text(value: Any, limit: int) -> str:
    text = str(value if value is not None else "")
    return text[:limit]


def parse_servers(raw: str) -> list[dict[str, Any]]:
    """Parse the ``AI_MCP_SERVERS`` deployment setting.

    Expected shape (a JSON list, empty or absent when unused)::

        [{"name": "partner",
          "base_url": "https://partner.example.org/mcp",
          "token": "secret",
          "tools": ["attendance_summary", "fee_summary"]}]

    ``tools`` is the allowlist: only these tool names may be called on this
    server. An entry with no ``tools`` list is refused rather than granted
    everything the server happens to offer.
    """
    raw = (raw or "").strip()
    if not raw:
        return []
    try:
        parsed = json.loads(raw)
    except (TypeError, ValueError) as error:
        raise McpError("AI_MCP_SERVERS is not valid JSON") from error
    if not isinstance(parsed, list):
        raise McpError("AI_MCP_SERVERS must be a JSON list")

    servers: list[dict[str, Any]] = []
    seen: set[str] = set()
    for entry in parsed[:8]:
        if not isinstance(entry, dict):
            continue
        name = _as_text(entry.get("name"), 60).strip()
        base_url = _as_text(entry.get("base_url"), 300).strip()
        if not name or not base_url:
            continue
        if name in seen:
            continue
        if not base_url.startswith(("https://", "http://")):
            continue
        allowlist = entry.get("tools")
        if not isinstance(allowlist, list) or not allowlist:
            # Refuse rather than default to "everything".
            continue
        tools = [
            _as_text(tool, MAX_TOOL_NAME).strip()
            for tool in allowlist[:MAX_TOOLS_PER_SERVER]
            if _as_text(tool, MAX_TOOL_NAME).strip()
        ]
        if not tools:
            continue
        seen.add(name)
        servers.append(
            {
                "name": name,
                "base_url": base_url.rstrip("/"),
                "token": _as_text(entry.get("token"), 400),
                "tools": tools,
                "timeout": max(
                    3, min(60, int(entry.get("timeout") or DEFAULT_TIMEOUT))
                ),
            }
        )
    return servers


def tool_id(server_name: str, tool_name: str) -> str:
    """The agent-facing id of one external MCP tool."""
    return f"mcp.{server_name}.{tool_name}"


class McpClient:
    """Bounded JSON-RPC client for the configured external MCP servers."""

    def __init__(self, servers: list[dict[str, Any]] | None = None, journal=None):
        self.servers = {str(server["name"]): server for server in (servers or [])}
        self.journal = journal
        self._lock = threading.Lock()
        self._cache: dict[str, tuple[float, list[dict[str, Any]]]] = {}
        self._initialized: set[str] = set()

    # ------------------------------------------------------------------ shape
    def available(self) -> bool:
        return bool(self.servers)

    def describe(self) -> list[dict[str, Any]]:
        """Server summaries for the agent's own tool catalogue."""
        described = []
        for name, server in self.servers.items():
            described.append(
                {
                    "server": name,
                    "base_url": server["base_url"],
                    "tools": list(server["tools"]),
                }
            )
        return described

    def known_tools(self) -> dict[str, tuple[str, str]]:
        """Every callable external tool as ``tool_id -> (server, tool_name)``."""
        known: dict[str, tuple[str, str]] = {}
        for name, server in self.servers.items():
            for tool in server["tools"]:
                known[tool_id(name, tool)] = (name, tool)
        return known

    def owns(self, tool: str) -> bool:
        return tool in self.known_tools()

    # ------------------------------------------------------------- transport
    def _post(self, server: dict[str, Any], payload: dict[str, Any]) -> Any:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        request = urllib.request.Request(server["base_url"], data=body, method="POST")
        request.add_header("Content-Type", "application/json")
        request.add_header("Accept", "application/json")
        if server["token"]:
            request.add_header("Authorization", f"Bearer {server['token']}")
        try:
            with urllib.request.urlopen(request, timeout=server["timeout"]) as response:
                raw = response.read(MAX_RESPONSE_BYTES).decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            try:
                detail = error.read(400).decode("utf-8", "replace")
            except OSError:
                detail = ""
            raise McpError(
                f"the {server['name']} server rejected the call ({error.code})"
                + (f": {detail}" if detail else "")
            ) from error
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            raise McpError(
                f"the {server['name']} server could not be reached"
            ) from error
        try:
            return json.loads(raw)
        except (TypeError, ValueError) as error:
            raise McpError(
                f"the {server['name']} server returned an unreadable response"
            ) from error

    def _rpc(self, server: dict[str, Any], method: str, params: dict) -> Any:
        if method not in METHODS:
            raise McpError(f"{method} is not a permitted MCP method")
        response = self._post(
            server,
            {
                "jsonrpc": "2.0",
                "id": 1,
                "method": method,
                "params": params,
            },
        )
        if not isinstance(response, dict):
            raise McpError(f"the {server['name']} server gave no result")
        if isinstance(response.get("error"), dict):
            message = _as_text(response["error"].get("message"), 300)
            raise McpError(
                f"the {server['name']} server reported an error"
                + (f": {message}" if message else "")
            )
        return response.get("result")

    def _ensure_initialized(self, server: dict[str, Any]) -> None:
        name = server["name"]
        if name in self._initialized:
            return
        self._rpc(
            server,
            "initialize",
            {
                "protocolVersion": PROTOCOL_VERSION,
                "capabilities": {},
                "clientInfo": CLIENT_INFO,
            },
        )
        # The notification is fire-and-forget; a server that rejects it is not
        # a server worth refusing the call over.
        try:
            self._post(
                server,
                {"jsonrpc": "2.0", "method": "notifications/initialized"},
            )
        except McpError:
            pass
        self._initialized.add(name)

    # ---------------------------------------------------------------- methods
    def list_tools(self, server_name: str) -> list[dict[str, Any]]:
        """The allowlisted tools this server actually offers."""
        server = self.servers.get(server_name)
        if server is None:
            return []
        now = time.monotonic()
        with self._lock:
            cached = self._cache.get(server_name)
            if cached and now - cached[0] < TOOL_LIST_TTL_SECONDS:
                return cached[1]
        self._ensure_initialized(server)
        result = self._rpc(server, "tools/list", {})
        listed = result.get("tools") if isinstance(result, dict) else None
        if not isinstance(listed, list):
            listed = []
        tools = []
        for tool in listed[:MAX_TOOLS_PER_SERVER]:
            if not isinstance(tool, dict):
                continue
            name = _as_text(tool.get("name"), MAX_TOOL_NAME).strip()
            if name in server["tools"]:
                tools.append(
                    {
                        "tool": name,
                        "description": _as_text(tool.get("description"), 400),
                    }
                )
        with self._lock:
            self._cache[server_name] = (time.monotonic(), tools)
        return tools

    def call(
        self, server_name: str, tool_name: str, arguments: dict[str, Any]
    ) -> dict[str, Any]:
        """Call one allowlisted tool on one configured server."""
        server = self.servers.get(server_name)
        if server is None:
            return {
                "status": "unavailable",
                "message": "that external server is not configured",
            }
        if tool_name not in server["tools"]:
            # The allowlist is the operator's, not the model's: a tool the
            # school did not name is never called, even if the server offers it.
            return {
                "status": "unavailable",
                "message": "that tool is not enabled for this server",
            }
        if not isinstance(arguments, dict):
            arguments = {}
        encoded = json.dumps(arguments, ensure_ascii=False, default=str)
        if len(encoded) > MAX_ARGUMENT_CHARS:
            return {
                "status": "unavailable",
                "message": "the arguments for this tool are too large",
            }
        self._ensure_initialized(server)
        try:
            result = self._rpc(
                server, "tools/call", {"name": tool_name, "arguments": arguments}
            )
        except McpError as error:
            self._journal(server_name, tool_name, arguments, "failed")
            return {"status": "unavailable", "message": str(error)[:300]}

        self._journal(server_name, tool_name, arguments, "answered")
        text = self._result_text(result)
        if not text:
            return {
                "status": "unavailable",
                "message": f"the {server_name} server returned nothing usable",
            }
        return {
            "status": "answered",
            "server": server_name,
            "tool": tool_name,
            # Tool output is DATA for the model to reason over, never an
            # instruction, and it is bounded before it reaches the prompt.
            "result": text[:MAX_ARGUMENT_CHARS],
        }

    @staticmethod
    def _result_text(result: Any) -> str:
        """Flatten an MCP result into bounded plain text.

        MCP returns a content array; text parts are joined, and anything else is
        described rather than dropped so the model knows something was there.
        """
        if isinstance(result, str):
            return result.strip()
        if isinstance(result, dict):
            content = result.get("content")
            if isinstance(content, list):
                parts: list[str] = []
                for item in content[:20]:
                    if not isinstance(item, dict):
                        continue
                    kind = str(item.get("type") or "")
                    if kind == "text":
                        parts.append(_as_text(item.get("text"), 8000))
                    elif kind:
                        # An image or resource link is reported, never fetched:
                        # the client does not become a content relay.
                        parts.append(f"[{kind} content not included]")
                if parts:
                    return "\n".join(part for part in parts if part).strip()
            if "structuredContent" in result:
                return _as_text(
                    json.dumps(result["structuredContent"], ensure_ascii=False),
                    MAX_ARGUMENT_CHARS,
                )
        return ""

    def _journal(
        self, server_name: str, tool_name: str, arguments: dict, outcome: str
    ) -> None:
        if self.journal is None:
            return
        try:
            self.journal.write(
                "ai_generation",
                {
                    "type": "mcp_tool_call",
                    "server": server_name,
                    "tool": tool_name,
                    "outcome": outcome,
                    # A hash, never the payload: an external integration is
                    # auditable without recording what was sent to it.
                    "args_hash": hash_text(
                        json.dumps(arguments, ensure_ascii=False, default=str)
                    ),
                },
            )
        except Exception:  # noqa: BLE001 - a journal failure never blocks a call
            return


def hash_text(value: str) -> str:
    import hashlib

    return hashlib.sha256(value.encode("utf-8")).hexdigest()[:16]
