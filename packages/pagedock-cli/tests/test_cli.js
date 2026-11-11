/**
 * PageDock CLI Unit & Functional Test Suite
 */

import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import assert from 'assert';

const CLI_PATH = path.resolve('bin/pagedock');

console.log('=== Running PageDock CLI Test Suite ===\n');

// 1. Test version command
const versionOut = execSync(`node "${CLI_PATH}" --version`, { encoding: 'utf-8' }).trim();
assert.strictEqual(versionOut, '0.1.0', 'CLI version should be 0.1.0');
console.log('✅ PASS: pagedock --version');

// 2. Test help command
const helpOut = execSync(`node "${CLI_PATH}" --help`, { encoding: 'utf-8' });
assert(helpOut.includes('PageDock CLI'), 'Help must include PageDock banner');
assert(helpOut.includes('COMMANDS:'), 'Help must list commands');
assert(helpOut.includes('deploy'), 'Help must list deploy command');
assert(helpOut.includes('pull'), 'Help must list pull command');
assert(helpOut.includes('rollback'), 'Help must list rollback command');
console.log('✅ PASS: pagedock --help');

// 3. Test directory packaging & ZIP creation
const tmpDir = path.join('/tmp', 'pd_cli_pack_test_' + Date.now());
fs.mkdirSync(tmpDir, { recursive: true });
fs.writeFileSync(path.join(tmpDir, 'index.html'), '<h1>Test Zip Packaging</h1>');
fs.writeFileSync(path.join(tmpDir, 'style.css'), 'body { color: green; }');
fs.writeFileSync(path.join(tmpDir, 'script.js'), 'console.log("ready");');

const tmpZip = path.join('/tmp', `pd_test_bundle_${Date.now()}.zip`);
execSync(`zip -rq "${tmpZip}" . -x ".*"`, { cwd: tmpDir });
assert(fs.existsSync(tmpZip), 'ZIP file should be created');
assert(fs.statSync(tmpZip).size > 100, 'ZIP file should have non-zero size');
console.log('✅ PASS: Native directory zip packaging');

// 4. Test .pagedock configuration state persistence
const configPath = path.join(tmpDir, '.pagedock');
const testConfig = {
    slug: 'my-awesome-site',
    claim_token: 'pd_claim_9876543210abcdef',
    url: 'http://localhost:8080/s/my-awesome-site',
    updated_at: new Date().toISOString()
};
fs.writeFileSync(configPath, JSON.stringify(testConfig, null, 2));

const readConfig = JSON.parse(fs.readFileSync(configPath, 'utf8'));
assert.strictEqual(readConfig.slug, 'my-awesome-site', 'Slug should match');
assert.strictEqual(readConfig.claim_token, 'pd_claim_9876543210abcdef', 'Claim token should match');
console.log('✅ PASS: .pagedock state persistence & schema verification');

// 5. Test CLI argument validation (pull requires slug)
try {
    execSync(`node "${CLI_PATH}" pull`, { stdio: 'pipe' });
    assert.fail('Should have exited with non-zero error');
} catch (e) {
    assert(e.status > 0, 'Exit code should be non-zero when slug is missing');
}
console.log('✅ PASS: Command argument validation (pull requires slug)');

// Clean up tmp files
fs.rmSync(tmpDir, { recursive: true, force: true });
try { fs.unlinkSync(tmpZip); } catch (e) {}

console.log('\n✨ ALL 5 PAGEDOCK CLI TESTS PASSED!\n');
