/**
 * Zoho OAuth: แลก refresh token (เก็บใน ENV) เป็น access token
 * - แคช access token ในหน่วยความจำจนใกล้หมดอายุ (ปกติ 1 ชม.)
 * - ถ้ามีหลาย request ขอพร้อมกัน จะ refresh แค่ครั้งเดียว
 *   (Zoho จำกัดจำนวน access token ต่อ refresh token ต่อช่วงเวลา)
 */
export class ZohoAuth {
  constructor({ clientId, clientSecret, refreshToken, accountsUrl }, { fetchImpl = fetch, now = Date.now } = {}) {
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.refreshToken = refreshToken;
    this.accountsUrl = accountsUrl;
    this.fetch = fetchImpl;
    this.now = now;
    this.accessToken = null;
    this.expiresAt = 0;
    this.apiDomain = null;
    this.inflight = null;
  }

  async getAccessToken({ forceRefresh = false } = {}) {
    const safetyMs = 2 * 60 * 1000; // refresh ก่อนหมดอายุ 2 นาที
    if (!forceRefresh && this.accessToken && this.now() < this.expiresAt - safetyMs) {
      return this.accessToken;
    }
    if (!this.inflight) {
      this.inflight = this.#refresh().finally(() => {
        this.inflight = null;
      });
    }
    return this.inflight;
  }

  invalidate() {
    this.accessToken = null;
    this.expiresAt = 0;
  }

  async #refresh() {
    const body = new URLSearchParams({
      refresh_token: this.refreshToken,
      client_id: this.clientId,
      client_secret: this.clientSecret,
      grant_type: 'refresh_token',
    });
    const res = await this.fetch(`${this.accountsUrl}/oauth/v2/token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    let json = {};
    try {
      json = await res.json();
    } catch {
      /* ignore */
    }
    // Zoho มักตอบ 200 แต่มี field error มาแทน
    if (!res.ok || json.error || !json.access_token) {
      const reason = json.error || `HTTP ${res.status}`;
      throw new Error(`Zoho token refresh failed: ${reason}`);
    }
    this.accessToken = json.access_token;
    this.expiresAt = this.now() + Number(json.expires_in || 3600) * 1000;
    if (json.api_domain) this.apiDomain = json.api_domain;
    return this.accessToken;
  }
}
