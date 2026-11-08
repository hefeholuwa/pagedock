/**
 * PageDock Browser Ingestion & Client Pre-Processing Pipeline
 * Reverse-engineered from HTMLHost.co Master Specification Section 3 & 4:
 * - Client-side ZIP decompression via fflate.unzipSync
 * - Root folder unwrapping & path normalization (removes single parent folder nesting)
 * - Junk asset filtering (__MACOSX, .DS_Store, Thumbs.db)
 * - Direct folder drag-and-drop processing via webkitdirectory / webkitGetAsEntry
 * - Pre-flight client-side entry file validation (ensures index.html exists before upload)
 * - Client state & offline draft autosave via Dexie.js (IndexedDB)
 */

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define(['fflate', 'dexie'], factory);
    } else if (typeof exports === 'object') {
        module.exports = factory(
            typeof require !== 'undefined' ? require('./vendor/fflate.min.js') : null,
            typeof require !== 'undefined' ? require('./vendor/dexie.min.js') : null
        );
    } else {
        root.PageDockPipeline = factory(root.fflate, root.Dexie);
    }
})(typeof self !== 'undefined' ? self : this, function (fflate, Dexie) {
    'use strict';

    // Initialize Dexie IndexedDB Store
    let db = null;
    if (typeof Dexie !== 'undefined') {
        try {
            db = new Dexie('PageDockDB');
            db.version(1).stores({
                drafts: 'id, slug, title, updatedAt',
                editorHistory: 'id, slug, timestamp'
            });
        } catch (err) {
            console.warn('[PageDock] Could not initialize Dexie IndexedDB:', err);
        }
    }

    const Pipeline = {
        /**
         * Check if a file is a ZIP archive
         */
        isZip(file) {
            if (!file) return false;
            const name = (file.name || '').toLowerCase();
            const type = (file.type || '').toLowerCase();
            return name.endsWith('.zip') || type.includes('zip');
        },

        /**
         * Check if a file is HTML
         */
        isHtml(file) {
            if (!file) return false;
            const name = (file.name || '').toLowerCase();
            const type = (file.type || '').toLowerCase();
            return name.endsWith('.html') || name.endsWith('.htm') || type.includes('html');
        },

        /**
         * Detect and strip common enclosing root folder prefix
         * e.g. "my-project/index.html" -> "index.html", "dist/css/style.css" -> "css/style.css"
         */
        unwrapRootPrefix(rawEntries) {
            const clean = {};
            const keys = Object.keys(rawEntries).filter(k => {
                if (k.startsWith('__MACOSX/') || k.includes('/.DS_Store') || k === '.DS_Store' || k.includes('/Thumbs.db')) {
                    return false;
                }
                return !k.endsWith('/'); // Ignore pure directory paths
            });

            if (keys.length === 0) {
                return { files: clean, unwrapped: false, rootPrefix: '' };
            }

            // Check if all files share a common single root folder
            const firstSlash = keys[0].indexOf('/');
            if (firstSlash !== -1) {
                const candidate = keys[0].substring(0, firstSlash + 1);
                const allSharePrefix = keys.every(k => k.startsWith(candidate));
                if (allSharePrefix) {
                    for (const k of keys) {
                        const unwrappedKey = k.substring(candidate.length);
                        clean[unwrappedKey] = rawEntries[k];
                    }
                    return { files: clean, unwrapped: true, rootPrefix: candidate.slice(0, -1) };
                }
            }

            for (const k of keys) {
                clean[k] = rawEntries[k];
            }
            return { files: clean, unwrapped: false, rootPrefix: '' };
        },

        /**
         * Decompress ZIP archive client-side via fflate.unzipSync
         * Returns file breakdown, root-unwrapped clean blob, and validation metrics
         */
        async processZip(fileOrBuffer, fileName = 'archive.zip') {
            const lib = typeof fflate !== 'undefined' ? fflate : (typeof window !== 'undefined' ? window.fflate : null);
            if (!lib || !lib.unzipSync) {
                throw new Error('Client decompression engine (fflate) not loaded');
            }

            let uint8;
            let origName = fileName;

            if (fileOrBuffer instanceof Uint8Array) {
                uint8 = fileOrBuffer;
            } else if (fileOrBuffer instanceof ArrayBuffer) {
                uint8 = new Uint8Array(fileOrBuffer);
            } else if (typeof Blob !== 'undefined' && fileOrBuffer instanceof Blob) {
                origName = fileOrBuffer.name || fileName;
                const buffer = await fileOrBuffer.arrayBuffer();
                uint8 = new Uint8Array(buffer);
            } else {
                throw new Error('Unsupported input type for processZip');
            }

            // Perform fast synchronous browser decompression
            const rawUnzipped = lib.unzipSync(uint8);

            // Strip enclosing folder prefix and OS junk
            const { files: cleanFiles, unwrapped, rootPrefix } = this.unwrapRootPrefix(rawUnzipped);
            const filenames = Object.keys(cleanFiles);

            if (filenames.length === 0) {
                throw new Error('Archive is empty or contains only invalid system files.');
            }

            let hasIndex = false;
            let entryFile = null;
            let totalBytes = 0;
            let htmlCount = 0;
            let assetCount = 0;
            const fileSummary = [];

            for (const path of filenames) {
                const bytes = cleanFiles[path];
                const size = bytes.length;
                totalBytes += size;

                const lower = path.toLowerCase();
                const isHtml = lower === 'index.html' || lower === 'index.htm' || lower.endsWith('.html') || lower.endsWith('.htm');
                if (lower === 'index.html' || lower === 'index.htm') {
                    hasIndex = true;
                    entryFile = path;
                } else if (isHtml && !entryFile) {
                    entryFile = path;
                }

                if (isHtml) htmlCount++; else assetCount++;

                fileSummary.push({
                    path,
                    size,
                    isHtml
                });
            }

            // Re-package into a clean, normalized, flattened ZIP archive ready for zero-latency server dispatch
            const rezippedUint8 = lib.zipSync(cleanFiles, { level: 6 });
            let cleanBlob = null;
            if (typeof Blob !== 'undefined') {
                cleanBlob = new Blob([rezippedUint8], { type: 'application/zip' });
            }

            return {
                originalName: origName,
                fileCount: filenames.length,
                totalBytes,
                hasIndex,
                entryFile: entryFile || filenames[0],
                unwrapped,
                rootPrefix,
                htmlCount,
                assetCount,
                files: fileSummary,
                cleanBlob,
                cleanUint8: rezippedUint8
            };
        },

        /**
         * Recursively process a folder dropped via drag-and-drop
         */
        async processDroppedFolder(directoryEntry) {
            const lib = typeof fflate !== 'undefined' ? fflate : (typeof window !== 'undefined' ? window.fflate : null);
            if (!lib || !lib.zipSync) {
                throw new Error('Client compression engine (fflate) not loaded');
            }

            const rawFiles = {};

            async function walk(entry, pathPrefix = '') {
                if (entry.isFile) {
                    const file = await new Promise((res, rej) => entry.file(res, rej));
                    const buffer = await file.arrayBuffer();
                    rawFiles[pathPrefix + entry.name] = new Uint8Array(buffer);
                } else if (entry.isDirectory) {
                    const reader = entry.createReader();
                    const entries = await new Promise((res, rej) => {
                        reader.readEntries(res, rej);
                    });
                    for (const child of entries) {
                        await walk(child, pathPrefix + entry.name + '/');
                    }
                }
            }

            await walk(directoryEntry);

            const { files: cleanFiles, unwrapped, rootPrefix } = this.unwrapRootPrefix(rawFiles);
            const filenames = Object.keys(cleanFiles);

            if (filenames.length === 0) {
                throw new Error('Folder is empty or contains no readable files.');
            }

            let hasIndex = false;
            let entryFile = null;
            let totalBytes = 0;
            let htmlCount = 0;
            let assetCount = 0;
            const fileSummary = [];

            for (const path of filenames) {
                const bytes = cleanFiles[path];
                const size = bytes.length;
                totalBytes += size;

                const lower = path.toLowerCase();
                const isHtml = lower === 'index.html' || lower === 'index.htm' || lower.endsWith('.html') || lower.endsWith('.htm');
                if (lower === 'index.html' || lower === 'index.htm') {
                    hasIndex = true;
                    entryFile = path;
                } else if (isHtml && !entryFile) {
                    entryFile = path;
                }

                if (isHtml) htmlCount++; else assetCount++;

                fileSummary.push({ path, size, isHtml });
            }

            const rezipped = lib.zipSync(cleanFiles, { level: 6 });
            const cleanBlob = typeof Blob !== 'undefined' ? new Blob([rezipped], { type: 'application/zip' }) : null;

            return {
                originalName: directoryEntry.name,
                fileCount: filenames.length,
                totalBytes,
                hasIndex,
                entryFile: entryFile || filenames[0],
                unwrapped,
                rootPrefix,
                htmlCount,
                assetCount,
                files: fileSummary,
                cleanBlob,
                cleanUint8: rezipped
            };
        },

        /**
         * IndexedDB Draft State Management (Dexie.js)
         */
        async saveDraft(id, payload) {
            if (!db) return false;
            try {
                await db.drafts.put({
                    id,
                    slug: payload.slug || id,
                    title: payload.title || 'Untitled Draft',
                    html: payload.html || '',
                    updatedAt: Date.now()
                });
                return true;
            } catch (e) {
                console.warn('[PageDock] Failed to autosave draft to IndexedDB:', e);
                return false;
            }
        },

        async getDraft(id) {
            if (!db) return null;
            try {
                return await db.drafts.get(id);
            } catch (e) {
                return null;
            }
        },

        async deleteDraft(id) {
            if (!db) return false;
            try {
                await db.drafts.delete(id);
                return true;
            } catch (e) {
                return false;
            }
        },

        async listDrafts() {
            if (!db) return [];
            try {
                return await db.drafts.orderBy('updatedAt').reverse().toArray();
            } catch (e) {
                return [];
            }
        }
    };

    return Pipeline;
});

