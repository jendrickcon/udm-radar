/* Progressive enhancement only: the server owns all academic calculations and validation. */
(() => {
    'use strict';
    const form = document.getElementById('pef-cc-planning-form');
    if (!form) return;
    const subjects = document.getElementById('pef-cc-subjects');
    const counter = document.querySelector('[data-testid="proposed-count"]');
    const downloadForm = document.getElementById('pef-cc-download-form');
    const downloadButton = document.getElementById('pef-cc-download');
    const invalidated = document.getElementById('preview-invalidated');
    const previewSubmit = document.getElementById('btn-generate-preview');
    const refreshSubmit = document.getElementById('btn-refresh-preview');
    const section = document.getElementById('proposed-section');
    const sectionOptions = section?.tagName === 'SELECT'
        ? new Set(Array.from(section.options).filter(option => option.dataset.approved !== 'false').map(option => option.value)) : null;
    const fixedSection = section?.readOnly ? section.value : null;
    let dirty = false;
    let busy = false;
    let downloadController = null;

    // Accessible toggle disclosure handlers
    document.querySelectorAll('.pef-cc-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('aria-controls');
            const target = document.getElementById(targetId);
            if (!target) return;
            const isExpanded = btn.getAttribute('aria-expanded') === 'true';
            const nextState = !isExpanded;
            btn.setAttribute('aria-expanded', String(nextState));
            target.hidden = !nextState;
            if (btn.id === 'btn-view-current-semester') {
                btn.textContent = nextState ? 'Hide recorded subjects' : 'View recorded subjects';
            } else if (btn.id === 'btn-review-next-plan') {
                btn.textContent = nextState ? 'Hide proposed subjects' : 'View proposed subjects';
            }
        });
    });

    const updateCount = () => {
        if (sectionOptions) {
            section.setCustomValidity(!sectionOptions.has(section.value) ? 'Choose one of the approved section options for this planning context.' : '');
        } else if (fixedSection !== null) {
            section.setCustomValidity(section.value !== fixedSection ? 'Regular students must retain their recorded section for this semester.' : '');
        }
        if (!subjects || !counter) return;
        const count = subjects.selectedOptions.length;
        counter.textContent = String(count);
        subjects.setCustomValidity(count > Number(subjects.dataset.max) ? 'Choose at most twelve proposed subjects. No selections will be truncated.' : '');
    };

    const invalidate = () => {
        updateCount();
        dirty = true;
        if (downloadButton) {
            downloadButton.disabled = true;
            downloadButton.style.display = 'none';
        }
        if (invalidated) {
            invalidated.hidden = false;
        }
        if (refreshSubmit) {
            refreshSubmit.classList.replace('btn--secondary', 'btn--primary');
            refreshSubmit.focus();
        }
        if (previewSubmit) {
            previewSubmit.textContent = 'Refresh preview';
        }
    };

    form.addEventListener('input', invalidate);
    form.addEventListener('change', invalidate);
    form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');
        if (previewSubmit) previewSubmit.disabled = true;
        if (refreshSubmit) refreshSubmit.disabled = true;
    });

    if (downloadForm && downloadButton && window.fetch && window.URL && URL.createObjectURL) {
        const status = document.getElementById('download-status');
        downloadForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || dirty) return;
            busy = true;
            downloadButton.disabled = true;
            downloadForm.setAttribute('aria-busy', 'true');
            if (status) status.textContent = 'Checking your preview and preparing the Excel copy…';
            let objectUrl;
            let timeout;
            try {
                downloadController = typeof AbortController === 'function' ? new AbortController() : null;
                if (downloadController) timeout = window.setTimeout(() => downloadController?.abort(), 45000);
                const response = await fetch(downloadForm.getAttribute('action'), {
                    method: 'POST', credentials: 'same-origin',
                    body: new URLSearchParams(new FormData(downloadForm)),
                    headers: { Accept: 'application/json, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                    cache: 'no-store',
                    ...(downloadController ? { signal: downloadController.signal } : {})
                });
                const type = response.headers.get('content-type') || '';
                if (!response.ok || !type.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                    let message = response.redirected ? 'Your session may have expired. Reload the page and sign in again.' : 'The planning copy could not be downloaded. Try again or refresh the preview.';
                    if (type.includes('application/json')) {
                        const error = await response.json();
                        if (typeof error.error === 'string') message = error.error;
                    }
                    if ([403, 409, 422].includes(response.status)) {
                        dirty = true;
                        if (invalidated) invalidated.hidden = false;
                        if (downloadButton) downloadButton.style.display = 'none';
                        if (refreshSubmit) refreshSubmit.classList.replace('btn--secondary', 'btn--primary');
                    }
                    throw new Error(message);
                }
                const blob = await response.blob();
                if (!blob.size || blob.size > 5242880) throw new Error('The download was incomplete. Refresh the preview and try again.');
                objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = 'UDM-RADAR-PEF-CC.xlsx';
                document.body.append(link);
                link.click();
                link.remove();
                if (status) status.textContent = 'Your planning copy is ready. Proposed subjects still require academic review.';
            } catch (error) {
                if (status) status.textContent = error instanceof Error && error.name === 'AbortError'
                    ? 'The download request was interrupted or timed out. Please try again.'
                    : error instanceof TypeError ? 'The network request failed. Please try again.'
                    : error instanceof Error ? error.message : 'The network request failed. Please try again.';
            } finally {
                if (timeout) window.clearTimeout(timeout);
                downloadController = null;
                if (objectUrl) window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
                busy = false;
                downloadForm.removeAttribute('aria-busy');
                downloadButton.disabled = dirty;
            }
        });
    }

    window.addEventListener('pageshow', () => {
        busy = false;
        form.removeAttribute('aria-busy');
        if (previewSubmit) previewSubmit.disabled = false;
        if (refreshSubmit) refreshSubmit.disabled = false;
        if (downloadForm) downloadForm.removeAttribute('aria-busy');
        if (downloadButton) downloadButton.disabled = dirty;
    });
    window.addEventListener('pagehide', () => downloadController?.abort());
    updateCount();
})();
