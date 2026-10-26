/* REFERENCE ONLY. NOT LOADED BY PRODUCTION PAGES.
Future implementers must adapt this file to the active UDM-RADAR layout and tests. */
export const REFERENCE_LAYOUT_EVENT = 'udmradar:reference-layout-change';
const controllers = new WeakMap();

export function initReferenceSidebar(root, options = {}) {
  if (controllers.has(root)) return controllers.get(root);
  const toggle = root.querySelector('[data-ui-ref-toggle]');
  const sidebar = root.querySelector('[data-ui-ref-sidebar]');
  if (!toggle || !sidebar || !sidebar.id) throw new TypeError('Named toggle and sidebar ID required');
  const win = root.ownerDocument.defaultView;
  const media = options.desktopQuery || win.matchMedia('(min-width: 901px)');
  const reduced = win.matchMedia('(prefers-reduced-motion: reduce)');
  const key = options.storageKey || 'udmRadarReferenceSidebarCollapsedV1';
  let storage = options.storage;
  if (storage === undefined) { try { storage = win.localStorage; } catch { storage = null; } }
  let desired = false;
  try { desired = storage?.getItem(key) === 'true'; } catch { /* Expanded fallback. */ }
  const originalClass = root.classList.contains('ui-ref-sidebar-collapsed');
  const original = new Map(['aria-expanded', 'aria-label', 'aria-controls'].map(name => [name, toggle.getAttribute(name)]));
  let timer = null;
  let pending = false;
  let destroyed = false;
  let applied;
  const finish = () => {
    if (!pending || destroyed) return;
    pending = false;
    clearTimeout(timer);
    timer = null;
    root.dispatchEvent(new win.CustomEvent(REFERENCE_LAYOUT_EVENT, {
      bubbles: true, detail: { collapsed: applied, phase: 'settled' }
    }));
  };
  const duration = () => {
    if (reduced.matches || root.closest('[data-motion="reduce"]')) return 0;
    const raw = win.getComputedStyle(root).getPropertyValue('--ui-ref-motion-sidebar').trim();
    const value = Number.parseFloat(raw);
    if (!Number.isFinite(value)) return 180;
    return Math.min(200, Math.max(0, raw.endsWith('ms') ? value : value * 1000));
  };
  const apply = () => {
    const next = media.matches && desired;
    root.classList.toggle('ui-ref-sidebar-collapsed', next);
    toggle.setAttribute('aria-expanded', String(!next));
    toggle.setAttribute('aria-label', next ? 'Expand navigation' : 'Collapse navigation');
    toggle.setAttribute('aria-controls', sidebar.id);
    if (next === applied) return;
    applied = next;
    pending = true;
    clearTimeout(timer);
    timer = setTimeout(finish, duration() + (duration() ? 30 : 0));
  };
  const setCollapsed = value => {
    if (destroyed) return;
    if (typeof value !== 'boolean') throw new TypeError('Collapse preference must be boolean');
    desired = value;
    try { storage?.setItem(key, String(value)); } catch { /* In-memory preference still works. */ }
    apply();
  };
  const onClick = () => setCollapsed(!desired);
  const onTransition = event => { if (event.target === root && event.propertyName === 'grid-template-columns') finish(); };
  const destroy = () => {
    if (destroyed) return;
    destroyed = true;
    pending = false;
    clearTimeout(timer);
    toggle.removeEventListener('click', onClick);
    media.removeEventListener('change', apply);
    reduced.removeEventListener('change', apply);
    root.removeEventListener('transitionend', onTransition);
    win.removeEventListener('pagehide', destroy);
    root.classList.toggle('ui-ref-sidebar-collapsed', originalClass);
    for (const [name, value] of original) {
      if (value === null) toggle.removeAttribute(name); else toggle.setAttribute(name, value);
    }
    controllers.delete(root);
  };
  const controller = { setCollapsed, destroy };
  controllers.set(root, controller);
  toggle.addEventListener('click', onClick);
  media.addEventListener('change', apply);
  reduced.addEventListener('change', apply);
  root.addEventListener('transitionend', onTransition);
  win.addEventListener('pagehide', destroy);
  apply();
  return controller;
}
