/* ================================================================
   INVESTHOOD IT - PROGRAMME MANAGER REPORTS
   Interactive Summary Cards + Detailed Modals
   ================================================================ */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return 'Date not set';
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('en-ZA', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function iconForReport(key) {
        const icons = {
            total_programmes: 'fa-graduation-cap',
            active_programmes: 'fa-play-circle',
            completed_programmes: 'fa-check-circle',
            paused_programmes: 'fa-pause-circle',
            total_cohorts: 'fa-layer-group',
            total_candidates: 'fa-users',
            completed_candidates: 'fa-user-check',
            completion_rate: 'fa-chart-line',
            active_candidates: 'fa-user-clock',
            withdrawn_candidates: 'fa-user-minus'
        };
        return icons[key] || 'fa-chart-line';
    }

    function renderDetails(data) {
        const items = Array.isArray(data.items) ? data.items : [];
        const summary = Array.isArray(data.summary) ? data.summary : [];

        if (summary.length) {
            return `
                <div class="pm-report-summary-grid">
                    ${summary.map(item => `
                        <div class="pm-report-summary-item">
                            <span class="pm-report-summary-item__label">${escapeHtml(item.label)}</span>
                            <span class="pm-report-summary-item__value">${escapeHtml(item.value)}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (!items.length) {
            return `
                <div class="pm-report-modal__empty">
                    <i class="fas fa-inbox"></i>
                    <strong>No records found</strong>
                    <span>There are currently no records for this report.</span>
                </div>
            `;
        }

        const isProgramme = Object.prototype.hasOwnProperty.call(items[0], 'cohort_count');
        const isCohort = Object.prototype.hasOwnProperty.call(items[0], 'programme_name') &&
            Object.prototype.hasOwnProperty.call(items[0], 'candidate_count') &&
            Object.prototype.hasOwnProperty.call(items[0], 'start_date');

        return `<div class="pm-report-detail-list">
            ${items.map(item => {
                if (isProgramme) {
                    return `
                        <article class="pm-report-detail-item">
                            <div class="pm-report-detail-item__main">
                                <span class="pm-report-detail-item__title">${escapeHtml(item.name)}</span>
                                <div class="pm-report-detail-item__meta">
                                    <span>${escapeHtml(item.type)}</span>
                                    <span>${formatDate(item.start_date)}${item.end_date ? ` – ${formatDate(item.end_date)}` : ''}</span>
                                    <span class="pm-report-status">${escapeHtml(item.status)}</span>
                                </div>
                            </div>
                            <div class="pm-report-detail-item__value">
                                ${Number(item.candidate_count || 0).toLocaleString()} candidates<br>
                                ${Number(item.cohort_count || 0).toLocaleString()} cohorts
                            </div>
                        </article>
                    `;
                }

                if (isCohort) {
                    return `
                        <article class="pm-report-detail-item">
                            <div class="pm-report-detail-item__main">
                                <span class="pm-report-detail-item__title">${escapeHtml(item.name)}</span>
                                <div class="pm-report-detail-item__meta">
                                    <span>${escapeHtml(item.programme_name)}</span>
                                    <span>${formatDate(item.start_date)}${item.end_date ? ` – ${formatDate(item.end_date)}` : ''}</span>
                                    <span class="pm-report-status">${escapeHtml(item.status)}</span>
                                </div>
                            </div>
                            <div class="pm-report-detail-item__value">
                                ${Number(item.candidate_count || 0).toLocaleString()} candidates
                            </div>
                        </article>
                    `;
                }

                return `
                    <article class="pm-report-detail-item">
                        <div class="pm-report-detail-item__main">
                            <span class="pm-report-detail-item__title">${escapeHtml(item.candidate_name)}</span>
                            <div class="pm-report-detail-item__meta">
                                <span>${escapeHtml(item.programme_name)}</span>
                                <span>${escapeHtml(item.cohort_name)}</span>
                                ${item.email ? `<span>${escapeHtml(item.email)}</span>` : ''}
                            </div>
                        </div>
                        <div class="pm-report-detail-item__value">
                            <span class="pm-report-status">${escapeHtml(item.participant_status)}</span>
                        </div>
                    </article>
                `;
            }).join('')}
        </div>`;
    }

    function initReportCards() {
        const modal = document.getElementById('pmReportModal');
        const body = document.getElementById('pmReportModalBody');
        const title = document.getElementById('pmReportModalTitle');
        const subtitle = document.getElementById('pmReportModalSubtitle');
        const icon = document.getElementById('pmReportModalIcon');
        const close = document.getElementById('pmReportModalClose');
        const cards = document.querySelectorAll('.pm-report-card[data-report-key]');

        if (!modal || !body || !title || !subtitle || !close || !cards.length) {
            return;
        }

        let lastTrigger = null;

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('pm-report-modal-open');
            if (lastTrigger) lastTrigger.focus();
        }

        function openModal(trigger, key) {
            lastTrigger = trigger;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('pm-report-modal-open');
            title.textContent = 'Report Details';
            subtitle.textContent = 'Loading report details...';
            body.innerHTML = `
                <div class="pm-report-modal__loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading report details...</span>
                </div>
            `;
            if (icon) {
                icon.innerHTML = `<i class="fas ${iconForReport(key)}"></i>`;
            }

            const endpoint = new URL('report_details.php', window.location.href);
            endpoint.searchParams.set('report', key);

            fetch(endpoint.toString(), {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(result => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Unable to load report details.');
                    }

                    const data = result.data.data || {};
                    title.textContent = data.title || 'Report Details';
                    subtitle.textContent = data.subtitle || '';
                    body.innerHTML = renderDetails(data);
                })
                .catch(error => {
                    body.innerHTML = `
                        <div class="pm-report-modal__error">
                            <i class="fas fa-circle-exclamation"></i>
                            <strong>Unable to load report details</strong>
                            <span>${escapeHtml(error.message)}</span>
                        </div>
                    `;
                });
        }

        cards.forEach(card => {
            card.addEventListener('click', function () {
                openModal(this, this.dataset.reportKey);
            });
        });

        close.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReportCards);
    } else {
        initReportCards();
    }
})();
