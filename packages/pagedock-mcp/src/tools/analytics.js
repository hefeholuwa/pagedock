import { z } from 'zod';

/**
 * Register analytics and traffic insight tools on the PageDock MCP Server
 * @param {import('@modelcontextprotocol/sdk/server/mcp.js').McpServer} server
 * @param {import('../client.js').PageDockClient} client
 */
export function registerAnalyticsTools(server, client) {
  server.tool(
    'get_site_analytics',
    'Get visitor analytics, page views, unique visitors, top referrers, and device metrics for a hosted PageDock site.',
    {
      slug: z.string().describe('Unique site slug identifier'),
      days: z
        .number()
        .int()
        .positive()
        .optional()
        .describe('Lookback window in days (optional, e.g. 7, 30, 90) [default: 30]'),
    },
    async ({ slug, days }) => {
      try {
        const res = await (days !== undefined
          ? client.getAnalytics(slug, days)
          : client.getAnalytics(slug));
        const raw = res?.analytics || res || {};
        const totalViews = raw.total_views ?? raw.totalViews ?? raw.views ?? 0;
        const uniqueVisitors = raw.unique_visitors ?? raw.uniqueVisitors ?? raw.uniques ?? 0;
        const todayViews = raw.today_views ?? raw.todayViews ?? 0;
        const todayUniques = raw.today_uniques ?? raw.todayUniques ?? 0;
        const topReferrers = raw.top_referrers ?? raw.referrers ?? [];
        const topPages = raw.top_pages ?? raw.pages ?? [];
        const devices = raw.devices ?? raw.device_distribution ?? [];
        const countries = raw.countries ?? raw.top_countries ?? [];
        const timeSeries = raw.time_series ?? raw.daily ?? [];

        const summary = {
          total_views: totalViews,
          unique_visitors: uniqueVisitors,
          today_views: todayViews,
          today_uniques: todayUniques,
          days: raw.days ?? days ?? 30,
        };

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  slug,
                  views: totalViews,
                  unique_visitors: uniqueVisitors,
                  referrers: topReferrers,
                  top_pages: topPages,
                  devices,
                  countries,
                  time_series: timeSeries,
                  summary,
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
              text: `Failed to get analytics for site '${slug}': ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );
}

export default registerAnalyticsTools;
