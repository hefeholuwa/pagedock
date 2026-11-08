/**
 * PageDock REST API Client for MCP Server and Agent Automation
 */

export class PageDockClient {
  /**
   * @param {Object} [options]
   * @param {string} [options.baseUrl] - Base URL of PageDock instance (default: http://localhost:8080)
   * @param {string} [options.apiToken] - Optional Bearer authentication token
   * @param {string} [options.claimToken] - Optional guest site claim token for unauthenticated authoring
   */
  constructor(options = {}) {
    const rawBase =
      options.baseUrl ||
      (typeof process !== 'undefined' ? process.env?.PAGEDOCK_BASE_URL : null) ||
      'http://localhost:8080';
    this.baseUrl = rawBase.replace(/\/+$/, '');
    this.apiToken =
      options.apiToken ||
      (typeof process !== 'undefined' ? process.env?.PAGEDOCK_API_TOKEN : null) ||
      null;
    this.claimToken =
      options.claimToken ||
      (typeof process !== 'undefined' ? process.env?.PAGEDOCK_CLAIM_TOKEN : null) ||
      null;
  }

  /**
   * Internal HTTP request helper using native fetch
   * @private
   */
  async _request(endpoint, options = {}) {
    const activeClaimToken = options.token || options.claimToken || (!this.apiToken ? this.claimToken : null);
    let url = endpoint.startsWith('http://') || endpoint.startsWith('https://')
      ? endpoint
      : `${this.baseUrl}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;

    if (activeClaimToken && !url.includes('token=')) {
      const sep = url.includes('?') ? '&' : '?';
      url = `${url}${sep}token=${encodeURIComponent(activeClaimToken)}`;
    }

    const headers = {
      ...(options.headers || {}),
    };

    if (this.apiToken && !headers.Authorization && !headers.authorization) {
      headers.Authorization = `Bearer ${this.apiToken}`;
    }

    if (activeClaimToken && !headers['X-Claim-Token']) {
      headers['X-Claim-Token'] = activeClaimToken;
    }

    const res = await fetch(url, {
      ...options,
      headers,
    });

    const contentType = res.headers?.get?.('content-type') || '';
    let data;
    if (contentType.includes('application/json')) {
      try {
        data = await res.json();
      } catch {
        data = null;
      }
    } else {
      data = await res.text();
    }

    if (!res.ok) {
      const errorMsg =
        typeof data === 'object' && data !== null && (data.error || data.message)
          ? data.error || data.message
          : `Request to ${url} failed with status ${res.status} (${res.statusText || 'Error'})`;
      const err = new Error(errorMsg);
      err.status = res.status;
      err.data = data;
      throw err;
    }

    return data;
  }

  /**
   * Deploy or update HTML content for a site
   * @param {Object} params
   * @param {string} params.html - HTML markup string
   * @param {string} [params.title] - Site title
   * @param {string} [params.slug] - Site slug (if updating an existing site)
   * @param {number} [params.ttl] - Expiry TTL in seconds
   * @param {string} [params.file] - Target file name (defaults to index.html)
   * @param {string} [params.endpoint] - Optional custom endpoint override
   * @param {string} [params.token] - Optional claim/api token override
   */
  async deployHtml({ html, title, slug, ttl, file = 'index.html', endpoint, token } = {}) {
    let targetEndpoint = endpoint;
    if (!targetEndpoint) {
      targetEndpoint = slug ? `/api/editor/${encodeURIComponent(slug)}` : '/api/upload';
    }

    const payload = { html };
    if (title !== undefined) payload.title = title;
    if (slug !== undefined) payload.slug = slug;
    if (ttl !== undefined) payload.ttl = ttl;
    if (file !== undefined && slug) payload.file = file;

    return this._request(targetEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      ...(token ? { token } : {}),
    });
  }

  /**
   * List all hosted sites
   * @param {Object} [options]
   */
  async listSites(options = {}) {
    return this._request('/api/sites', {
      method: 'GET',
      ...options,
    });
  }

  /**
   * Get metadata and configuration for a specific site
   * @param {string} slug - Unique site slug
   * @param {Object} [options]
   */
  async getSite(slug, options = {}) {
    return this._request(`/api/sites?slug=${encodeURIComponent(slug)}`, {
      method: 'GET',
      ...options,
    });
  }

  /**
   * Pull raw HTML source code of a hosted site
   * @param {string} slug - Unique site slug
   * @param {Object} [options]
   */
  async pullSite(slug, options = {}) {
    const path = `/s/${encodeURIComponent(slug)}/`;
    const res = await this._request(path, {
      method: 'GET',
      ...options,
    });

    if (typeof res === 'string') {
      return {
        success: true,
        slug,
        html: res,
      };
    }
    return res;
  }

  /**
   * List version snapshot timeline for a site
   * @param {string} slug - Unique site slug
   * @param {Object} [options]
   */
  async listVersions(slug, options = {}) {
    return this._request(`/api/sites/${encodeURIComponent(slug)}/versions`, {
      method: 'GET',
      ...options,
    });
  }

  /**
   * Rollback site to a specific snapshot version (or previous version if omitted)
   * @param {string} slug - Unique site slug
   * @param {number|Object} [version] - Target version number (optional 1-click rollback if omitted)
   * @param {Object} [options]
   */
  async rollbackSite(slug, version, options = {}) {
    let opts = options;
    let ver = version;
    if (typeof version === 'object' && version !== null) {
      opts = version;
      ver = undefined;
    }

    const payload = {};
    if (ver !== undefined && ver !== null) {
      payload.version = ver;
    }

    return this._request(`/api/sites/${encodeURIComponent(slug)}/rollback`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      ...opts,
    });
  }

  /**
   * Retrieve visitor and page view analytics for a site
   * @param {string} slug - Unique site slug
   * @param {number|Object} [days] - Analytics lookback window in days (default: 30)
   * @param {Object} [options]
   */
  async getAnalytics(slug, days, options = {}) {
    let opts = options;
    let numDays = days;
    if (typeof days === 'object' && days !== null) {
      opts = days;
      numDays = undefined;
    }

    const qs = numDays !== undefined && numDays !== null ? `?days=${encodeURIComponent(numDays)}` : '';
    return this._request(`/api/sites/${encodeURIComponent(slug)}/analytics${qs}`, {
      method: 'GET',
      ...opts,
    });
  }

  /**
   * Get form lead submissions for a site with optional spam filtering
   * @param {string} slug - Unique site slug
   * @param {boolean|Object} [isSpam] - Optional spam filter flag
   * @param {Object} [options]
   */
  async getFormSubmissions(slug, isSpam, options = {}) {
    let opts = options;
    let spamFilter = isSpam;
    if (typeof isSpam === 'object' && isSpam !== null) {
      opts = isSpam;
      spamFilter = undefined;
    }

    let qs = '';
    if (spamFilter !== undefined && spamFilter !== null) {
      qs = `?is_spam=${encodeURIComponent(spamFilter)}`;
    }

    return this._request(`/api/sites/${encodeURIComponent(slug)}/forms${qs}`, {
      method: 'GET',
      ...opts,
    });
  }

  /**
   * Delete a site permanently
   * @param {string} slug - Unique site slug
   * @param {Object} [options]
   */
  async deleteSite(slug, options = {}) {
    return this._request(`/api/sites/${encodeURIComponent(slug)}/delete`, {
      method: 'POST',
      ...options,
    });
  }

  /**
   * Update multiple project files simultaneously
   * Normalizes files from Array<{path, content}> to Object {[path]: content}
   * @param {string} slug - Unique site slug
   * @param {Array<{path?: string, file?: string, content?: string, code?: string}>|Object} files
   * @param {string|Object} [summary] - Change summary or options
   * @param {Object} [options]
   */
  async updateSiteFiles(slug, files, summary, options = {}) {
    let opts = options;
    let summaryText = summary;
    if (typeof summary === 'object' && summary !== null) {
      opts = summary;
      summaryText = undefined;
    }

    const filesPayload = Array.isArray(files)
      ? Object.fromEntries(files.map((f) => [f.path || f.file, f.content !== undefined ? f.content : (f.code || '')]))
      : files;

    return this._request(`/api/editor/${encodeURIComponent(slug)}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ files: filesPayload, summary: summaryText }),
      ...opts,
    });
  }
}

export default PageDockClient;
