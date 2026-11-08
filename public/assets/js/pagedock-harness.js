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
  var dragPotential = null;
  var dropIndicator = null;

  function ensureDropIndicator() {
    if (!dropIndicator || !dropIndicator.parentNode) {
      dropIndicator = document.createElement('div');
      dropIndicator.setAttribute('data-pagedock-ui', 'drop-indicator');
      dropIndicator.className = 'pagedock-drop-indicator';
      dropIndicator.style.cssText = 'position: absolute; height: 3px; background: linear-gradient(90deg, #38bdf8, #818cf8); pointer-events: none; z-index: 999999; display: none; box-shadow: 0 0 8px rgba(56, 189, 248, 0.8); border-radius: 2px;';
      document.body.appendChild(dropIndicator);
    }
    return dropIndicator;
  }

  function hideDropIndicator() {
    if (dropIndicator) {
      dropIndicator.style.display = 'none';
    }
  }

  function showDropIndicator(rect, position) {
    var ind = ensureDropIndicator();
    var top = position === 'before' ? (rect.top + window.scrollY) : (rect.bottom + window.scrollY - 3);
    ind.style.top = top + 'px';
    ind.style.left = (rect.left + window.scrollX) + 'px';
    ind.style.width = rect.width + 'px';
    ind.style.display = 'block';
  }

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

  function isEditableTextNode(el) {
    if (!el || el.nodeType !== 1) return false;
    if (isInert(el)) return false;
    if (/^(IMG|INPUT|BR|HR|SCRIPT|STYLE|TEXTAREA|SELECT|IFRAME|CANVAS|SVG|VIDEO|AUDIO|EMBED|OBJECT)$/i.test(el.tagName)) return false;

    // Direct leaf node with text
    if (el.children.length === 0) {
      return (el.textContent || '').trim().length > 0;
    }

    // Semantic text elements that may contain inline formatting (span, strong, em, b, i, a, code, small)
    var textTags = /^(H1|H2|H3|H4|H5|H6|P|BUTTON|A|LI|BLOCKQUOTE|LABEL|SPAN|STRONG|EM|B|I|SMALL|CITE|FIGCAPTION)$/i;
    if (textTags.test(el.tagName)) {
      // Ensure it doesn't contain block-level structural containers
      for (var i = 0; i < el.children.length; i++) {
        var childTag = el.children[i].tagName;
        if (/^(DIV|SECTION|ARTICLE|NAV|HEADER|FOOTER|MAIN|ASIDE|FORM|TABLE|UL|OL)$/i.test(childTag)) {
          return false;
        }
      }
      return (el.textContent || '').trim().length > 0;
    }
    return false;
  }

  function isTextOnly(el) {
    return isEditableTextNode(el);
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
    if (!isEditableTextNode(el) && el.parentElement && isEditableTextNode(el.parentElement)) {
      el = el.parentElement;
    }
    if (!isEditableTextNode(el)) return;
    if (editingEl === el) return;
    e.preventDefault();
    e.stopPropagation();
    if (editingEl) endTextEdit(true);

    window.parent.postMessage({ type: 'text-edit-start' }, '*');
    editingEl = el;
    editingOriginalText = (el.textContent || '');
    el.setAttribute('contenteditable', 'plaintext-only');
    el.style.outline = '2px solid #38bdf8';
    el.style.outlineOffset = '2px';
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
      if (selectedEl) {
        selectedEl.style.outline = '';
        selectedEl.style.outlineOffset = '';
        selectedEl = null;
      }
      if (hoveredEl) {
        hoveredEl.style.outline = '';
        hoveredEl.style.outlineOffset = '';
        hoveredEl = null;
      }
      clearSectionHighlight();
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

  // --- 5b. Section Drag-and-Drop Reordering ---
  document.addEventListener('mousedown', function (e) {
    if (!aiEnabled || isInert(e.target) || e.button !== 0) return;
    if (editingEl) return;
    var sec = findSection(e.target);
    if (sec && sec !== document.body) {
      dragPotential = {
        startX: e.clientX,
        startY: e.clientY,
        sourceSec: sec,
        startTime: Date.now()
      };
    }
  }, true);

  document.addEventListener('mousemove', function (e) {
    if (dragPotential && !dragState) {
      var dx = Math.abs(e.clientX - dragPotential.startX);
      var dy = Math.abs(e.clientY - dragPotential.startY);
      if (dx > 8 || dy > 8) {
        dragState = {
          active: true,
          sourceSec: dragPotential.sourceSec
        };
        dragState.sourceSec.style.opacity = '0.5';
      }
    }

    if (dragState && dragState.active) {
      e.preventDefault();
      var under = document.elementFromPoint(e.clientX, e.clientY);
      if (under && !isInert(under)) {
        var targetSec = findSection(under);
        if (targetSec && targetSec !== dragState.sourceSec && targetSec.parentNode === dragState.sourceSec.parentNode) {
          var rect = targetSec.getBoundingClientRect();
          var midY = rect.top + rect.height / 2;
          var pos = e.clientY < midY ? 'before' : 'after';
          dragState.targetSec = targetSec;
          dragState.position = pos;
          showDropIndicator(rect, pos);
          return;
        }
      }
      hideDropIndicator();
      dragState.targetSec = null;
    }
  }, true);

  document.addEventListener('mouseup', function (e) {
    if (dragState && dragState.active) {
      e.preventDefault();
      e.stopPropagation();
      dragState.sourceSec.style.opacity = '';
      hideDropIndicator();

      if (dragState.targetSec && dragState.sourceSec) {
        var src = dragState.sourceSec;
        var tgt = dragState.targetSec;
        var pos = dragState.position;

        var sourceTag = src.tagName;
        var sourceIndex = tagIndex(src);
        var targetTag = tgt.tagName;
        var targetIndex = tagIndex(tgt);

        // Perform DOM reorder in preview
        if (pos === 'before') {
          tgt.parentNode.insertBefore(src, tgt);
        } else {
          tgt.parentNode.insertBefore(src, tgt.nextSibling);
        }

        // Notify parent editor to reorder in CodeMirror
        window.parent.postMessage({
          type: 'element-reorder',
          sourceTag: sourceTag,
          sourceIndex: sourceIndex,
          targetTag: targetTag,
          targetIndex: targetIndex,
          position: pos
        }, '*');

        sendStructureTree();
      }

      dragState = null;
      dragPotential = null;
      return;
    }
    dragPotential = null;
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
      if (selectedEl) {
        selectedEl.style.outline = '';
        selectedEl.style.outlineOffset = '';
        selectedEl = null;
      }
      if (hoveredEl) {
        hoveredEl.style.outline = '';
        hoveredEl.style.outlineOffset = '';
        hoveredEl = null;
      }
      clearSectionHighlight();
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

  // --- 8. Structure Tree Serialization & Dispatch ---
  function sendStructureTree() {
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

  // --- 9. Inbound Messages from Parent Window ---
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
      var target = els && els[d.tagIndex];
      if (target) {
        if (d.html !== undefined) {
          target.outerHTML = d.html;
        } else if (d.text !== undefined) {
          target.textContent = d.text;
        }
        if (selectedEl && d.html !== undefined) {
          selectedEl.style.outline = '';
          selectedEl.style.outlineOffset = '';
          selectedEl = null;
          clearSectionHighlight();
        }
      }
    }

    // Live Streaming / Full-Document Patch
    if (d.type === 'live-patch') {
      if (d.tag !== undefined && d.tag !== null && d.tag !== '') {
        // Surgical element replacement
        var target = document.getElementsByTagName(d.tag)[d.tagIndex];
        if (target && d.html) {
          target.outerHTML = d.html;
        }
      } else if (d.html) {
        // Full document synchronization
        try {
          var parser = new DOMParser();
          var doc = parser.parseFromString(d.html, 'text/html');

          // 0. Synchronize documentElement attributes (class, data-theme, lang, dir)
          if (doc.documentElement && document.documentElement) {
            var rootAttrs = ['class', 'data-theme', 'lang', 'dir'];
            for (var raIdx = 0; raIdx < rootAttrs.length; raIdx++) {
              var attrName = rootAttrs[raIdx];
              if (doc.documentElement.hasAttribute(attrName)) {
                document.documentElement.setAttribute(attrName, doc.documentElement.getAttribute(attrName));
              } else {
                document.documentElement.removeAttribute(attrName);
              }
            }
          }

          // 1. Update Document Title
          if (doc.title) {
            document.title = doc.title;
          }

          // 2. Update Head Styles and Links (preserving injected harness assets)
          if (doc.head && document.head) {
            // Remove previous user styles
            var oldStyles = document.head.querySelectorAll('style:not([data-pagedock-ui]):not([data-htmlhost-ui])');
            for (var sIdx = 0; sIdx < oldStyles.length; sIdx++) {
              if (oldStyles[sIdx].parentNode) oldStyles[sIdx].parentNode.removeChild(oldStyles[sIdx]);
            }
            // Append new user styles
            var newStyles = doc.head.querySelectorAll('style:not([data-pagedock-ui]):not([data-htmlhost-ui])');
            for (var nsIdx = 0; nsIdx < newStyles.length; nsIdx++) {
              var newStyleEl = document.createElement('style');
              for (var sa = 0; sa < newStyles[nsIdx].attributes.length; sa++) {
                var sattr = newStyles[nsIdx].attributes[sa];
                newStyleEl.setAttribute(sattr.name, sattr.value);
              }
              newStyleEl.textContent = newStyles[nsIdx].textContent || newStyles[nsIdx].innerHTML;
              document.head.appendChild(newStyleEl);
            }

            // Update user stylesheet / external links
            var oldLinks = document.head.querySelectorAll('link:not([data-pagedock-ui]):not([data-htmlhost-ui])');
            for (var lIdx = 0; lIdx < oldLinks.length; lIdx++) {
              if (oldLinks[lIdx].parentNode) oldLinks[lIdx].parentNode.removeChild(oldLinks[lIdx]);
            }
            var newLinks = doc.head.querySelectorAll('link:not([data-pagedock-ui]):not([data-htmlhost-ui])');
            for (var nlIdx = 0; nlIdx < newLinks.length; nlIdx++) {
              var newLinkEl = document.createElement('link');
              for (var la = 0; la < newLinks[nlIdx].attributes.length; la++) {
                var lattr = newLinks[nlIdx].attributes[la];
                newLinkEl.setAttribute(lattr.name, lattr.value);
              }
              document.head.appendChild(newLinkEl);
            }
          }

          // 3. Update Body Content & Attributes (preserving injected harness scripts/elements)
          if (doc.body && document.body) {
            var preservedUiNodes = [];
            var uiNodes = document.body.querySelectorAll('[data-pagedock-ui], [data-htmlhost-ui], script[src*="pagedock-harness"]');
            for (var pIdx = 0; pIdx < uiNodes.length; pIdx++) {
              preservedUiNodes.push(uiNodes[pIdx]);
            }

            // Sync body className properly when empty
            document.body.className = doc.body.className || '';
            for (var ba = 0; ba < doc.body.attributes.length; ba++) {
              var bAttr = doc.body.attributes[ba];
              if (bAttr.name !== 'class') {
                document.body.setAttribute(bAttr.name, bAttr.value);
              }
            }

            // Apply new body HTML
            document.body.innerHTML = doc.body.innerHTML;

            // Re-append preserved harness elements
            for (var rIdx = 0; rIdx < preservedUiNodes.length; rIdx++) {
              if (!document.body.contains(preservedUiNodes[rIdx])) {
                document.body.appendChild(preservedUiNodes[rIdx]);
              }
            }

            // Reset inspector selection & editing state
            if (selectedEl) {
              selectedEl.style.outline = '';
              selectedEl.style.outlineOffset = '';
              selectedEl = null;
            }
            hoveredEl = null;
            editingEl = null;
            clearSectionHighlight();

            // Mute & silence any new media elements
            scanMedia(document.body);
          }

          // 4. Dispatch refreshed structure tree back to parent
          sendStructureTree();
        } catch (err) {
          console.error('[PageDock Harness] Live patch error:', err);
        }
      }
    }

    // Hot CSS Live Reload
    if (d.type === 'live-style-update' && typeof d.css === 'string') {
      var styleEl = document.getElementById('__pagedock_live_style');
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = '__pagedock_live_style';
        document.head.appendChild(styleEl);
      }
      styleEl.textContent = d.css;
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
      if (hoveredEl) {
        hoveredEl.style.outline = '';
        hoveredEl.style.outlineOffset = '';
        hoveredEl = null;
      }
      clearSectionHighlight();
    }

    // Duplicate Element
    if (d.type === 'duplicate-element') {
      var els = document.getElementsByTagName(d.tag);
      var target = els && els[d.tagIndex];
      if (target && target.parentNode) {
        var clone = target.cloneNode(true);
        clone.style.outline = '';
        clone.style.outlineOffset = '';
        clone.removeAttribute('contenteditable');
        if (target.nextSibling) {
          target.parentNode.insertBefore(clone, target.nextSibling);
        } else {
          target.parentNode.appendChild(clone);
        }
        scanMedia(clone);
        if (selectedEl) {
          selectedEl.style.outline = '';
          selectedEl.style.outlineOffset = '';
          selectedEl = null;
        }
        clearSectionHighlight();
        sendStructureTree();
      }
    }

    // Delete Element
    if (d.type === 'delete-element') {
      var els = document.getElementsByTagName(d.tag);
      var target = els && els[d.tagIndex];
      if (target && target.parentNode) {
        target.parentNode.removeChild(target);
        if (selectedEl && (selectedEl === target || (target.contains && target.contains(selectedEl)))) {
          selectedEl = null;
        }
        if (hoveredEl && (hoveredEl === target || (target.contains && target.contains(hoveredEl)))) {
          hoveredEl = null;
        }
        clearSectionHighlight();
        sendStructureTree();
      }
    }

    // Begin Text Edit
    if (d.type === 'begin-text-edit') {
      var te = (d.tag && d.tagIndex !== undefined)
        ? (document.getElementsByTagName(d.tag) && document.getElementsByTagName(d.tag)[d.tagIndex])
        : selectedEl;
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
      sendStructureTree();
    }
  });

})();
