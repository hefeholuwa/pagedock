/**
 * PageDock Injected Preview Harness (Visual Inspector & Interaction Engine)
 * 
 * Injected into preview iframes to enable:
 * 1. Automatic audio/video muting on load
 * 2. Stuck overlay/loader detection & unsticking
 * 3. Element hover and selection highlighting
 * 4. Section discovery and breadcrumb ancestor tracking
 * 5. In-place text editing on double-click
 * 6. Drag-and-drop element reordering with live insertion guides
 * 7. Preview form interception (/__pd/form)
 * 8. Full bidirectional postMessage protocol with PageDock parent editor
 */

(function () {
  'use strict';

  // --- 1. Audio / Video Silencing & Dom Ready Signaling ---
  var heldMedia = [];
  var mutedMedia = [];
  var isHoldingMedia = true;

  function muteNode(m) {
    if (isHoldingMedia && !m.muted) {
      m.muted = true;
      mutedMedia.push(m);
    }
  }

  function holdNode(m) {
    if (isHoldingMedia && m && typeof m.pause === 'function') {
      muteNode(m);
      if (!m.paused) {
        m.pause();
        if (heldMedia.indexOf(m) < 0) heldMedia.push(m);
      }
    }
  }

  function scanMedia(node) {
    if (!node || node.nodeType !== 1) return;
    if (/^(AUDIO|VIDEO)$/i.test(node.tagName)) muteNode(node);
    if (node.querySelectorAll) {
      var list = node.querySelectorAll('audio, video');
      for (var i = 0; i < list.length; i++) muteNode(list[i]);
    }
  }

  var mediaObserver = new MutationObserver(function (mutations) {
    for (var i = 0; i < mutations.length; i++) {
      var added = mutations[i].addedNodes;
      for (var j = 0; j < added.length; j++) scanMedia(added[j]);
    }
  });

  if (document.documentElement) {
    mediaObserver.observe(document.documentElement, { childList: true, subtree: true });
    scanMedia(document.documentElement);
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      scanMedia(document.documentElement);
    });
  }

  document.addEventListener('play', function (e) {
    holdNode(e.target);
  }, true);

  function signalDomReady() {
    window.parent.postMessage({ type: 'buffer-dom-ready' }, '*');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', signalDomReady);
  } else {
    setTimeout(signalDomReady, 0);
  }

  // --- 2. Fullscreen Loader Unsticking ---
  function unstickOverlays() {
    if (!document.body) return;
    var root = document.documentElement;
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var samplePoints = [[0.5, 0.5], [0.2, 0.2], [0.8, 0.2], [0.2, 0.8], [0.8, 0.8]];
    var hitMap = new Map();

    samplePoints.forEach(function (pt) {
      var el = document.elementFromPoint(vw * pt[0], vh * pt[1]);
      var found = null;
      for (var cur = el; cur && cur !== document.body && cur !== root; cur = cur.parentElement) {
        if (cur.hasAttribute && cur.hasAttribute('data-pagedock-ui')) {
          found = null;
          break;
        }
        var cs = window.getComputedStyle(cur);
        if (cs.position === 'fixed' || cs.position === 'absolute') {
          var rect = cur.getBoundingClientRect();
          var w = Math.min(rect.right, vw) - Math.max(rect.left, 0);
          var h = Math.min(rect.bottom, vh) - Math.max(rect.top, 0);
          if (w > 0 && h > 0 && (w * h >= vw * vh * 0.6)) {
            // Check if thin (loader/spinner, not interactive dialog)
            if (!cur.matches('dialog, [role=dialog], [role=alertdialog]') && !cur.querySelector('button, a, input, form')) {
              var textLen = (cur.textContent || '').trim().length;
              if (textLen <= 120) found = cur;
            }
          }
        }
      }
      if (found) hitMap.set(found, (hitMap.get(found) || 0) + 1);
    });

    hitMap.forEach(function (count, el) {
      if (count >= 3) {
        var s = document.createElement('style');
        s.setAttribute('data-pagedock-ui', '');
        s.textContent = '[data-pagedock-overlay-hidden]{display:none!important}';
        document.head.appendChild(s);
        el.setAttribute('data-pagedock-overlay-hidden', '');
        window.parent.postMessage({ type: 'preview-overlay-stuck' }, '*');
      }
    });
  }

  window.addEventListener('load', function () { setTimeout(unstickOverlays, 3000); });
  setTimeout(unstickOverlays, 8000);

  // --- 3. Visual Inspector & Element Interaction State ---
  var hoveredEl = null;
  var selectedEl = null;
  var highlightedSection = null;
  var editingEl = null;
  var editingOriginalText = '';
  var aiEnabled = true; // Enabled by default in visual mode
  window.__aiEnabled = true;
  var currentModel = { provider: '', model: '', label: '' };
  var dragState = null;

  function isInert(el) {
    if (!el || el.nodeType !== 1) return true;
    if (el === document.body || el === document.documentElement) return true;
    if (el.hasAttribute && el.hasAttribute('data-pagedock-ui')) return true;
    return false;
  }

  function findSection(el) {
    var tags = ['SECTION', 'ARTICLE', 'MAIN', 'HEADER', 'FOOTER', 'NAV', 'ASIDE'];
    var cur = el.parentElement;
    while (cur && cur !== document.body && cur !== document.documentElement) {
      if (tags.indexOf(cur.tagName) !== -1) return cur;
      cur = cur.parentElement;
    }
    cur = el;
    while (cur.parentElement && cur.parentElement !== document.body) {
      cur = cur.parentElement;
    }
    return cur !== el ? cur : null;
  }

  function clearSectionHighlight() {
    if (highlightedSection) {
      highlightedSection.style.outline = '';
      highlightedSection.style.outlineOffset = '';
      highlightedSection = null;
    }
  }

  function highlightSection(sec) {
    clearSectionHighlight();
    if (!sec) return;
    highlightedSection = sec;
    sec.style.outline = '1.5px dashed rgba(125, 211, 252, 0.35)';
    sec.style.outlineOffset = '2px';
  }

  function tagIndex(el) {
    var all = document.getElementsByTagName(el.tagName);
    for (var i = 0; i < all.length; i++) {
      if (all[i] === el) return i;
    }
    return 0;
  }

  function sameTagIndex(el) {
    var p = el.parentElement;
    if (!p) return 0;
    var n = 0;
    for (var i = 0; i < p.children.length; i++) {
      var c = p.children[i];
      if (c === el) return n;
      if (c.tagName === el.tagName) n++;
    }
    return n;
  }

  function childIndex(el) {
    var p = el.parentElement;
    if (!p) return 0;
    for (var i = 0; i < p.children.length; i++) {
      if (p.children[i] === el) return i;
    }
    return 0;
  }

  function ancestorChain(el) {
    var out = [];
    var cur = el;
    var guard = 0;
    while (cur && cur !== document.documentElement && guard++ < 40) {
      var label = cur.tagName.toLowerCase();
      if (cur.id) label += '#' + cur.id;
      else if (cur.classList && cur.classList.length) label += '.' + cur.classList[0];
      out.unshift({
        tag: cur.tagName,
        tagIndex: tagIndex(cur),
        sameTagIndex: sameTagIndex(cur),
        label: label
      });
      if (cur === document.body) break;
      cur = cur.parentElement;
    }
    return out;
  }

  function isTextOnly(el) {
    if (!el || el.children.length !== 0) return false;
    if (/^(IMG|INPUT|BR|HR|SCRIPT|STYLE|TEXTAREA|SELECT|IFRAME|CANVAS|SVG|VIDEO|AUDIO|EMBED|OBJECT)$/i.test(el.tagName)) return false;
    return (el.textContent || '').trim().length > 0;
  }

  function formInfo(el) {
    var f = el && el.closest ? el.closest('form') : null;
    if (!f) return null;
    var action = f.getAttribute('action') || '';
    var nameEl = f.querySelector('input[name="_form"]');
    var fields = f.querySelectorAll('input, textarea, select');
    var count = 0;
    for (var i = 0; i < fields.length; i++) {
      var fi = fields[i];
      var nm = (fi.getAttribute('name') || '').toLowerCase();
      var ty = (fi.getAttribute('type') || '').toLowerCase();
      if (ty !== 'hidden' && ty !== 'submit' && ty !== 'button' && nm && nm.charAt(0) !== '_') {
        count++;
      }
    }
    return {
      tagIndex: tagIndex(f),
      delivered: /__pd\/form|__hh\/form/.test(action),
      name: nameEl ? (nameEl.getAttribute('value') || '') : '',
      fieldCount: count
    };
  }

  function visibleText(el) {
    var t = el.textContent || '';
    var hidden = el.querySelectorAll ? el.querySelectorAll('[aria-hidden="true"]') : [];
    for (var i = 0; i < hidden.length; i++) {
      var h = hidden[i].textContent || '';
      if (h) t = t.replace(h, '');
    }
    return t.replace(/\s+/g, ' ').trim().slice(0, 60);
  }

  function lockedProperties(el, cs) {
    var props = ['color', 'font-size', 'font-weight', 'text-align', 'background-image', 'background'];
    var locked = {};
    for (var i = 0; i < document.styleSheets.length; i++) {
      var sheet = document.styleSheets[i];
      var rules = null;
      try { rules = sheet.cssRules; } catch (x) {}
      if (!rules) continue;
      for (var j = 0; j < rules.length; j++) {
        var r = rules[j];
        if (!r.selectorText || !r.style) continue;
        var hit = false;
        try { hit = el.matches(r.selectorText); } catch (e) {}
        if (!hit) continue;
        for (var k = 0; k < props.length; k++) {
          if (r.style.getPropertyPriority(props[k]) === 'important') {
            locked[props[k]] = 1;
          }
        }
      }
    }
    return Object.keys(locked);
  }

  function selectElement(el) {
    if (selectedEl && selectedEl !== el) {
      selectedEl.style.outline = '';
      selectedEl.style.outlineOffset = '';
    }
    selectedEl = el;
    el.style.outline = '2px solid #7dd3fc';
    el.style.outlineOffset = '-1px';
  }

  function postSelected(el) {
    if (isInert(el)) return;
    var rect = el.getBoundingClientRect();
    var txt = visibleText(el);
    var sec = findSection(el);
    var secHtml = sec ? sec.outerHTML : '';
    var secTag = sec ? sec.tagName : '';
    var secIndex = sec ? tagIndex(sec) : 0;

    var cs = window.getComputedStyle(el);
    var rawStyles = {
      display: cs.display,
      position: cs.position,
      color: cs.color,
      backgroundColor: cs.backgroundColor,
      fontSize: cs.fontSize,
      fontFamily: cs.fontFamily,
      fontWeight: cs.fontWeight,
      padding: cs.padding,
      margin: cs.margin,
      border: cs.border,
      borderRadius: cs.borderRadius,
      width: cs.width,
      height: cs.height,
      textAlign: cs.textAlign
    };

    var filteredStyles = {};
    for (var k in rawStyles) {
      var val = rawStyles[k];
      if (val && val !== 'none' && val !== 'normal' && val !== 'auto' && val !== '0px' && val !== 'rgba(0, 0, 0, 0)') {
        filteredStyles[k] = val;
      }
    }

    var editStyles = {
      color: cs.color,
      backgroundColor: cs.backgroundColor,
      fontSize: cs.fontSize,
      fontWeight: cs.fontWeight,
      textAlign: cs.textAlign,
      paddingTop: cs.paddingTop,
      borderRadius: cs.borderTopLeftRadius,
      width: String(rect.width),
      height: String(rect.height),
      locked: lockedProperties(el, cs).join(',')
    };

    var mediaUrl = (el.tagName === 'IMG' || el.tagName === 'VIDEO') ? (el.currentSrc || el.getAttribute('src') || '') : '';
    var parent = el.parentElement;

    window.parent.postMessage({
      type: 'element-selected',
      html: el.outerHTML,
      editStyles: editStyles,
      mediaUrl: mediaUrl,
      tag: el.tagName,
      tagIndex: tagIndex(el),
      text: txt,
      parentTag: parent ? parent.tagName : '',
      parentTagIndex: parent ? tagIndex(parent) : 0,
      childIndex: childIndex(el),
      siblingCount: parent ? parent.children.length : 0,
      hasParent: !!parent && parent !== document.body,
      isTextOnly: isTextOnly(el),
      ancestors: ancestorChain(el),
      form: formInfo(el),
      rect: {
        x: rect.left,
        y: rect.top,
        width: rect.width,
        height: rect.height,
        top: rect.top,
        right: rect.right,
        bottom: rect.bottom,
        left: rect.left
      },
      sectionHtml: secHtml,
      sectionTag: secTag,
      sectionTagIndex: secIndex,
      computedStyles: filteredStyles
    }, '*');

    highlightSection(sec);
    selectElement(el);
  }

  // --- 4. Hover Outlines ---
  document.addEventListener('mouseover', function (e) {
    if (!aiEnabled || isInert(e.target)) return;
    if (dragState && dragState.active) return;
    if (hoveredEl && hoveredEl !== selectedEl) {
      hoveredEl.style.outline = '';
      hoveredEl.style.outlineOffset = '';
    }
    hoveredEl = e.target;
    if (hoveredEl !== selectedEl) {
      hoveredEl.style.outline = '2px dashed rgba(125, 211, 252, 0.5)';
      hoveredEl.style.outlineOffset = '-1px';
    }
  });

  document.addEventListener('mouseout', function (e) {
    if (!aiEnabled || isInert(e.target)) return;
    if (dragState && dragState.active) return;
    if (hoveredEl && hoveredEl !== selectedEl) {
      hoveredEl.style.outline = '';
      hoveredEl.style.outlineOffset = '';
      hoveredEl = null;
    }
  });

  // --- 5. Click Selection & In-Place Text Edit ---
  document.addEventListener('click', function (e) {
    // 1. Prevent link clicks from navigating the preview frame away to 404 or external pages
    var link = e.target.closest('a');
    if (link) {
      var href = link.getAttribute('href') || '';
      if (href.startsWith('#') && href.length > 1) {
        var targetNode = document.getElementById(href.substring(1));
        if (targetNode) {
          e.preventDefault();
          targetNode.scrollIntoView({ behavior: 'smooth' });
        }
      } else {
        e.preventDefault();
      }
    }

    // 2. If edit mode is disabled or clicked on background/inert area, dismiss popover
    if (!aiEnabled || isInert(e.target)) {
      window.parent.postMessage({ type: 'dismiss-popovers' }, '*');
      return;
    }

    // 3. Selection in edit mode
    e.preventDefault();
    e.stopPropagation();
    postSelected(e.target);
  }, true);

  function endTextEdit(commit) {
    if (!editingEl) return;
    var el = editingEl;
    var orig = editingOriginalText;
    editingEl = null;
    editingOriginalText = '';
    el.removeAttribute('contenteditable');
    el.style.outline = '';
    el.style.outlineOffset = '';
    el.style.cursor = '';
    var next = (el.textContent || '');
    if (commit && next.trim() !== orig.trim()) {
      window.parent.postMessage({
        type: 'element-text-edit',
        tag: el.tagName,
        tagIndex: tagIndex(el),
        text: next
      }, '*');
    } else {
      el.textContent = orig;
      selectElement(el);
    }
  }

  document.addEventListener('dblclick', function (e) {
    if (!aiEnabled || isInert(e.target)) return;
    var el = e.target;
    if (editingEl === el) return;
    if (!isTextOnly(el)) return;
    e.preventDefault();
    e.stopPropagation();
    if (editingEl) endTextEdit(true);

    window.parent.postMessage({ type: 'text-edit-start' }, '*');
    editingEl = el;
    editingOriginalText = (el.textContent || '');
    el.setAttribute('contenteditable', 'plaintext-only');
    el.focus();
    selectElement(el);
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (editingEl) {
        e.preventDefault();
        e.stopPropagation();
        endTextEdit(false);
      }
      window.parent.postMessage({ type: 'dismiss-popovers' }, '*');
      return;
    }
    if (!editingEl) return;
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      e.stopPropagation();
      endTextEdit(true);
    }
  }, true);

  document.addEventListener('focusout', function (e) {
    if (editingEl && e.target === editingEl) {
      endTextEdit(true);
    }
  }, true);

  // --- 6. Form Preview Interception ---
  document.addEventListener('submit', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var form = e.target;
    var successMsg = form.querySelector('input[name="_success"]');
    var redirectUrl = form.querySelector('input[name="_redirect"]');
    window.parent.postMessage({
      type: 'form-preview-submit',
      form: {
        success: successMsg ? successMsg.value : (form.getAttribute('data-success') || 'Form submitted successfully (Preview)'),
        redirect: redirectUrl ? redirectUrl.value : ''
      }
    }, '*');
  }, true);

  // --- 7. Hotkey Forwarding to Parent Window ---
  var HOTKEYS = [
    { k: 'z', m: true, s: false }, // Undo
    { k: 'y', m: true, s: false }, // Redo
    { k: 'z', m: true, s: true },  // Redo (Mac)
    { k: 'j', m: true, s: false }, // AI Edit
    { k: 'e', m: true, s: true },  // Edit Mode toggle
    { k: 'escape', m: false, s: false }
  ];

  window.addEventListener('keydown', function (e) {
    var k = (e.key || '').toLowerCase();
    var m = e.metaKey || e.ctrlKey;
    if (k === 'escape') {
      window.parent.postMessage({ type: 'iframe-escape' }, '*');
      return;
    }
    for (var i = 0; i < HOTKEYS.length; i++) {
      var hk = HOTKEYS[i];
      if (hk.k === k && hk.m === m && hk.s === e.shiftKey) {
        e.preventDefault();
        window.parent.postMessage({
          type: 'iframe-key',
          key: e.key,
          metaKey: e.metaKey,
          ctrlKey: e.ctrlKey,
          shiftKey: e.shiftKey,
          altKey: e.altKey
        }, '*');
        return;
      }
    }
  });

  // --- 8. Inbound Messages from Parent Window ---
  window.addEventListener('message', function (e) {
    if (!e.data || typeof e.data !== 'object') return;
    var d = e.data;

    // Toggle AI visual edit mode
    if (d.type === 'ai-mode') {
      aiEnabled = !!(d.enabled !== undefined ? d.enabled : (d.active !== undefined ? d.active : true));
      window.__aiEnabled = aiEnabled;
      if (!aiEnabled) {
        if (editingEl) endTextEdit(true);
        if (hoveredEl) {
          hoveredEl.style.outline = '';
          hoveredEl.style.outlineOffset = '';
          hoveredEl = null;
        }
        if (selectedEl) {
          selectedEl.style.outline = '';
          selectedEl.style.outlineOffset = '';
          selectedEl = null;
        }
        clearSectionHighlight();
      }
    }

    // Model identity update
    if (d.type === 'ai-model-info') {
      currentModel = d;
    }

    // Direct Patch Element
    if (d.type === 'patch-element') {
      var els = document.getElementsByTagName(d.tag);
      if (els && els[d.tagIndex]) {
        els[d.tagIndex].outerHTML = d.html;
        if (selectedEl) {
          selectedEl.style.outline = '';
          selectedEl.style.outlineOffset = '';
          selectedEl = null;
        }
        clearSectionHighlight();
      }
    }

    // Live Streaming Patch
    if (d.type === 'live-patch') {
      var target = document.getElementsByTagName(d.tag)[d.tagIndex];
      if (target && d.html) {
        target.outerHTML = d.html;
      }
    }

    // Select Element
    if (d.type === 'select-element') {
      var targetEl = document.getElementsByTagName(d.tag)[d.tagIndex];
      if (targetEl) {
        postSelected(targetEl);
        try { targetEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (x) {}
      }
    }

    // Clear Selection
    if (d.type === 'clear-selection') {
      if (selectedEl) {
        selectedEl.style.outline = '';
        selectedEl.style.outlineOffset = '';
        selectedEl = null;
      }
      clearSectionHighlight();
    }

    // Begin Text Edit
    if (d.type === 'begin-text-edit') {
      var te = document.getElementsByTagName(d.tag)[d.tagIndex];
      if (te && isTextOnly(te)) {
        editingEl = te;
        editingOriginalText = (te.textContent || '');
        te.setAttribute('contenteditable', 'plaintext-only');
        te.focus();
        selectElement(te);
      }
    }

    // Request Structure Tree
    if (d.type === 'request-structure') {
      function serializeNode(node) {
        if (!node || node.nodeType !== 1) return null;
        if (node.hasAttribute && node.hasAttribute('data-pagedock-ui')) return null;
        var tag = node.tagName.toLowerCase();
        if (/^(script|style|meta|link|head)$/i.test(tag)) return null;
        var children = [];
        for (var i = 0; i < node.children.length; i++) {
          var sc = serializeNode(node.children[i]);
          if (sc) children.push(sc);
        }
        return {
          tag: node.tagName,
          tagIndex: tagIndex(node),
          id: node.id || '',
          className: (node.className && typeof node.className === 'string') ? node.className.trim() : '',
          text: visibleText(node),
          children: children
        };
      }
      var tree = serializeNode(document.body);
      window.parent.postMessage({ type: 'page-structure', tree: tree }, '*');
    }
  });

})();
