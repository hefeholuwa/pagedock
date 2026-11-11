#!/usr/bin/env node

/**
 * PageDock CLI — Instant Terminal Deployment for Static Sites
 * Zero-dependency Node.js CLI (Surge / Vercel model)
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import http from 'http';
import https from 'https';
import { execSync } from 'child_process';

const DEFAULT_SERVER = process.env.PAGEDOCK_SERVER || 'http://localhost:8080';
const CONFIG_FILE = '.pagedock';

// ANSI Colors
const c = {
    reset: '\x1b[0m',
    bold: '\x1b[1m',
    dim: '\x1b[2m',
    green: '\x1b[32m',
    cyan: '\x1b[36m',
    yellow: '\x1b[33m',
    red: '\x1b[31m',
    magenta: '\x1b[35m'
};

function printBanner() {
    console.log(`\n${c.bold}${c.green}  ▲ PageDock CLI${c.reset} ${c.dim}v0.1.0 — Sovereign Static Hosting${c.reset}\n`);
}

function printHelp() {
    printBanner();
    console.log(`  ${c.bold}USAGE:${c.reset}
    pagedock [command] [options]

  ${c.bold}COMMANDS:${c.reset}
    ${c.cyan}deploy${c.reset} [path]       Deploy a local folder, ZIP, or HTML file (default: .)
    ${c.cyan}pull${c.reset} <slug>         Download HTML source of a published site
    ${c.cyan}rollback${c.reset} <slug>     Revert site to a previous version snapshot
    ${c.cyan}help${c.reset}                Show this manual

  ${c.bold}OPTIONS:${c.reset}
    ${c.yellow}--slug, -s${c.reset} <name>    Custom URL slug for deployment (/s/<name>)
    ${c.yellow}--claim, -c${c.reset} <token>   X-Claim-Token for updating an existing deployment
    ${c.yellow}--server${c.reset} <url>        Target PageDock server (default: ${DEFAULT_SERVER})
    ${c.yellow}--json${c.reset}                Output raw JSON response

  ${c.bold}EXAMPLES:${c.reset}
    $ pagedock deploy ./dist
    $ pagedock deploy index.html --slug my-portfolio
    $ pagedock pull my-portfolio --out index.html
    $ pagedock rollback my-portfolio 2
`);
}

// Parse CLI flags
function parseArgs(args) {
    const opts = {
        command: 'deploy',
        targetPath: null,
        slug: null,
        claimToken: null,
        server: DEFAULT_SERVER,
        json: false,
        extra: []
    };

    let i = 0;
    while (i < args.length) {
        const arg = args[i];
        if (arg === '--help' || arg === '-h' || arg === 'help') {
            opts.command = 'help';
        } else if (arg === '--version' || arg === '-v') {
            opts.command = 'version';
        } else if (arg === '--json') {
            opts.json = true;
        } else if (arg === '--slug' || arg === '-s') {
            opts.slug = args[++i];
        } else if (arg === '--claim' || arg === '-c') {
            opts.claimToken = args[++i];
        } else if (arg === '--server') {
            opts.server = args[++i];
        } else if (arg.startsWith('--slug=')) {
            opts.slug = arg.split('=')[1];
        } else if (arg.startsWith('--claim=')) {
            opts.claimToken = arg.split('=')[1];
        } else if (arg.startsWith('--server=')) {
            opts.server = arg.split('=')[1];
        } else if (!arg.startsWith('-')) {
            if (['deploy', 'pull', 'rollback'].includes(arg)) {
                opts.command = arg;
            } else if (!opts.targetPath) {
                opts.targetPath = arg;
            } else {
                opts.extra.push(arg);
            }
        }
        i++;
    }

    return opts;
}

// HTTP request helper
function sendRequest(urlStr, options, bodyBuffer) {
    return new Promise((resolve, reject) => {
        const url = new URL(urlStr);
        const isHttps = url.protocol === 'https:';
        const client = isHttps ? https : http;

        const reqOptions = {
            hostname: url.hostname,
            port: url.port || (isHttps ? 443 : 80),
            path: url.pathname + url.search,
            method: options.method || 'GET',
            headers: options.headers || {}
        };

        const req = client.request(reqOptions, (res) => {
            const chunks = [];
            res.on('data', chunk => chunks.push(chunk));
            res.on('end', () => {
                const buffer = Buffer.concat(chunks);
                const text = buffer.toString('utf-8');
                let json = null;
                try { json = JSON.parse(text); } catch (e) {}
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    text,
                    json,
                    buffer
                });
            });
        });

        req.on('error', reject);
        if (bodyBuffer) {
            req.write(bodyBuffer);
        }
        req.end();
    });
}

// Multipart Form-Data encoder
function buildMultipart(fields, files) {
    const boundary = '----PageDockFormBoundary' + Math.random().toString(36).substring(2);
    const parts = [];

    for (const [key, val] of Object.entries(fields)) {
        if (val === null || val === undefined) continue;
        parts.push(Buffer.from(
            `--${boundary}\r\nContent-Disposition: form-data; name="${key}"\r\n\r\n${val}\r\n`
        ));
    }

    for (const file of files) {
        parts.push(Buffer.from(
            `--${boundary}\r\nContent-Disposition: form-data; name="${file.field}"; filename="${file.name}"\r\nContent-Type: ${file.type || 'application/octet-stream'}\r\n\r\n`
        ));
        parts.push(file.data);
        parts.push(Buffer.from('\r\n'));
    }

    parts.push(Buffer.from(`--${boundary}--\r\n`));
    const body = Buffer.concat(parts);

    return {
        body,
        contentType: `multipart/form-data; boundary=${boundary}`
    };
}

// ZIP creation using native system zip
function zipDirectory(dirPath) {
    const tmpZip = path.join('/tmp', `pd_cli_${Date.now()}_${Math.random().toString(36).substr(2, 5)}.zip`);
    const absDir = path.resolve(dirPath);
    try {
        execSync(`zip -rq "${tmpZip}" . -x ".*" -x "__MACOSX" -x "node_modules/*"`, {
            cwd: absDir,
            stdio: 'pipe'
        });
        const data = fs.readFileSync(tmpZip);
        fs.unlinkSync(tmpZip);
        return data;
    } catch (err) {
        throw new Error(`Failed to create ZIP archive: ${err.message}`);
    }
}

// Main Command: deploy
async function handleDeploy(opts) {
    let target = opts.targetPath || '.';
    if (target === '.' && fs.existsSync('./dist') && fs.statSync('./dist').isDirectory()) {
        target = './dist';
    }

    if (!fs.existsSync(target)) {
        console.error(`${c.red}Error:${c.reset} Target path '${target}' does not exist.`);
        process.exit(1);
    }

    const stat = fs.statSync(target);
    const isDir = stat.isDirectory();
    const configPath = isDir ? path.join(target, CONFIG_FILE) : path.join(path.dirname(target), CONFIG_FILE);

    // Read saved configuration
    let savedConfig = {};
    if (fs.existsSync(configPath)) {
        try {
            savedConfig = JSON.parse(fs.readFileSync(configPath, 'utf8'));
        } catch (e) {}
    }

    const slug = opts.slug || savedConfig.slug || null;
    const claimToken = opts.claimToken || savedConfig.claim_token || null;

    if (!opts.json) {
        printBanner();
        console.log(`  ${c.dim}Deploying:${c.reset}   ${path.resolve(target)}`);
        console.log(`  ${c.dim}Target:${c.reset}      ${opts.server}`);
        if (slug) console.log(`  ${c.dim}Project:${c.reset}     ${slug}`);
        console.log('');
    }

    let files = [];
    if (isDir) {
        process.stdout.write(`  📦 Packaging ${c.cyan}${path.basename(path.resolve(target))}${c.reset}... `);
        const zipData = zipDirectory(target);
        console.log(`${c.green}Done (${(zipData.length / 1024).toFixed(1)} KB)${c.reset}`);
        files.push({
            field: 'file',
            name: `${slug || 'website'}.zip`,
            type: 'application/zip',
            data: zipData
        });
    } else {
        const fileContent = fs.readFileSync(target);
        const ext = path.extname(target).toLowerCase();
        files.push({
            field: 'file',
            name: path.basename(target),
            type: ext === '.zip' ? 'application/zip' : 'text/html',
            data: fileContent
        });
    }

    const multipart = buildMultipart({
        slug: slug,
        title: isDir ? path.basename(path.resolve(target)) : path.basename(target, path.extname(target))
    }, files);

    const headers = {
        'Content-Type': multipart.contentType,
        'Content-Length': multipart.body.length,
        'User-Agent': 'curl/8.7.1 pagedock-cli/0.1.0',
        'Accept': 'application/json'
    };

    if (claimToken) {
        headers['X-Claim-Token'] = claimToken;
    }

    const deployUrl = `${opts.server.replace(/\/+$/, '')}/api/deploy${slug ? '?slug=' + encodeURIComponent(slug) : ''}`;
    
    if (!opts.json) {
        process.stdout.write(`  🚀 Uploading to PageDock edge... `);
    }

    try {
        const res = await sendRequest(deployUrl, { method: 'POST', headers }, multipart.body);
        if (!opts.json) console.log(`${c.green}Done!${c.reset}\n`);

        if (opts.json) {
            console.log(res.text);
            if (res.statusCode >= 400) process.exit(1);
            return;
        }

        if (res.statusCode >= 400) {
            const errMsg = (res.json && res.json.error) ? res.json.error : res.text;
            console.error(`  ${c.red}❌ Deployment Failed (${res.statusCode}):${c.reset}`);
            console.error(`     ${errMsg}\n`);
            process.exit(1);
        }

        const data = res.json || {};
        const siteSlug = data.slug || slug;
        const liveUrl = data.url || `${opts.server}/s/${siteSlug}`;
        const editorUrl = `${opts.server}/editor/${siteSlug}`;
        const newClaimToken = data.claim_token || claimToken;
        const version = data.version ? `v${data.version}` : 'v1';

        // Save deployment context to .pagedock
        if (siteSlug) {
            const configToSave = {
                slug: siteSlug,
                claim_token: newClaimToken,
                url: liveUrl,
                updated_at: new Date().toISOString()
            };
            try {
                fs.writeFileSync(configPath, JSON.stringify(configToSave, null, 2));
            } catch (e) {}
        }

        console.log(`  ${c.bold}${c.green}✨ Published Successfully!${c.reset}`);
        console.log(`  ${c.dim}------------------------------------------------${c.reset}`);
        console.log(`  🌐 ${c.bold}Live URL:${c.reset}     ${c.cyan}${liveUrl}${c.reset}`);
        console.log(`  ✏️  ${c.bold}Online IDE:${c.reset}   ${editorUrl}`);
        console.log(`  🏷️  ${c.bold}Snapshot:${c.reset}     ${version}`);
        if (newClaimToken) {
            console.log(`  🔑 ${c.bold}Claim Token:${c.reset}  ${c.yellow}${newClaimToken}${c.reset}`);
        }
        console.log(`  ${c.dim}------------------------------------------------${c.reset}`);
        console.log(`  ${c.dim}Subsequent 'pagedock deploy' commands will automatically update this deployment.${c.reset}\n`);

    } catch (err) {
        console.error(`\n  ${c.red}Network Error:${c.reset} Could not connect to ${opts.server}`);
        console.error(`  ${err.message}\n`);
        process.exit(1);
    }
}

// Main Command: pull
async function handlePull(opts) {
    const slug = opts.targetPath || opts.slug;
    if (!slug) {
        console.error(`${c.red}Error:${c.reset} Please provide a site slug to pull. Example: pagedock pull my-site`);
        process.exit(1);
    }

    const url = `${opts.server.replace(/\/+$/, '')}/s/${encodeURIComponent(slug)}/`;
    try {
        const res = await sendRequest(url, { method: 'GET' });
        if (res.statusCode >= 400) {
            console.error(`${c.red}Error (${res.statusCode}):${c.reset} Could not fetch site '${slug}'`);
            process.exit(1);
        }

        const outFile = opts.extra[0] || 'index.html';
        fs.writeFileSync(outFile, res.text);
        console.log(`${c.green}✓${c.reset} Pulled ${c.bold}${slug}${c.reset} into ${c.cyan}${outFile}${c.reset} (${(res.text.length / 1024).toFixed(1)} KB)`);
    } catch (err) {
        console.error(`${c.red}Error:${c.reset} ${err.message}`);
        process.exit(1);
    }
}

// Main Command: rollback
async function handleRollback(opts) {
    const slug = opts.targetPath || opts.slug;
    if (!slug) {
        console.error(`${c.red}Error:${c.reset} Site slug required. Example: pagedock rollback my-site [version]`);
        process.exit(1);
    }

    const targetVersion = opts.extra[0] ? parseInt(opts.extra[0], 10) : null;
    const url = `${opts.server.replace(/\/+$/, '')}/api/sites/${encodeURIComponent(slug)}/rollback`;
    
    try {
        const payload = JSON.stringify(targetVersion ? { version: targetVersion } : {});
        const res = await sendRequest(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(payload)
            }
        }, Buffer.from(payload));

        if (res.statusCode >= 400) {
            console.error(`${c.red}Rollback Failed (${res.statusCode}):${c.reset} ${res.text}`);
            process.exit(1);
        }

        const data = res.json || {};
        console.log(`${c.green}✓ Restored site '${slug}'${c.reset} to version ${c.bold}v${data.restored_version || targetVersion}${c.reset}`);
    } catch (err) {
        console.error(`${c.red}Error:${c.reset} ${err.message}`);
        process.exit(1);
    }
}

// CLI Entrypoint
const args = process.argv.slice(2);
const opts = parseArgs(args);

switch (opts.command) {
    case 'help':
        printHelp();
        break;
    case 'version':
        console.log('0.1.0');
        break;
    case 'pull':
        await handlePull(opts);
        break;
    case 'rollback':
        await handleRollback(opts);
        break;
    case 'deploy':
    default:
        await handleDeploy(opts);
        break;
}

