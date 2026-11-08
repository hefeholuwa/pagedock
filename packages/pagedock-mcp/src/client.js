/**
 * PageDock REST API Client for MCP Server and Agent Automation
 */

export class PageDockClient {
  /**
   * @param {Object} [options]
   * @param {string} [options.baseUrl] - Base URL of PageDock instance (default: http://localhost:8080)
   * @param {string} [options.apiToken] - Optional Bearer authentication token
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
  }

  /**
   * Internal HTTP request helper using native fetch
   * @private
   */
  async _request(endpoint, options = {}) {
    const url = endpoint.startsWith('http://') || endpoint.startsWith('https://')
      ? endpoint
      : `${this.baseUrl}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;

    const headers = {
      ...(options.headers || {}),
    };

    if (this.apiToken && !headers.Authorization && !headers.authorization) {
      headers.Authorization = `Bearer ${this.apiToken}`;
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
   */
  async deployHtml({ html, title, slug, ttl, file = 'index.html' } = {}) {
    const endpoint = slug ? `/api/editor/${encodeURIComponent(slug)}` : '/api/upload';
    const payload = { html };
    if (title !== undefined) payload.title = title;
    if (slug !== undefined) payload.slug = slug;
    if (ttl !== undefined) payload.ttl = ttl;
    if (file !== undefined && slug) payload.file = file;

    return this._request(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });
  }

  /**
   * List all hosted sites
   */
  async listSites() {
    return this._request('/api/sites', {
      method: 'GET',
    });
  }

  /**
   * Get metadata and configuration for a specific site
   * @param {string} slug - Unique site slug
   */
  async getSite(slug) {
    return this._request(`/api/sites?slug=${encodeURIComponent(slug)}`, {
      method: 'GET',
    });
  }

  /**
   * Pull raw HTML source code of a hosted site
   * @param {string} slug - Unique site slug
   */
  async pullSite(slug) {
    const path = `/s/${encodeURIComponent(slug)}/`;
    const res = await this._request(path, {
      method: 'GET',
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
   */
  async listVersions(slug) {
    return this._request(`/api/sites/${encodeURIComponent(slug)}/versions`, {
      method: 'GET',
    });
  }

  /**
   * Rollback site to a specific snapshot version
   * @param {string} slug - Unique site slug
   * @param {number} [version] - Target version number (optional, reverts to previous if omitted)
   */
  async rollbackSite(slug, version) {
    const payload = {};
    if (version !== undefined && version !== null) {
      payload.version = version;
    }
    return this._request(`/api/sites/${encodeURIComponent(slug)}/rollback`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });
  }

  /**
   * Retrieve visitor and page view analytics for a site
   * @param {string} slug - Unique site slug
   * @param {number} [days] - Analytics lookback window in days (default: 30)
   */
  async getAnalytics(slug, days) {
    const qs = days !== undefined && days !== null ? `?days=${encodeURIComponent(days)}` : '';
    return this._request(`/api/sites/${encodeURIComponent(slug)}/analytics${qs}`, {
      method: 'GET',
    });
  }

  /**
   * Get form lead submissions for a site with optional spam filtering
   * @param {string} slug - Unique site slug
   * @param {boolean} [isSpam] - Optional spam filter flag
   */
  async getFormSubmissions(slug, isSpam) {
    let qs = '';
    if (isSpam !== undefined && isSpam !== null) {
      qs = `?is_spam=${encodeURIComponent(isSpam)}`;
    }
    return this._request(`/api/sites/${encodeURIComponent(slug)}/forms${qs}`, {
      method: 'GET',
    });
  }

  /**
   * Delete a site permanently
   * @param {string} slug - Unique site slug
   */
  async deleteSite(slug) {
    return this._request(`/api/sites/${encodeURIComponent(slug)}/delete`, {
      method: 'POST',
    });
  }

  /**
   * Update multiple project files simultaneously
   * @param {string} slug - Unique site slug
   * @param {Array<{path: string, content: string}>} files - Array of relative file paths and contents
   * @param {string} [summary] - Change summary
   */
  async updateSiteFiles(slug, files, summary) {
    return this._request(`/api/editor/${encodeURIComponent(slug)}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ files, summary }),
    });
  }
}

export default PageDockClient;
