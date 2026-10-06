import 'dotenv/config';

const list = (v) => (v || '').split(',').map((s) => s.trim()).filter(Boolean);
const trimSlash = (v) => (v || '').replace(/\/+$/, '');

export function loadConfig(env = process.env) {
  const cfg = {
    port: Number(env.PORT || 3000),
    publicBaseUrl: trimSlash(env.PUBLIC_BASE_URL || `http://localhost:${env.PORT || 3000}`),
    trackingSecret: env.TRACKING_SECRET || '',
    adminApiKey: env.ADMIN_API_KEY || '',
    allowedPipelines: list(env.ALLOWED_PIPELINES),
    hiddenStages: list(env.HIDDEN_STAGES),
    showDelayReason: (env.SHOW_DELAY_REASON || 'true').toLowerCase() === 'true',
    cacheTtlMs: Number(env.CACHE_TTL_SECONDS || 60) * 1000,
    company: {
      name: env.COMPANY_NAME || 'The First Good Man Group',
      phone: env.CONTACT_PHONE || '',
      lineUrl: env.CONTACT_LINE_URL || '',
    },
    foreign: {
      enabled: (env.FOREIGN_ENABLED || 'false').toLowerCase() === 'true',
      sessionSecret: env.FOREIGN_SESSION_SECRET || '',
      sessionMinutes: Number(env.FOREIGN_SESSION_MINUTES || 30),
      hiddenFields: list(env.FOREIGN_HIDDEN_FIELDS),
      workdriveDownloadUrl: trimSlash(env.ZOHO_WORKDRIVE_DOWNLOAD_URL || 'https://download.zoho.com/v1/workdrive/download'),
      secureCookie: (env.PUBLIC_BASE_URL || '').startsWith('https://'),
    },
    zoho: {
      clientId: env.ZOHO_CLIENT_ID || '',
      clientSecret: env.ZOHO_CLIENT_SECRET || '',
      refreshToken: env.ZOHO_REFRESH_TOKEN || '',
      accountsUrl: trimSlash(env.ZOHO_ACCOUNTS_URL || 'https://accounts.zoho.com'),
      apiDomain: trimSlash(env.ZOHO_API_DOMAIN || 'https://www.zohoapis.com'),
      apiVersion: env.ZOHO_CRM_API_VERSION || 'v7',
    },
  };
  return cfg;
}

export function assertConfig(cfg) {
  const missing = [];
  if (!cfg.zoho.clientId) missing.push('ZOHO_CLIENT_ID');
  if (!cfg.zoho.clientSecret) missing.push('ZOHO_CLIENT_SECRET');
  if (!cfg.zoho.refreshToken) missing.push('ZOHO_REFRESH_TOKEN');
  if (cfg.trackingSecret.length < 16) missing.push('TRACKING_SECRET (>=16 chars)');
  if (cfg.adminApiKey.length < 12) missing.push('ADMIN_API_KEY (>=12 chars)');
  if (cfg.foreign.enabled && cfg.foreign.sessionSecret.length < 32) missing.push('FOREIGN_SESSION_SECRET (>=32 chars)');
  if (missing.length) {
    throw new Error(`Missing/invalid env: ${missing.join(', ')}`);
  }
}
