import { z } from 'zod';

/**
 * Register form lead capture tools on the PageDock MCP Server
 * @param {import('@modelcontextprotocol/sdk/server/mcp.js').McpServer} server
 * @param {import('../client.js').PageDockClient} client
 */
export function registerFormTools(server, client) {
  server.tool(
    'list_form_submissions',
    'List contact form leads and submissions captured from a hosted PageDock site, with optional spam filtering.',
    {
      slug: z.string().describe('Unique site slug identifier'),
      isSpam: z
        .boolean()
        .optional()
        .default(false)
        .describe('Filter submissions by spam flag (false for genuine leads, true for spam) [default: false]'),
    },
    async ({ slug, isSpam = false }) => {
      try {
        const res = await client.getFormSubmissions(slug, isSpam);
        const rawSubmissions = Array.isArray(res) ? res : (res?.submissions || []);
        const submissions = rawSubmissions.map((s) => {
          const fields = s.fields || s.data || {};
          const timestamp = s.timestamp || s.created_at || null;
          const ip = s.ip || s.ip_address || null;
          const isSpamFlag = Boolean(s.is_spam ?? s.isSpam ?? false);

          return {
            id: s.id,
            fields,
            timestamp,
            created_at: timestamp,
            ip,
            ip_address: ip,
            is_spam: isSpamFlag,
            isSpam: isSpamFlag,
            form_name: s.form_name || s.formName || 'default',
          };
        });

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  slug,
                  isSpam,
                  count: submissions.length,
                  submission_count: submissions.length,
                  submissions,
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
              text: `Failed to list form submissions for site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );
}

export default registerFormTools;
