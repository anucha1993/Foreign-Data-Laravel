/**
 * ตัวเรียก Zoho CRM API แบบบางๆ + แคชผลลัพธ์ระยะสั้น
 */
export class ZohoCrm {
  constructor(auth, { apiDomain, apiVersion = 'v7', cacheTtlMs = 60000 }, { fetchImpl = fetch, now = Date.now } = {}) {
    this.auth = auth;
    this.apiDomain = apiDomain;
    this.apiVersion = apiVersion;
    this.cacheTtlMs = cacheTtlMs;
    this.fetch = fetchImpl;
    this.now = now;
    this.cache = new Map();
  }

  async request(path, { retry = true } = {}) {
    const token = await this.auth.getAccessToken();
    const base = this.auth.apiDomain || this.apiDomain;
    const res = await this.fetch(`${base}/crm/${this.apiVersion}${path}`, {
      headers: { Authorization: `Zoho-oauthtoken ${token}` },
    });

    if (res.status === 204) return null; // ไม่พบข้อมูล
    const json = await res.json().catch(() => ({}));

    // token ถูกเพิกถอน/หมดอายุก่อนเวลา -> refresh แล้วลองใหม่ 1 ครั้ง
    if (res.status === 401 && retry) {
      this.auth.invalidate();
      return this.request(path, { retry: false });
    }
    if (!res.ok) {
      const err = new Error(`Zoho CRM ${res.status}: ${json.code || ''} ${json.message || ''}`.trim());
      err.status = res.status;
      err.code = json.code;
      throw err;
    }
    return json;
  }

  async getDeal(dealId) {
    if (!/^\d{5,25}$/.test(String(dealId))) return null;
    const hit = this.cache.get(dealId);
    if (hit && hit.expires > this.now()) return hit.value;

    let deal = null;
    try {
      const json = await this.request(`/Deals/${dealId}`);
      deal = json?.data?.[0] || null;
    } catch (e) {
      if (e.status === 404 || e.code === 'INVALID_DATA') deal = null;
      else throw e;
    }
    this.cache.set(dealId, { value: deal, expires: this.now() + this.cacheTtlMs });
    if (this.cache.size > 5000) this.cache.delete(this.cache.keys().next().value);
    return deal;
  }
}
