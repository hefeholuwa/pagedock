import urllib.request
import json
import re
import os
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

templates_index = {}

for slug in SLUGS:
    print(f"Fetching {slug}...")
    try:
        # 1. Fetch Bundle
        bundle_url = f"https://htmlhost.co/api/templates/{slug}/bundle"
        req = urllib.request.Request(bundle_url, headers={"User-Agent": "Mozilla/5.0"})
        bundle = json.loads(urllib.request.urlopen(req, timeout=15).read().decode("utf-8"))
        
        name = bundle.get("name", slug.replace("-", " ").title())
        is_pro = bundle.get("proOnly", False)
        
        # Get entry HTML
        entry_html = ""
        files = bundle.get("files", [])
        for f in files:
            if f.get("type") == "html" and "content" in f:
                entry_html = f["content"]
                break
        
        if not entry_html and files and "content" in files[0]:
            entry_html = files[0]["content"]

        # 2. Fetch page for category & description & tags
        page_url = f"https://htmlhost.co/templates/{slug}"
        req_page = urllib.request.Request(page_url, headers={"User-Agent": "Mozilla/5.0"})
        page_html = urllib.request.urlopen(req_page, timeout=15).read().decode("utf-8", errors="ignore")

        # Extract description
        desc_match = re.search(r'<meta\s+name="description"\s+content="([^"]+)"', page_html)
        description = desc_match.group(1) if desc_match else f"{name} website template."

        # Extract category from breadcrumbs or link
        cat_match = re.search(r'/templates/category/([a-zA-Z0-9_-]+)', page_html)
        raw_cat = cat_match.group(1) if cat_match else "landing-page"
        category = raw_cat.replace("-", " ").title()

        # Extract tags
        tags = [t.lower() for t in re.findall(r'<span[^>]*class="[^"]*font-mono[^"]*"[^>]*>([a-zA-Z0-9_-]+)</span>', page_html)]
        if not tags:
            tags = [category.lower(), "responsive", "html5"]

        # Save HTML file
        html_file_path = os.path.join(OUT_DIR, f"{slug}.html")
        with open(html_file_path, "w", encoding="utf-8") as f:
            f.write(entry_html)

        templates_index[slug] = {
            "slug": slug,
            "title": name,
            "category": category,
            "description": description,
            "badge": "Pro" if is_pro else "Free",
            "tags": list(set(tags))[:4],
            "file": f"{slug}.html"
        }
        print(f"  ✓ Saved {slug} ({len(entry_html)} bytes)")
        time.sleep(0.5)

    except Exception as e:
        print(f"  ✗ Error on {slug}: {e}")

# Save master index
with open("/data/data/com.termux/files/home/pagedock/data/templates_index.json", "w", encoding="utf-8") as f:
    json.dump(templates_index, f, indent=2, ensure_ascii=False)

print(f"\nDone! Saved {len(templates_index)} templates to {OUT_DIR}.")
