/**
 * The proxy in front of the production Node subdomain (Apache/LiteSpeed under
 * Passenger) drops the Authorization header, so every authenticated call that
 * crossed it, PHP's /internal/views relay and the browser's /api/agents calls,
 * answered 401 even though the session was valid. Callers therefore send the
 * same bearer a second time in X-Kingsway-Authorization. When Authorization
 * did not arrive, restore it from that copy so every route keeps reading one
 * header. The value is still verified by each route; this only moves it.
 */
const BEARER = /^Bearer\s+[A-Za-z0-9._~+/=-]+$/i;

export function restoreProxyStrippedAuthorization(req) {
  const headers = req?.headers;
  if (!headers) return false;
  const current = typeof headers.authorization === 'string' ? headers.authorization.trim() : '';
  if (current !== '') return false;
  const fallback = typeof headers['x-kingsway-authorization'] === 'string'
    ? headers['x-kingsway-authorization'].trim()
    : '';
  if (!BEARER.test(fallback)) return false;
  headers.authorization = fallback;
  return true;
}
