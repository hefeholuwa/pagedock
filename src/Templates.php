<?php

class Templates {
    /**
     * Get all curated templates
     */
    public static function getAll(): array {
        return self::registry();
    }

    /**
     * Get template by slug
     */
    public static function get(string $slug): ?array {
        $all = self::registry();
        return $all[$slug] ?? null;
    }

    /**
     * Get list of unique categories
     */
    public static function getCategories(): array {
        $categories = [];
        foreach (self::registry() as $template) {
            $cat = $template['category'];
            if (!in_array($cat, $categories)) {
                $categories[] = $cat;
            }
        }
        return $categories;
    }

    /**
     * Master template registry
     */
    private static function registry(): array {
        return [
            'futuristic-saas' => [
                'slug' => 'futuristic-saas',
                'title' => 'Futuristic SaaS Landing',
                'category' => 'Landing Page',
                'description' => 'High-conversion dark theme with lime accents, feature cards, pricing table, and interactive CTA.',
                'badge' => 'Popular',
                'tags' => ['dark', 'saas', 'modern', 'pricing'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aura — Next-Gen AI Cloud</title>
  <style>
    :root { --bg: #0b0c10; --card: #151821; --border: #232838; --accent: #C7F547; --text: #f3f4f6; --muted: #949ba8; }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 40px 20px; line-height: 1.6; }
    .container { max-width: 900px; margin: 0 auto; }
    header { text-align: center; margin-bottom: 50px; }
    .badge { display: inline-block; padding: 4px 14px; background: rgba(199, 245, 71, 0.12); color: var(--accent); border: 1px solid rgba(199, 245, 71, 0.3); border-radius: 50px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 16px; }
    h1 { font-size: clamp(34px, 6vw, 54px); font-weight: 800; line-height: 1.1; margin-bottom: 16px; letter-spacing: -0.03em; }
    h1 span { color: var(--accent); }
    p.lead { color: var(--muted); font-size: clamp(16px, 2vw, 19px); max-width: 600px; margin: 0 auto 30px; }
    .cta-row { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .btn { padding: 12px 24px; border-radius: 8px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; font-size: 14px; }
    .btn-accent { background: var(--accent); color: #000; }
    .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin: 60px 0; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 26px; }
    .card h3 { font-size: 18px; margin-bottom: 8px; }
    .card p { color: var(--muted); font-size: 14px; }
    .pricing { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 36px 24px; text-align: center; margin-bottom: 50px; }
    .price { font-size: 42px; font-weight: 800; color: var(--accent); margin: 16px 0; }
    footer { text-align: center; color: var(--muted); font-size: 13px; border-top: 1px solid var(--border); padding-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <span class="badge">Aura Cloud v2.0</span>
      <h1>Build faster with <span>Zero-Coldstart</span> Cloud</h1>
      <p class="lead">Supercharge your web services with autonomous edge scaling and sub-10ms global delivery.</p>
      <div class="cta-row">
        <a href="#pricing" class="btn btn-accent">Start Free Trial &rarr;</a>
        <a href="#features" class="btn btn-outline">Explore Docs</a>
      </div>
    </header>

    <div class="grid" id="features">
      <div class="card">
        <h3>⚡ Real-Time Ingestion</h3>
        <p>Process millions of concurrent telemetry streams with automated backpressure and zero packet drops.</p>
      </div>
      <div class="card">
        <h3>🛡️ Edge Security</h3>
        <p>Automatic DDoS filtering, TLS termination, and web application firewall rules enabled by default.</p>
      </div>
      <div class="card">
        <h3>📊 Observability</h3>
        <p>Full-fidelity tracing and log aggregation with instant metrics visualization and alerting.</p>
      </div>
    </div>

    <div class="pricing" id="pricing">
      <span class="badge">Pro Plan</span>
      <h2>Predictable Monthly Pricing</h2>
      <div class="price">$19<span style="font-size: 16px; color: var(--muted);">/month</span></div>
      <p style="color:var(--muted); margin-bottom: 24px;">Everything included. Unlimited domains, bandwidth, and edge storage.</p>
      <a href="#" class="btn btn-accent">Claim Access Now</a>
    </div>

    <footer>
      &copy; 2026 Aura Technologies. Hosted securely on PageDock.
    </footer>
  </div>
</body>
</html>
HTML
            ],

            'developer-portfolio' => [
                'slug' => 'developer-portfolio',
                'title' => 'Developer Portfolio',
                'category' => 'Portfolio',
                'description' => 'Minimalist dark developer showcase with project grid, tech stack tags, and contact form.',
                'badge' => 'Free',
                'tags' => ['portfolio', 'dark', 'minimal', 'developer'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alex Rivera — Senior Systems Engineer</title>
  <style>
    :root { --bg: #090a0f; --card: #12141c; --border: #222636; --accent: #C7F547; --text: #f3f4f6; --muted: #8e97a6; }
    * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 40px 20px; line-height: 1.6; }
    .container { max-width: 680px; margin: 0 auto; }
    .status { display: inline-flex; align-items: center; gap: 8px; color: var(--accent); font-size: 13px; font-weight: 600; margin-bottom: 14px; }
    .status::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--accent); }
    h1 { font-size: clamp(32px, 5vw, 44px); font-weight: 800; letter-spacing: -0.03em; margin-bottom: 12px; }
    p.bio { color: var(--muted); font-size: 16px; margin-bottom: 30px; }
    .tags { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 40px; }
    .tag { background: var(--card); border: 1px solid var(--border); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-family: monospace; }
    .section-title { font-size: 20px; font-weight: 700; margin-bottom: 16px; }
    .project-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; transition: border-color 0.2s; }
    .project-card:hover { border-color: var(--accent); }
    .project-card h3 { font-size: 16px; margin-bottom: 6px; }
    .project-card p { color: var(--muted); font-size: 13px; }
    form { display: flex; flex-direction: column; gap: 12px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; }
    input, textarea { background: #090a0f; border: 1px solid var(--border); color: #fff; padding: 12px; border-radius: 6px; font-size: 14px; outline: none; }
    input:focus, textarea:focus { border-color: var(--accent); }
    button { background: var(--accent); color: #000; border: none; padding: 12px; border-radius: 6px; font-weight: 700; cursor: pointer; }
    footer { text-align: center; color: var(--muted); font-size: 12px; margin-top: 40px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="status">Available for Q4 contracts &amp; advisory</div>
    <h1>Alex Rivera</h1>
    <p class="bio">Staff Infrastructure Engineer specialized in distributed systems, high-throughput caches, and developer tooling.</p>

    <div class="tags">
      <span class="tag">Rust</span>
      <span class="tag">Go</span>
      <span class="tag">PHP 8.5</span>
      <span class="tag">SQLite/WAL</span>
      <span class="tag">Linux/eBPF</span>
    </div>

    <h2 class="section-title">Selected Projects</h2>
    <div class="project-card">
      <h3>HyperScale Cache &rarr;</h3>
      <p>Low-latency in-memory cache proxy achieving sub-millisecond p99 latencies under 500k RPS.</p>
    </div>
    <div class="project-card">
      <h3>Orbit Engine &rarr;</h3>
      <p>Static deployment orchestrator built with pure asynchronous socket primitives.</p>
    </div>

    <h2 class="section-title" style="margin-top: 40px;">Send a Note</h2>
    <form action="/api/forms/alex-portfolio" method="POST">
      <input type="text" name="name" placeholder="Your Name" required>
      <input type="email" name="email" placeholder="Your Email" required>
      <textarea name="message" rows="4" placeholder="Project details or inquiry..." required></textarea>
      <button type="submit">Send Direct Message &rarr;</button>
    </form>

    <footer>
      Alex Rivera &middot; Hosted on PageDock
    </footer>
  </div>
</body>
</html>
HTML
            ],

            'executive-resume' => [
                'slug' => 'executive-resume',
                'title' => 'Executive Resume / CV',
                'category' => 'Resume',
                'description' => 'Print-ready clean CV layout featuring career timeline, core competencies, and education.',
                'badge' => 'Clean',
                'tags' => ['resume', 'cv', 'editorial', 'professional'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jordan Taylor — Curriculum Vitae</title>
  <style>
    :root { --bg: #ffffff; --text: #1a1a1a; --sub: #555555; --border: #e5e5e5; --accent: #0f172a; }
    @media (prefers-color-scheme: dark) {
      :root { --bg: #0f1117; --text: #f3f4f6; --sub: #9ca3af; --border: #262a36; --accent: #C7F547; }
    }
    * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 40px 20px; line-height: 1.6; }
    .paper { max-width: 720px; margin: 0 auto; }
    header { border-bottom: 2px solid var(--border); padding-bottom: 24px; margin-bottom: 30px; }
    h1 { font-size: 32px; font-weight: 800; }
    .contact-line { color: var(--sub); font-size: 14px; margin-top: 6px; }
    h2 { font-size: 16px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent); margin: 28px 0 16px; border-bottom: 1px solid var(--border); padding-bottom: 4px; }
    .timeline-item { margin-bottom: 22px; }
    .timeline-header { display: flex; justify-content: space-between; font-weight: 700; font-size: 15px; }
    .company { color: var(--sub); font-size: 13px; margin-bottom: 6px; }
    ul { margin-left: 20px; font-size: 13.5px; color: var(--sub); }
    li { margin-bottom: 4px; }
    .skills-pills { display: flex; gap: 6px; flex-wrap: wrap; }
    .pill { border: 1px solid var(--border); padding: 3px 10px; border-radius: 4px; font-size: 12px; }
  </style>
</head>
<body>
  <div class="paper">
    <header>
      <h1>Jordan Taylor</h1>
      <p class="contact-line">VP of Product &middot; San Francisco, CA &middot; jordan@example.com &middot; (555) 234-5678</p>
    </header>

    <h2>Professional Summary</h2>
    <p style="font-size:14px; color:var(--sub);">Product leader with 10+ years scaling SaaS platforms from zero to $40M ARR. Champion of developer experience, user-centric metrics, and lean cross-functional teams.</p>

    <h2>Experience</h2>
    <div class="timeline-item">
      <div class="timeline-header">
        <span>Head of Product Platform</span>
        <span>2022 — Present</span>
      </div>
      <div class="company">Veloce Cloud Inc.</div>
      <ul>
        <li>Grew developer ecosystem from 12k to 180k active monthly developers.</li>
        <li>Led migration to micro-frontend architecture resulting in 40% faster release cadence.</li>
      </ul>
    </div>

    <div class="timeline-item">
      <div class="timeline-header">
        <span>Senior Product Manager</span>
        <span>2019 — 2022</span>
      </div>
      <div class="company">Kinetics AI</div>
      <ul>
        <li>Owned enterprise ingestion pipeline handling 2B+ events per day.</li>
        <li>Managed cross-functional squad of 14 engineers and designers.</li>
      </ul>
    </div>

    <h2>Skills &amp; Methodologies</h2>
    <div class="skills-pills">
      <span class="pill">Product Strategy</span>
      <span class="pill">OKRs &amp; KPIs</span>
      <span class="pill">Developer APIs</span>
      <span class="pill">SQL / Analytics</span>
      <span class="pill">System Architecture</span>
    </div>
  </div>
</body>
</html>
HTML
            ],

            'early-access-waitlist' => [
                'slug' => 'early-access-waitlist',
                'title' => 'Early Access / Waitlist',
                'category' => 'Waitlist',
                'description' => 'Viral pre-launch landing page with live countdown timer and email signup form.',
                'badge' => 'High Convert',
                'tags' => ['waitlist', 'prelaunch', 'countdown', 'email'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Synapse — The AI Second Brain</title>
  <style>
    :root { --bg: #07080a; --card: #11131a; --border: #1f2330; --accent: #C7F547; --text: #ffffff; --muted: #8b92a4; }
    * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); min-height: 100vh; display: grid; place-items: center; padding: 30px 20px; text-align: center; }
    .box { max-width: 540px; width: 100%; }
    .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; background: rgba(199, 245, 71, 0.12); color: var(--accent); border-radius: 99px; font-size: 12px; font-weight: 700; margin-bottom: 20px; border: 1px solid rgba(199, 245, 71, 0.25); }
    h1 { font-size: clamp(34px, 6vw, 48px); font-weight: 800; letter-spacing: -0.03em; line-height: 1.15; margin-bottom: 14px; }
    h1 span { color: var(--accent); }
    p.desc { color: var(--muted); font-size: 16px; margin-bottom: 30px; }
    .countdown { display: flex; justify-content: center; gap: 14px; margin-bottom: 30px; }
    .timer-block { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 12px 18px; min-width: 70px; }
    .timer-num { font-size: 26px; font-weight: 800; color: var(--accent); }
    .timer-lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; }
    form { display: flex; gap: 8px; margin-bottom: 14px; }
    input { flex: 1; background: var(--card); border: 1px solid var(--border); color: #fff; padding: 14px; border-radius: 8px; font-size: 15px; outline: none; }
    input:focus { border-color: var(--accent); }
    button { background: var(--accent); color: #000; border: none; padding: 14px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .social-proof { font-size: 12px; color: var(--muted); }
  </style>
</head>
<body>
  <div class="box">
    <span class="badge">&bull; Private Beta Dropping Soon</span>
    <h1>Think faster with <span>Synapse AI</span></h1>
    <p class="desc">The spatial knowledge graph that organizes your thoughts, bookmarks, and code snippets in real time.</p>

    <div class="countdown">
      <div class="timer-block"><div class="timer-num" id="d">14</div><div class="timer-lbl">Days</div></div>
      <div class="timer-block"><div class="timer-num" id="h">09</div><div class="timer-lbl">Hours</div></div>
      <div class="timer-block"><div class="timer-num" id="m">42</div><div class="timer-lbl">Mins</div></div>
      <div class="timer-block"><div class="timer-num" id="s">18</div><div class="timer-lbl">Secs</div></div>
    </div>

    <form action="/api/forms/synapse-waitlist" method="POST">
      <input type="email" name="email" placeholder="Enter your email for early access" required>
      <button type="submit">Join Waitlist &rarr;</button>
    </form>

    <p class="social-proof">⚡ Join 3,420+ engineers and designers on the waitlist</p>
  </div>
</body>
</html>
HTML
            ],

            'creative-agency' => [
                'slug' => 'creative-agency',
                'title' => 'Creative Agency & Studio',
                'category' => 'Agency',
                'description' => 'Bold editorial design studio template showcasing branding, case studies, and contact forms.',
                'badge' => 'Bold',
                'tags' => ['agency', 'design', 'studio', 'creative'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vogue &amp; Craft — Design Atelier</title>
  <style>
    :root { --bg: #0e0e11; --card: #17171d; --border: #292934; --accent: #C7F547; --text: #f0f0f4; --sub: #9090a2; }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 50px 20px; line-height: 1.5; }
    .container { max-width: 860px; margin: 0 auto; }
    .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 60px; }
    .brand { font-weight: 800; font-size: 20px; letter-spacing: -0.02em; }
    h1 { font-size: clamp(38px, 7vw, 68px); font-weight: 800; letter-spacing: -0.04em; line-height: 1.05; margin-bottom: 24px; }
    p.lead { font-size: 20px; color: var(--sub); max-width: 580px; margin-bottom: 40px; }
    .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 18px; margin: 50px 0; }
    .service-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 26px; }
    .service-card span { color: var(--accent); font-family: monospace; font-size: 13px; font-weight: 700; }
    .service-card h3 { font-size: 18px; margin: 8px 0; }
    .service-card p { font-size: 14px; color: var(--sub); }
    footer { border-top: 1px solid var(--border); padding-top: 30px; display: flex; justify-content: space-between; color: var(--sub); font-size: 13px; flex-wrap: wrap; gap: 10px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="nav">
      <div class="brand">Vogue &amp; Craft</div>
      <a href="#contact" style="color:var(--accent); text-decoration:none; font-weight:700;">Initiate Project &rarr;</a>
    </div>

    <h1>We craft digital identities that cannot be ignored.</h1>
    <p class="lead">An independent digital studio operating at the intersection of typography, high-velocity motion, and brutalist aesthetics.</p>

    <div class="services-grid">
      <div class="service-card">
        <span>01 // BRANDING</span>
        <h3>Brand Strategy &amp; Systems</h3>
        <p>Comprehensive identity guides, custom vector iconography, and expressive motion tokens.</p>
      </div>
      <div class="service-card">
        <span>02 // DIGITAL</span>
        <h3>Interactive Web Platforms</h3>
        <p>Bespoke web applications designed for conversion and engineered with pixel-perfect precision.</p>
      </div>
      <div class="service-card">
        <span>03 // 3D &amp; ART</span>
        <h3>Spatial 3D &amp; Motion</h3>
        <p>Generative audio-reactive visuals, CGI product demos, and dynamic WebGL experiences.</p>
      </div>
    </div>

    <footer>
      <span>&copy; 2026 Vogue &amp; Craft Atelier</span>
      <span>Published with PageDock</span>
    </footer>
  </div>
</body>
</html>
HTML
            ],

            'link-in-bio' => [
                'slug' => 'link-in-bio',
                'title' => 'Link in Bio Profile',
                'category' => 'Social',
                'description' => 'Mobile-optimized personal hub with social buttons, links cards, and newsletter signup.',
                'badge' => 'Mobile',
                'tags' => ['links', 'bio', 'mobile', 'social'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sarah Chen — Links</title>
  <style>
    :root { --bg: #0c0d12; --card: #161822; --border: #252838; --accent: #C7F547; --text: #fff; --sub: #949cae; }
    * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 40px 16px; min-height: 100vh; display: flex; justify-content: center; }
    .hub { width: 100%; max-width: 440px; text-align: center; }
    .avatar { width: 88px; height: 88px; border-radius: 50%; background: var(--accent); color: #000; font-size: 34px; font-weight: 800; display: grid; place-items: center; margin: 0 auto 16px; border: 3px solid var(--border); }
    h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
    p.handle { color: var(--accent); font-size: 13px; font-family: monospace; margin-bottom: 8px; }
    p.bio { color: var(--sub); font-size: 14px; margin-bottom: 24px; }
    .links-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px; }
    .link-item { display: flex; align-items: center; justify-content: space-between; background: var(--card); border: 1px solid var(--border); padding: 14px 18px; border-radius: 12px; text-decoration: none; color: var(--text); font-weight: 600; font-size: 14px; transition: 0.15s ease; }
    .link-item:hover { border-color: var(--accent); transform: translateY(-2px); }
    .badge { font-size: 11px; background: rgba(199, 245, 71, 0.15); color: var(--accent); padding: 2px 8px; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="hub">
    <div class="avatar">SC</div>
    <h1>Sarah Chen</h1>
    <p class="handle">@sarahchen_tech</p>
    <p class="bio">AI Researcher &amp; Founder. Writing about spatial computing, distributed models, and startup engineering.</p>

    <div class="links-list">
      <a href="#" class="link-item">
        <span>🎙️ Latest Podcast Episode</span>
        <span class="badge">New</span>
      </a>
      <a href="#" class="link-item">
        <span>📖 My Substack Newsletter</span>
        <span>&rarr;</span>
      </a>
      <a href="#" class="link-item">
        <span>📦 GitHub Open Source</span>
        <span>&rarr;</span>
      </a>
      <a href="#" class="link-item">
        <span>📅 Book a 1:1 Advisory Session</span>
        <span>&rarr;</span>
      </a>
    </div>

    <p style="color:var(--sub); font-size:12px;">Powered by PageDock</p>
  </div>
</body>
</html>
HTML
            ],

            'ai-analytics-dashboard' => [
                'slug' => 'ai-analytics-dashboard',
                'title' => 'AI Analytics Dashboard',
                'category' => 'Dashboard',
                'description' => 'Production dashboard UI with responsive sidebar, KPI metric cards, and activity stream.',
                'badge' => 'Pro',
                'tags' => ['dashboard', 'analytics', 'metrics', 'admin'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nexus — AI Observability</title>
  <style>
    :root { --bg: #090a0f; --card: #12141c; --border: #212534; --accent: #C7F547; --text: #f3f4f6; --sub: #8e96a8; }
    * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 24px; line-height: 1.5; }
    .layout { max-width: 1040px; margin: 0 auto; }
    .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
    h1 { font-size: 24px; font-weight: 800; }
    .pill { background: rgba(199,245,71,0.12); color: var(--accent); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; border: 1px solid rgba(199,245,71,0.25); }
    .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .metric-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 18px; }
    .metric-lbl { font-size: 12px; color: var(--sub); text-transform: uppercase; margin-bottom: 4px; }
    .metric-val { font-size: 28px; font-weight: 800; color: var(--accent); }
    .chart-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; margin-bottom: 24px; }
    .bars { display: flex; align-items: flex-end; gap: 8px; height: 120px; margin-top: 20px; padding-top: 10px; }
    .bar { flex: 1; background: var(--accent); opacity: 0.8; border-radius: 4px 4px 0 0; }
  </style>
</head>
<body>
  <div class="layout">
    <div class="top">
      <div>
        <h1>Inference Telemetry</h1>
        <p style="color:var(--sub); font-size: 13px;">Live performance metrics across 12 distributed clusters</p>
      </div>
      <span class="pill">&bull; Systems Operational</span>
    </div>

    <div class="metrics">
      <div class="metric-card">
        <div class="metric-lbl">Total Requests</div>
        <div class="metric-val">48.2M</div>
      </div>
      <div class="metric-card">
        <div class="metric-lbl">Avg Response Time</div>
        <div class="metric-val">12.4ms</div>
      </div>
      <div class="metric-card">
        <div class="metric-lbl">Model Token Load</div>
        <div class="metric-val">1.8B</div>
      </div>
      <div class="metric-card">
        <div class="metric-lbl">Cache Hit Ratio</div>
        <div class="metric-val">94.6%</div>
      </div>
    </div>

    <div class="chart-card">
      <h3>Hourly Ingestion Throughput</h3>
      <div class="bars">
        <div class="bar" style="height: 35%;"></div>
        <div class="bar" style="height: 55%;"></div>
        <div class="bar" style="height: 40%;"></div>
        <div class="bar" style="height: 75%;"></div>
        <div class="bar" style="height: 60%;"></div>
        <div class="bar" style="height: 90%;"></div>
        <div class="bar" style="height: 100%;"></div>
        <div class="bar" style="height: 85%;"></div>
        <div class="bar" style="height: 70%;"></div>
      </div>
    </div>
  </div>
</body>
</html>
HTML
            ],

            'tech-conference-event' => [
                'slug' => 'tech-conference-event',
                'title' => 'Conference & Summit Event',
                'category' => 'Event',
                'description' => 'Event landing page with keynotes, speaker cards, schedule agenda, and ticket RSVP.',
                'badge' => 'Event',
                'tags' => ['conference', 'summit', 'rsvp', 'tickets'],
                'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PulseConf 2026 — Systems &amp; Web</title>
  <style>
    :root { --bg: #090a0d; --card: #13151f; --border: #232738; --accent: #C7F547; --text: #f8fafc; --muted: #94a3b8; }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background-color: var(--bg); color: var(--text); padding: 50px 20px; line-height: 1.6; }
    .container { max-width: 800px; margin: 0 auto; }
    header { text-align: center; margin-bottom: 50px; }
    .date-pill { background: rgba(199,245,71,0.12); color: var(--accent); padding: 4px 14px; border-radius: 50px; font-size: 13px; font-weight: 700; display: inline-block; margin-bottom: 16px; border: 1px solid rgba(199,245,71,0.3); }
    h1 { font-size: clamp(34px, 6vw, 52px); font-weight: 800; line-height: 1.1; margin-bottom: 12px; }
    h1 span { color: var(--accent); }
    .speakers { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 40px 0; }
    .speaker-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-align: center; }
    .speaker-card h3 { font-size: 16px; margin-bottom: 4px; }
    .speaker-card p { font-size: 12px; color: var(--muted); }
    .rsvp-box { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 30px; text-align: center; }
    form { display: flex; gap: 8px; max-width: 440px; margin: 20px auto 0; flex-wrap: wrap; }
    input { flex: 1; background: #090a0d; border: 1px solid var(--border); color: #fff; padding: 12px; border-radius: 6px; outline: none; }
    button { background: var(--accent); color: #000; border: none; padding: 12px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <span class="date-pill">Nov 18-20, 2026 &bull; Online &amp; San Francisco</span>
      <h1>PulseConf: <span>The Systems Era</span></h1>
      <p style="color:var(--muted); font-size:17px;">Join 4,000+ engineers building high-scale distributed architectures and autonomous agents.</p>
    </header>

    <h2 style="font-size:20px; margin-bottom:14px;">Keynote Speakers</h2>
    <div class="speakers">
      <div class="speaker-card">
        <h3>Dr. Aris Vance</h3>
        <p>Chief Scientist @ Kernel Dynamics</p>
      </div>
      <div class="speaker-card">
        <h3>Elena Rostova</h3>
        <p>VP Architecture @ CloudWire</p>
      </div>
      <div class="speaker-card">
        <h3>Marcus Thorne</h3>
        <p>Creator of HyperDB</p>
      </div>
    </div>

    <div class="rsvp-box">
      <h3>Reserve Your Digital Pass</h3>
      <p style="color:var(--muted); font-size:14px; margin-top:6px;">Livestream keynotes, workshop breakout tracks, and live Q&amp;A sessions.</p>
      <form action="/api/forms/pulseconf-rsvp" method="POST">
        <input type="email" name="email" placeholder="Enter your work email" required>
        <button type="submit">Claim Free Pass</button>
      </form>
    </div>
  </div>
</body>
</html>
HTML
            ],
        ];
    }
}
