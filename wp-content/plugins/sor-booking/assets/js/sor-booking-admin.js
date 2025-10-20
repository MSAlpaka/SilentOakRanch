(function ($) {
    'use strict';

    const Admin = {
        init() {
            this.config = window.SORBookingAdmin || {};
            this.$wrap = $('#sor-booking-admin');
            if (!this.$wrap.length) {
                return;
            }

            this.$filters = this.$wrap.find('#sor-booking-filters');
            this.$table = this.$wrap.find('.sor-booking-table');
            this.$tbody = this.$table.find('tbody');
            this.$pagination = this.$wrap.find('.sor-booking-pagination');
            this.$summaryCount = this.$wrap.find('.sor-booking-summary__count');
            this.$summaryPage = this.$wrap.find('.sor-booking-summary__page');
            this.$toast = this.$wrap.find('.sor-booking-toast');
            this.$detailsModal = $('#sor-booking-details-modal');
            this.$qrModal = $('#sor-booking-qr-modal');

            this.statuses = this.config.statuses || {};
            this.strings = this.config.strings || {};
            this.perPage = parseInt(this.config.perPage, 10) || 20;
            this.restUrl = (this.config.restUrl || '').replace(/\/$/, '');
            this.endpoints = {
                update: this.restUrl + '/admin/update',
                list: this.restUrl + '/admin/list'
            };
            this.labels = {
                qr: this.$wrap.find('.sor-booking-show-qr').first().text() || 'QR',
                status: this.$wrap.find('.sor-booking-status-select').first().attr('aria-label') || ''
            };

            this.bind();
        },

        bind() {
            this.$wrap.on('click', '.sor-booking-row', (event) => {
                const $target = $(event.target);
                if ($target.closest('select, button, a, label').length) {
                    return;
                }
                this.openDetails($(event.currentTarget));
            });

            this.$wrap.on('click', '.sor-booking-show-qr', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const uuid = $(event.currentTarget).data('uuid');
                this.openQr(uuid);
            });

            this.$wrap.on('change', '.sor-booking-status-select', (event) => {
                event.stopPropagation();
                const $select = $(event.currentTarget);
                const uuid = $select.data('uuid');
                const current = $select.data('current');
                const status = $select.val();

                if (!uuid || !status || status === current) {
                    $select.val(current);
                    return;
                }

                if ('cancelled' === status && !window.confirm(this.strings.confirmCancel || 'Buchung stornieren?')) {
                    $select.val(current);
                    return;
                }

                this.updateStatus(uuid, status, $select);
            });

            this.$wrap.on('click', '[data-close="modal"]', (event) => {
                event.preventDefault();
                this.closeModal($(event.currentTarget).closest('.sor-modal'));
            });

            $(document).on('keydown', (event) => {
                if (event.key === 'Escape') {
                    if (this.$detailsModal.is(':visible')) {
                        this.closeModal(this.$detailsModal);
                    }
                    if (this.$qrModal.is(':visible')) {
                        this.closeModal(this.$qrModal);
                    }
                }
            });

            this.$filters.on('submit', (event) => {
                event.preventDefault();
                this.loadBookings(1);
            });

            this.$wrap.on('click', '.sor-booking-pagination a', (event) => {
                event.preventDefault();
                const page = parseInt($(event.currentTarget).data('page'), 10);
                if (page) {
                    this.loadBookings(page);
                }
            });
        },

        getFilterValues() {
            const data = {};
            this.$filters.serializeArray().forEach((item) => {
                if (item.name === 'page') {
                    return;
                }
                data[item.name] = item.value || '';
            });
            return data;
        },

        loadBookings(page) {
            const params = this.getFilterValues();
            params.page = page;
            params.per_page = this.perPage;

            this.$table.addClass('is-loading');
            this.showToast(this.strings.loading || 'Loading…', 'info');

            const url = new URL(this.endpoints.list, window.location.origin);
            Object.keys(params).forEach((key) => {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                }
            });

            fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': this.config.nonce || ''
                }
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    if (!data || false === data.ok) {
                        throw new Error('Request failed');
                    }
                    this.renderRows(data.items || []);
                    this.updateSummary(data.total || 0, data.page || 1, data.total_pages || 1);
                    this.renderPagination(data.page || 1, data.total_pages || 1, params);
                    this.hideToast();
                })
                .catch(() => {
                    this.showToast(this.strings.statusFailed || 'Fehler beim Laden.', 'error');
                })
                .finally(() => {
                    this.$table.removeClass('is-loading');
                });
        },

        renderRows(items) {
            if (!items.length) {
                this.$tbody.html(
                    '<tr><td colspan="12" class="sor-booking-empty">' + this.escapeHtml(this.strings.noResults || 'Keine Buchungen gefunden.') + '</td></tr>'
                );
                return;
            }

            const rows = items.map((item) => this.renderRow(item));
            this.$tbody.html(rows.join(''));
        },

        renderRow(item) {
            const data = this.buildRowData(item);
            const attr = this.escapeAttr(JSON.stringify(data));
            const statusOptions = this.buildStatusOptions(data.status);
            const selectLabel = this.escapeAttr(this.strings.statusLabel || 'Status ändern');
            const badgeClass = 'sor-booking-status sor-booking-status--' + data.status;
            const qrLabel = this.escapeHtml(this.labels.qr || 'QR');

            return (
                '<tr class="sor-booking-row" data-uuid="' + this.escapeAttr(data.uuid) + '" data-booking="' + attr + '">' +
                '<td>' + this.escapeHtml(String(data.id)) + '</td>' +
                '<td>' + this.escapeHtml(data.resource) + '</td>' +
                '<td>' + this.escapeHtml(data.name) + '</td>' +
                '<td>' + this.escapeHtml(data.phone) + '</td>' +
                '<td>' + this.escapeHtml(data.email) + '</td>' +
                '<td>' + this.escapeHtml(data.horse) + '</td>' +
                '<td>' + this.escapeHtml(data.slot) + '</td>' +
                '<td>' + this.escapeHtml(data.price_display) + '</td>' +
                '<td>' +
                    '<span class="' + badgeClass + '">' + this.escapeHtml(data.status_label) + '</span>' +
                    '<select class="sor-booking-status-select" aria-label="' + selectLabel + '" data-uuid="' + this.escapeAttr(data.uuid) + '" data-current="' + this.escapeAttr(data.status) + '">' + statusOptions + '</select>' +
                    '<button type="button" class="button button-small sor-booking-show-qr" data-uuid="' + this.escapeAttr(data.uuid) + '">' + qrLabel + '</button>' +
                '</td>' +
                '<td>' + this.escapeHtml(data.payment_ref) + '</td>' +
                '<td>' + this.escapeHtml(data.created_at) + '</td>' +
                '<td>' + this.escapeHtml(data.updated_at) + '</td>' +
                '</tr>'
            );
        },

        buildRowData(item) {
            return {
                id: item.id,
                uuid: item.uuid,
                resource: item.resource_label || item.resource || '',
                name: item.name || '',
                phone: item.phone || '',
                email: item.email || '',
                horse: item.horse_name || '',
                slot: item.slot_human || '',
                price: item.price_formatted ? item.price_formatted + ' €' : '',
                price_display: item.price_formatted || '',
                status: item.status || 'pending',
                status_label: item.status_label || item.status || '',
                payment_ref: item.payment_ref || '',
                created_at: item.created_human || '',
                updated_at: item.updated_human || ''
            };
        },

        buildStatusOptions(current) {
            return Object.keys(this.statuses).map((key) => {
                const selected = key === current ? ' selected="selected"' : '';
                return '<option value="' + this.escapeAttr(key) + '"' + selected + '>' + this.escapeHtml(this.statuses[key]) + '</option>';
            }).join('');
        },

        updateSummary(total, page, totalPages) {
            if (this.$summaryCount.length) {
                const label = this.strings.totalLabel || '%d';
                this.$summaryCount.text(label.replace('%d', total));
            }
            if (this.$summaryPage.length) {
                const template = this.strings.pageLabel || 'Seite %1$d von %2$d';
                this.$summaryPage.text(template.replace('%1$d', page).replace('%2$d', totalPages));
            }
            this.$table.attr('data-total-pages', totalPages);
        },

        renderPagination(page, totalPages, filters) {
            if (!this.$pagination.length) {
                return;
            }

            if (totalPages <= 1) {
                this.$pagination.empty();
                return;
            }

            const cleanFilters = Object.assign({}, filters);
            delete cleanFilters.page;
            delete cleanFilters.per_page;

            const links = [];
            const prevPage = page > 1 ? page - 1 : null;
            const nextPage = page < totalPages ? page + 1 : null;

            links.push(this.buildPaginationLink(prevPage, 'prev', '‹', cleanFilters));

            const maxLinks = 5;
            let start = Math.max(1, page - 2);
            let end = Math.min(totalPages, start + maxLinks - 1);
            if (end - start < maxLinks - 1) {
                start = Math.max(1, end - maxLinks + 1);
            }

            for (let i = start; i <= end; i++) {
                links.push(this.buildPaginationLink(i === page ? null : i, 'number', String(i), cleanFilters, i === page));
            }

            links.push(this.buildPaginationLink(nextPage, 'next', '›', cleanFilters));

            this.$pagination.html(links.join(''));
        },

        buildPaginationLink(page, type, label, filters, isCurrent) {
            const classes = ['page-numbers'];
            if (type === 'prev' || type === 'next') {
                classes.push(type);
            }
            if (!page) {
                classes.push('disabled');
                if (isCurrent) {
                    classes.push('current');
                }
                return '<span class="' + classes.join(' ') + '">' + this.escapeHtml(label) + '</span>';
            }

            const url = this.buildAdminUrl(page, filters);
            if (isCurrent) {
                classes.push('current');
            }

            return '<a class="' + classes.join(' ') + '" href="' + this.escapeAttr(url) + '" data-page="' + this.escapeAttr(page) + '">' + this.escapeHtml(label) + '</a>';
        },

        buildAdminUrl(page, filters) {
            const url = new URL(this.config.listUrl || window.location.href, window.location.origin);
            Object.keys(filters || {}).forEach((key) => {
                if (filters[key]) {
                    url.searchParams.set(key, filters[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });
            url.searchParams.set('paged', page);
            url.searchParams.set('page', 'sor-booking');
            return url.toString();
        },

        updateStatus(uuid, status, $select) {
            $select.prop('disabled', true);
            const body = JSON.stringify({ uuid, status });

            fetch(this.endpoints.update, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce || ''
                },
                body
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    if (!data || false === data.ok || !data.booking) {
                        throw new Error('Request failed');
                    }
                    this.applyRowUpdate(data.booking);
                    this.showToast(this.strings.statusUpdated || 'Status gespeichert.', 'success');
                })
                .catch(() => {
                    this.showToast(this.strings.statusFailed || 'Aktualisierung fehlgeschlagen.', 'error');
                    $select.val($select.data('current'));
                })
                .finally(() => {
                    $select.prop('disabled', false);
                });
        },

        applyRowUpdate(item) {
            const data = this.buildRowData(item);
            const $row = this.$tbody.find('tr[data-uuid="' + data.uuid + '"]');
            if (!$row.length) {
                return;
            }

            const attr = this.escapeAttr(JSON.stringify(data));
            $row.attr('data-booking', attr);
            $row.data('booking', data);

            const cells = $row.find('td');
            cells.eq(1).text(data.resource);
            cells.eq(2).text(data.name);
            cells.eq(3).text(data.phone);
            cells.eq(4).text(data.email);
            cells.eq(5).text(data.horse);
            cells.eq(6).text(data.slot);
            cells.eq(7).text(data.price_display);
            cells.eq(9).text(data.payment_ref);
            cells.eq(10).text(data.created_at);
            cells.eq(11).text(data.updated_at);

            const $statusCell = cells.eq(8);
            const $badge = $statusCell.find('.sor-booking-status');
            $badge.attr('class', 'sor-booking-status sor-booking-status--' + data.status).text(data.status_label);

            const $select = $statusCell.find('.sor-booking-status-select');
            $select.html(this.buildStatusOptions(data.status)).data('current', data.status).val(data.status);
        },

        openDetails($row) {
            const dataAttr = $row.attr('data-booking');
            if (!dataAttr) {
                return;
            }

            let booking;
            try {
                booking = JSON.parse(dataAttr);
            } catch (e) {
                booking = $row.data('booking') || {};
            }

            if (!booking) {
                return;
            }

            const lines = [
                { label: 'ID', value: booking.id },
                { label: this.strings.date || 'Datum/Zeit', value: booking.slot || '—' },
                { label: this.strings.price || 'Preis', value: booking.price || '—' },
                { label: this.strings.statusTitle || 'Status', value: booking.status_label || booking.status },
                { label: this.strings.createdAt || 'Erstellt', value: booking.created_at || '—' },
                { label: this.strings.updatedAt || 'Aktualisiert', value: booking.updated_at || '—' }
            ];

            const contact = [
                booking.email ? '<a href="mailto:' + this.escapeAttr(booking.email) + '">' + this.escapeHtml(booking.email) + '</a>' : '',
                booking.phone ? '<a href="tel:' + this.escapeAttr(booking.phone) + '">' + this.escapeHtml(booking.phone) + '</a>' : ''
            ].filter(Boolean).join('<br>');

            let html = '<div class="sor-booking-details">';
            html += '<h3>' + this.escapeHtml(booking.name || '') + '</h3>';
            if (contact) {
                html += '<p>' + contact + '</p>';
            }
            if (booking.horse) {
                html += '<p><strong>' + this.escapeHtml(this.strings.horseLabel || 'Pferd') + ':</strong> ' + this.escapeHtml(booking.horse) + '</p>';
            }
            html += '<dl class="sor-booking-details__list">';
            lines.forEach((line) => {
                html += '<dt>' + this.escapeHtml(line.label) + '</dt><dd>' + this.escapeHtml(String(line.value || '—')) + '</dd>';
            });
            html += '</dl></div>';

            this.$detailsModal.find('#sor-booking-details-title').text(this.strings.detailsTitle || 'Details');
            this.$detailsModal.find('.sor-modal__content').html(html);
            this.$detailsModal.removeAttr('hidden');
        },

        openQr(uuid) {
            if (!uuid) {
                return;
            }

            const requestUrl = this.restUrl + '/qr?ref=' + encodeURIComponent(uuid);

            fetch(requestUrl, { credentials: 'same-origin' })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    const imgUrl = data && data.url ? data.url : requestUrl;
                    const linkText = (this.strings.qrTitle || 'QR-Code') + ' öffnen';
                    const html = '<img src="' + this.escapeAttr(imgUrl) + '" alt="QR" class="sor-booking-qr-image" />' +
                        '<p><a href="' + this.escapeAttr(requestUrl) + '" target="_blank" rel="noopener noreferrer">' + this.escapeHtml(linkText) + '</a></p>';
                    this.$qrModal.find('#sor-booking-qr-title').text(this.strings.qrTitle || 'QR-Code');
                    this.$qrModal.find('.sor-modal__content').html(html);
                    this.$qrModal.removeAttr('hidden');
                })
                .catch(() => {
                    this.showToast(this.strings.statusFailed || 'QR konnte nicht geladen werden.', 'error');
                });
        },

        closeModal($modal) {
            if (!$modal || !$modal.length) {
                return;
            }
            $modal.attr('hidden', 'hidden');
            $modal.find('.sor-modal__content').empty();
        },

        showToast(message, type) {
            if (!this.$toast.length) {
                return;
            }
            const classes = ['sor-booking-toast'];
            if (type) {
                classes.push('is-' + type);
            }
            this.$toast.text(message || '').attr('class', classes.join(' ')).removeAttr('hidden');
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.$toast.attr('hidden', 'hidden');
            }, 4000);
        },

        hideToast() {
            if (!this.$toast.length) {
                return;
            }
            clearTimeout(this.toastTimer);
            this.$toast.attr('hidden', 'hidden');
        },

        escapeHtml(value) {
            if (value === undefined || value === null) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        escapeAttr(value) {
            return this.escapeHtml(value);
        }
    };

    $(function () {
        Admin.init();
    });
})(jQuery);
