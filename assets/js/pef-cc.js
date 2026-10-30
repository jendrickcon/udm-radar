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
    const loadingPanel = document.getElementById('pef-cc-loading-panel');
    const downloadFeedback = document.getElementById('pef-cc-download-feedback');
    const announcer = document.getElementById('pef-cc-live-announcer');
    const workbookStep = document.getElementById('step-workbook');
    const section = document.getElementById('proposed-section');
    const sectionOptions = section?.tagName === 'SELECT'
        ? new Set(Array.from(section.options).filter(option => option.dataset.approved !== 'false').map(option => option.value)) : null;
    const fixedSection = section?.readOnly ? section.value : null;
    const initialPreviewDisabled = previewSubmit ? previewSubmit.hasAttribute('disabled') : false;
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
        const choices = Array.from(subjects.querySelectorAll('input[type="checkbox"]'));
        const checked = choices.filter(choice => choice.checked);
        const maximum = Number(subjects.dataset.max);
        counter.textContent = String(checked.length);
        for (const choice of choices) {
            choice.disabled = !choice.checked && checked.length >= maximum;
            choice.setCustomValidity('');
        }
        if (checked.length > maximum) checked[0].setCustomValidity('Choose at most twelve proposed subjects. Uncheck a subject before continuing.');
    };

    const invalidate = () => {
        updateCount();
        dirty = true;
        if (downloadButton) {
            downloadButton.disabled = true;
            downloadButton.setAttribute('aria-disabled', 'true');
            downloadButton.style.display = 'none';
        }
        if (loadingPanel) {
            loadingPanel.hidden = true;
        }
        if (downloadFeedback) {
            downloadFeedback.hidden = true;
            downloadFeedback.textContent = '';
        }
        if (invalidated) {
            invalidated.textContent = 'The academic record or plan changed. Refresh the preview before downloading.';
            invalidated.hidden = false;
        }
        if (refreshSubmit) {
            refreshSubmit.classList.replace('btn--secondary', 'btn--primary');
        }
        if (previewSubmit) {
            previewSubmit.textContent = 'Refresh preview';
        }
    };

    // form.elements includes controls associated through the form attribute,
    // even when the guided layout places them outside the transport form.
    Array.from(form.elements).forEach(control => {
        control.addEventListener('input', invalidate);
        control.addEventListener('change', invalidate);
    });
    form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');
        if (previewSubmit) previewSubmit.disabled = true;
        if (refreshSubmit) refreshSubmit.disabled = true;
    });

    if (downloadForm && downloadButton && window.fetch && window.URL && URL.createObjectURL) {
        const originalButtonHtml = downloadButton.innerHTML;

        downloadButton.addEventListener('click', event => {
            if (busy || dirty) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        });

        downloadForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || dirty) return;
            busy = true;

            // Preserve button width to prevent layout shift
            const rect = downloadButton.getBoundingClientRect();
            if (rect.width > 0) {
                downloadButton.style.minWidth = `${Math.ceil(rect.width)}px`;
            }

            downloadButton.disabled = true;
            downloadButton.setAttribute('aria-disabled', 'true');
            downloadButton.innerHTML = '<span class="pef-cc-btn-spinner" aria-hidden="true"></span><span class="pef-cc-btn-text">Preparing PDF…</span>';

            if (refreshSubmit) {
                refreshSubmit.disabled = true;
            }
            if (workbookStep) {
                workbookStep.setAttribute('aria-busy', 'true');
            }
            if (loadingPanel) {
                loadingPanel.hidden = false;
            }
            if (downloadFeedback) {
                downloadFeedback.hidden = true;
                downloadFeedback.textContent = '';
            }

            let objectUrl;
            let timeout;
            try {
                downloadController = typeof AbortController === 'function' ? new AbortController() : null;
                if (downloadController) timeout = window.setTimeout(() => downloadController?.abort(), 60000);

                const response = await fetch(downloadForm.getAttribute('action'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: new URLSearchParams(new FormData(downloadForm)),
                    headers: {
                        Accept: 'application/pdf, application/json'
                    },
                    cache: 'no-store',
                    ...(downloadController ? { signal: downloadController.signal } : {})
                });

                const type = (response.headers.get('content-type') || '').toLowerCase();

                if (response.status === 409) {
                    dirty = true;
                    const msg = 'The academic record or plan changed. Refresh the preview before downloading.';
                    if (invalidated) {
                        invalidated.textContent = msg;
                        invalidated.hidden = false;
                    }
                    if (downloadFeedback) {
                        downloadFeedback.textContent = msg;
                        downloadFeedback.hidden = false;
                    }
                    if (announcer) {
                        announcer.textContent = msg;
                    }
                    if (downloadButton) {
                        downloadButton.disabled = true;
                        downloadButton.setAttribute('aria-disabled', 'true');
                    }
                    if (refreshSubmit) {
                        refreshSubmit.disabled = false;
                        refreshSubmit.classList.replace('btn--secondary', 'btn--primary');
                        refreshSubmit.focus();
                    }
                    return;
                }

                if (!response.ok || !type.includes('application/pdf')) {
                    const msg = 'The planning PDF could not be created. Refresh the preview and try again.';
                    if (downloadFeedback) {
                        downloadFeedback.textContent = msg;
                        downloadFeedback.hidden = false;
                    }
                    if (announcer) {
                        announcer.textContent = msg;
                    }
                    if (refreshSubmit) {
                        refreshSubmit.disabled = false;
                        refreshSubmit.focus();
                    }
                    return;
                }

                const blob = await response.blob();
                if (!blob.size || blob.size > 5242880) {
                    const msg = 'The planning PDF could not be created. Refresh the preview and try again.';
                    if (downloadFeedback) {
                        downloadFeedback.textContent = msg;
                        downloadFeedback.hidden = false;
                    }
                    if (announcer) {
                        announcer.textContent = msg;
                    }
                    return;
                }

                objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = 'UDM-RADAR-PEF-CC.pdf';
                document.body.appendChild(link);
                link.click();
                link.remove();

                if (announcer) {
                    announcer.textContent = 'Your planning PDF download has started.';
                }
            } catch (error) {
                const isAbort = error instanceof Error && error.name === 'AbortError';
                const message = isAbort || error instanceof TypeError
                    ? 'The PDF download was interrupted. Check the connection and try again.'
                    : (error instanceof Error && error.message ? error.message : 'The PDF download was interrupted. Check the connection and try again.');
                if (downloadFeedback) {
                    downloadFeedback.textContent = message;
                    downloadFeedback.hidden = false;
                }
                if (announcer) {
                    announcer.textContent = message;
                }
                if (downloadButton && !dirty) {
                    downloadButton.focus();
                }
            } finally {
                if (timeout) window.clearTimeout(timeout);
                downloadController = null;
                if (objectUrl) {
                    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 100);
                }
                busy = false;
                if (loadingPanel) {
                    loadingPanel.hidden = true;
                }
                if (workbookStep) {
                    workbookStep.setAttribute('aria-busy', 'false');
                }
                if (refreshSubmit && !dirty) {
                    refreshSubmit.disabled = false;
                }
                if (downloadButton) {
                    downloadButton.innerHTML = originalButtonHtml;
                    downloadButton.style.minWidth = '';
                    downloadButton.disabled = dirty;
                    if (dirty) {
                        downloadButton.setAttribute('aria-disabled', 'true');
                    } else {
                        downloadButton.removeAttribute('aria-disabled');
                    }
                }
            }
        });
    }

    window.addEventListener('pageshow', () => {
        busy = false;
        form.removeAttribute('aria-busy');
        if (previewSubmit && !initialPreviewDisabled) previewSubmit.disabled = false;
        if (refreshSubmit) refreshSubmit.disabled = false;
        if (downloadForm) downloadForm.removeAttribute('aria-busy');
        if (downloadButton) {
            downloadButton.disabled = dirty;
            if (dirty) {
                downloadButton.setAttribute('aria-disabled', 'true');
            } else {
                downloadButton.removeAttribute('aria-disabled');
            }
        }
    });
    window.addEventListener('pagehide', () => downloadController?.abort());
    updateCount();
})();
