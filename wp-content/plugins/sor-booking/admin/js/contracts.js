(function () {
    const config = window.SORBookingContracts || {};
    const container = document.getElementById('sor-booking-contracts');

    if (!container) {
        return;
    }

    const ajaxUrl = config.ajaxUrl || window.ajaxurl;
    const messageArea = container.querySelector('.sor-contracts-messages');
    if (!ajaxUrl) {
        return;
    }

    const viewer = container.querySelector('#sor-contracts-viewer');
    const viewerFrame = viewer ? viewer.querySelector('iframe') : null;
    const auditModal = container.querySelector('#sor-contracts-audit-modal');
    const auditBody = auditModal ? auditModal.querySelector('.js-sor-contract-audit-entries') : null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);

        return div.innerHTML;
    }

    function showMessage(type, text) {
        if (!messageArea || !text) {
            return;
        }

        const notice = document.createElement('div');
        notice.className = `notice notice-${type}`;
        notice.innerHTML = `<p>${escapeHtml(text)}</p>`;
        messageArea.innerHTML = '';
        messageArea.appendChild(notice);
    }

    function formatTimestamp(timestamp) {
        if (!timestamp) {
            return '';
        }

        const parsed = new Date(timestamp);
        if (Number.isNaN(parsed.getTime())) {
            // Try to normalise MySQL timestamps.
            const normalised = timestamp.replace(' ', 'T');
            const fallback = new Date(normalised);
            if (!Number.isNaN(fallback.getTime())) {
                return fallback.toLocaleString();
            }

            return timestamp;
        }

        return parsed.toLocaleString();
    }

    function updateLastCheck(cell, result) {
        if (!cell) {
            return;
        }

        const labels = config.validationLabels || {};
        const strings = config.strings || {};
        const status = result && result.status ? String(result.status).toUpperCase() : '';
        const label = status && labels[status] ? labels[status] : status;
        const timestamp = result && (result.received_at || result.timestamp || result.signed_at);
        const formattedTime = formatTimestamp(timestamp);
        const summary = status === 'VALID' ? strings.verifySuccess : (status ? strings.verifyError : strings.pendingLabel);

        cell.dataset.status = status;

        if (status) {
            const statusClass = `sor-contract-validation sor-contract-validation--${status.toLowerCase()}`;
            cell.innerHTML = `<span class="${statusClass}">${escapeHtml(label)}</span>`;
        } else {
            cell.innerHTML = `<span class="sor-contract-validation sor-contract-validation--pending">${escapeHtml(strings.pendingLabel || '')}</span>`;
        }

        const meta = [];
        if (formattedTime) {
            meta.push(formattedTime);
        }
        if (summary) {
            meta.push(summary);
        }
        if (meta.length) {
            const description = document.createElement('div');
            description.className = 'description';
            description.textContent = meta.join(' · ');
            cell.appendChild(description);
        }
    }

    function toggleViewer(url) {
        if (!viewer || !viewerFrame) {
            return;
        }

        if (url) {
            viewer.hidden = false;
            viewerFrame.src = url;
            viewerFrame.focus();
        } else {
            viewer.hidden = true;
            viewerFrame.src = 'about:blank';
        }
    }

    function openAudit(entries) {
        if (!auditModal || !auditBody) {
            return;
        }

        auditBody.innerHTML = '';

        if (!entries || !entries.length) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'sor-contracts-audit-empty';
            emptyRow.innerHTML = `<td colspan="5">${escapeHtml((config.strings && config.strings.auditEmpty) || '')}</td>`;
            auditBody.appendChild(emptyRow);
        } else {
            entries.forEach((entry) => {
                const status = entry.meta && entry.meta.status ? String(entry.meta.status).toUpperCase() : '';
                const statusLabel = status && config.validationLabels && config.validationLabels[status] ? config.validationLabels[status] : status;
                const statusClass = status ? `sor-contract-validation sor-contract-validation--${status.toLowerCase()}` : 'sor-contract-validation';

                const row = document.createElement('tr');
                row.innerHTML = [
                    `<td>${escapeHtml(formatTimestamp(entry.timestamp))}</td>`,
                    `<td>${escapeHtml(entry.action || '')}</td>`,
                    `<td>${escapeHtml(entry.user || '')}</td>`,
                    `<td><span class="${statusClass}">${escapeHtml(statusLabel || '')}</span></td>`,
                    `<td><code>${escapeHtml(entry.hash || '')}</code></td>`,
                ].join('');
                auditBody.appendChild(row);
            });
        }

        auditModal.hidden = false;
    }

    function closeAudit() {
        if (auditModal) {
            auditModal.hidden = true;
            if (auditBody) {
                auditBody.innerHTML = '';
            }
        }
    }

    container.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.classList.contains('js-sor-contract-verify')) {
            event.preventDefault();
            const uuid = target.dataset.contract;
            const row = target.closest('tr');
            const cell = row ? row.querySelector('.js-sor-contract-last-check') : null;

            if (!uuid) {
                return;
            }

            target.disabled = true;
            const formData = new window.FormData();
            formData.append('action', 'sor_booking_contract_verify');
            formData.append('nonce', config.nonce || container.dataset.nonce || '');
            formData.append('uuid', uuid);

            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Verification failed');
                    }

                    showMessage('success', (config.strings && config.strings.verifySuccess) || '');
                    updateLastCheck(cell, payload.data || {});
                })
                .catch((error) => {
                    showMessage('error', error && error.message ? error.message : (config.strings && config.strings.verifyError) || '');
                })
                .finally(() => {
                    target.disabled = false;
                });
        }

        if (target.classList.contains('js-sor-contract-preview')) {
            event.preventDefault();
            const url = target.dataset.url;
            toggleViewer(url || '');
        }

        if (target.classList.contains('js-sor-contract-viewer-close')) {
            event.preventDefault();
            toggleViewer('');
        }

        if (target.classList.contains('js-sor-contract-audit')) {
            event.preventDefault();
            const uuid = target.dataset.contract;
            if (!uuid) {
                return;
            }

            const formData = new window.FormData();
            formData.append('action', 'sor_booking_contract_audit');
            formData.append('nonce', config.nonce || container.dataset.nonce || '');
            formData.append('uuid', uuid);

            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Audit failed');
                    }

                    const data = payload.data || {};
                    openAudit(data.audit || []);
                })
                .catch((error) => {
                    showMessage('error', error && error.message ? error.message : (config.strings && config.strings.auditError) || '');
                });
        }

        if (target.classList.contains('js-sor-contract-modal-close')) {
            event.preventDefault();
            closeAudit();
        }
    });

    if (auditModal) {
        auditModal.addEventListener('click', (event) => {
            if (event.target === auditModal) {
                closeAudit();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            toggleViewer('');
            closeAudit();
        }
    });
})();
