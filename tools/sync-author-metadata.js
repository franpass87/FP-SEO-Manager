#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = process.cwd();

const METADATA = {
  authorName: 'Francesco Passeri',
  authorEmail: 'info@francescopasseri.com',
  authorUri: 'https://francescopasseri.com',
  pluginUri: 'https://francescopasseri.com',
  description: 'FP SEO Performance provides an on-page SEO analyzer with configurable checks, bulk audits, and admin-facing guidance for WordPress editors.'
};

const args = process.argv.slice(2);
const applyFlag = args.find((arg) => arg.startsWith('--apply='));
const apply = applyFlag ? applyFlag.split('=')[1] === 'true' : false;
const syncDocs = args.includes('--docs');

const results = [];

function recordResult(filePath, changed, message) {
  results.push({ filePath, changed, message });
}

function writeFile(targetPath, content) {
  if (apply) {
    fs.writeFileSync(targetPath, content, 'utf8');
  }
}

function ensurePluginHeader() {
  const filePath = path.join(ROOT, 'fp-seo-performance.php');
  let content = fs.readFileSync(filePath, 'utf8');
  const original = content;
  content = content.replace(/^(\s*\*\s*Plugin URI:\s*).+$/m, `$1${METADATA.pluginUri}`);
  content = content.replace(/^(\s*\*\s*Description:\s*).+$/m, `$1${METADATA.description}`);
  content = content.replace(/^(\s*\*\s*Author:\s*).+$/m, `$1${METADATA.authorName}`);
  content = content.replace(/^(\s*\*\s*Author URI:\s*).+$/m, `$1${METADATA.authorUri}`);
  if (!content.includes('@author')) {
    content = content.replace(' * @package FP\\SEO', ` * @package FP\\SEO\n * @author ${METADATA.authorName}\n * @link ${METADATA.authorUri}`);
  }
  if (content !== original) {
    writeFile(filePath, content);
  }
  recordResult('fp-seo-performance.php', content !== original, content !== original ? 'Updated plugin header metadata.' : 'No changes needed.');
}

function collectPhpFiles() {
  const files = [];
  function walk(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      if (entry.name === 'vendor' || entry.name === 'node_modules') {
        continue;
      }
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        walk(full);
      } else if (entry.isFile() && entry.name.endsWith('.php')) {
        if (entry.name === 'fp-seo-performance.php') {
          continue;
        }
        files.push(full);
      }
    }
  }
  walk(path.join(ROOT, 'src'));
  walk(path.join(ROOT, 'tools'));
  return files;
}

