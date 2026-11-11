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
    return {
      tag: el.tagName,
      tagIndex: tagIdx(el),
      id: el.id || '',
      className: typeof el.className === 'string' ? el.className.trim().split(/\\s+/).slice(0, 2).join('.') : '',
      label: el.tagName.toLowerCase() + (el.id ? '#' + el.id : ''),
      hint: visibleText(el).slice(0, 36),
      delivered: !!(fInfo && fInfo.delivered),
      hideFp: fp,
      hiddenInEditor: el.getAttribute('data-pd-editor-hidden') === 'true',
      childIndex: childIdx(el),
      siblingCount: el.parentElement ? el.parentElement.children.length : 1,
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

