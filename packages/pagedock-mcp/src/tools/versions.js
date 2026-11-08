import { z } from 'zod';

/**
 * Register versioning and rollback tools on the PageDock MCP Server
 * @param {import('@modelcontextprotocol/sdk/server/mcp.js').McpServer} server
 * @param {import('../client.js').PageDockClient} client
 */
export function registerVersionTools(server, client) {
  // 1. list_versions
  server.tool(
    'list_versions',
    'List all historical immutable version snapshots for a site, including version number, timestamp, file count, and change summary.',
    {
      slug: z.string().describe('Unique site slug identifier'),
    },
    async ({ slug }) => {
      try {
        const res = await client.listVersions(slug);
        const rawVersions = Array.isArray(res) ? res : (res?.versions || []);
        const versions = rawVersions.map((v) => ({
          version_number: v.version_number ?? v.version ?? 0,
          created_at: v.created_at ?? null,
          file_count: v.file_count ?? v.fileCount ?? 1,
          summary: v.summary || '',
          ...(v.total_size_bytes !== undefined ? { total_size_bytes: v.total_size_bytes } : {}),
        }));

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  slug,
                  current_version: res?.current_version ?? (versions[0]?.version_number || 1),
                  count: versions.length,
                  versions,
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
              text: `Failed to list versions for site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );

  // 2. rollback_site
  server.tool(
    'rollback_site',
    'Rollback a hosted site to a previous version snapshot. If version is omitted, defaults to the immediate previous version (1-click rollback).',
    {
      slug: z.string().describe('Unique site slug identifier'),
      version: z
        .number()
        .optional()
        .describe('Target version number to restore (optional - if omitted, reverts to previous version)'),
    },
    async ({ slug, version }) => {
      try {
        const res = await client.rollbackSite(slug, version);
        const restoredVersion =
          res?.restoredVersion ?? res?.restored_version ?? (version !== undefined ? version : 'previous');
        const message =
          res?.message ||
          `Site '${slug}' successfully rolled back to version v${restoredVersion}.`;

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  slug,
                  restoredVersion,
                  ...(res?.new_version !== undefined ? { new_version: res.new_version } : {}),
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
              text: `Failed to rollback site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );
}

export default registerVersionTools;
