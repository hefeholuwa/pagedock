import urllib.request
import json
import os
import re
import time

SLUGS = [
    "adventure-travel-tours",
    "ai-observability-dashboard",
    "auto-parts-marketplace-nigeria",
    "bole-festival-landing-port-harcourt",
    "brand-coaching-landing",
    "brand-logos-download-free-svg-png-vector",
    "brickstone-construction-template",
    "business-web-studio",
    "creative-playground-landing",
    "dabira-stars-brainy-play-sing-learn",
    "elevated-fashion-storefront",
    "everyday-imports-storefront",
    "futuristic-dark-landing",
    "jewelry-store-template",
    "kinetica-motion-typography",
    "leica-photography-showcase",
    "photographer-portfolio-earth-xpression",
    "plenrik-ai-influencer-marketing",
    "prompt-scaffolding-spec-senior-designers",
    "quarterly-review-report",
    "royal-wedding-invitation",
    "savora-modern-dining",
    "smartspend-financial-dashboard",
    "svarna-jewellery-brand-landing",
    "vaultera-labs-innovation-open-tech",
    "zaza-fashion-store"
]

OUT_DIR = "/data/data/com.termux/files/home/pagedock/data/templates"
os.makedirs(OUT_DIR, exist_ok=True)

for slug in SLUGS:
    print(f"Processing {slug}...")
    try:
        url = f"https://htmlhost.co/api/templates/{slug}/bundle"
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        bundle = json.loads(urllib.request.urlopen(req, timeout=25).read().decode("utf-8"))

        files = bundle.get("files", [])
        
        # 1. Find main HTML file
        html_file = None
        for f in files:
            p = f.get("path", "")
            if p == "/" or p == "/index.html" or p == "index.html" or (f.get("type") == "html" and not html_file):
                html_file = f
        
        if not html_file and files:
            html_file = files[0]

        html = html_file.get("content", "")

        # 2. Extract and inline all CSS files
        css_blocks = []
        css_paths = []
        for f in files:
            if f.get("type") == "css" and "content" in f and f["content"].strip():
                p = f.get("path", "").lstrip("/")
                css_paths.append(p)
                css_content = f["content"]
                
                # Rewrite asset URLs inside CSS
                for af in files:
                    au = af.get("url", "")
                    ap = af.get("path", "").lstrip("/")
                    if au and ap:
                        abs_url = "https://htmlhost.co" + au if au.startswith("/") else au
                        css_content = css_content.replace(f'"{ap}"', f'"{abs_url}"')
                        css_content = css_content.replace(f"'{ap}'", f"'{abs_url}'")
                        css_content = css_content.replace(f"url({ap})", f"url({abs_url})")
                        css_content = css_content.replace(f"url(../{ap})", f"url({abs_url})")
                        css_content = css_content.replace(f"url(/{ap})", f"url({abs_url})")
                
                css_blocks.append(f"/* === {p} === */\n" + css_content)
        
        if css_blocks:
            merged_css = "\n".join(css_blocks)
            style_tag = f"\n<style id=\"pagedock-inlined-styles\">\n{merged_css}\n</style>\n"
            if "</head>" in html:
                html = html.replace("</head>", style_tag + "</head>", 1)
            else:
                html = style_tag + html
            
            # Remove dead <link rel="stylesheet"> tags that were inlined
            for cp in css_paths:
                base_name = cp.split("/")[-1]
                pattern = re.compile(rf'<link[^>]+href=[\"\'][^\"\']*{re.escape(base_name)}[^\"\']*[\"\'][^>]*>', re.IGNORECASE)
                html = pattern.sub(f'<!-- inlined {base_name} -->', html)

        # 3. Extract and inline all JS files (if any standalone js in bundle)
        js_blocks = []
        js_paths = []
        for f in files:
            if f.get("type") == "js" and "content" in f and f["content"].strip():
                p = f.get("path", "").lstrip("/")
                js_paths.append(p)
                js_blocks.append(f"/* === {p} === */\n" + f["content"])
        
        if js_blocks:
            merged_js = "\n".join(js_blocks)
            script_tag = f"\n<script id=\"pagedock-inlined-scripts\">\n{merged_js}\n</script>\n"
            if "</body>" in html:
                html = html.replace("</body>", script_tag + "</body>", 1)
            else:
                html = html + script_tag
            
            # Remove dead <script src="..."> tags that were inlined
            for jp in js_paths:
                base_name = jp.split("/")[-1]
                pattern = re.compile(rf'<script[^>]+src=[\"\'][^\"\']*{re.escape(base_name)}[^\"\']*[\"\'][^>]*>\s*</script>', re.IGNORECASE)
                html = pattern.sub(f'<!-- inlined {base_name} -->', html)

        # 4. Clean orphan non-existent files
        html = re.sub(r'<link[^>]+scroll-animations[^>]*>', '<!-- removed orphan scroll-animations -->', html, flags=re.IGNORECASE)
        html = re.sub(r'<link[^>]+fevicon\.png[^>]*>', '<!-- removed missing favicon -->', html, flags=re.IGNORECASE)

        # 5. Resolve image and binary assets to absolute URLs
        for f in files:
            u = f.get("url", "")
            p = f.get("path", "").lstrip("/")
            if u:
                abs_url = "https://htmlhost.co" + u if u.startswith("/") else u
                # Replace both relative and slash-prefixed references
                html = html.replace(f'"{p}"', f'"{abs_url}"')
                html = html.replace(f"'{p}'", f"'{abs_url}'")
                html = html.replace(f'"/{p}"', f'"{abs_url}"')
                html = html.replace(f"'/{p}'", f"'{abs_url}'")
                html = html.replace(f'./{p}', abs_url)

        # Write to disk
        dest_path = os.path.join(OUT_DIR, f"{slug}.html")
        with open(dest_path, "w", encoding="utf-8") as out:
            out.write(html)

        print(f"  ✓ Inlined & saved {slug} ({len(html)} bytes, {len(css_blocks)} CSS inlined, {len(js_blocks)} JS inlined)")
        time.sleep(0.3)

    except Exception as e:
        print(f"  ✗ Error on {slug}: {e}")

print("All templates inlined successfully!")
