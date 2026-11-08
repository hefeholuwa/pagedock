/**
 * Test Suite for Phase 4: Browser Ingestion & Client Pre-Processing Pipeline
 */

const fs = require('fs');
const path = require('path');
const fflate = require('../public/assets/js/vendor/fflate.min.js');
const Pipeline = require('../public/assets/js/pipeline.js');

const baseUrl = 'http://localhost:8080';
const results = [];

function assertTest(name, condition, details = '') {
    if (condition) {
        results.push({ name, status: 'PASS' });
        console.log(`PASS: ${name}`);
    } else {
        results.push({ name, status: 'FAIL', details });
        console.log(`FAIL: ${name} (${details})`);
    }
}

console.log('=== Running Phase 4: Browser Ingestion Pipeline Test Suite ===\n');

async function run() {
    // 1. Module Export & Structure
    console.log('--- 1. Module Initialization ---');
    assertTest('PageDockPipeline exports properly', typeof Pipeline === 'object' && Pipeline !== null);
    assertTest('isZip identifies .zip archives', Pipeline.isZip({ name: 'bundle.zip' }) === true);
    assertTest('isHtml identifies .html files', Pipeline.isHtml({ name: 'index.html' }) === true);

    // 2. Root Folder Normalization & Junk Elimination
    console.log('\n--- 2. Root Folder Normalization & Junk Filtering ---');
    const nestedRaw = {
        'my-cool-site/index.html': new TextEncoder().encode('<h1>Hello World</h1>'),
        'my-cool-site/style.css': new TextEncoder().encode('body { color: red; }'),
        'my-cool-site/sub/about.html': new TextEncoder().encode('<h1>About Us</h1>'),
        'my-cool-site/.DS_Store': new Uint8Array([0, 1, 2]),
        '__MACOSX/my-cool-site/._index.html': new Uint8Array([0, 0, 0])
    };

    const unwrapped = Pipeline.unwrapRootPrefix(nestedRaw);
    assertTest('Unwrapped flag is true for single root folder', unwrapped.unwrapped === true);
    assertTest('Root prefix identified as my-cool-site', unwrapped.rootPrefix === 'my-cool-site');
    assertTest('Root prefix stripped from index.html', 'index.html' in unwrapped.files);
    assertTest('Root prefix stripped from sub/about.html', 'sub/about.html' in unwrapped.files);
    assertTest('.DS_Store stripped from clean entries', !('.DS_Store' in unwrapped.files) && !('my-cool-site/.DS_Store' in unwrapped.files));
    assertTest('__MACOSX metadata stripped from clean entries', Object.keys(unwrapped.files).every(k => !k.startsWith('__MACOSX')));

    // 3. Client ZIP Decompression & Re-packaging
    console.log('\n--- 3. Client ZIP Decompression via fflate.unzipSync ---');
    // Create a real nested zip archive in memory using fflate
    const zipFiles = {
        'project-v1/index.html': strToU8('<!DOCTYPE html><html><head><title>Unwrapped Site</title></head><body><h1>Unwrapped</h1></body></html>'),
        'project-v1/app.js': strToU8('console.log("ready");'),
        'project-v1/css/main.css': strToU8('body { background: #000; }'),
        'project-v1/.DS_Store': new Uint8Array([1, 2, 3]),
        '__MACOSX/._project-v1': new Uint8Array([4, 5, 6])
    };

    const nestedZipBytes = fflate.zipSync(zipFiles, { level: 6 });
    assertTest('Generated sample nested ZIP archive in memory', nestedZipBytes.length > 0);

    const processed = await Pipeline.processZip(nestedZipBytes, 'my-project.zip');
    assertTest('processZip successfully unpacked archive', processed.fileCount === 3);
    assertTest('processZip detected index.html', processed.hasIndex === true);
    assertTest('processZip entry file is index.html', processed.entryFile === 'index.html');
    assertTest('processZip unwrapped root prefix', processed.unwrapped === true && processed.rootPrefix === 'project-v1');
    assertTest('processZip count: 1 HTML, 2 assets', processed.htmlCount === 1 && processed.assetCount === 2);
    assertTest('processZip generated clean Uint8Array archive', processed.cleanUint8 instanceof Uint8Array && processed.cleanUint8.length > 0);

    // 4. Missing index.html Pre-Flight Warning
    console.log('\n--- 4. Pre-Flight Validation Checks ---');
    const noIndexFiles = {
        'docs/readme.txt': strToU8('Documentation'),
        'docs/style.css': strToU8('body {}')
    };
    const noIndexBytes = fflate.zipSync(noIndexFiles);
    const noIndexResult = await Pipeline.processZip(noIndexBytes, 'docs.zip');
    assertTest('Archive without index.html reports hasIndex = false', noIndexResult.hasIndex === false);
    assertTest('Archive designates first entry file fallback', !!noIndexResult.entryFile);

    // 5. Empty Archive Validation
    let emptyCaught = false;
    try {
        const emptyZip = fflate.zipSync({});
        await Pipeline.processZip(emptyZip);
    } catch (e) {
        emptyCaught = true;
    }
    assertTest('Empty archive raises error', emptyCaught === true);

    // 6. Live Deployment of Client-Normalized Archive to /api/upload
    console.log('\n--- 5. Live Edge Deployment of Client-Preprocessed Archive ---');
    const testSlug = 'pipeline-test-' + Date.now();
    
    // Create multipart form data payload using Node fetch / FormData
    const form = new FormData();
    const cleanBlob = new Blob([processed.cleanUint8], { type: 'application/zip' });
    form.append('file', cleanBlob, 'clean-archive.zip');
    form.append('slug', testSlug);
    form.append('title', 'Browser Pipeline Deployed Site');

    const res = await fetch(`${baseUrl}/api/upload`, {
        method: 'POST',
        body: form
    });
    const deployData = await res.json();

    assertTest('POST /api/upload returns HTTP 200 for client-preprocessed archive', res.status === 200, `Status: ${res.status}`);
    assertTest('Response contains success = true', deployData.success === true);
    assertTest('Deployed slug matches requested custom slug', deployData.slug === testSlug);

    // Verify deployed site is live and reachable at /s/{slug}
    const siteRes = await fetch(`${baseUrl}/s/${testSlug}/`);
    const siteHtml = await siteRes.text();
    assertTest('Live site served at /s/{slug} returns HTTP 200', siteRes.status === 200);
    assertTest('Live site serves unpacked HTML content', siteHtml.includes('Unwrapped Site'));

    // Verify asset /s/{slug}/app.js is reachable at root (proving root nesting was flattened)
    const assetRes = await fetch(`${baseUrl}/s/${testSlug}/app.js`);
    const assetJs = await assetRes.text();
    assertTest('Nested asset app.js is served at root scope without project-v1/ prefix', assetJs.includes('console.log("ready")'));

    // 7. Cleanup
    console.log('\n--- 6. Cleanup ---');
    const delRes = await fetch(`${baseUrl}/api/sites/${testSlug}/delete?token=${deployData.claim_token}`, {
        method: 'POST'
    });
    const delData = await delRes.json();
    assertTest('Site cleanly deleted', delData.success === true);

    console.log('\n============================================');
    const total = results.length;
    const passed = results.filter(r => r.status === 'PASS').length;
    console.log(`RESULTS: ${passed} / ${total} TESTS PASSED`);
    console.log('============================================');

    if (passed !== total) {
        process.exit(1);
    }
}

function strToU8(str) {
    return new TextEncoder().encode(str);
}

run().catch(err => {
    console.error('Test execution error:', err);
    process.exit(1);
});

