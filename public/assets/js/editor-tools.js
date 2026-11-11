/**
 * PageDock Pure HTML Editor — Component Library & Deterministic Developer Tools (Zero-AI Edition)
 * Provides:
 * 1. LIBRARY_SECTIONS (Pre-built semantic sections across 8 categories)
 * 2. LIBRARY_ELEMENTS (Pre-built semantic elements across 7 groups)
 * 3. DEVELOPER_TOOLS (Deterministic HTML/CSS/JS/SEO code transformations)
 * 4. SHORTCUT_GROUPS (Elementor-compatible keyboard shortcuts reference)
 */

(function (global) {
    'use strict';

    const LIBRARY_SECTIONS = [
        // 1. Hero
        {
            id: 'hero-centered',
            name: 'Centered Hero',
            category: 'Hero',
            html: `<section style="padding:72px 24px;text-align:center;max-width:960px;margin:0 auto;">
  <span style="display:inline-block;padding:5px 12px;border-radius:999px;background:rgba(4,120,87,0.1);color:#047857;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;margin-bottom:16px;">New Release</span>
  <h1 style="font-size:clamp(32px,5vw,52px);line-height:1.12;font-weight:800;margin:0 auto 18px;max-width:760px;">Build and publish fast static websites in seconds</h1>
  <p style="font-size:18px;line-height:1.6;opacity:0.78;max-width:600px;margin:0 auto 28px;">Drop your HTML, customize every element visually or in code, and ship directly to a clean URL with zero backend setup.</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
    <a href="#contact" style="display:inline-flex;align-items:center;padding:12px 24px;border-radius:999px;background:#047857;color:#ffffff;text-decoration:none;font-weight:600;">Get Started</a>
    <a href="#features" style="display:inline-flex;align-items:center;padding:12px 24px;border-radius:999px;border:1px solid rgba(128,128,128,0.3);color:inherit;text-decoration:none;font-weight:600;">Learn More</a>
  </div>
</section>`
        },
        {
            id: 'hero-split',
            name: 'Split Hero',
            category: 'Hero',
            html: `<section style="padding:64px 24px;max-width:1120px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:40px;align-items:center;">
  <div>
    <h1 style="font-size:clamp(30px,4.5vw,46px);line-height:1.15;font-weight:800;margin:0 0 16px;">Designed for clarity and high conversion</h1>
    <p style="font-size:17px;line-height:1.65;opacity:0.8;margin:0 0 24px;">Showcase your product or service with a balanced two-column layout that adapts cleanly across desktop, tablet, and mobile screens.</p>
    <a href="#contact" style="display:inline-flex;align-items:center;padding:12px 24px;border-radius:10px;background:#047857;color:#ffffff;text-decoration:none;font-weight:600;">Book a Consultation</a>
  </div>
  <div style="border-radius:16px;overflow:hidden;border:1px solid rgba(128,128,128,0.2);background:rgba(128,128,128,0.06);min-height:280px;display:grid;place-items:center;">
    <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=900&q=80" alt="Workspace preview" style="width:100%;height:100%;object-fit:cover;display:block;">
  </div>
</section>`
        },
        // 2. Features
        {
            id: 'features-grid',
            name: 'Feature Grid (3 Columns)',
            category: 'Features',
            html: `<section id="features" style="padding:64px 24px;max-width:1120px;margin:0 auto;">
  <div style="text-align:center;max-width:640px;margin:0 auto 44px;">
    <h2 style="font-size:32px;font-weight:800;margin:0 0 12px;">Everything you need out of the box</h2>
    <p style="font-size:16px;opacity:0.75;margin:0;">Clean semantic markup, responsive grid architecture, and instant form delivery.</p>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
    <div style="padding:24px;border-radius:14px;border:1px solid rgba(128,128,128,0.2);">
      <h3 style="font-size:19px;font-weight:700;margin:0 0 8px;">Instant Publishing</h3>
      <p style="font-size:15px;line-height:1.6;opacity:0.78;margin:0;">Deploy multi-page HTML sites with clean URLs and automatic asset linking.</p>
    </div>
    <div style="padding:24px;border-radius:14px;border:1px solid rgba(128,128,128,0.2);">
      <h3 style="font-size:19px;font-weight:700;margin:0 0 8px;">Visual & Code Editing</h3>
      <p style="font-size:15px;line-height:1.6;opacity:0.78;margin:0;">Click any element on the page to edit text, links, images, and styles directly.</p>
    </div>
    <div style="padding:24px;border-radius:14px;border:1px solid rgba(128,128,128,0.2);">
      <h3 style="font-size:19px;font-weight:700;margin:0 0 8px;">Zero-Backend Forms</h3>
      <p style="font-size:15px;line-height:1.6;opacity:0.78;margin:0;">Collect leads and inquiries straight into your inbox with built-in spam protection.</p>
    </div>
  </div>
</section>`
        },
        {
            id: 'features-steps',
            name: 'How It Works (Steps)',
            category: 'Features',
            html: `<section style="padding:64px 24px;max-width:1040px;margin:0 auto;">
  <h2 style="font-size:30px;font-weight:800;text-align:center;margin:0 0 40px;">How It Works</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:28px;">
    <div style="padding:24px;border-radius:14px;background:rgba(128,128,128,0.05);">
      <span style="display:inline-grid;place-items:center;width:32px;height:32px;border-radius:999px;background:#047857;color:#fff;font-weight:700;font-size:14px;margin-bottom:14px;">1</span>
      <h3 style="font-size:18px;font-weight:700;margin:0 0 8px;">Import or Choose a Template</h3>
      <p style="font-size:14.5px;line-height:1.6;opacity:0.78;margin:0;">Start from an existing HTML folder, ZIP archive, or pre-built section.</p>
    </div>
    <div style="padding:24px;border-radius:14px;background:rgba(128,128,128,0.05);">
      <span style="display:inline-grid;place-items:center;width:32px;height:32px;border-radius:999px;background:#047857;color:#fff;font-weight:700;font-size:14px;margin-bottom:14px;">2</span>
      <h3 style="font-size:18px;font-weight:700;margin:0 0 8px;">Customize Visually</h3>
      <p style="font-size:14.5px;line-height:1.6;opacity:0.78;margin:0;">Double-click text to edit in place or inspect properties in the side panel.</p>
    </div>
    <div style="padding:24px;border-radius:14px;background:rgba(128,128,128,0.05);">
      <span style="display:inline-grid;place-items:center;width:32px;height:32px;border-radius:999px;background:#047857;color:#fff;font-weight:700;font-size:14px;margin-bottom:14px;">3</span>
      <h3 style="font-size:18px;font-weight:700;margin:0 0 8px;">Publish Live</h3>
      <p style="font-size:14.5px;line-height:1.6;opacity:0.78;margin:0;">Go live immediately with version snapshots and instant rollback support.</p>
    </div>
  </div>
</section>`
        },
        // 3. Content
        {
            id: 'content-faq',
            name: 'FAQ Accordion',
            category: 'Content',
            html: `<section style="padding:64px 24px;max-width:760px;margin:0 auto;">
  <h2 style="font-size:30px;font-weight:800;text-align:center;margin:0 0 32px;">Frequently Asked Questions</h2>
  <div style="display:flex;flex-direction:column;gap:12px;">
    <details style="padding:18px 20px;border-radius:12px;border:1px solid rgba(128,128,128,0.22);" open>
      <summary style="font-weight:700;font-size:16px;cursor:pointer;">Can I edit the raw HTML, CSS, and JavaScript directly?</summary>
      <p style="margin:12px 0 0;font-size:15px;line-height:1.6;opacity:0.8;">Yes. You have full access to the source code editor alongside the live visual inspector.</p>
    </details>
    <details style="padding:18px 20px;border-radius:12px;border:1px solid rgba(128,128,128,0.22);">
      <summary style="font-weight:700;font-size:16px;cursor:pointer;">How do contact forms work without a backend?</summary>
      <p style="margin:12px 0 0;font-size:15px;line-height:1.6;opacity:0.8;">Point any form to /__pd/form with method="post" and submissions go straight to your site inbox.</p>
    </details>
    <details style="padding:18px 20px;border-radius:12px;border:1px solid rgba(128,128,128,0.22);">
      <summary style="font-weight:700;font-size:16px;cursor:pointer;">Can I restore an earlier version of my site?</summary>
      <p style="margin:12px 0 0;font-size:15px;line-height:1.6;opacity:0.8;">Every publish creates an immutable snapshot that you can compare or restore with one click.</p>
    </details>
  </div>
</section>`
        },
        {
            id: 'content-stats',
            name: 'Stats & Metrics Row',
            category: 'Content',
            html: `<section style="padding:48px 24px;max-width:1040px;margin:0 auto;">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:24px;text-align:center;padding:32px;border-radius:16px;border:1px solid rgba(128,128,128,0.2);">
    <div>
      <div style="font-size:36px;font-weight:800;color:#047857;">99.9%</div>
      <div style="font-size:14px;opacity:0.75;margin-top:4px;">Uptime SLA</div>
    </div>
    <div>
      <div style="font-size:36px;font-weight:800;color:#047857;">&lt; 50ms</div>
      <div style="font-size:14px;opacity:0.75;margin-top:4px;">Global TTFB</div>
    </div>
    <div>
      <div style="font-size:36px;font-weight:800;color:#047857;">10k+</div>
      <div style="font-size:14px;opacity:0.75;margin-top:4px;">Sites Published</div>
    </div>
    <div>
      <div style="font-size:36px;font-weight:800;color:#047857;">24/7</div>
      <div style="font-size:14px;opacity:0.75;margin-top:4px;">Form Monitoring</div>
    </div>
  </div>
</section>`
        },
        // 4. Social Proof
        {
            id: 'social-testimonials',
            name: 'Testimonials Grid',
            category: 'Social Proof',
            html: `<section style="padding:64px 24px;max-width:1120px;margin:0 auto;">
  <h2 style="font-size:30px;font-weight:800;text-align:center;margin:0 0 36px;">Trusted by teams and creators</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
    <blockquote style="margin:0;padding:24px;border-radius:14px;border:1px solid rgba(128,128,128,0.2);">
      <p style="font-size:15.5px;line-height:1.6;margin:0 0 16px;">"We moved our campaign landing pages here and cut deployment time from hours to seconds."</p>
      <footer style="font-size:14px;font-weight:700;">Amaka Okafor <span style="font-weight:400;opacity:0.7;">— Growth Lead</span></footer>
    </blockquote>
    <blockquote style="margin:0;padding:24px;border-radius:14px;border:1px solid rgba(128,128,128,0.2);">
      <p style="font-size:15.5px;line-height:1.6;margin:0 0 16px;">"Having a clean visual inspector right next to the HTML source code makes quick edits effortless."</p>
      <footer style="font-size:14px;font-weight:700;">David Mensah <span style="font-weight:400;opacity:0.7;">— Frontend Architect</span></footer>
    </blockquote>
    <blockquote style="margin:0;padding:24px;border-radius:14px;border:1px solid rgba(128,128,128,0.2);">
      <p style="font-size:15.5px;line-height:1.6;margin:0 0 16px;">"The built-in form handling and CSV exports saved us from wiring up a separate backend."</p>
      <footer style="font-size:14px;font-weight:700;">Sarah Jenkins <span style="font-weight:400;opacity:0.7;">— Agency Director</span></footer>
    </blockquote>
  </div>
</section>`
        },
        // 5. Commerce
        {
            id: 'commerce-pricing',
            name: 'Pricing (3 Plans)',
            category: 'Commerce',
            html: `<section id="pricing" style="padding:64px 24px;max-width:1120px;margin:0 auto;">
  <div style="text-align:center;max-width:600px;margin:0 auto 40px;">
    <h2 style="font-size:32px;font-weight:800;margin:0 0 10px;">Simple, transparent pricing</h2>
    <p style="font-size:16px;opacity:0.75;margin:0;">Choose the plan that fits your project scale.</p>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;align-items:stretch;">
    <div style="padding:28px;border-radius:16px;border:1px solid rgba(128,128,128,0.22);display:flex;flex-direction:column;">
      <h3 style="font-size:18px;font-weight:700;margin:0 0 8px;">Starter</h3>
      <div style="font-size:34px;font-weight:800;margin-bottom:16px;">$0<span style="font-size:15px;font-weight:400;opacity:0.7;">/mo</span></div>
      <ul style="padding-left:18px;margin:0 0 24px;line-height:1.8;font-size:14.5px;flex:1;">
        <li>Up to 3 static websites</li>
        <li>Built-in contact forms</li>
        <li>Visual & code editor</li>
      </ul>
      <a href="#contact" style="display:block;text-align:center;padding:11px 18px;border-radius:10px;border:1px solid rgba(128,128,128,0.3);color:inherit;text-decoration:none;font-weight:600;">Start Free</a>
    </div>
    <div style="padding:28px;border-radius:16px;border:2px solid #047857;display:flex;flex-direction:column;">
      <h3 style="font-size:18px;font-weight:700;margin:0 0 8px;color:#047857;">Pro</h3>
      <div style="font-size:34px;font-weight:800;margin-bottom:16px;">$12<span style="font-size:15px;font-weight:400;opacity:0.7;">/mo</span></div>
      <ul style="padding-left:18px;margin:0 0 24px;line-height:1.8;font-size:14.5px;flex:1;">
        <li>Unlimited static websites</li>
        <li>Custom domains with SSL</li>
        <li>100 version snapshots & rollback</li>
      </ul>
      <a href="#contact" style="display:block;text-align:center;padding:11px 18px;border-radius:10px;background:#047857;color:#ffffff;text-decoration:none;font-weight:600;">Choose Pro</a>
    </div>
    <div style="padding:28px;border-radius:16px;border:1px solid rgba(128,128,128,0.22);display:flex;flex-direction:column;">
      <h3 style="font-size:18px;font-weight:700;margin:0 0 8px;">Agency</h3>
      <div style="font-size:34px;font-weight:800;margin-bottom:16px;">$39<span style="font-size:15px;font-weight:400;opacity:0.7;">/mo</span></div>
      <ul style="padding-left:18px;margin:0 0 24px;line-height:1.8;font-size:14.5px;flex:1;">
        <li>Team collaborators</li>
        <li>Priority webhook delivery</li>
        <li>Dedicated support</li>
      </ul>
      <a href="#contact" style="display:block;text-align:center;padding:11px 18px;border-radius:10px;border:1px solid rgba(128,128,128,0.3);color:inherit;text-decoration:none;font-weight:600;">Contact Sales</a>
    </div>
  </div>
</section>`
        },
        // 6. Conversion
        {
            id: 'conversion-contact',
            name: 'Contact Form Section',
            category: 'Conversion',
            html: `<section id="contact" style="padding:64px 24px;max-width:680px;margin:0 auto;">
  <div style="text-align:center;margin-bottom:32px;">
    <h2 style="font-size:30px;font-weight:800;margin:0 0 10px;">Get in Touch</h2>
    <p style="font-size:16px;opacity:0.75;margin:0;">Send us a message and we will respond within one business day.</p>
  </div>
  <form action="/__pd/form" method="post" style="display:flex;flex-direction:column;gap:16px;padding:28px;border-radius:16px;border:1px solid rgba(128,128,128,0.22);">
    <input type="hidden" name="_form" value="Contact">
    <input type="hidden" name="_success" value="Thank you! Your message has been received.">
    <div>
      <label style="display:block;font-size:13.5px;font-weight:600;margin-bottom:6px;">Your Name</label>
      <input type="text" name="name" placeholder="Ada Lovelace" required style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);background:transparent;color:inherit;font:inherit;">
    </div>
    <div>
      <label style="display:block;font-size:13.5px;font-weight:600;margin-bottom:6px;">Email Address</label>
      <input type="email" name="email" placeholder="ada@example.com" required style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);background:transparent;color:inherit;font:inherit;">
    </div>
    <div>
      <label style="display:block;font-size:13.5px;font-weight:600;margin-bottom:6px;">Message</label>
      <textarea name="message" rows="4" placeholder="How can we help?" required style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);background:transparent;color:inherit;font:inherit;"></textarea>
    </div>
    <button type="submit" style="padding:12px 20px;border-radius:10px;border:none;background:#047857;color:#ffffff;font-weight:700;cursor:pointer;">Send Message</button>
    <p data-pd-form-msg></p>
  </form>
</section>`
        },
        {
            id: 'conversion-cta',
            name: 'Call to Action Banner',
            category: 'Conversion',
            html: `<section style="padding:56px 24px;max-width:1040px;margin:0 auto;">
  <div style="padding:48px 32px;border-radius:20px;background:#047857;color:#ffffff;text-align:center;">
    <h2 style="font-size:30px;font-weight:800;margin:0 0 12px;">Ready to launch your next project?</h2>
    <p style="font-size:16.5px;opacity:0.9;max-width:560px;margin:0 auto 24px;">Publish clean semantic HTML with instant updates, version history, and built-in lead capture.</p>
    <a href="#contact" style="display:inline-flex;align-items:center;padding:12px 26px;border-radius:999px;background:#ffffff;color:#047857;text-decoration:none;font-weight:700;">Start Building Now</a>
  </div>
</section>`
        },
        // 7. Company
        {
            id: 'company-about',
            name: 'About / Story',
            category: 'Company',
            html: `<section style="padding:64px 24px;max-width:960px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:36px;align-items:center;">
  <div>
    <h2 style="font-size:30px;font-weight:800;margin:0 0 14px;">Built for speed, simplicity, and permanence</h2>
    <p style="font-size:16px;line-height:1.7;opacity:0.82;margin:0 0 14px;">We believe websites should be fast, portable, and easy to maintain without complex build pipelines or fragile plugins.</p>
    <p style="font-size:16px;line-height:1.7;opacity:0.82;margin:0;">Every page you publish remains standard HTML, CSS, and JavaScript that works anywhere.</p>
  </div>
  <div style="padding:28px;border-radius:16px;border:1px solid rgba(128,128,128,0.2);text-align:center;">
    <div style="font-size:44px;font-weight:800;color:#047857;">2026</div>
    <p style="font-size:14px;opacity:0.75;margin:8px 0 0;">Engineered for modern static web publishing</p>
  </div>
</section>`
        },
        // 8. Navigation
        {
            id: 'nav-navbar',
            name: 'Responsive Navbar',
            category: 'Navigation',
            html: `<header style="border-bottom:1px solid rgba(128,128,128,0.18);padding:16px 24px;">
  <nav style="max-width:1120px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <a href="/" style="font-size:18px;font-weight:800;color:inherit;text-decoration:none;">BrandName</a>
    <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap;font-size:14.5px;">
      <a href="/" style="color:inherit;text-decoration:none;opacity:0.85;">Home</a>
      <a href="#features" style="color:inherit;text-decoration:none;opacity:0.85;">Features</a>
      <a href="#pricing" style="color:inherit;text-decoration:none;opacity:0.85;">Pricing</a>
      <a href="#contact" style="padding:8px 16px;border-radius:999px;background:#047857;color:#ffffff;text-decoration:none;font-weight:600;">Contact</a>
    </div>
  </nav>
</header>`
        },
        {
            id: 'nav-footer',
            name: 'Simple Footer',
            category: 'Navigation',
            html: `<footer style="border-top:1px solid rgba(128,128,128,0.18);padding:32px 24px;margin-top:48px;">
  <div style="max-width:1120px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:14px;opacity:0.78;">
    <div>&copy; 2026 BrandName. All rights reserved.</div>
    <div style="display:flex;gap:18px;">
      <a href="#privacy" style="color:inherit;text-decoration:none;">Privacy</a>
      <a href="#terms" style="color:inherit;text-decoration:none;">Terms</a>
      <a href="#contact" style="color:inherit;text-decoration:none;">Contact</a>
    </div>
  </div>
</footer>`
        }
    ];

    const LIBRARY_ELEMENTS = [
        // Basic
        { id: 'heading', name: 'Heading', group: 'Basic', html: `<h2>Section Heading</h2>` },
        { id: 'text', name: 'Text Block', group: 'Basic', html: `<p>Add your paragraph text here. Double-click anywhere on this text in the preview to edit it directly in place.</p>` },
        { id: 'button', name: 'Button', group: 'Basic', html: `<a href="#action" style="display:inline-flex;align-items:center;padding:10px 20px;border-radius:8px;background:#047857;color:#ffffff;text-decoration:none;font-weight:600;">Click Here</a>` },
        { id: 'button-group', name: 'Button Group', group: 'Basic', html: `<div style="display:flex;gap:12px;flex-wrap:wrap;"><a href="#primary" style="display:inline-flex;align-items:center;padding:10px 20px;border-radius:8px;background:#047857;color:#ffffff;text-decoration:none;font-weight:600;">Primary Action</a><a href="#secondary" style="display:inline-flex;align-items:center;padding:10px 20px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);color:inherit;text-decoration:none;font-weight:600;">Secondary</a></div>` },
        { id: 'link', name: 'Text Link', group: 'Basic', html: `<a href="https://example.com" style="color:#047857;text-decoration:underline;font-weight:600;">Learn more &rarr;</a>` },
        { id: 'badge', name: 'Badge', group: 'Basic', html: `<span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:rgba(4,120,87,0.12);color:#047857;">Featured</span>` },
        { id: 'list', name: 'List', group: 'Basic', html: `<ul style="padding-left:20px;line-height:1.75;"><li>First key benefit or point</li><li>Second key benefit or point</li><li>Third key benefit or point</li></ul>` },
        // Media
        { id: 'image', name: 'Image', group: 'Media', html: `<img src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80" alt="Descriptive image text" style="max-width:100%;height:auto;border-radius:12px;display:block;">` },
        { id: 'video', name: 'Video Embed', group: 'Media', html: `<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Video player" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe></div>` },
        { id: 'audio', name: 'Audio Player', group: 'Media', html: `<audio controls style="width:100%;"><source src="" type="audio/mpeg">Your browser does not support the audio element.</audio>` },
        { id: 'map', name: 'Map Embed', group: 'Media', html: `<iframe src="https://maps.google.com/maps?q=Lagos%2C%20Nigeria&z=14&output=embed" width="100%" height="340" style="border:0;border-radius:12px;display:block;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>` },
        // Layout
        { id: 'columns-2', name: '2 Columns', group: 'Layout', html: `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;margin:16px 0;"><div><h3>Column One</h3><p>First column content goes here.</p></div><div><h3>Column Two</h3><p>Second column content goes here.</p></div></div>` },
        { id: 'columns-3', name: '3 Columns', group: 'Layout', html: `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin:16px 0;"><div><h3>Column 1</h3><p>Content here.</p></div><div><h3>Column 2</h3><p>Content here.</p></div><div><h3>Column 3</h3><p>Content here.</p></div></div>` },
        { id: 'card', name: 'Card', group: 'Layout', html: `<div style="padding:24px;border-radius:14px;border:1px solid rgba(128,128,128,0.22);"><h3 style="margin:0 0 8px;">Card Title</h3><p style="margin:0;opacity:0.8;">Supporting description inside a clean bordered container.</p></div>` },
        { id: 'divider', name: 'Divider', group: 'Layout', html: `<hr style="border:0;border-top:1px solid rgba(128,128,128,0.22);margin:24px 0;">` },
        { id: 'spacer', name: 'Spacer', group: 'Layout', html: `<div style="height:40px;" aria-hidden="true"></div>` },
        // Content
        { id: 'quote', name: 'Blockquote', group: 'Content', html: `<blockquote style="border-left:3px solid #047857;padding-left:16px;margin:16px 0;font-style:italic;"><p style="margin:0 0 6px;">"Simplicity is about subtracting the obvious and adding the meaningful."</p><footer style="font-size:13px;opacity:0.75;font-style:normal;">— John Maeda</footer></blockquote>` },
        { id: 'accordion', name: 'Accordion', group: 'Content', html: `<details style="padding:14px 16px;border-radius:10px;border:1px solid rgba(128,128,128,0.22);margin:8px 0;"><summary style="font-weight:600;cursor:pointer;">Click to expand details</summary><p style="margin:10px 0 0;opacity:0.85;">Detailed explanation revealed when opened.</p></details>` },
        { id: 'table', name: 'Table', group: 'Content', html: `<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;"><thead><tr style="border-bottom:2px solid rgba(128,128,128,0.25);text-align:left;"><th style="padding:10px;">Feature</th><th style="padding:10px;">Standard</th><th style="padding:10px;">Pro</th></tr></thead><tbody><tr style="border-bottom:1px solid rgba(128,128,128,0.15);"><td style="padding:10px;">Clean URLs</td><td style="padding:10px;">Included</td><td style="padding:10px;">Included</td></tr><tr style="border-bottom:1px solid rgba(128,128,128,0.15);"><td style="padding:10px;">Version History</td><td style="padding:10px;">3 Versions</td><td style="padding:10px;">100 Versions</td></tr></tbody></table>` },
        { id: 'stat', name: 'Stat Box', group: 'Content', html: `<div style="padding:18px;border-radius:12px;border:1px solid rgba(128,128,128,0.2);"><div style="font-size:32px;font-weight:800;color:#047857;">98%</div><div style="font-size:13.5px;opacity:0.75;">Customer Satisfaction</div></div>` },
        // Forms
        { id: 'form', name: 'Contact Form', group: 'Forms', html: `<form action="/__pd/form" method="post" style="display:flex;flex-direction:column;gap:12px;max-width:480px;"><input type="hidden" name="_form" value="Contact"><input type="text" name="name" placeholder="Your name" required style="padding:10px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);"><input type="email" name="email" placeholder="you@example.com" required style="padding:10px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);"><textarea name="message" rows="4" placeholder="Your message" required style="padding:10px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);"></textarea><button type="submit" style="padding:11px 18px;border-radius:8px;border:none;background:#047857;color:#ffffff;font-weight:600;cursor:pointer;">Send Message</button><p data-pd-form-msg></p></form>` },
        { id: 'newsletter', name: 'Email Signup', group: 'Forms', html: `<form action="/__pd/form" method="post" style="display:flex;gap:8px;flex-wrap:wrap;max-width:460px;"><input type="hidden" name="_form" value="Newsletter"><input type="email" name="email" placeholder="Enter your email" required style="flex:1;min-width:200px;padding:10px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);"><button type="submit" style="padding:10px 18px;border-radius:8px;border:none;background:#047857;color:#ffffff;font-weight:600;cursor:pointer;">Subscribe</button><p data-pd-form-msg style="width:100%;margin:4px 0 0;"></p></form>` },
        { id: 'input', name: 'Input Field', group: 'Forms', html: `<div style="margin-bottom:12px;"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Label</label><input type="text" name="field" placeholder="Enter value" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid rgba(128,128,128,0.35);"></div>` },
        // Interactive
        { id: 'modal', name: 'Popup Dialog', group: 'Interactive', html: `<div><button type="button" onclick="this.nextElementSibling.showModal()" style="padding:10px 18px;border-radius:8px;border:none;background:#047857;color:#fff;font-weight:600;cursor:pointer;">Open Popup</button><dialog style="max-width:440px;width:calc(100% - 32px);border:1px solid rgba(128,128,128,0.25);border-radius:14px;padding:24px;"><h3 style="margin:0 0 8px;">Popup Heading</h3><p style="margin:0 0 18px;opacity:0.8;">Dialog content using native HTML5 dialog element.</p><form method="dialog"><button type="submit" style="padding:8px 16px;border-radius:8px;border:1px solid rgba(128,128,128,0.3);background:transparent;cursor:pointer;">Close</button></form></dialog></div>` },
        { id: 'cookie', name: 'Cookie Notice', group: 'Interactive', html: `<aside id="cookie-consent" data-pd-cookie-consent role="dialog" aria-label="Cookie consent" style="position:fixed;bottom:16px;right:16px;left:16px;max-width:520px;margin:0 auto;padding:16px 20px;border-radius:14px;background:#0f1714;color:#f4f7f5;box-shadow:0 16px 40px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;z-index:9999;"><span style="font-size:13.5px;line-height:1.5;flex:1;min-width:200px;">We use cookies to improve your browsing experience.</span><div style="display:flex;gap:8px;"><button type="button" onclick="localStorage.setItem('pd_cookie_consent','accepted');this.closest('aside').style.display='none'" style="padding:7px 14px;border-radius:8px;border:none;background:#10b981;color:#052e16;font-weight:700;font-size:12.5px;cursor:pointer;">Accept</button><button type="button" onclick="localStorage.setItem('pd_cookie_consent','declined');this.closest('aside').style.display='none'" style="padding:7px 14px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:transparent;color:#fff;font-size:12.5px;cursor:pointer;">Decline</button></div></aside>` }
    ];

    // Deterministic Zero-AI Developer Tools Suite
    const DEVELOPER_TOOLS = [
        {
            id: 'format-code',
            label: 'Format & Beautify',
            description: 'Indents HTML tags cleanly and places structural block elements on separate lines.',
            run: function (html) {
                let formatted = String(html || '');
                formatted = formatted.replace(/>\s+</g, '>\n<');
                const lines = formatted.split('\n');
                let indent = 0;
                const voidTags = /^(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr|!doctype)$/i;
                const out = [];
                for (let raw of lines) {
                    let line = raw.trim();
                    if (!line) continue;
                    if (/^<\/[a-zA-Z0-9-]+>/.test(line)) {
                        indent = Math.max(0, indent - 1);
                    }
                    out.push('  '.repeat(indent) + line);
                    const openMatch = line.match(/^<([a-zA-Z0-9!-]+)(?:\s[^>]*)?>$/);
                    if (openMatch && !voidTags.test(openMatch[1]) && !line.endsWith('/>') && !line.startsWith('<!--')) {
                        indent++;
                    }
                }
                return { html: out.join('\n'), summary: 'Formatted and re-indented HTML document.' };
            }
        },
        {
            id: 'compress-minify',
            label: 'Compress & Minify',
            description: 'Removes non-shared HTML comments and collapses extra whitespace to reduce page size.',
            run: function (html) {
                const origLen = (html || '').length;
                let minified = String(html || '')
                    .replace(/<!--(?!\s*\/?(?:pd|hh):shared)[\s\S]*?-->/g, '')
                    .replace(/>\s+</g, '><')
                    .replace(/\s{2,}/g, ' ')
                    .trim();
                const saved = Math.max(0, origLen - minified.length);
                return { html: minified, summary: `Minified HTML (saved ${saved} bytes).` };
            }
        },
        {
            id: 'clean-empty-tags',
            label: 'Clean Up Empty Tags',
            description: 'Removes empty div, span, and p tags that have no attributes or text content.',
            run: function (html) {
                let count = 0;
                let cleaned = String(html || '');
                let prev;
                do {
                    prev = cleaned;
                    cleaned = cleaned.replace(/<(div|span|p)>\s*<\/\1>/gi, () => {
                        count++;
                        return '';
                    });
                } while (cleaned !== prev);
                return { html: cleaned, summary: count ? `Removed ${count} empty tag(s).` : 'No empty attribute-less tags found.' };
            }
        },
        {
            id: 'purge-unused-css',
            label: 'Purge Unused CSS',
            description: 'Scans inline <style> rules and removes simple class/ID selectors not referenced in the HTML body.',
            run: function (html) {
                let removed = 0;
                const bodyMatch = String(html || '').match(/<body[\s\S]*<\/body>/i);
                const bodyHtml = bodyMatch ? bodyMatch[0] : String(html || '');
                const updated = String(html || '').replace(/<style\b[^>]*>([\s\S]*?)<\/style>/gi, (full, cssContent) => {
                    const cleanedCss = cssContent.replace(/([.#][a-zA-Z0-9_-]+)\s*\{[^}]*\}/g, (rule, selector) => {
                        const name = selector.slice(1);
                        const isId = selector.startsWith('#');
                        const regex = isId
                            ? new RegExp(`\\bid\\s*=\\s*["'][^"']*\\b${name}\\b`, 'i')
                            : new RegExp(`\\bclass\\s*=\\s*["'][^"']*\\b${name}\\b`, 'i');
                        if (!regex.test(bodyHtml)) {
                            removed++;
                            return '';
                        }
                        return rule;
                    });
                    return full.replace(cssContent, cleanedCss);
                });
                return { html: updated, summary: removed ? `Purged ${removed} unused CSS rule(s).` : 'No unused CSS rules detected.' };
            }
        },
        {
            id: 'extract-inline-css',
            label: 'Extract Inline CSS',
            description: 'Moves inline <style> blocks out of HTML into style.css and adds a <link rel="stylesheet"> tag.',
            run: function (html, fileCache) {
                let extracted = [];
                let updated = String(html || '').replace(/<style\b[^>]*>([\s\S]*?)<\/style>/gi, (m, inner) => {
                    if (inner && inner.trim()) extracted.push(inner.trim());
                    return '';
                });
                if (!extracted.length) {
                    return { html: updated, summary: 'No inline <style> blocks found to extract.' };
                }
                if (fileCache) {
                    fileCache['style.css'] = ((fileCache['style.css'] || '') + '\n\n' + extracted.join('\n\n')).trim();
                }
                if (!/<link\b[^>]*href=["']\/?style\.css["']/i.test(updated)) {
                    updated = updated.replace(/<\/head>/i, '  <link rel="stylesheet" href="style.css">\n</head>');
                }
                return { html: updated, summary: `Extracted ${extracted.length} <style> block(s) into style.css.` };
            }
        },
        {
            id: 'extract-inline-js',
            label: 'Extract Inline JS',
            description: 'Moves inline <script> blocks without src attributes into script.js.',
            run: function (html, fileCache) {
                let extracted = [];
                let updated = String(html || '').replace(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/gi, (m, inner) => {
                    if (inner && inner.trim()) extracted.push(inner.trim());
                    return '';
                });
                if (!extracted.length) {
                    return { html: updated, summary: 'No inline <script> blocks found to extract.' };
                }
                if (fileCache) {
                    fileCache['script.js'] = ((fileCache['script.js'] || '') + '\n\n' + extracted.join('\n\n')).trim();
                }
                if (!/<script\b[^>]*src=["']\/?script\.js["']/i.test(updated)) {
                    updated = updated.replace(/<\/body>/i, '  <script src="script.js"><\/script>\n</body>');
                }
                return { html: updated, summary: `Extracted ${extracted.length} inline <script> block(s) into script.js.` };
            }
        },
        {
            id: 'convert-monolith',
            label: 'Convert to Monolith',
            description: 'Inlines project style.css and script.js directly into the HTML file for single-file portability.',
            run: function (html, fileCache) {
                let updated = String(html || '');
                let inlinedCount = 0;
                if (fileCache && fileCache['style.css']) {
                    updated = updated.replace(/<link\b[^>]*href=["']\/?style\.css["'][^>]*>/gi, '');
                    updated = updated.replace(/<\/head>/i, `<style>\n${fileCache['style.css']}\n</style>\n</head>`);
                    inlinedCount++;
                }
                if (fileCache && fileCache['script.js']) {
                    updated = updated.replace(/<script\b[^>]*src=["']\/?script\.js["'][^>]*><\/script>/gi, '');
                    updated = updated.replace(/<\/body>/i, `<script>\n${fileCache['script.js']}\n<\/script>\n</body>`);
                    inlinedCount++;
                }
                return { html: updated, summary: inlinedCount ? `Inlined ${inlinedCount} project asset file(s) into HTML.` : 'No external style.css or script.js buffer to inline.' };
            }
        },
        {
            id: 'inject-seo-boilerplate',
            label: 'Add Missing SEO Tags',
            description: 'Adds missing <title>, meta description, Open Graph (og:*), and Twitter Card tags from page headings and paragraphs.',
            run: function (html) {
                let updated = String(html || '');
                const h1Match = updated.match(/<h1\b[^>]*>([\s\S]*?)<\/h1>/i);
                const pMatch = updated.match(/<p\b[^>]*>([\s\S]*?)<\/p>/i);
                const titleText = (h1Match ? h1Match[1].replace(/<[^>]+>/g, '').trim() : 'Website') || 'Website';
                const descText = (pMatch ? pMatch[1].replace(/<[^>]+>/g, '').trim().slice(0, 155) : titleText);
                const added = [];
                let tagsToInject = '';

                if (!/<title\b/i.test(updated)) {
                    tagsToInject += `  <title>${titleText}</title>\n`;
                    added.push('title');
                }
                if (!/<meta\b[^>]*name=["']description["']/i.test(updated)) {
                    tagsToInject += `  <meta name="description" content="${descText.replace(/"/g, '&quot;')}">\n`;
                    added.push('description');
                }
                if (!/<meta\b[^>]*property=["']og:title["']/i.test(updated)) {
                    tagsToInject += `  <meta property="og:title" content="${titleText.replace(/"/g, '&quot;')}">\n`;
                    tagsToInject += `  <meta property="og:description" content="${descText.replace(/"/g, '&quot;')}">\n`;
                    tagsToInject += `  <meta property="og:type" content="website">\n`;
                    added.push('og:*');
                }
                if (!/<meta\b[^>]*name=["']twitter:card["']/i.test(updated)) {
                    tagsToInject += `  <meta name="twitter:card" content="summary_large_image">\n`;
                    added.push('twitter:card');
                }
                if (tagsToInject) {
                    if (/<\/head>/i.test(updated)) {
                        updated = updated.replace(/<\/head>/i, tagsToInject + '</head>');
                    } else {
                        updated = `<head>\n${tagsToInject}</head>\n` + updated;
                    }
                }
                return { html: updated, summary: added.length ? `Added SEO tags: ${added.join(', ')}.` : 'All standard SEO tags are already present.' };
            }
        },
        {
            id: 'generate-sitemap',
            label: 'Generate Sitemap & Robots',
            description: 'Generates sitemap.xml and robots.txt in the project file buffer from all HTML pages.',
            run: function (html, fileCache, config) {
                const baseUrl = (config && config.liveUrl) ? config.liveUrl.replace(/\/$/, '') : 'https://example.com';
                const files = (config && Array.isArray(config.files)) ? config.files : ['index.html'];
                const htmlPages = files.filter(f => /\.html?$/i.test(f) && f !== '404.html');
                const urls = htmlPages.map(f => {
                    const clean = f.replace(/\.html?$/i, '');
                    const route = clean === 'index' ? '/' : '/' + clean.replace(/^\/+/, '');
                    return `  <url><loc>${baseUrl}${route}</loc></url>`;
                });
                const sitemapXml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join('\n')}\n</urlset>`;
                if (fileCache) {
                    fileCache['sitemap.xml'] = sitemapXml;
                    fileCache['robots.txt'] = `User-agent: *\nAllow: /\nSitemap: ${baseUrl}/sitemap.xml\n`;
                }
                return { html: html, summary: `Generated sitemap.xml (${urls.length} page(s)) and robots.txt.` };
            }
        },
        {
            id: 'normalize-links',
            label: 'Normalize & Fix Links',
            description: 'Converts internal .html links (e.g. about.html or index.html) to clean URL paths (/about, /).',
            run: function (html) {
                let count = 0;
                const updated = String(html || '').replace(/\bhref=(["'])([^"']+\.html?)\1/gi, (full, quote, href) => {
                    if (/^(https?:|mailto:|tel:|\/\/)/i.test(href)) return full;
                    count++;
                    let clean = href.replace(/^\.\//, '').replace(/\.html?$/i, '');
                    if (clean === 'index' || clean === '/index') clean = '/';
                    else if (!clean.startsWith('/')) clean = '/' + clean;
                    return `href=${quote}${clean}${quote}`;
                });
                return { html: updated, summary: count ? `Normalized ${count} internal link(s) to clean URLs.` : 'All internal links already use clean URLs.' };
            }
        },
        {
            id: 'force-https',
            label: 'Force HTTPS Links',
            description: 'Upgrades insecure http:// image, script, stylesheet, and media URLs to https://.',
            run: function (html) {
                let count = 0;
                const updated = String(html || '').replace(/\b(src|href)=(["'])http:\/\/([^"']+)\2/gi, (full, attr, quote, rest) => {
                    if (rest.startsWith('localhost') || rest.startsWith('127.0.0.1')) return full;
                    count++;
                    return `${attr}=${quote}https://${rest}${quote}`;
                });
                return { html: updated, summary: count ? `Upgraded ${count} resource URL(s) to HTTPS.` : 'No insecure http:// resource URLs found.' };
            }
        }
    ];

    const SHORTCUT_GROUPS = [
        {
            title: 'Editor Workspace',
            items: [
                { label: 'Save & Publish', keys: 'Cmd/Ctrl + S' },
                { label: 'Undo / Redo', keys: 'Cmd/Ctrl + Z / Cmd+Shift+Z' },
                { label: 'Toggle Edit Mode On/Off', keys: 'Cmd/Ctrl + Shift + E' },
                { label: 'Open Structure Tree', keys: 'Cmd/Ctrl + I' },
                { label: 'Open Component Library', keys: 'Cmd/Ctrl + Shift + L' },
                { label: 'Switch Device Viewport', keys: 'Cmd/Ctrl + Shift + M' },
                { label: 'Open Version History', keys: 'Cmd/Ctrl + Shift + H' },
                { label: 'Keyboard Shortcuts Help', keys: 'Cmd/Ctrl + / or ?' }
            ]
        },
        {
            title: 'Selected Element in Preview',
            items: [
                { label: 'Edit Text in Place', keys: 'Double-Click or Enter' },
                { label: 'Duplicate Element', keys: 'Cmd/Ctrl + D' },
                { label: 'Copy / Cut / Paste Element', keys: 'Cmd/Ctrl + C / X / V' },
                { label: 'Paste Style (Classes & Inline Style)', keys: 'Cmd/Ctrl + Shift + V' },
                { label: 'Move Element Up / Down', keys: 'Alt + Up / Alt + Down' },
                { label: 'Select Parent Element', keys: 'Alt + Left' },
                { label: 'Delete Selected Element', keys: 'Delete / Backspace' },
                { label: 'Close Popover / Cancel Edit', keys: 'Esc' }
            ]
        }
    ];

    global.PAGEDOCK_EDITOR_TOOLS = {
        LIBRARY_SECTIONS: LIBRARY_SECTIONS,
        LIBRARY_ELEMENTS: LIBRARY_ELEMENTS,
        DEVELOPER_TOOLS: DEVELOPER_TOOLS,
        SHORTCUT_GROUPS: SHORTCUT_GROUPS
    };
})(typeof window !== 'undefined' ? window : globalThis);

