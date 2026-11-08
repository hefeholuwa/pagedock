#!/usr/bin/env python3
"""
PageDock InfinityFree / cPanel FTP Deployer
Synchronizes repository files to remote FTP host based on .env configuration.
Zero third-party dependencies (uses Python standard library: ftplib, os, sys, argparse).
"""

import os
import sys
import argparse
from ftplib import FTP, FTP_TLS, error_perm

# Files and directories excluded from deployment
EXCLUDE_DIRS = {
    '.git',
    '.agents',
    '.gemini',
    'graphify-out',
    'tests',
    '__pycache__',
    'storage',
    'node_modules',
}

EXCLUDE_FILES = {
    '.DS_Store',
    '.env',  # Local .env contains FTP credentials and must never be exposed publicly
    'elementor.4.3.6.zip',
    '.gitignore',
    '.graphifyignore',
    'HTMLHOST_REVERSE_ENGINEERING.md',
    'HTMLHOST_REVERSE_ENGINEERING_VERIFIED.md',
}

EXCLUDE_EXTENSIONS = {
    '.pyc',
    '.sqlite-shm',
    '.sqlite-wal',
}


def load_env(env_path):
    config = {}
    if not os.path.isfile(env_path):
        return config
    with open(env_path, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if '=' in line:
                k, v = line.split('=', 1)
                k = k.strip()
                v = v.strip().strip('"').strip("'")
                config[k] = v
    return config


def get_ftp_connection(config):
    host = config.get('FTP_HOST', 'ftpupload.net')
    port = int(config.get('FTP_PORT', 21))
    user = config.get('FTP_USER', '')
    password = config.get('FTP_PASS', '')
    use_ssl = config.get('FTP_SSL', 'false').lower() in ('true', '1', 'yes')

    if not user or not password:
        sys.stderr.write("[ERROR] FTP_USER or FTP_PASS is empty in .env\n")
        sys.exit(1)

    print(f"[STATUS] Connecting to {host}:{port} (User: {user}, SSL: {use_ssl})...")

    if use_ssl:
        ftp = FTP_TLS()
        ftp.connect(host, port, timeout=90)
        ftp.login(user, password)
        ftp.prot_p()  # Secure data connection
    else:
        ftp = FTP()
        ftp.connect(host, port, timeout=90)
        ftp.login(user, password)

    print("[STATUS] Authentication successful.")
    return ftp


def ensure_remote_dir(ftp, remote_dir):
    parts = [p for p in remote_dir.split('/') if p]
    current = ""
    if remote_dir.startswith('/'):
        current = "/"

    for part in parts:
        current = f"{current}/{part}" if current != "/" else f"/{part}"
        try:
            ftp.cwd(current)
        except error_perm:
            try:
                ftp.mkd(current)
                print(f"[MKDIR] Created remote directory: {current}")
                ftp.cwd(current)
            except error_perm as e:
                pass


def should_exclude(rel_path):
    parts = rel_path.split(os.sep)
    for part in parts:
        if part in EXCLUDE_DIRS:
            return True
        if part in EXCLUDE_FILES:
            return True
        if part.startswith('test-'):
            return True

    base = os.path.basename(rel_path)
    if base in EXCLUDE_FILES:
        return True

    _, ext = os.path.splitext(base)
    if ext in EXCLUDE_EXTENSIONS:
        return True

    return False


def get_remote_file_size(ftp, filename):
    try:
        return ftp.size(filename)
    except Exception:
        return None


def upload_file_with_retry(ftp_ref, config, target_remote_dir, local_path, filename, max_retries=3):
    import time
    for attempt in range(1, max_retries + 1):
        try:
            with open(local_path, 'rb') as f:
                ftp_ref[0].storbinary(f"STOR {filename}", f)
            return True
        except Exception as e:
            print(f"[RETRY] Upload error for {filename} (Attempt {attempt}/{max_retries}): {e}")
            try:
                ftp_ref[0].quit()
            except Exception:
                pass
            if attempt < max_retries:
                time.sleep(3 * attempt)
                print(f"[RECONNECT] Re-establishing FTP connection...")
                ftp_ref[0] = get_ftp_connection(config)
                ftp_ref[0].cwd(target_remote_dir)
            else:
                raise e


def sync_files(ftp_ref, config, root_dir, remote_root, dry_run=False):
    ftp = ftp_ref[0]
    ensure_remote_dir(ftp, remote_root)
    ftp.cwd(remote_root)

    uploaded_count = 0
    skipped_count = 0

    for dirpath, dirnames, filenames in os.walk(root_dir):
        # Prune excluded directories
        dirnames[:] = [d for d in dirnames if not should_exclude(os.path.relpath(os.path.join(dirpath, d), root_dir))]

        rel_dir = os.path.relpath(dirpath, root_dir)
        target_remote_dir = remote_root if rel_dir == '.' else f"{remote_root}/{rel_dir.replace(os.sep, '/')}"

        if not dry_run:
            ensure_remote_dir(ftp_ref[0], target_remote_dir)
            ftp_ref[0].cwd(target_remote_dir)

        for filename in filenames:
            local_path = os.path.join(dirpath, filename)
            rel_file = os.path.relpath(local_path, root_dir)

            if should_exclude(rel_file):
                continue

            local_size = os.path.getsize(local_path)
            remote_file_path = f"{target_remote_dir}/{filename}"

            # Check size on remote to skip unmodified files
            remote_size = None
            if not dry_run:
                remote_size = get_remote_file_size(ftp_ref[0], filename)

            if remote_size == local_size:
                skipped_count += 1
                continue

            if dry_run:
                print(f"[DRY-RUN] Would upload: {rel_file} ({local_size} bytes) -> {remote_file_path}")
                uploaded_count += 1
            else:
                print(f"[UPLOAD] Uploading: {rel_file} ({local_size} bytes)...")
                upload_file_with_retry(ftp_ref, config, target_remote_dir, local_path, filename)
                uploaded_count += 1

    print(f"\n[SUMMARY] Uploaded: {uploaded_count} files | Skipped (Unchanged): {skipped_count} files.")


def main():
    parser = argparse.ArgumentParser(description="PageDock Automated FTP Deployer")
    parser.add_argument('--test', action='store_true', help="Test FTP credentials and connection only")
    parser.add_argument('--dry-run', action='store_true', help="Preview upload operations without transferring files")
    args = parser.parse_args()

    repo_root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
    env_file = os.path.join(repo_root, '.env')

    if not os.path.isfile(env_file):
        sys.stderr.write(f"[ERROR] .env file not found at: {env_file}\n")
        sys.exit(1)

    config = load_env(env_file)
    ftp_root = config.get('FTP_ROOT', '/htdocs').rstrip('/')

    ftp = get_ftp_connection(config)

    if args.test:
        print(f"[STATUS] Testing directory access to '{ftp_root}'...")
        try:
            ftp.cwd(ftp_root)
            print(f"[VERIFICATION] Successfully navigated to '{ftp_root}'. Directory listing:")
            listing = []
            ftp.retrlines('LIST', listing.append)
            for line in listing[:10]:
                print(f"  {line}")
            if len(listing) > 10:
                print(f"  ... ({len(listing) - 10} more items)")
        except error_perm as e:
            print(f"[ERROR] Could not navigate to '{ftp_root}': {e}")
            sys.exit(1)
        finally:
            ftp.quit()
        print("\n[VERIFICATION] FTP connection test PASSED.")
        sys.exit(0)

    ftp_ref = [ftp]
    try:
        sync_files(ftp_ref, config, repo_root, ftp_root, dry_run=args.dry_run)
    finally:
        try:
            ftp_ref[0].quit()
        except Exception:
            pass


if __name__ == '__main__':
    main()

