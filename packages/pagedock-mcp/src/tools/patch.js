import { z } from 'zod';

/**
 * Register patching and surgical file update tools on the PageDock MCP Server
 * @param {import('@modelcontextprotocol/sdk/server/mcp.js').McpServer} server
 * @param {import('../client.js').PageDockClient} client
 */
export function registerPatchTools(server, client) {
  server.tool(
    'update_site_file',
    'Update or patch a specific file (HTML, CSS, JS, etc.) on a hosted PageDock site, creating an immutable version snapshot.',
    {
      slug: z.string().describe('Unique site slug identifier'),
      path: z
        .string()
        .optional()
        .describe('Relative file path to update (e.g. index.html, style.css, script.js) [default: index.html]'),
      content: z.string().describe('Text or code content for the target file'),
      summary: z
        .string()
        .optional()
        .describe('Change summary for snapshot version history [default: Updated file via MCP]'),
    },
    async ({ slug, path, content, summary }) => {
      const filePath = (path || 'index.html').replace(/^\/+/, '');
      const changeSummary = summary || 'Updated file via MCP';
      try {
        const res = await client.updateSiteFiles(
          slug,
          [{ path: filePath, content }],
          changeSummary
        );
        const version = res?.version !== undefined ? res.version : (res?.new_version ?? null);
        const message =
          res?.message ||
          `File '${filePath}' on site '${slug}' successfully updated${version ? ` (version v${version})` : ''}.`;

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  slug,
                  path: filePath,
                  version,
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
              text: `Failed to update file '${filePath}' on site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );
}

export default registerPatchTools;
