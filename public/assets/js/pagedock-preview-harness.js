/**
 * PageDock Sandboxed Iframe Preview Harness & Visual Inspector (Zero-AI Edition)
 * Provides:
 * 1. Head Pre-Parser (<script data-pagedock-ui>): History hash guard, audio/video auto-muter,
 *    buffer-dom-ready signaling, and stuck fullscreen overlay unsticker.
 * 2. Visual Inspector & Direct Manipulation Engine:
 *    - Hover, Selection, and Semantic Section boundary outlines
 *    - Click-to-select (element-selected) & Right-click menu (element-context-menu)
 *    - Double-click in-place contenteditable text editing (begin-text-edit / element-text-edit)
 *    - Hold-and-drag DOM reordering (element-reorder)
 *    - Three-way live DOM patch (live-patch / patch-element)
 *    - Structure tree serialization (request-structure / page-structure)
 *    - Temporary editor-only element hiding (hide-elements)
 *    - Form preview interception (form-preview-submit) & keyboard shortcut forwarding (iframe-key)
 */

(function (global) {
    'use strict';

    const HEAD_PRE_PARSER_SCRIPT = `<script data-pagedock-ui>(function(){
  var H = history, replace = H.replaceState, L = location;
  function here(u) { return typeof u === 'string' && u.charAt(0) === '#' ? L.href.split('#')[0] + u : u; }
  function rs(s, t, u) { return arguments.length > 2 ? replace.call(H, s, t, here(u)) : replace.call(H, s, t); }
  try { H.pushState = rs; H.replaceState = rs; } catch (e) {}
  function muteAll(root) {
    if (!root || !root.querySelectorAll) return;
    var media = root.querySelectorAll('audio, video');
    for (var i = 0; i < media.length; i++) {
      try { media[i].muted = true; media[i].autoplay = false; } catch (e) {}
    }
  }
  document.addEventListener('play', function(e) {
    if (e.target && (e.target.tagName === 'AUDIO' || e.target.tagName === 'VIDEO')) {
      try { e.target.muted = true; } catch (err) {}
    }
  }, true);
  if (typeof MutationObserver !== 'undefined') {
    new MutationObserver(function(muts) {
      for (var i = 0; i < muts.length; i++) {
        var added = muts[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          if (added[j].nodeType === 1) muteAll(added[j]);
        }
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
  document.addEventListener('DOMContentLoaded', function() {
    muteAll(document);
    try { window.parent.postMessage({ type: 'buffer-dom-ready' }, '*'); } catch (e) {}
    setTimeout(function() {
      var vw = window.innerWidth || 1024, vh = window.innerHeight || 768;
      var nodes = document.body ? document.body.children : [];
      for (var i = 0; i < nodes.length; i++) {
        var el = nodes[i];
        if (!el || el.tagName === 'SCRIPT' || el.tagName === 'STYLE') continue;
        var st = window.getComputedStyle(el);
        if ((st.position === 'fixed' || st.position === 'absolute') && parseFloat(st.zIndex) >= 999) {
          var r = el.getBoundingClientRect();
          if (r.width >= vw * 0.6 && r.height >= vh * 0.6) {
            var hasInteractive = el.querySelector('button, a, input, form, select, textarea');
            var txtLen = (el.textContent || '').trim().length;
            if (!hasInteractive && txtLen < 40) {
              el.setAttribute('data-pagedock-overlay-hidden', 'true');
              el.style.setProperty('display', 'none', 'important');
              try { window.parent.postMessage({ type: 'preview-overlay-stuck' }, '*'); } catch (e) {}
            }
          }
        }
      }
    }, 1200);
  });
})();<\/script>`;

    const BODY_INSPECTOR_SCRIPT = `<script data-pagedock-inspector>(function(){
  if (window.__pdInspectorActive) return;
  window.__pdInspectorActive = true;

  var editMode = true;
  var hovered = null;
  var selected = null;
  var highlightedSection = null;
  var editingEl = null;
  var editingOrigText = '';
  var hiddenFps = [];
  var dragState = null;

  var styleEl = document.createElement('style');
  styleEl.setAttribute('data-pagedock-inspector-styles', 'true');
  styleEl.textContent = [
    '[data-pd-hover] { outline: 2px dashed rgba(4, 120, 87, 0.65) !important; outline-offset: -1px !important; cursor: pointer !important; }',
    '[data-pd-selected] { outline: 2px solid #047857 !important; outline-offset: -1px !important; }',
    '[data-pd-section-hl] { outline: 1.5px dashed rgba(4, 120, 87, 0.35) !important; outline-offset: -1px !important; }',
    '[data-pd-editing] { outline: 2px solid #0284c7 !important; outline-offset: 1px !important; cursor: text !important; min-width: 12px; }',
    '[data-pd-editor-hidden="true"] { display: none !important; }'
  ].join('\\n');
  (document.head || document.documentElement).appendChild(styleEl);

  var SECTION_TAGS = { SECTION: 1, ARTICLE: 1, MAIN: 1, HEADER: 1, FOOTER: 1, NAV: 1, ASIDE: 1 };
  var IGNORED_TAGS = { HTML: 1, HEAD: 1, SCRIPT: 1, STYLE: 1, META: 1, LINK: 1, TITLE: 1, BASE: 1 };

  function isIgnored(el) {
    if (!el || el.nodeType !== 1) return true;
    if (IGNORED_TAGS[el.tagName]) return true;
    if (el.hasAttribute('data-pagedock-ui') || el.hasAttribute('data-pagedock-inspector') || el.hasAttribute('data-pagedock-inspector-styles') || el.hasAttribute('data-pd-drop-indicator')) return true;
    return false;
  }

  function tagIdx(el) {
    if (!el || !el.tagName) return 0;
    var list = document.getElementsByTagName(el.tagName);
    for (var i = 0; i < list.length; i++) {
      if (list[i] === el) return i;
    }
    return 0;
  }

  function childIdx(el) {
    if (!el || !el.parentElement) return 0;
    var ch = el.parentElement.children;
    var idx = 0;
    for (var i = 0; i < ch.length; i++) {
      if (ch[i] === el) return idx;
      if (!isIgnored(ch[i])) idx++;
    }
    return 0;
  }

  function findSection(el) {
    var cur = el;
    while (cur && cur !== document.body && cur !== document.documentElement) {
      if (SECTION_TAGS[cur.tagName]) return cur;
      cur = cur.parentElement;
    }
    return null;
  }

  function fpOf(el) {
    if (!el || !el.tagName) return '';
    return el.tagName + ':' + tagIdx(el) + ':' + (el.id || '') + ':' + (typeof el.className === 'string' ? el.className.trim().split(/\\s+/).slice(0, 2).join('.') : '');
  }

  function visibleText(el) {
    if (!el) return '';
    return (el.innerText || el.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 160);
  }

  function isTextOnly(el) {
    if (!el || el.nodeType !== 1) return false;
    var inlineAllowed = { STRONG: 1, EM: 1, B: 1, I: 1, U: 1, SPAN: 1, SMALL: 1, MARK: 1, SUB: 1, SUP: 1, BR: 1, CODE: 1, A: 1 };
    for (var i = 0; i < el.children.length; i++) {
      if (!inlineAllowed[el.children[i].tagName]) return false;
    }
    return visibleText(el).length > 0 || el.tagName === 'P' || /^H[1-6]$/.test(el.tagName) || el.tagName === 'SPAN' || el.tagName === 'BUTTON' || el.tagName === 'A' || el.tagName === 'LI' || el.tagName === 'LABEL';
  }

  function extractTextParts(el) {
    var parts = [];
    if (!el) return parts;
    function walk(node) {
      for (var i = 0; i < node.childNodes.length; i++) {
        var c = node.childNodes[i];
        if (c.nodeType === 3) {
          var t = c.nodeValue;
          if (t && t.trim().length > 0) parts.push(t.trim());
        } else if (c.nodeType === 1 && c.tagName !== 'SCRIPT' && c.tagName !== 'STYLE') {
          walk(c);
        }
      }
    }
    walk(el);
    if (parts.length === 0 && isTextOnly(el)) {
      var fallback = (el.textContent || '').trim();
      if (fallback) parts.push(fallback);
    }
    return parts.slice(0, 8);
  }

  function ancestorChain(el) {
    var list = [];
    var cur = el ? el.parentElement : null;
    while (cur && cur !== document.documentElement) {
      if (!isIgnored(cur)) {
        list.unshift({ tag: cur.tagName, tagIndex: tagIdx(cur), id: cur.id || '', className: typeof cur.className === 'string' ? cur.className : '' });
      }
      if (cur === document.body) break;
      cur = cur.parentElement;
    }
    return list;
  }

  function formInfo(el) {
    var formEl = el && el.closest ? el.closest('form') : null;
    if (!formEl) return null;
    var action = (formEl.getAttribute('action') || '').trim();
    var delivered = action === '/__pd/form' || action === '/__hh/form' || action.indexOf('/f/') !== -1 || action.indexOf('/api/forms/') !== -1;
    var nameInput = formEl.querySelector('input[name="_form"]');
    var successInput = formEl.querySelector('input[name="_success"]');
    var redirectInput = formEl.querySelector('input[name="_redirect"]');
    var errorInput = formEl.querySelector('input[name="_error"]');
    return {
      tagIndex: tagIdx(formEl),
      action: action,
      delivered: delivered,
      name: nameInput ? nameInput.value : (formEl.getAttribute('name') || formEl.getAttribute('id') || 'Contact'),
      success: successInput ? successInput.value : '',
      redirect: redirectInput ? redirectInput.value : '',
      error: errorInput ? errorInput.value : ''
    };
  }

  function rgbToHex(rgb) {
    if (!rgb || typeof rgb !== 'string') return '';
    if (rgb.charAt(0) === '#') return rgb;
    var m = rgb.match(/rgba?\\((\\d+),\\s*(\\d+),\\s*(\\d+)/i);
    if (!m) return '';
    var r = parseInt(m[1], 10), g = parseInt(m[2], 10), b = parseInt(m[3], 10);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  }

  function extractBgUrl(bg) {
    if (!bg || bg === 'none') return '';
    var m = bg.match(/url\\(["']?([^"')]+)["']?\\)/i);
    return m ? m[1] : '';
  }

  function clearHover() {
    if (hovered) {
      hovered.removeAttribute('data-pd-hover');
      hovered = null;
    }
  }

  function clearSectionHL() {
    if (highlightedSection) {
      highlightedSection.removeAttribute('data-pd-section-hl');
      highlightedSection = null;
    }
  }

  function hlSection(sec) {
    if (highlightedSection === sec) return;
    clearSectionHL();
    if (sec && sec !== selected) {
      highlightedSection = sec;
      highlightedSection.setAttribute('data-pd-section-hl', 'true');
    }
  }

  function selEl(el) {
    if (selected && selected !== el) {
      selected.removeAttribute('data-pd-selected');
    }
    selected = el;
    if (selected) {
      selected.removeAttribute('data-pd-hover');
      selected.setAttribute('data-pd-selected', 'true');
      hlSection(findSection(selected));
    } else {
      clearSectionHL();
    }
  }

  function buildElementPayload(el, type) {
    var r = el.getBoundingClientRect();
    var cs = window.getComputedStyle(el);
    var par = el.parentElement;
    var sibCount = 0;
    if (par) {
      for (var i = 0; i < par.children.length; i++) {
        if (!isIgnored(par.children[i])) sibCount++;
      }
    }
    var sec = findSection(el);
    var linkEl = el.tagName === 'A' ? el : (el.closest ? el.closest('a') : null);
    var mediaUrl = '';
    if (el.tagName === 'IMG' || el.tagName === 'VIDEO' || el.tagName === 'AUDIO' || el.tagName === 'IFRAME') {
      mediaUrl = el.getAttribute('src') || '';
    }
    var bgUrl = extractBgUrl(cs.backgroundImage);
    return {
      type: type || 'element-selected',
      html: el.outerHTML,
      tag: el.tagName,
      tagIndex: tagIdx(el),
      text: visibleText(el),
      textParts: extractTextParts(el),
      hasChildren: el.children.length > 0,
      hasParent: !!par && par !== document.body && par !== document.documentElement,
      parentTag: par ? par.tagName : '',
      parentTagIndex: par ? tagIdx(par) : 0,
      childIndex: childIdx(el),
      siblingCount: sibCount,
      isTextOnly: isTextOnly(el),
      ancestors: ancestorChain(el),
      form: formInfo(el),
      hideFp: fpOf(el),
      mediaUrl: mediaUrl,
      bgUrl: bgUrl,
      attrs: {
        id: el.id || '',
        className: typeof el.className === 'string' ? el.className : '',
        href: linkEl ? (linkEl.getAttribute('href') || '') : (el.getAttribute('href') || ''),
        target: linkEl ? (linkEl.getAttribute('target') || '') : (el.getAttribute('target') || ''),
        src: el.getAttribute('src') || '',
        alt: el.getAttribute('alt') || '',
        value: el.getAttribute('value') || '',
        placeholder: el.getAttribute('placeholder') || '',
        style: el.getAttribute('style') || ''
      },
      computedStyles: {
        color: rgbToHex(cs.color),
        backgroundColor: rgbToHex(cs.backgroundColor),
        fontSize: cs.fontSize || '',
        fontWeight: String(cs.fontWeight || '400'),
        textAlign: (cs.textAlign || 'left').replace('start', 'left').replace('end', 'right'),
        backgroundImage: cs.backgroundImage || 'none',
        width: r.width,
        height: r.height
      },
      rect: { x: r.left, y: r.top, width: r.width, height: r.height },
      sectionTag: sec ? sec.tagName : '',
      sectionTagIndex: sec ? tagIdx(sec) : 0
    };
  }

  function postSel(el) {
    if (!el || isIgnored(el)) return;
    selEl(el);
    window.parent.postMessage(buildElementPayload(el, 'element-selected'), '*');
  }

  function endTextEdit(commit) {
    if (!editingEl) return;
    var el = editingEl;
    editingEl = null;
    el.removeAttribute('contenteditable');
    el.removeAttribute('data-pd-editing');
    if (commit) {
      var newText = el.innerText || el.textContent || '';
      window.parent.postMessage({
        type: 'element-text-edit',
        tag: el.tagName,
        tagIndex: tagIdx(el),
        text: newText,
        html: el.outerHTML
      }, '*');
    } else {
      el.textContent = editingOrigText;
    }
  }

  function startTextEdit(el) {
    if (!el || isIgnored(el)) return;
    if (editingEl && editingEl !== el) endTextEdit(true);
    editingEl = el;
    editingOrigText = el.textContent || '';
    el.setAttribute('contenteditable', 'true');
    el.setAttribute('data-pd-editing', 'true');
    el.focus();
    window.parent.postMessage({ type: 'text-edit-start' }, '*');
  }

  // ——— 1. Canvas Hold-and-Drag Reordering Engine ———
  function mkGhost(el) {
    var r = el.getBoundingClientRect();
    var g = el.cloneNode(true);
    g.style.cssText = 'position:fixed;pointer-events:none;z-index:99999;opacity:0.72;width:' + Math.max(80, Math.min(420, r.width)) + 'px;max-height:84px;overflow:hidden;border-radius:8px;box-shadow:0 10px 28px rgba(0,0,0,0.28),0 0 0 2px #047857;transform:scale(0.94);background:#ffffff;';
    g.setAttribute('data-pagedock-ui', 'true');
    document.body.appendChild(g);
    return g;
  }

  function mkInd() {
    var d = document.createElement('div');
    d.style.cssText = 'position:fixed;pointer-events:none;z-index:99998;background:#047857;border-radius:2px;box-shadow:0 0 0 2px rgba(4,120,87,0.22);transition:all 0.08s ease;';
    d.setAttribute('data-pagedock-ui', 'true');
    document.body.appendChild(d);
    return d;
  }

  var edge = { timer: null, v: 0, fn: null };
  function edgeScroll(y, fn) {
    var h = document.documentElement.clientHeight || window.innerHeight || 768;
    var z = Math.min(60, h / 6);
    var v = y < z ? -(z - y) : (y > h - z ? y - (h - z) : 0);
    edge.fn = fn;
    edge.v = v;
    if (!v) {
      edgeStop();
      return;
    }
    if (!edge.timer) {
      edge.timer = setInterval(function() {
        window.scrollBy(0, Math.ceil(Math.abs(edge.v) / 3) * (edge.v < 0 ? -1 : 1));
        if (edge.fn) edge.fn();
      }, 16);
    }
  }

  function edgeStop() {
    if (edge.timer) {
      clearInterval(edge.timer);
      edge.timer = null;
    }
    edge.fn = null;
  }

  function validChildren(par) {
    var list = [];
    if (!par || !par.children) return list;
    for (var i = 0; i < par.children.length; i++) {
      if (!isIgnored(par.children[i])) list.push(par.children[i]);
    }
    return list;
  }

  function isRow(par) {
    if (!par) return false;
    var cs = window.getComputedStyle(par);
    if (cs.display.indexOf('flex') !== -1 && cs.flexDirection.indexOf('row') === 0) return true;
    if (cs.display.indexOf('grid') !== -1 && cs.gridTemplateColumns.split(' ').filter(Boolean).length > 1) return true;
    var ch = validChildren(par);
    if (ch.length < 2) return false;
    var a = ch[0].getBoundingClientRect(), b = ch[1].getBoundingClientRect();
    return Math.abs(a.top - b.top) < Math.max(a.height, b.height) * 0.5;
  }

  function calcDrop(par, x, y) {
    var ch = validChildren(par);
    var best = ch.length, bd = 1e9, r0 = isRow(par);
    for (var i = 0; i < ch.length; i++) {
      var b = ch[i].getBoundingClientRect();
      var mid = r0 ? (b.left + b.width / 2) : (b.top + b.height / 2);
      var cur = r0 ? x : y;
      if (cur < mid && Math.abs(cur - mid) < bd) {
        bd = Math.abs(cur - mid);
        best = i;
      }
    }
    if (ch.length > 0) {
      var la = ch[ch.length - 1].getBoundingClientRect();
      if ((r0 ? x : y) >= (r0 ? la.right : la.bottom)) best = ch.length;
    }
    return best;
  }

  function posInd(ind, par, di) {
    var ch = validChildren(par);
    if (!ch.length) return;
    var r0 = isRow(par);
    var ref = di < ch.length ? ch[di].getBoundingClientRect() : ch[ch.length - 1].getBoundingClientRect();
    if (!ref) return;
    if (r0) {
      var l = di < ch.length ? ref.left : ref.right;
      ind.style.left = (l - 1.5) + 'px';
      ind.style.top = ref.top + 'px';
      ind.style.width = '3px';
      ind.style.height = Math.max(16, ref.height) + 'px';
    } else {
      var t = di < ch.length ? ref.top : ref.bottom;
      ind.style.left = ref.left + 'px';
      ind.style.top = (t - 1.5) + 'px';
      ind.style.width = Math.max(24, ref.width) + 'px';
      ind.style.height = '3px';
    }
  }

  function endDrag() {
    edgeStop();
    if (!dragState) return;
    if (dragState.timer) {
      clearTimeout(dragState.timer);
      dragState.timer = null;
    }
    if (dragState.ghost && dragState.ghost.parentNode) dragState.ghost.parentNode.removeChild(dragState.ghost);
    if (dragState.ind && dragState.ind.parentNode) dragState.ind.parentNode.removeChild(dragState.ind);
    if (dragState.el) {
      dragState.el.style.opacity = '';
      dragState.el.style.cursor = '';
    }
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    dragState = null;
  }

  function aimDrag(x, y) {
    if (!dragState || !dragState.active) return;
    dragState.ghost.style.left = (x + 12) + 'px';
    dragState.ghost.style.top = (y - 18) + 'px';
    var di = calcDrop(dragState.parent, x, y);
    posInd(dragState.ind, dragState.parent, di);
    dragState.di = di;
  }

  document.addEventListener('pointerdown', function(e) {
    if (!editMode || editingEl || e.button !== 0 || !e.isPrimary) return;
    var el = e.target;
    if (!el || isIgnored(el) || el === document.body || el === document.documentElement) return;
    while (el && el.parentElement && el.parentElement !== document.body && window.getComputedStyle(el).display === 'inline') {
      el = el.parentElement;
    }
    var par = el.parentElement;
    var canDrag = !!(par && validChildren(par).length >= 2);
    if (!canDrag) return;

    dragState = {
      el: el,
      parent: par,
      id: e.pointerId,
      sx: e.clientX,
      sy: e.clientY,
      fromIndex: childIdx(el),
      di: childIdx(el),
      ready: e.pointerType !== 'touch',
      active: false,
      ghost: null,
      ind: null,
      timer: null
    };

    if (e.pointerType === 'touch') {
      dragState.timer = setTimeout(function() {
        if (dragState) dragState.ready = true;
      }, 320);
    }
  }, true);

  document.addEventListener('pointermove', function(e) {
    if (!editMode || !dragState || e.pointerId !== dragState.id) return;
    var dx = e.clientX - dragState.sx;
    var dy = e.clientY - dragState.sy;
    var dist = Math.sqrt(dx * dx + dy * dy);

    if (!dragState.ready && dist > 10) {
      endDrag();
      return;
    }
    if (dragState.ready && !dragState.active && dist > 6) {
      dragState.active = true;
      clearHover();
      dragState.el.style.opacity = '0.35';
      document.body.style.cursor = 'grabbing';
      document.body.style.userSelect = 'none';
      dragState.ghost = mkGhost(dragState.el);
      dragState.ind = mkInd();
    }
    if (dragState && dragState.active) {
      e.preventDefault();
      var px = e.clientX, py = e.clientY;
      aimDrag(px, py);
      edgeScroll(py, function() { aimDrag(px, py); });
    }
  }, true);

  document.addEventListener('pointerup', function(e) {
    if (!dragState || e.pointerId !== dragState.id) return;
    if (dragState.active) {
      e.preventDefault();
      e.stopPropagation();
      var par = dragState.parent;
      var el = dragState.el;
      var fi = dragState.fromIndex;
      var rawDi = dragState.di;
      var ti = rawDi > fi ? rawDi - 1 : rawDi;
      endDrag();
      if (ti !== fi && par && el) {
        var siblings = validChildren(par);
        var refNode = rawDi < siblings.length ? siblings[rawDi] : null;
        par.insertBefore(el, refNode);
        selEl(el);
        window.parent.postMessage({
          type: 'element-reorder',
          parentTag: par.tagName,
          parentTagIndex: tagIdx(par),
          childTag: el.tagName,
          fromIndex: fi,
          toIndex: ti
        }, '*');
        postStructure();
      }
      return;
    }
    endDrag();
  }, true);

  document.addEventListener('pointercancel', function() {
    if (dragState) endDrag();
  }, true);

  // ——— 2. Library-to-Canvas Drag & Drop + Page Token Sampler ———
  var libDragActive = false;
  var libDragName = '';
  var libDragKind = 'section';
  var libDropInd = null;
  var libDropTarget = null;
  var NO_HOLD = /^(P|H1|H2|H3|H4|H5|H6|A|BUTTON|LABEL|SPAN|STRONG|EM|B|I|U|SMALL|MARK|SUB|SUP|CODE|PRE|IMG|INPUT|TEXTAREA|SELECT|OPTION|SVG|VIDEO|AUDIO|IFRAME|BR|HR|UL|OL|TABLE|THEAD|TBODY|TR|SUMMARY|HTML|HEAD)$/;

  function canHold(el) {
    if (!el || el.nodeType !== 1 || isIgnored(el)) return false;
    if (el === document.body) return true;
    if (NO_HOLD.test(el.tagName.toUpperCase())) return false;
    if (el.closest && el.closest('svg')) return false;
    return window.getComputedStyle(el).display !== 'inline';
  }

  function findDropTarget(x, y, kind) {
    if (kind === 'section') {
      var root = document.querySelector('main') || document.body;
      var secs = validChildren(root);
      var bestIdx = secs.length;
      var bestDist = 1e9;
      for (var i = 0; i < secs.length; i++) {
        var r = secs[i].getBoundingClientRect();
        var topDist = Math.abs(y - r.top);
        var botDist = Math.abs(y - r.bottom);
        if (topDist < bestDist) { bestDist = topDist; bestIdx = i; }
        if (botDist < bestDist) { bestDist = botDist; bestIdx = i + 1; }
      }
      return { parent: root, insertIndex: bestIdx };
    }
    var hit = document.elementFromPoint(x, y) || document.body;
    while (hit && hit !== document.body && !canHold(hit)) {
      hit = hit.parentElement;
    }
    if (!hit || hit === document.documentElement) hit = document.body;
    return { parent: hit, insertIndex: calcDrop(hit, x, y) };
  }

  function showLibDropInd(target) {
    if (!libDropInd) {
      libDropInd = document.createElement('div');
      libDropInd.setAttribute('data-pagedock-ui', 'true');
      libDropInd.style.cssText = 'position:fixed;z-index:99999;pointer-events:none;background:#047857;border-radius:2px;box-shadow:0 0 0 3px rgba(4,120,87,0.22);';
      var pill = document.createElement('div');
      pill.style.cssText = 'position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font:700 11px/1 sans-serif;color:#ffffff;background:#047857;padding:4px 10px;border-radius:999px;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
      libDropInd.appendChild(pill);
      document.body.appendChild(libDropInd);
    }
    var par = target.parent;
    var ch = validChildren(par);
    var di = target.insertIndex;
    var parRect = par.getBoundingClientRect();
    var y = parRect.top + 12;
    if (ch.length > 0) {
      var ref = di < ch.length ? ch[di].getBoundingClientRect() : ch[ch.length - 1].getBoundingClientRect();
      y = di < ch.length ? ref.top : ref.bottom;
    }
    var vh = document.documentElement.clientHeight || window.innerHeight || 768;
    y = Math.max(14, Math.min(vh - 14, y));
    libDropInd.style.left = Math.max(8, parRect.left + 8) + 'px';
    libDropInd.style.width = Math.max(60, parRect.width - 16) + 'px';
    libDropInd.style.height = '3px';
    libDropInd.style.top = (y - 1.5) + 'px';
    var containerLabel = par === document.body ? 'body' : (par.tagName.toLowerCase() + (par.id ? '#' + par.id : ''));
    libDropInd.firstChild.textContent = '+ Drop ' + (libDragName || 'Component') + ' in ' + containerLabel;
    libDropTarget = target;
  }

  function hideLibDropInd() {
    if (libDropInd && libDropInd.parentNode) libDropInd.parentNode.removeChild(libDropInd);
    libDropInd = null;
    libDropTarget = null;
  }

  function pageTokens(container) {
    var accent = '';
    var btnClass = '';
    var btns = document.querySelectorAll('a[class*="btn"], a[class*="button"], button, [class*="cta"]');
    for (var i = 0; i < btns.length; i++) {
      if (isIgnored(btns[i])) continue;
      var cs = window.getComputedStyle(btns[i]);
      var bg = rgbToHex(cs.backgroundColor);
      if (bg && bg !== '#000000' && bg !== '#ffffff' && cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent') {
        accent = bg;
        btnClass = typeof btns[i].className === 'string' ? btns[i].className.trim() : '';
        break;
      }
    }
    var targetEl = container || document.body;
    var tcs = window.getComputedStyle(targetEl);
    return {
      accent: accent,
      buttonClass: btnClass,
      textColor: rgbToHex(tcs.color),
      bgColor: rgbToHex(tcs.backgroundColor),
      fontFamily: tcs.fontFamily || ''
    };
  }

  document.addEventListener('dragover', function(e) {
    if (!editMode) return;
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
    var target = findDropTarget(e.clientX, e.clientY, libDragKind);
    showLibDropInd(target);
    edgeScroll(e.clientY, function() {
      showLibDropInd(findDropTarget(e.clientX, e.clientY, libDragKind));
    });
  }, true);

  document.addEventListener('dragleave', function(e) {
    if (!e.relatedTarget || e.relatedTarget === document.documentElement) {
      edgeStop();
      hideLibDropInd();
    }
  }, true);

  document.addEventListener('drop', function(e) {
    if (!editMode) return;
    e.preventDefault();
    e.stopPropagation();
    edgeStop();
    var target = libDropTarget || findDropTarget(e.clientX, e.clientY, libDragKind);
    hideLibDropInd();
    if (target && target.parent) {
      window.parent.postMessage({
        type: 'library-drop',
        kind: libDragKind,
        containerTag: target.parent.tagName,
        containerTagIndex: tagIdx(target.parent),
        insertIndex: target.insertIndex,
        tokens: pageTokens(target.parent)
      }, '*');
    }
  }, true);

  // Mouse & Pointer Listeners in Capture Phase
  document.addEventListener('mousemove', function(e) {
    if (!editMode || editingEl || dragState) return;
    var t = e.target;
    if (!t || isIgnored(t) || t === document.body || t === document.documentElement) {
      clearHover();
      return;
    }
    if (hovered !== t) {
      clearHover();
      if (t !== selected) {
        hovered = t;
        hovered.setAttribute('data-pd-hover', 'true');
      }
      hlSection(findSection(t));
    }
  }, true);

  document.addEventListener('mouseleave', function() {
    clearHover();
  }, true);

  document.addEventListener('click', function(e) {
    if (editingEl && editingEl.contains(e.target)) return;
    if (editingEl && !editingEl.contains(e.target)) {
      endTextEdit(true);
    }
    if (!editMode) {
      var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
      if (a) {
        var href = a.getAttribute('href') || '';
        if (href && href.charAt(0) === '/' && href.charAt(1) !== '/') {
          e.preventDefault();
          window.parent.postMessage({ type: 'navigate-page', path: href }, '*');
        }
      }
      return;
    }
    var t = e.target;
    if (!t || isIgnored(t) || t === document.documentElement) return;
    e.preventDefault();
    e.stopPropagation();
    postSel(t);
  }, true);

  document.addEventListener('dblclick', function(e) {
    if (!editMode) return;
    var t = e.target;
    if (!t || isIgnored(t) || t === document.body || t === document.documentElement) return;
    if (isTextOnly(t)) {
      e.preventDefault();
      e.stopPropagation();
      startTextEdit(t);
    }
  }, true);

  document.addEventListener('contextmenu', function(e) {
    if (!editMode) return;
    var t = e.target;
    if (!t || isIgnored(t) || t === document.documentElement) return;
    e.preventDefault();
    e.stopPropagation();
    selEl(t);
    var payload = buildElementPayload(t, 'element-context-menu');
    payload.clientX = e.clientX;
    payload.clientY = e.clientY;
    window.parent.postMessage(payload, '*');
  }, true);

  // Intercept Form Submissions in Preview
  document.addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var info = formInfo(e.target);
    window.parent.postMessage({
      type: 'form-preview-submit',
      form: info || { name: 'Form', success: 'Thank you! Form preview simulated.' }
    }, '*');
  }, true);

  // Keyboard Handling Inside Iframe
  document.addEventListener('keydown', function(e) {
    if (editingEl) {
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        endTextEdit(false);
      } else if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        e.stopPropagation();
        endTextEdit(true);
      }
      return;
    }
    if (e.key === 'Escape') {
      window.parent.postMessage({ type: 'iframe-escape' }, '*');
      return;
    }
    var active = document.activeElement;
    var isInput = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable);
    if (isInput && !(e.metaKey || e.ctrlKey)) return;

    window.parent.postMessage({
      type: 'iframe-key',
      key: e.key,
      metaKey: !!e.metaKey,
      ctrlKey: !!e.ctrlKey,
      shiftKey: !!e.shiftKey,
      altKey: !!e.altKey
    }, '*');
  }, true);

  // Serialize DOM Structure for Structure Tree Panel
  function serializeNode(el, depth) {
    if (!el || isIgnored(el) || depth > 12) return null;
    var children = [];
    for (var i = 0; i < el.children.length; i++) {
      var childNode = serializeNode(el.children[i], depth + 1);
      if (childNode) children.push(childNode);
    }
    var fInfo = el.tagName === 'FORM' ? formInfo(el) : null;
    var fp = fpOf(el);
    var par = el.parentElement;
    return {
      tag: el.tagName,
      tagIndex: tagIdx(el),
      parentTag: par ? par.tagName : '',
      parentTagIndex: par ? tagIdx(par) : 0,
      id: el.id || '',
      className: typeof el.className === 'string' ? el.className.trim().split(/\\s+/).slice(0, 2).join('.') : '',
      label: el.tagName.toLowerCase() + (el.id ? '#' + el.id : ''),
      hint: visibleText(el).slice(0, 36),
      delivered: !!(fInfo && fInfo.delivered),
      hideFp: fp,
      hiddenInEditor: el.getAttribute('data-pd-editor-hidden') === 'true',
      childIndex: childIdx(el),
      siblingCount: par ? validChildren(par).length : 1,
      children: children
    };
  }

  function postStructure() {
    if (!document.body) return;
    var tree = serializeNode(document.body, 0);
    window.parent.postMessage({ type: 'page-structure', tree: tree }, '*');
  }

  // Three-way DOM Merge for Live Patching
  function parseFrag(html) {
    var t = document.createElement('template');
    t.innerHTML = (html || '').trim();
    return t.content.firstElementChild;
  }

  function applyHiddenFps() {
    var all = document.querySelectorAll('[data-pd-editor-hidden]');
    for (var i = 0; i < all.length; i++) {
      all[i].removeAttribute('data-pd-editor-hidden');
    }
    if (!hiddenFps || !hiddenFps.length) return;
    var els = document.body ? document.body.querySelectorAll('*') : [];
    for (var j = 0; j < els.length; j++) {
      var fp = fpOf(els[j]);
      if (hiddenFps.indexOf(fp) !== -1) {
        els[j].setAttribute('data-pd-editor-hidden', 'true');
      }
    }
  }

  // Listen to Parent Messages
  window.addEventListener('message', function(e) {
    var d = e.data;
    if (!d || typeof d !== 'object' || !d.type) return;

    if (d.type === 'library-drag') {
      libDragActive = !!d.active;
      libDragName = d.name || '';
      libDragKind = d.kind || 'section';
      if (!libDragActive) {
        edgeStop();
        hideLibDropInd();
      }
      return;
    }

    if (d.type === 'edit-mode') {
      editMode = !!d.enabled;
      if (!editMode) {
        if (editingEl) endTextEdit(true);
        clearHover();
        selEl(null);
      }
      return;
    }

    if (d.type === 'begin-text-edit') {
      if (selected) startTextEdit(selected);
      return;
    }

    if (d.type === 'clear-selection') {
      if (editingEl) endTextEdit(true);
      clearHover();
      selEl(null);
      return;
    }

    if (d.type === 'select-element') {
      var list = document.getElementsByTagName(d.tag || 'DIV');
      var target = list[d.tagIndex || 0];
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        postSel(target);
      }
      return;
    }

    if (d.type === 'select-parent') {
      if (selected && selected.parentElement && selected.parentElement !== document.body && selected.parentElement !== document.documentElement) {
        postSel(selected.parentElement);
      }
      return;
    }

    if (d.type === 'hover-element') {
      clearHover();
      if (d.tag) {
        var hList = document.getElementsByTagName(d.tag);
        var hEl = hList[d.tagIndex || 0];
        if (hEl && hEl !== selected) {
          hovered = hEl;
          hovered.setAttribute('data-pd-hover', 'true');
        }
      }
      return;
    }

    if (d.type === 'reveal-element') {
      var rList = document.getElementsByTagName(d.tag || 'DIV');
      var rEl = rList[d.tagIndex || 0];
      if (rEl) {
        rEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        postSel(rEl);
      }
      return;
    }

    if (d.type === 'mark-selected') {
      var mList = document.getElementsByTagName(d.tag || 'DIV');
      var mEl = mList[d.tagIndex || 0];
      if (mEl) selEl(mEl);
      return;
    }

    if (d.type === 'request-structure') {
      postStructure();
      return;
    }

    if (d.type === 'structure-context-menu') {
      var cList = document.getElementsByTagName(d.tag || 'DIV');
      var cEl = cList[d.tagIndex || 0];
      if (cEl) {
        selEl(cEl);
        var payload = buildElementPayload(cEl, 'element-context-menu');
        payload.screenPos = { x: d.x, y: d.y };
        window.parent.postMessage(payload, '*');
      }
      return;
    }

    if (d.type === 'patch-element') {
      var pList = document.getElementsByTagName(d.tag || 'DIV');
      var pEl = pList[d.tagIndex || 0];
      if (pEl && typeof d.html === 'string') {
        var replacement = parseFrag(d.html);
        if (replacement) {
          pEl.replaceWith(replacement);
          selEl(replacement);
          postStructure();
        }
      }
      return;
    }

    if (d.type === 'live-patch') {
      var lList = document.getElementsByTagName(d.tag || 'DIV');
      var lEl = lList[d.tagIndex || 0];
      var ok = false;
      if (lEl && d.after) {
        var nextFrag = parseFrag(d.after);
        if (nextFrag) {
          lEl.replaceWith(nextFrag);
          selEl(nextFrag);
          ok = true;
        }
      }
      window.parent.postMessage({ type: 'live-patch-result', seq: d.seq, ok: ok }, '*');
      postStructure();
      return;
    }

    if (d.type === 'element-append' || d.type === 'insert-section') {
      if (typeof d.html === 'string' && document.body) {
        var frag = parseFrag(d.html);
        if (frag) {
          if (selected && selected !== document.body && selected.parentElement) {
            selected.insertAdjacentElement('afterend', frag);
          } else {
            var footer = document.body.querySelector('footer');
            if (footer) {
              footer.insertAdjacentElement('beforebegin', frag);
            } else {
              document.body.appendChild(frag);
            }
          }
          frag.scrollIntoView({ behavior: 'smooth', block: 'center' });
          postSel(frag);
          postStructure();
        }
      }
      return;
    }

    if (d.type === 'hide-elements') {
      hiddenFps = Array.isArray(d.fps) ? d.fps : [];
      applyHiddenFps();
      postStructure();
      return;
    }
  });

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(postStructure, 60);
  } else {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(postStructure, 60);
    });
  }
})();<\/script>`;

    function injectIntoHtml(rawHtml) {
        let doc = String(rawHtml || '');
        if (!doc.includes('data-pagedock-ui')) {
            if (/<head\b[^>]*>/i.test(doc)) {
                doc = doc.replace(/<head\b[^>]*>/i, (m) => m + '\n' + HEAD_PRE_PARSER_SCRIPT);
            } else {
                doc = HEAD_PRE_PARSER_SCRIPT + '\n' + doc;
            }
        }
        if (!doc.includes('data-pagedock-inspector')) {
            if (/<\/body>/i.test(doc)) {
                doc = doc.replace(/<\/body>/i, BODY_INSPECTOR_SCRIPT + '\n</body>');
            } else {
                doc = doc + '\n' + BODY_INSPECTOR_SCRIPT;
            }
        }
        return doc;
    }

    global.PAGEDOCK_PREVIEW_HARNESS = {
        HEAD_PRE_PARSER_SCRIPT: HEAD_PRE_PARSER_SCRIPT,
        BODY_INSPECTOR_SCRIPT: BODY_INSPECTOR_SCRIPT,
        injectIntoHtml: injectIntoHtml
    };
})(typeof window !== 'undefined' ? window : globalThis);

