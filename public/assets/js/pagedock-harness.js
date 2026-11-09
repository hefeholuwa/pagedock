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

  function ensureHarnessStyles() {
    if (document.getElementById('pd-harness-styles')) return;
    var style = document.createElement('style');
    style.id = 'pd-harness-styles';
    style.setAttribute('data-pagedock-ui', 'styles');
    style.textContent = `
      .pd-has-overlay {
        position: relative !important;
      }
      .pd-element-overlay {
        position: absolute !important;
        inset-inline-end: 8px !important;
        inset-block-start: -14px !important;
        z-index: 99999 !important;
        pointer-events: none !important;
      }
      .pd-selection-overlay {
        position: absolute !important;
        z-index: 999999 !important;
        pointer-events: none !important;
      }
      .pd-selection-overlay .pd-element-toolbar {
        opacity: 1 !important;
        transform: translateY(0) !important;
        pointer-events: all !important;
      }
      .pd-element-selected {
        outline: 2px solid #38bdf8 !important;
        outline-offset: 2px !important;
      }
      .pd-element-toolbar {
        display: inline-flex !important;
        align-items: center !important;
        background: #0f172a !important;
        border: 1px solid #334155 !important;
        border-radius: 6px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5) !important;
        pointer-events: all !important;
        padding: 2px !important;
        gap: 2px !important;
        opacity: 0;
        transition: opacity 0.15s ease, transform 0.15s ease !important;
        transform: translateY(-2px) !important;
      }
      .pd-has-overlay:hover > .pd-element-overlay .pd-element-toolbar,
      .pd-element-selected > .pd-element-overlay .pd-element-toolbar,
      .pd-has-overlay.pd-element-selected > .pd-element-overlay .pd-element-toolbar,
      .pd-has-overlay.pd-element-active > .pd-element-overlay .pd-element-toolbar,
      .pd-selection-overlay .pd-element-toolbar {
        opacity: 1 !important;
        transform: translateY(0) !important;
      }
      .pd-tool-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 22px !important;
        height: 22px !important;
        min-width: 22px !important;
        min-height: 22px !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
        color: #94a3b8 !important;
        border-radius: 4px !important;
        cursor: pointer !important;
        touch-action: manipulation !important;
        user-select: none !important;
        -webkit-user-select: none !important;
        transition: all 0.12s ease !important;
      }
      .pd-tool-btn:hover, .pd-tool-btn:active {
        background: #1e293b !important;
        color: #38bdf8 !important;
      }
      .pd-tool-btn.pd-tool-up:hover, .pd-tool-btn.pd-tool-down:hover,
      .pd-tool-btn.pd-tool-edit:hover, .pd-tool-btn.pd-tool-edit:active {
        background: #1e293b !important;
        color: #38bdf8 !important;
      }
      .pd-tool-btn.pd-tool-drag {
        cursor: grab !important;
      }
      .pd-tool-btn.pd-tool-drag:active, .pd-dragging .pd-tool-btn.pd-tool-drag {
        cursor: grabbing !important;
      }
      .pd-tool-btn.pd-tool-delete:hover, .pd-tool-btn.pd-tool-delete:active {
        background: rgba(239, 68, 68, 0.2) !important;
        color: #f87171 !important;
      }
      .pd-drop-slot {
        box-sizing: border-box !important;
        background: rgba(56, 189, 248, 0.25) !important;
        border: 2px dashed #38bdf8 !important;
        border-radius: 6px !important;
        pointer-events: none !important;
        position: absolute !important;
        z-index: 999999 !important;
        box-shadow: 0 0 14px rgba(56, 189, 248, 0.7) !important;
        animation: pd-slot-pulse 1s infinite alternate cubic-bezier(0.4, 0, 0.2, 1) !important;
        display: none;
      }
      @keyframes pd-slot-pulse {
        from {
          border-color: #38bdf8;
          background: rgba(56, 189, 248, 0.16);
        }
        to {
          border-color: #818cf8;
          background: rgba(129, 140, 248, 0.32);
        }
      }
      .pd-empty-canvas {
        margin: 40px auto !important;
        max-width: 600px !important;
        padding: 60px 24px !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        border: 2px dashed rgba(56, 189, 248, 0.45) !important;
        border-radius: 12px !important;
        background: rgba(15, 23, 42, 0.65) !important;
        backdrop-filter: blur(8px) !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
      }
      .pd-empty-canvas:hover, .pd-empty-canvas.dragover {
        border-color: #38bdf8 !important;
        background: rgba(56, 189, 248, 0.1) !important;
        box-shadow: 0 0 24px rgba(56, 189, 248, 0.25) !important;
      }
      .pd-empty-icon {
        color: #38bdf8 !important;
        margin-bottom: 12px !important;
      }
      .pd-empty-title {
        font-family: system-ui, -apple-system, sans-serif !important;
        font-size: 18px !important;
        font-weight: 600 !important;
        color: #f1f5f9 !important;
        margin-bottom: 6px !important;
      }
      .pd-empty-desc {
        font-family: system-ui, -apple-system, sans-serif !important;
        font-size: 13px !important;
        color: #94a3b8 !important;
        max-width: 320px !important;
      }
    `;
    (document.head || document.documentElement).appendChild(style);
  }

  function checkEmptyCanvas() {
    if (!document.body) return;
    ensureHarnessStyles();
    var meaningfulChildren = [];
    for (var i = 0; i < document.body.children.length; i++) {
      var ch = document.body.children[i];
      if (!ch.hasAttribute('data-pagedock-ui') && ['SCRIPT', 'STYLE'].indexOf(ch.tagName) === -1) {
        meaningfulChildren.push(ch);
      }
    }
    var existing = document.querySelector('.pd-empty-canvas');
    if (meaningfulChildren.length === 0) {
      if (!existing) {
        var emptyBox = document.createElement('div');
        emptyBox.className = 'pd-empty-canvas';
        emptyBox.setAttribute('data-pagedock-ui', 'empty-canvas');
        emptyBox.innerHTML = '<div class="pd-empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div><div class="pd-empty-title">Start Building Your Page</div><div class="pd-empty-desc">Drag any section or element from the library into this space, or tap here to pick a component</div>';
        emptyBox.addEventListener('click', function () {
          window.parent.postMessage({ type: 'open-library-insert' }, '*');
        });
        document.body.appendChild(emptyBox);
      }
    } else if (existing) {
      existing.remove();
    }
  }

  function signalDomReady() {
    ensureHarnessStyles();
    initBlockOverlays();
    checkEmptyCanvas();
    window.parent.postMessage({ type: 'buffer-dom-ready' }, '*');
  }

  ensureHarnessStyles();
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
  var justFinishedDrag = false;

  var VOID_TAGS = ['AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR'];

  function isVoidTag(el) {
    if (!el || !el.tagName) return false;
    return VOID_TAGS.indexOf(el.tagName.toUpperCase()) !== -1;
  }

  function isContainerElement(el) {
    if (!el || el.nodeType !== 1) return false;
    var tag = el.tagName.toUpperCase();
    if (['SECTION', 'ARTICLE', 'MAIN', 'HEADER', 'FOOTER', 'NAV', 'ASIDE', 'FORM'].indexOf(tag) !== -1) return true;
    if (tag === 'DIV') {
      var cls = el.className || '';
      if (/container|grid|flex|col|row|card|wrap|box/i.test(cls)) return true;
      if (el.children.length >= 1) return true;
    }
    return false;
  }

  function detectDropPosition(el, clientX, clientY) {
    if (!el || el === document.body || el === document.documentElement) return { position: 'append', target: el };
    
    // Auto-scroll near edges
    if (clientY < 50) {
      window.scrollBy(0, -12);
    } else if (clientY > window.innerHeight - 50) {
      window.scrollBy(0, 12);
    }

    var target = el;
    // Guard against dropping inside void tags
    if (isVoidTag(target)) {
      var rect = target.getBoundingClientRect();
      var pos = clientY < (rect.top + rect.height / 2) ? 'before' : 'after';
      return { position: pos, target: target };
    }

    var cs = window.getComputedStyle(target);
    var isFlexRow = (cs.display === 'flex' || cs.display === 'inline-flex') && (!cs.flexDirection || cs.flexDirection.indexOf('row') !== -1);
    var targetRect = target.getBoundingClientRect();

    // Check if target is a container and cursor is well inside its bounds (not just near edges)
    if (isContainerElement(target)) {
      var innerX = targetRect.left + targetRect.width * 0.15;
      var innerW = targetRect.width * 0.7;
      var innerY = targetRect.top + targetRect.height * 0.15;
      var innerH = targetRect.height * 0.7;
      if (clientX >= innerX && clientX <= innerX + innerW && clientY >= innerY && clientY <= innerY + innerH) {
        if (target.children.length === 0 || target.querySelectorAll('.pd-element-overlay').length === target.children.length) {
          return { position: 'inside', target: target };
        }
      }
    }

    if (isFlexRow) {
      var midX = targetRect.left + targetRect.width / 2;
      return { position: clientX < midX ? 'before' : 'after', target: target };
    } else {
      var midY = targetRect.top + targetRect.height / 2;
      return { position: clientY < midY ? 'before' : 'after', target: target };
    }
  }

  function ensureDropIndicator() {
    if (!dropIndicator || !dropIndicator.parentNode) {
      dropIndicator = document.createElement('div');
      dropIndicator.setAttribute('data-pagedock-ui', 'drop-indicator');
      dropIndicator.className = 'pd-drop-slot pagedock-drop-indicator';
      document.body.appendChild(dropIndicator);
    }
    return dropIndicator;
  }

  function hideDropIndicator() {
    if (dropIndicator) {
      dropIndicator.style.display = 'none';
    }
  }

  function showDropIndicator(rect, position, target) {
    var ind = ensureDropIndicator();
    if (position === 'inside') {
      ind.style.top = (rect.top + window.scrollY + 4) + 'px';
      ind.style.left = (rect.left + window.scrollX + 4) + 'px';
      ind.style.width = Math.max(rect.width - 8, 40) + 'px';
      ind.style.height = Math.max(rect.height - 8, 36) + 'px';
    } else {
      var cs = target ? window.getComputedStyle(target.parentElement || target) : null;
      var isFlexRow = cs && (cs.display === 'flex' || cs.display === 'inline-flex') && (!cs.flexDirection || cs.flexDirection.indexOf('row') !== -1);
      
      if (isFlexRow) {
        var left = position === 'before' ? (rect.left + window.scrollX) : (rect.right + window.scrollX - 4);
        ind.style.top = (rect.top + window.scrollY) + 'px';
        ind.style.left = left + 'px';
        ind.style.width = '6px';
        ind.style.height = rect.height + 'px';
      } else {
        var top = position === 'before' ? (rect.top + window.scrollY) : (rect.bottom + window.scrollY - 4);
        ind.style.top = top + 'px';
        ind.style.left = (rect.left + window.scrollX) + 'px';
        ind.style.width = rect.width + 'px';
        ind.style.height = '6px';
      }
    }
    ind.style.display = 'block';
  }

  function isInert(el) {
    if (!el || el.nodeType !== 1) return true;
    if (el === document.body || el === document.documentElement) return true;
    if (el.hasAttribute && el.hasAttribute('data-pagedock-ui')) return true;
    return false;
  }

  function findSection(el) {
    if (!el || el === document.body || el === document.documentElement) return null;
    var tags = ['SECTION', 'ARTICLE', 'MAIN', 'HEADER', 'FOOTER', 'NAV', 'ASIDE'];
    if (tags.indexOf(el.tagName) !== -1) return el;
    var cur = el.parentElement;
    while (cur && cur !== document.body && cur !== document.documentElement) {
      if (tags.indexOf(cur.tagName) !== -1) return cur;
      cur = cur.parentElement;
    }
    cur = el;
    while (cur.parentElement && cur.parentElement !== document.body) {
      cur = cur.parentElement;
    }
    return cur !== document.body ? cur : null;
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
  }

  // --- Element Overlays & Dedicated Action Toolbars ---
  var blockIdCounter = 1;

  function getAdjacentBlock(sec, direction) {
    if (!sec || !sec.parentElement) return null;
    var sib = direction === -1 ? sec.previousElementSibling : sec.nextElementSibling;
    while (sib) {
      if (!isInert(sib) && ['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE'].indexOf(sib.tagName) === -1) {
        return sib;
      }
      sib = direction === -1 ? sib.previousElementSibling : sib.nextElementSibling;
    }
    return null;
  }

  function moveBlockRelative(sec, direction) {
    var target = getAdjacentBlock(sec, direction);
    if (!target) return;
    var sourceTag = sec.tagName;
    var sourceIndex = tagIndex(sec);
    var targetTag = target.tagName;
    var targetIndex = tagIndex(target);
    var pos = direction === -1 ? 'before' : 'after';

    if (direction === -1) {
      sec.parentNode.insertBefore(sec, target);
    } else {
      sec.parentNode.insertBefore(sec, target.nextSibling);
    }

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

  function createBlockOverlay(el) {
    if (!el || el.nodeType !== 1 || isInert(el)) return;
    if (el === document.body || el === document.documentElement) return;
    if (el.querySelector && el.querySelector(':scope > .pd-element-overlay')) return;

    if (!el.getAttribute('data-pd-id')) {
      el.setAttribute('data-pd-id', 'pd_' + (el.tagName.toLowerCase()) + '_' + (blockIdCounter++));
    }
    el.classList.add('pd-has-overlay');

    var cs = window.getComputedStyle(el);
    if (cs.position === 'static') {
      el.style.position = 'relative';
    }

    var overlay = document.createElement('div');
    overlay.className = 'pd-element-overlay';
    overlay.setAttribute('data-pagedock-ui', 'overlay');

    var toolbar = document.createElement('div');
    toolbar.className = 'pd-element-toolbar';
    toolbar.setAttribute('data-pagedock-ui', 'toolbar');

    // 1. Drag Handle
    var dragBtn = document.createElement('button');
    dragBtn.type = 'button';
    dragBtn.className = 'pd-tool-btn pd-tool-drag';
    dragBtn.title = 'Drag to Move';
    dragBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>';

    function handleDragGripStart(e) {
      e.stopPropagation();
      var pt = e.touches ? e.touches[0] : e;
      dragPotential = {
        startX: pt.clientX,
        startY: pt.clientY,
        sourceSec: el,
        startTime: Date.now()
      };
    }
    dragBtn.addEventListener('mousedown', handleDragGripStart);
    dragBtn.addEventListener('touchstart', handleDragGripStart, { passive: false });

    // 2. Edit Text Button
    var editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'pd-tool-btn pd-tool-edit';
    editBtn.title = 'Edit Text';
    editBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
    editBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      startTextEdit(el);
    });

    // 3. Move Up Button
    var upBtn = document.createElement('button');
    upBtn.type = 'button';
    upBtn.className = 'pd-tool-btn pd-tool-up';
    upBtn.title = 'Move Up';
    upBtn.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>';
    upBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      moveBlockRelative(el, -1);
    });

    // 4. Move Down Button
    var downBtn = document.createElement('button');
    downBtn.type = 'button';
    downBtn.className = 'pd-tool-btn pd-tool-down';
    downBtn.title = 'Move Down';
    downBtn.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>';
    downBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      moveBlockRelative(el, 1);
    });

    // 5. Duplicate Block
    var dupBtn = document.createElement('button');
    dupBtn.type = 'button';
    dupBtn.className = 'pd-tool-btn pd-tool-duplicate';
    dupBtn.title = 'Duplicate Block';
    dupBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    dupBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      window.parent.postMessage({
        type: 'element-duplicate',
        targetId: el.getAttribute('data-pd-id'),
        targetTag: el.tagName,
        targetIndex: tagIndex(el)
      }, '*');
    });

    // 6. Add Block Below
    var addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'pd-tool-btn pd-tool-add';
    addBtn.title = 'Add Block Below';
    addBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
    addBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      window.parent.postMessage({
        type: 'open-library-insert',
        targetId: el.getAttribute('data-pd-id'),
        targetTag: el.tagName,
        targetIndex: tagIndex(el)
      }, '*');
    });

    // 7. Delete Block
    var delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'pd-tool-btn pd-tool-delete';
    delBtn.title = 'Delete Block';
    delBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    delBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      deleteElement(el);
    });

    toolbar.appendChild(dragBtn);
    toolbar.appendChild(editBtn);
    toolbar.appendChild(upBtn);
    toolbar.appendChild(downBtn);
    toolbar.appendChild(dupBtn);
    toolbar.appendChild(addBtn);
    toolbar.appendChild(delBtn);
    overlay.appendChild(toolbar);
    el.appendChild(overlay);
  }

  function initBlockOverlays() {
    if (!document.body) return;
    ensureHarnessStyles();
    var tags = ['SECTION', 'ARTICLE', 'MAIN', 'HEADER', 'FOOTER', 'NAV', 'ASIDE'];
    tags.forEach(function (tag) {
      var elems = document.getElementsByTagName(tag);
      for (var i = 0; i < elems.length; i++) {
        createBlockOverlay(elems[i]);
      }
    });

    var bodyChildren = document.body.children;
    for (var j = 0; j < bodyChildren.length; j++) {
      var child = bodyChildren[j];
      if (!isInert(child) && ['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE'].indexOf(child.tagName) === -1) {
        createBlockOverlay(child);
      }
    }
  }

  function tagIndex(el) {
    var all = document.getElementsByTagName(el.tagName);
    for (var i = 0; i < all.length; i++) {
      if (all[i] === el) return i;
    }
    return 0;
  }

  function deleteElement(el) {
    if (!el || el === document.body || el === document.documentElement) return;

    if (el.getAttribute && el.getAttribute('data-pd-temp-radius')) {
      el.style.borderRadius = '';
      el.removeAttribute('data-pd-temp-radius');
    }

    // Clean up overlays and toolbars before recording HTML and removing
    var overlays = el.querySelectorAll ? el.querySelectorAll('.pd-element-overlay, [data-pagedock-ui]') : [];
    for (var o = 0; o < overlays.length; o++) {
      overlays[o].remove();
    }
    el.classList.remove('pd-element-selected', 'pd-has-overlay', 'pd-element-active');
    el.style.outline = '';
    el.style.outlineOffset = '';

    var targetTag = el.tagName;
    var targetIndex = tagIndex(el);
    var targetId = el.getAttribute ? el.getAttribute('data-pd-id') : null;
    var targetHtml = el.outerHTML;

    if (el.parentNode) {
      el.parentNode.removeChild(el);
    }

    if (selectedEl === el) {
      selectedEl = null;
    }
    if (hoveredEl === el) {
      hoveredEl = null;
    }
    if (editingEl === el) {
      editingEl = null;
    }

    hideSelectionToolbar();
    clearSectionHighlight();
    checkEmptyCanvas();
    sendStructureTree();

    window.parent.postMessage({
      type: 'element-delete',
      targetTag: targetTag,
      targetIndex: targetIndex,
      targetId: targetId,
      html: targetHtml
    }, '*');
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

  var selectionToolbarOverlay = null;

  function ensureSelectionToolbar() {
    if (selectionToolbarOverlay && selectionToolbarOverlay.parentNode) {
      return selectionToolbarOverlay;
    }
    selectionToolbarOverlay = document.createElement('div');
    selectionToolbarOverlay.className = 'pd-element-overlay pd-selection-overlay';
    selectionToolbarOverlay.setAttribute('data-pagedock-ui', 'selection-toolbar');

    var toolbar = document.createElement('div');
    toolbar.className = 'pd-element-toolbar';
    toolbar.setAttribute('data-pagedock-ui', 'toolbar');

    // 1. Move / Drag handle (✥)
    var dragBtn = document.createElement('button');
    dragBtn.type = 'button';
    dragBtn.className = 'pd-tool-btn pd-tool-drag';
    dragBtn.title = 'Drag to Move';
    dragBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>';

    function handleSelectionDragGripStart(e) {
      e.stopPropagation();
      var target = selectedEl || (dragBtn.closest && findSection(dragBtn));
      if (!target || target === document.body) return;
      var pt = e.touches ? e.touches[0] : e;
      dragPotential = {
        startX: pt.clientX,
        startY: pt.clientY,
        sourceSec: target,
        startTime: Date.now()
      };
    }
    dragBtn.addEventListener('mousedown', handleSelectionDragGripStart);
    dragBtn.addEventListener('touchstart', handleSelectionDragGripStart, { passive: false });

    // 2. Edit Text button (✏️)
    var editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'pd-tool-btn pd-tool-edit';
    editBtn.title = 'Edit Text';
    editBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
    editBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (selectedEl) {
        startTextEdit(selectedEl);
      }
    });

    // 3. Move Up button
    var upBtn = document.createElement('button');
    upBtn.type = 'button';
    upBtn.className = 'pd-tool-btn pd-tool-up';
    upBtn.title = 'Move Up';
    upBtn.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>';
    upBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (selectedEl) {
        moveBlockRelative(selectedEl, -1);
        positionSelectionToolbar(selectedEl);
      }
    });

    // 4. Move Down button
    var downBtn = document.createElement('button');
    downBtn.type = 'button';
    downBtn.className = 'pd-tool-btn pd-tool-down';
    downBtn.title = 'Move Down';
    downBtn.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>';
    downBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (selectedEl) {
        moveBlockRelative(selectedEl, 1);
        positionSelectionToolbar(selectedEl);
      }
    });

    // 5. Duplicate button (📋)
    var dupBtn = document.createElement('button');
    dupBtn.type = 'button';
    dupBtn.className = 'pd-tool-btn pd-tool-duplicate';
    dupBtn.title = 'Duplicate Element';
    dupBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    dupBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (selectedEl) {
        if (!selectedEl.getAttribute('data-pd-id')) {
          selectedEl.setAttribute('data-pd-id', 'pd_' + (selectedEl.tagName.toLowerCase()) + '_' + (blockIdCounter++));
        }
        window.parent.postMessage({
          type: 'element-duplicate',
          targetId: selectedEl.getAttribute('data-pd-id'),
          targetTag: selectedEl.tagName,
          targetIndex: tagIndex(selectedEl)
        }, '*');
      }
    });

    // 6. Delete button (🗑️ with red hover highlight)
    var delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'pd-tool-btn pd-tool-delete';
    delBtn.title = 'Delete Element (Delete / Backspace)';
    delBtn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
    delBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (selectedEl) {
        deleteElement(selectedEl);
      }
    });

    toolbar.appendChild(dragBtn);
    toolbar.appendChild(editBtn);
    toolbar.appendChild(upBtn);
    toolbar.appendChild(downBtn);
    toolbar.appendChild(dupBtn);
    toolbar.appendChild(delBtn);
    selectionToolbarOverlay.appendChild(toolbar);

    (document.body || document.documentElement).appendChild(selectionToolbarOverlay);
    return selectionToolbarOverlay;
  }

  function positionSelectionToolbar(el) {
    if (!el || !document.body || isInert(el)) return;
    var overlay = ensureSelectionToolbar();
    var toolbar = overlay.querySelector('.pd-element-toolbar');
    var editBtn = overlay.querySelector('.pd-tool-edit');

    if (editBtn) {
      var canEdit = isEditableTextNode(el) || (el.parentElement && isEditableTextNode(el.parentElement));
      editBtn.style.display = canEdit ? 'inline-flex' : 'none';
    }

    var rect = el.getBoundingClientRect();
    var scrollX = window.scrollX || window.pageXOffset || 0;
    var scrollY = window.scrollY || window.pageYOffset || 0;

    overlay.style.display = 'block';
    overlay.style.visibility = 'hidden';

    var tbHeight = toolbar ? toolbar.offsetHeight || 28 : 28;
    var tbWidth = toolbar ? toolbar.offsetWidth || 150 : 150;

    var left = rect.right + scrollX - tbWidth;
    var minLeft = scrollX + 8;
    var maxLeft = window.innerWidth + scrollX - tbWidth - 8;
    if (left < minLeft) left = minLeft;
    if (left > maxLeft) left = maxLeft;

    // Position neatly above element; or inside top-right if element is at top of viewport so it doesn't get clipped off-screen
    var top;
    if (rect.top < tbHeight + 8) {
      top = rect.top + scrollY + 6;
    } else {
      top = rect.top + scrollY - tbHeight - 6;
    }

    overlay.style.position = 'absolute';
    overlay.style.left = Math.round(left) + 'px';
    overlay.style.top = Math.round(top) + 'px';
    overlay.style.zIndex = '999999';
    overlay.style.visibility = 'visible';
    overlay.style.pointerEvents = 'all';
  }

  function attachSelectionToolbar(el) {
    if (!el || isInert(el)) {
      hideSelectionToolbar();
      return;
    }
    positionSelectionToolbar(el);
  }

  function hideSelectionToolbar() {
    if (selectionToolbarOverlay) {
      selectionToolbarOverlay.style.display = 'none';
    }
  }

  function removeSelectionToolbar() {
    hideSelectionToolbar();
    if (selectionToolbarOverlay && selectionToolbarOverlay.parentNode) {
      selectionToolbarOverlay.parentNode.removeChild(selectionToolbarOverlay);
      selectionToolbarOverlay = null;
    }
  }

  window.addEventListener('scroll', function () {
    if (selectedEl) {
      positionSelectionToolbar(selectedEl);
    }
  }, { passive: true });

  window.addEventListener('resize', function () {
    if (selectedEl) {
      positionSelectionToolbar(selectedEl);
    }
  }, { passive: true });

  function selectElement(el) {
    if (selectedEl && selectedEl !== el) {
      selectedEl.style.outline = '';
      selectedEl.style.outlineOffset = '';
      selectedEl.classList.remove('pd-element-selected');
      if (selectedEl.getAttribute && selectedEl.getAttribute('data-pd-temp-radius')) {
        selectedEl.style.borderRadius = '';
        selectedEl.removeAttribute('data-pd-temp-radius');
      }
      var oldSec = findSection(selectedEl);
      if (oldSec) oldSec.classList.remove('pd-element-selected');
    }
    selectedEl = el;
    if (!el || isInert(el)) {
      hideSelectionToolbar();
      return;
    }
    el.style.outline = '2px solid #38bdf8';
    el.style.outlineOffset = '2px';
    el.classList.add('pd-element-selected');

    try {
      var comp = window.getComputedStyle(el);
      if (!el.style.borderRadius && (comp.borderRadius === '0px' || !comp.borderRadius)) {
        el.style.borderRadius = '4px';
        el.setAttribute('data-pd-temp-radius', '1');
      }
    } catch (e) {}

    var curSec = findSection(el);
    if (curSec) curSec.classList.add('pd-element-selected');

    attachSelectionToolbar(el);
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
    if (justFinishedDrag) {
      e.preventDefault();
      e.stopPropagation();
      return;
    }
    // 1. Prevent link clicks from navigating the preview frame away to 404 or external pages
    var link = e.target.closest('a');
    if (link) {
      var href = link.getAttribute('href') || '';
      if (href.startsWith('#') && href.length > 1) {
        var targetNode = document.getElementById(href.substring(1));
        if (targetNode) {
          e.preventDefault();
          targetNode.scrollIntoView({ behavior: 'smooth' });
          return;
        }
      } else {
        e.preventDefault();
        // Check if internal HTML link (e.g. "about.html", "/about", "contact.html", etc.)
        var cleanHref = href.split('?')[0].split('#')[0];
        if (cleanHref && (cleanHref.endsWith('.html') || cleanHref.endsWith('.htm') || (!cleanHref.includes('.') && !cleanHref.startsWith('http') && !cleanHref.startsWith('//') && !cleanHref.startsWith('mailto:') && !cleanHref.startsWith('tel:')))) {
          var targetPath = cleanHref.replace(/^\//, '');
          if (!targetPath.endsWith('.html') && !targetPath.endsWith('.htm')) {
            targetPath = targetPath ? targetPath + '.html' : 'index.html';
          }
          window.parent.postMessage({
            type: 'navigate-page',
            path: targetPath,
            href: href
          }, '*');
          return;
        }
      }
    }

    // 2. If edit mode is disabled or clicked on background/inert area, dismiss popover
    if (!aiEnabled || isInert(e.target)) {
      if (e.target === document.body || e.target === document.documentElement) {
        if (selectedEl) {
          selectedEl.style.outline = '';
          selectedEl.style.outlineOffset = '';
          selectedEl.classList.remove('pd-element-selected');
          if (selectedEl.getAttribute && selectedEl.getAttribute('data-pd-temp-radius')) {
            selectedEl.style.borderRadius = '';
            selectedEl.removeAttribute('data-pd-temp-radius');
          }
          selectedEl = null;
        }
        hideSelectionToolbar();
        clearSectionHighlight();
      }
      window.parent.postMessage({ type: 'dismiss-popovers' }, '*');
      return;
    }

    // 3. Selection in edit mode
    e.preventDefault();
    e.stopPropagation();
    postSelected(e.target);
  }, true);

  function startTextEdit(el) {
    if (!el || !aiEnabled || isInert(el)) return;
    if (!isEditableTextNode(el) && el.parentElement && isEditableTextNode(el.parentElement)) {
      el = el.parentElement;
    }
    if (!isEditableTextNode(el)) return;
    if (editingEl === el) return;
    if (editingEl) endTextEdit(true);

    window.parent.postMessage({ type: 'text-edit-start' }, '*');
    editingEl = el;
    editingOriginalText = (el.textContent || '');
    el.setAttribute('contenteditable', 'plaintext-only');
    el.style.outline = '2px solid #38bdf8';
    el.style.outlineOffset = '2px';
    el.focus();
    try {
      var range = document.createRange();
      range.selectNodeContents(el);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    } catch (x) {}
    selectElement(el);
  }

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
    e.preventDefault();
    e.stopPropagation();
    startTextEdit(el);
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
        selectedEl.classList.remove('pd-element-selected');
        if (selectedEl.getAttribute && selectedEl.getAttribute('data-pd-temp-radius')) {
          selectedEl.style.borderRadius = '';
          selectedEl.removeAttribute('data-pd-temp-radius');
        }
        selectedEl = null;
      }
      if (hoveredEl) {
        hoveredEl.style.outline = '';
        hoveredEl.style.outlineOffset = '';
        hoveredEl = null;
      }
      clearSectionHighlight();
      hideSelectionToolbar();
      window.parent.postMessage({ type: 'dismiss-popovers' }, '*');
      return;
    }

    if (e.key === 'Delete' || e.key === 'Backspace') {
      var active = document.activeElement;
      var isInputOrTextarea = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA');
      var isContentEditable = active && (active.isContentEditable || (active.getAttribute && (active.getAttribute('contenteditable') === 'true' || active.getAttribute('contenteditable') === 'plaintext-only')));
      var isEditing = editingEl || isInputOrTextarea || isContentEditable || (selectedEl && (selectedEl.isContentEditable || (selectedEl.getAttribute && selectedEl.getAttribute('contenteditable') === 'true')));

      if (selectedEl && !isEditing && selectedEl !== document.body && selectedEl !== document.documentElement) {
        e.preventDefault();
        e.stopPropagation();
        deleteElement(selectedEl);
        return;
      }
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
  function onPointerDown(e) {
    if (!aiEnabled || isInert(e.target) || (e.button !== undefined && e.button !== 0)) return;
    if (editingEl) return;
    var dragHandle = e.target.closest ? e.target.closest('.pd-tool-drag') : null;
    if (dragHandle) {
      var sec = findSection(dragHandle);
      if (sec && sec !== document.body) {
        var pt = e.touches ? e.touches[0] : e;
        dragPotential = {
          startX: pt.clientX,
          startY: pt.clientY,
          sourceSec: sec,
          startTime: Date.now()
        };
      }
    }
  }

  function onPointerMove(e) {
    var pt = e.touches ? e.touches[0] : e;
    if (!pt) return;

    if (dragPotential && !dragState) {
      var dx = Math.abs(pt.clientX - dragPotential.startX);
      var dy = Math.abs(pt.clientY - dragPotential.startY);
      if (dx > 4 || dy > 4) {
        dragState = {
          active: true,
          sourceSec: dragPotential.sourceSec
        };
        document.body.style.userSelect = 'none';
        dragState.sourceSec.style.opacity = '0.5';
        dragState.sourceSec.classList.add('pd-dragging');
        dragState.sourceSec.style.transition = 'opacity 0.15s ease';
      }
    }

    if (dragState && dragState.active) {
      if (e.cancelable) e.preventDefault();
      // Auto-scroll near edges
      if (pt.clientY < 50) {
        window.scrollBy(0, -12);
      } else if (pt.clientY > window.innerHeight - 50) {
        window.scrollBy(0, 12);
      }

      var under = document.elementFromPoint(pt.clientX, pt.clientY);
      if (under && !isInert(under)) {
        var targetSec = findSection(under);
        if (targetSec && targetSec !== dragState.sourceSec) {
          var rect = targetSec.getBoundingClientRect();
          var midY = rect.top + rect.height / 2;
          var pos = pt.clientY < midY ? 'before' : 'after';
          dragState.targetSec = targetSec;
          dragState.position = pos;
          showDropIndicator(rect, pos, targetSec);
          return;
        }
      }
      hideDropIndicator();
      dragState.targetSec = null;
    }
  }

  function onPointerUp(e) {
    if (dragState && dragState.active) {
      if (e.cancelable) e.preventDefault();
      e.stopPropagation();
      justFinishedDrag = true;
      document.body.style.userSelect = '';
      if (dragState.sourceSec) {
        dragState.sourceSec.style.opacity = '';
        dragState.sourceSec.classList.remove('pd-dragging');
      }
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
        if (tgt.parentNode) {
          if (pos === 'before') {
            tgt.parentNode.insertBefore(src, tgt);
          } else {
            tgt.parentNode.insertBefore(src, tgt.nextSibling);
          }
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
      setTimeout(function () { justFinishedDrag = false; }, 300);
      return;
    }
    dragPotential = null;
  }

  document.addEventListener('mousedown', onPointerDown, true);
  document.addEventListener('touchstart', onPointerDown, { passive: false });

  document.addEventListener('mousemove', onPointerMove, true);
  document.addEventListener('touchmove', onPointerMove, { passive: false });

  document.addEventListener('mouseup', onPointerUp, true);
  document.addEventListener('touchend', onPointerUp, true);
  document.addEventListener('touchcancel', onPointerUp, true);

  // Suppress native browser link/image drag ghost showing URL pill inside preview canvas
  document.addEventListener('dragstart', function (e) {
    if (!e.target.closest || !e.target.closest('.pd-tool-drag')) {
      e.preventDefault();
    }
  }, false);

  // --- 5c. Library Component Drag & Drop to Canvas ---
  var libraryDragTarget = null;

  document.addEventListener('dragover', function (e) {
    e.preventDefault();
    if (e.dataTransfer) {
      e.dataTransfer.dropEffect = 'copy';
    }
    var under = document.elementFromPoint(e.clientX, e.clientY);
    if (under && !isInert(under)) {
      var dropPlan = detectDropPosition(under, e.clientX, e.clientY);
      var tgt = dropPlan.target;
      if (tgt && tgt !== document.body && tgt !== document.documentElement) {
        var rect = tgt.getBoundingClientRect();
        showDropIndicator(rect, dropPlan.position, tgt);
        libraryDragTarget = {
          targetTag: tgt.tagName,
          targetIndex: tagIndex(tgt),
          targetId: tgt.getAttribute ? tgt.getAttribute('data-pd-id') : null,
          position: dropPlan.position
        };
        return;
      }
    }
    hideDropIndicator();
    libraryDragTarget = null;
  });

  document.addEventListener('dragleave', function (e) {
    if (!e.relatedTarget) {
      hideDropIndicator();
      libraryDragTarget = null;
    }
  });

  document.addEventListener('drop', function (e) {
    e.preventDefault();
    hideDropIndicator();
    var snippet = '';
    var name = 'Component';
    var rawJson = '';
    try {
      rawJson = e.dataTransfer.getData('application/json');
    } catch (err) {}

    if (rawJson) {
      try {
        var parsed = JSON.parse(rawJson);
        snippet = parsed.snippet || '';
        name = parsed.name || 'Component';
      } catch (err) {}
    }
    if (!snippet) {
      try {
        var plain = (e.dataTransfer.getData('text/plain') || '').trim();
        if (plain.startsWith('<') && plain.endsWith('>')) {
          snippet = plain;
        }
      } catch (err) {}
    }

    if (!snippet || !snippet.trim()) {
      libraryDragTarget = null;
      return;
    }

    window.parent.postMessage({
      type: 'library-drop',
      name: name,
      snippet: snippet,
      targetTag: libraryDragTarget ? libraryDragTarget.targetTag : null,
      targetIndex: libraryDragTarget ? libraryDragTarget.targetIndex : null,
      targetId: libraryDragTarget ? libraryDragTarget.targetId : null,
      position: libraryDragTarget ? libraryDragTarget.position : 'append'
    }, '*');
    libraryDragTarget = null;
  });

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

    // Library Component Drag from Parent Window
    if (d.type === 'library-drag-hover') {
      var under = document.elementFromPoint(d.x, d.y);
      if (under && !isInert(under)) {
        var dropPlan = detectDropPosition(under, d.x, d.y);
        var tgt = dropPlan.target;
        if (tgt && tgt !== document.body && tgt !== document.documentElement) {
          var rect = tgt.getBoundingClientRect();
          showDropIndicator(rect, dropPlan.position, tgt);
          libraryDragTarget = {
            targetTag: tgt.tagName,
            targetIndex: tagIndex(tgt),
            targetId: tgt.getAttribute ? tgt.getAttribute('data-pd-id') : null,
            position: dropPlan.position,
            snippet: d.snippet,
            name: d.name
          };
          return;
        }
      }
      hideDropIndicator();
      libraryDragTarget = null;
    }

    if (d.type === 'library-drag-drop') {
      hideDropIndicator();
      var snippet = d.snippet || (libraryDragTarget ? libraryDragTarget.snippet : '');
      var name = d.name || (libraryDragTarget ? libraryDragTarget.name : 'Component');
      if (snippet) {
        window.parent.postMessage({
          type: 'library-drop',
          name: name,
          snippet: snippet,
          targetTag: libraryDragTarget ? libraryDragTarget.targetTag : null,
          targetIndex: libraryDragTarget ? libraryDragTarget.targetIndex : null,
          position: libraryDragTarget ? libraryDragTarget.position : 'append'
        }, '*');
      }
      libraryDragTarget = null;
    }

    if (d.type === 'library-drag-end') {
      hideDropIndicator();
      libraryDragTarget = null;
    }
  });

})();