function ensureDocblocks() {
  const files = collectPhpFiles();
  for (const filePath of files) {
    let content = fs.readFileSync(filePath, 'utf8');
    const original = content;
    const match = content.match(/\/\*\*[^]*?\*\//);
    if (match && match[0].includes('@package')) {
      const block = match[0];
      if (!block.includes('@author') || !block.includes('@link')) {
        const lines = block.split('\n');
        const newLines = [];
        for (const line of lines) {
          newLines.push(line);
          if (line.includes('@package')) {
            if (!block.includes('@author')) {
              newLines.push(` * @author ${METADATA.authorName}`);
            }
            if (!block.includes('@link')) {
              newLines.push(` * @link ${METADATA.authorUri}`);
            }
          }
        }
        const updatedBlock = newLines.join('\n');
        content = content.replace(block, updatedBlock);
      }
    }
    if (content !== original) {
      writeFile(filePath, content);
      recordResult(path.relative(ROOT, filePath), true, 'Injected author/link into docblock.');
    } else {
      recordResult(path.relative(ROOT, filePath), false, 'Docblock already up to date.');
    }
  }
}

function updateJson(fileName, updater, message) {
  const filePath = path.join(ROOT, fileName);
  if (!fs.existsSync(filePath)) {
    return;
  }
  const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
  const original = JSON.stringify(data, null, 2);
  updater(data);
  const updated = JSON.stringify(data, null, 2) + '\n';
  if (updated !== original + '\n') {
    writeFile(filePath, updated);
    recordResult(fileName, true, message);
  } else {
    recordResult(fileName, false, 'No changes needed.');
  }
}

function updateComposer() {
  updateJson('composer.json', (data) => {
    data.description = 'Provides an on-page SEO analyzer with configurable checks, bulk audits, and admin guidance for editors.';
    data.homepage = METADATA.authorUri;
    data.support = { issues: 'https://francescopasseri.com/contact/' };
    data.authors = [
      {
        name: METADATA.authorName,
        email: METADATA.authorEmail,
        homepage: METADATA.authorUri,
        role: 'Developer'
      }
    ];
    data.scripts = data.scripts || {};
    data.scripts['sync:author'] = 'node tools/sync-author-metadata.js --apply=${APPLY:-false}';
    data.scripts['sync:docs'] = 'node tools/sync-author-metadata.js --docs --apply=${APPLY:-false}';
    data.scripts['changelog:from-git'] = 'conventional-changelog -p angular -i CHANGELOG.md -s || true';
  }, 'Updated composer metadata and scripts.');
}

function updatePackageJson() {
  updateJson('package.json', (data) => {
    data.name = data.name || 'fp-seo-performance';
    data.version = data.version || '0.1.2';
    data.description = 'Provides an on-page SEO analyzer with configurable checks, bulk audits, and admin guidance for editors.';
    data.author = `${METADATA.authorName} <${METADATA.authorEmail}> (${METADATA.authorUri})`;
    data.homepage = METADATA.authorUri;
    data.bugs = { url: 'https://francescopasseri.com/contact/' };
    data.repository = data.repository || { type: 'git', url: 'https://github.com/franpass87/FP-SEO-Manager.git' };
    data.scripts = data.scripts || {};
    data.scripts['sync:author'] = 'node tools/sync-author-metadata.js --apply=${APPLY:-false}';
    data.scripts['sync:docs'] = 'node tools/sync-author-metadata.js --docs --apply=${APPLY:-false}';
    data.scripts['changelog:from-git'] = 'conventional-changelog -p angular -i CHANGELOG.md -s || true';
  }, 'Updated package metadata and scripts.');
}

function updateReadmeTxt() {
  const filePath = path.join(ROOT, 'readme.txt');
  if (!fs.existsSync(filePath)) {
    return;
  }
  let content = fs.readFileSync(filePath, 'utf8');
  const original = content;
  content = content.replace(/^(Contributors:\s*).+$/m, `$1fp, francescopasseri`);
  content = content.replace(/^(Author:\s*).+$/m, `$1${METADATA.authorName}`);
  content = content.replace(/^(Author URI:\s*).+$/m, `$1${METADATA.authorUri}`);
  content = content.replace(/^(Plugin Homepage:\s*).+$/m, `$1${METADATA.pluginUri}`);
  const descHeader = '== Description ==';
  const index = content.indexOf(descHeader);
  if (index !== -1) {
    const after = index + descHeader.length;
    const rest = content.slice(after);
    const doubleBreak = rest.indexOf('\n\n', 1);
    if (doubleBreak !== -1) {
      const before = content.slice(0, after);
      const afterDesc = rest.slice(doubleBreak);
      content = `${before}\n\n${METADATA.description}${afterDesc}`;
    }
  }
  if (content !== original) {
    writeFile(filePath, content);
    recordResult('readme.txt', true, 'Synchronized readme.txt metadata.');
  } else {
    recordResult('readme.txt', false, 'No changes needed.');
  }
}

function updateReadmeMd() {
  const filePath = path.join(ROOT, 'README.md');
  if (!fs.existsSync(filePath)) {
    return;
  }
  let content = fs.readFileSync(filePath, 'utf8');
  const original = content;
  content = content.replace(/^(>\s*).+$/m, `$1${METADATA.description}`);
  content = content.replace(/\| \*\*Author\*\* \| .*?\|/, `| **Author** | [${METADATA.authorName}](${METADATA.authorUri}) |`);
  content = content.replace(/\| \*\*Author Email\*\* \| .*?\|/, `| **Author Email** | [${METADATA.authorEmail}](mailto:${METADATA.authorEmail}) |`);
  content = content.replace(/\| \*\*Author URI\*\* \| .*?\|/, `| **Author URI** | ${METADATA.authorUri} |`);
  if (content !== original) {
    writeFile(filePath, content);
    recordResult('README.md', true, 'Updated README metadata.');
  } else {
    recordResult('README.md', false, 'No changes needed.');
  }
}

function updateDocs() {
  if (!syncDocs) {
    return;
  }
  const docsToUpdate = ['docs/overview.md', 'docs/architecture.md', 'docs/faq.md'];
  for (const relativePath of docsToUpdate) {
    const filePath = path.join(ROOT, relativePath);
    if (!fs.existsSync(filePath)) {
      continue;
    }
    let content = fs.readFileSync(filePath, 'utf8');
    const original = content;
    content = content.replace(/FP SEO Performance provides[^\n]*\./, METADATA.description);
    if (content !== original) {
      writeFile(filePath, content);
      recordResult(relativePath, true, 'Aligned description.');
    } else {
      recordResult(relativePath, false, 'No changes needed.');
    }
  }
}

function printSummary() {
  const header = `${apply ? 'APPLIED' : 'DRY-RUN'} SUMMARY`;
  console.log(`\n${header}`);
  console.log('='.repeat(header.length));
  const rows = [];
  for (const { filePath, changed, message } of results) {
    if (!filePath) {
      continue;
    }
    rows.push({ file: filePath, changed: changed ? 'yes' : 'no', note: message });
  }
  if (rows.length) {
    const maxFile = Math.max(...rows.map((row) => row.file.length));
    const maxChanged = Math.max(...rows.map((row) => row.changed.length));
    for (const row of rows) {
      console.log(`${row.file.padEnd(maxFile)}  ${row.changed.padEnd(maxChanged)}  ${row.note}`);
    }
  }
  if (!apply) {
    console.log('\nRun with --apply=true to write the changes.');
  }
}

function main() {
  try {
    ensurePluginHeader();
    ensureDocblocks();
    updateComposer();
    updatePackageJson();
    updateReadmeTxt();
    updateReadmeMd();
    updateDocs();
    printSummary();
  } catch (error) {
    console.error('sync-author-metadata failed:', error);
    process.exit(1);
  }
}

main();
