import { z } from 'zod';

/**
 * Register site management tools on the PageDock MCP Server
 * @param {import('@modelcontextprotocol/sdk/server/mcp.js').McpServer} server
 * @param {import('../client.js').PageDockClient} client
 */
export function registerSiteTools(server, client) {
  // 1. list_sites
  server.tool(
    'list_sites',
    'List all hosted sites on PageDock with view statistics, slugs, and live URLs.',
    {},
    async () => {
      try {
        const res = await client.listSites();
        const rawSites = Array.isArray(res) ? res : (res?.sites || []);
        const sites = rawSites.map((site) => {
          const slug = site.slug;
          const liveUrl =
            site.liveUrl ||
            site.url ||
            (client.baseUrl ? `${client.baseUrl}/s/${slug}/` : `/s/${slug}/`);
          const editorUrl =
            site.editorUrl ||
            site.editor_url ||
            (client.baseUrl ? `${client.baseUrl}/editor/${slug}` : `/editor/${slug}`);
          return {
            ...site,
            slug,
            title: site.title || slug,
            views: site.views ?? site.total_views ?? 0,
            liveUrl,
            editorUrl,
          };
        });

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  count: sites.length,
                  sites,
                },
                null,
                2
              ),
            },
          ],
        };
      } catch (error) {
        return {
          isError: true,
          content: [
            {
              type: 'text',
              text: `Failed to list sites: ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );

  // 2. get_site
  server.tool(
    'get_site',
    'Get full metadata, configuration, file count, storage size, and URLs for a specific site by slug.',
    {
      slug: z.string().describe('Unique site slug identifier'),
    },
    async ({ slug }) => {
      try {
        const res = await client.getSite(slug);
        const rawSite = res?.site || res || {};
        const siteSlug = rawSite.slug || slug;
        const liveUrl =
          rawSite.liveUrl ||
          rawSite.url ||
          (client.baseUrl ? `${client.baseUrl}/s/${siteSlug}/` : `/s/${siteSlug}/`);
        const editorUrl =
          rawSite.editorUrl ||
          rawSite.editor_url ||
          (client.baseUrl ? `${client.baseUrl}/editor/${siteSlug}` : `/editor/${siteSlug}`);

        const site = {
          ...rawSite,
          slug: siteSlug,
          title: rawSite.title || siteSlug,
          entry_file: rawSite.entry_file || rawSite.entryFile || 'index.html',
          file_count: rawSite.file_count ?? rawSite.fileCount ?? 1,
          total_size_bytes: rawSite.total_size_bytes ?? rawSite.totalSizeBytes ?? rawSite.size ?? 0,
          views: rawSite.views ?? rawSite.total_views ?? 0,
          liveUrl,
          editorUrl,
        };

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  site,
                },
                null,
                2
              ),
            },
          ],
        };
      } catch (error) {
        return {
          isError: true,
          content: [
            {
              type: 'text',
              text: `Failed to get site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );

  // 3. pull_site
  server.tool(
    'pull_site',
    'Pull raw HTML source code of a hosted site on PageDock for inspection or editing.',
    {
      slug: z.string().describe('Unique site slug identifier to pull HTML from'),
    },
    async ({ slug }) => {
      try {
        const res = await client.pullSite(slug);
        const html = typeof res === 'string' ? res : (res?.html ?? '');

        return {
          content: [
            {
              type: 'text',
              text: html,
            },
          ],
        };
      } catch (error) {
        return {
          isError: true,
          content: [
            {
              type: 'text',
              text: `Failed to pull site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );

  // 4. delete_site
  server.tool(
    'delete_site',
    'Permanently delete a hosted site from PageDock.',
    {
      slug: z.string().describe('Unique site slug identifier to delete'),
    },
    async ({ slug }) => {
      try {
        const res = await client.deleteSite(slug);
        const message = res?.message || `Site '${slug}' was successfully deleted.`;

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  slug,
                  message,
                },
                null,
                2
              ),
            },
          ],
        };
      } catch (error) {
        return {
          isError: true,
          content: [
            {
              type: 'text',
              text: `Failed to delete site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );
}

export default registerSiteTools;
