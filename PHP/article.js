const STORAGE_KEY = `scroll-position:${document.title || 'article'}:${location.pathname}`;

function saveScrollPosition(y) {
  try {
    const yInt = Math.max(0, Math.floor(Number(y)));
    localStorage.setItem(STORAGE_KEY, String(yInt));
  } catch (err) {
    console.error('Unable to save scroll position', err);
  }
}

function readStoredPosition() {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    if (v == null) return null;
    const n = Number.parseInt(v, 10);
    return Number.isNaN(n) ? null : n;
  } catch (err) {
    console.error('Unable to read scroll position', err);
    return null;
  }
}

function restoreScrollPosition() {
  const y = readStoredPosition();
  if (y == null) return;

  try {
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

    const jump = () => {
      window.scrollTo({ top: y, left: 0 });
    };

    if (document.readyState === 'complete') {
      requestAnimationFrame(jump);
    } else {
      window.addEventListener('load', () => requestAnimationFrame(jump), { once: true });
    }
  } catch (err) {
    console.error('Unable to restore scroll position', err);
  }
}

let scrollTimeoutId = null;
window.addEventListener(
  'scroll',
  () => {
    if (scrollTimeoutId !== null) clearTimeout(scrollTimeoutId);
    scrollTimeoutId = setTimeout(() => {
      saveScrollPosition(window.scrollY);
      scrollTimeoutId = null;
    }, 1000);
  },
  { passive: true }
);

window.addEventListener('beforeunload', () => saveScrollPosition(window.scrollY));
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') saveScrollPosition(window.scrollY);
});

function isInsideHighlight(node) {
  if (!node) return false;
  if (node.nodeType === Node.ELEMENT_NODE) {
    return node.closest('.highlight');
  }
  const parent = node.parentElement;
  return parent ? parent.closest('.highlight') : null;
}

function createHighlightSpan() {
  const span = document.createElement('span');
  span.classList.add('highlight');
  span.addEventListener('click', (event) => {
    event.stopPropagation();
    const current = event.currentTarget;
    if (!(current instanceof HTMLElement) || !current.parentNode) return;
    const textNode = document.createTextNode(current.textContent || '');
    current.parentNode.replaceChild(textNode, current);
  });
  return span;
}

function handleArticleMouseUp(article, event) {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
    return;
  }

  const range = selection.getRangeAt(0);
  const ancestor = range.commonAncestorContainer;

  if (!article.contains(ancestor)) {
    return;
  }

  if (isInsideHighlight(range.startContainer) || isInsideHighlight(range.endContainer)) {
    return;
  }

  const highlight = createHighlightSpan();

  try {
    range.surroundContents(highlight);
    selection.removeAllRanges();
  } catch (error) {
    console.error('Unable to create highlight', error);
  }
}

function downloadHighlights(article) {
  const highlights = Array.from(article.querySelectorAll('.highlight')).map((span) => span.textContent || '');
  const json = JSON.stringify(highlights, null, 2);
  const encoded = encodeURIComponent(json);

  const anchor = document.createElement('a');
  anchor.href = `data:application/json;charset=utf-8,${encoded}`;
  anchor.download = 'highlights.json';

  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
}

function setupHighlighting() {
  const article = document.querySelector('main');
  if (!article) {
    return;
  }

  article.addEventListener('mouseup', (event) => handleArticleMouseUp(article, event));

  const downloadButton = article.querySelector('footer button[type="button"]');
  if (downloadButton) {
    downloadButton.addEventListener('click', () => downloadHighlights(article));
  }
}

document.addEventListener('DOMContentLoaded', restoreScrollPosition);
document.addEventListener('DOMContentLoaded', setupHighlighting);
window.addEventListener('pageshow', (e) => {
  if (e.persisted) restoreScrollPosition();
});
