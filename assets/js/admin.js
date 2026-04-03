/**
 * Kuwait Delivery Manager — Admin JavaScript  v2.0.0
 *
 * Driven entirely by cities stored in the database.
 * Cities are loaded via AJAX for the selected country.
 * Areas are loaded per-city with full inline CRUD support.
 */
(function ($) {
    'use strict';

    var S  = (typeof kdmData !== 'undefined') ? kdmData.strings : {};
    var KDM = {

        currentCityId: null,
        editingRowId:  null,

        // ====================================================================
        // Boot
        // ====================================================================

        init: function () {
            this.bindEvents();
            this.loadCities();
        },

        // ====================================================================
        // Event binding (delegation)
        // ====================================================================

        bindEvents: function () {
            // Sidebar — city list
            $('#kdm-city-list').on('click', '.kdm-city-item', this.onCityClick.bind(this));

            // Row CRUD
            var $panel = $('#kdm-content-panel');
            $panel.on('click', '.kdm-edit-btn',        this.onEditClick.bind(this));
            $panel.on('click', '.kdm-save-btn',        this.onSaveClick.bind(this));
            $panel.on('click', '.kdm-cancel-btn',      this.onCancelClick.bind(this));
            $panel.on('click', '.kdm-delete-btn',      this.onDeleteClick.bind(this));
            $panel.on('click', '.kdm-toggle input',    this.onToggleClick.bind(this));

            // Add new area
            $panel.on('click', '#kdm-add-area-btn',    this.onAddClick.bind(this));
            $panel.on('click', '.kdm-save-new-btn',    this.onSaveNewClick.bind(this));
            $panel.on('click', '.kdm-cancel-new-btn',  this.onCancelNewClick.bind(this));

            // Copy / push to all
            $panel.on('click', '.kdm-copy-col-btn',    this.onCopyColClick.bind(this));
            $panel.on('click', '.kdm-push-val-btn',    this.onPushValClick.bind(this));
        },

        // ====================================================================
        // Sidebar — Cities
        // ====================================================================

        loadCities: function () {
            var self = this;
            var $list = $('#kdm-city-list');
            $list.html('<li class="kdm-loading">' + this.escHtml(S.loading) + '</li>');

            $.post(kdmData.ajaxUrl, {
                action:       'kdm_get_cities',
                country_iso2: kdmData.selectedCountry,
                nonce:        kdmData.nonce
            }, function (res) {
                if (res.success && res.data && res.data.cities) {
                    self.renderCitySidebar(res.data.cities);
                } else {
                    $list.html('<li class="kdm-error">' + self.escHtml(S.error) + '</li>');
                }
            }).fail(function () {
                $list.html('<li class="kdm-error">' + self.escHtml(S.error) + '</li>');
            });
        },

        renderCitySidebar: function (cities) {
            var $list = $('#kdm-city-list');
            $list.empty();

            if (!cities.length) {
                $list.html('<li class="kdm-empty">' + this.escHtml(S.noAreas) + '</li>');
                return;
            }

            for (var i = 0; i < cities.length; i++) {
                var c   = cities[i];
                var dec = c.city_name_decoded || {};
                var ar  = dec.ar || c.city_name || '';
                var en  = dec.en || '';

                var html = '<li class="kdm-city-item" data-id="' + this.escAttr(c.city_id) + '">' +
                    '<span class="kdm-city-dot"></span>' +
                    '<span class="kdm-city-names">' +
                        '<span class="kdm-city-name-ar">' + this.escHtml(ar) + '</span>' +
                        '<span class="kdm-city-name-en">' + this.escHtml(en) + '</span>' +
                    '</span>' +
                    '<span class="kdm-city-count">' + parseInt(c.area_count, 10) + '</span>' +
                    '<span class="kdm-city-arrow dashicons dashicons-arrow-left-alt2"></span>' +
                '</li>';

                $list.append(html);
            }
        },

        onCityClick: function (e) {
            var $li = $(e.currentTarget);
            var cityId = $li.data('id');

            if (this.editingRowId) {
                if (!confirm(S.unsavedChanges)) {
                    return;
                }
                this.editingRowId = null;
            }

            $('#kdm-city-list .kdm-city-item').removeClass('active');
            $li.addClass('active');

            this.currentCityId = cityId;
            this.loadAreas(cityId);
        },

        // ====================================================================
        // Areas — Load & Render
        // ====================================================================

        loadAreas: function (cityId) {
            var self  = this;
            var $panel = $('#kdm-content-panel');
            $panel.html('<div class="kdm-loading">' + this.escHtml(S.loading) + '</div>');

            $.post(kdmData.ajaxUrl, {
                action:  'kdm_get_areas',
                city_id: cityId,
                nonce:   kdmData.nonce
            }, function (res) {
                if (res.success && res.data) {
                    self.renderTable(res.data);
                } else {
                    $panel.html('<div class="kdm-error">' + self.escHtml(S.error) + '</div>');
                }
            }).fail(function () {
                $panel.html('<div class="kdm-error">' + self.escHtml(S.error) + '</div>');
            });
        },

        renderTable: function (data) {
            var city  = data.city || {};
            var areas = data.areas || [];
            var dec   = city.city_name_decoded || {};
            var cityLabel = (dec.ar || city.city_name || '') +
                            (dec.en ? ' — ' + dec.en : '');

            var html = '<div class="kdm-area-header">';
            html += '<h2>' + this.escHtml(cityLabel) + '</h2>';
            html += '<button type="button" id="kdm-add-area-btn" class="button button-primary">' +
                    this.escHtml(S.addBtn) + '</button>';
            html += '</div>';

            html += '<table class="kdm-areas-table widefat striped">';
            html += '<thead><tr>';
            html += '<th class="kdm-col-drag" title="' + this.escAttr(S.dragSort) + '"></th>';
            html += '<th class="kdm-col-area">' + this.escHtml(S.colArea) + '</th>';
            html += '<th class="kdm-col-price">' + this.escHtml(S.colPrice) +
                     ' <button type="button" class="kdm-copy-col-btn" data-field="delivery_price" title="' +
                     this.escAttr(S.copyFirstToAll) + '"><span class="dashicons dashicons-admin-page"></span></button></th>';

            if (kdmData.expressEnabled) {
                html += '<th class="kdm-col-express">' + this.escHtml(S.colExpress) +
                         ' <button type="button" class="kdm-copy-col-btn" data-field="express_fee" title="' +
                         this.escAttr(S.copyFirstToAll) + '"><span class="dashicons dashicons-admin-page"></span></button></th>';
            }

            html += '<th class="kdm-col-notes">' + this.escHtml(S.colNotes) +
                     ' <button type="button" class="kdm-copy-col-btn" data-field="delivery_notes" title="' +
                     this.escAttr(S.copyFirstToAll) + '"><span class="dashicons dashicons-admin-page"></span></button></th>';
            html += '<th class="kdm-col-minorder">' + this.escHtml(S.colMinOrder) +
                     ' <button type="button" class="kdm-copy-col-btn" data-field="minimum_order" title="' +
                     this.escAttr(S.copyFirstToAll) + '"><span class="dashicons dashicons-admin-page"></span></button></th>';
            html += '<th class="kdm-col-status">' + this.escHtml(S.colStatus) + '</th>';
            html += '<th class="kdm-col-actions">' + this.escHtml(S.colActions) + '</th>';
            html += '</tr></thead>';

            html += '<tbody id="kdm-areas-body">';
            if (!areas.length) {
                html += '<tr class="kdm-no-areas"><td colspan="' + this.colCount() + '">' +
                        this.escHtml(S.noAreas) + '</td></tr>';
            } else {
                for (var i = 0; i < areas.length; i++) {
                    html += this.renderRow(areas[i]);
                }
            }
            html += '</tbody></table>';

            $('#kdm-content-panel').html(html);
            this.editingRowId = null;
            this.initSortable();
        },

        colCount: function () {
            return kdmData.expressEnabled ? 8 : 7;
        },

        renderRow: function (area) {
            var dec   = area.area_name_decoded || {};
            var ndec  = area.delivery_notes_decoded || {};
            var nameAr  = dec.ar  || area.area_name || '';
            var nameEn  = dec.en  || '';
            var notesAr = ndec.ar || '';
            var notesEn = ndec.en || '';
            var checked = parseInt(area.is_active, 10) ? ' checked' : '';

            var html = '<tr class="kdm-area-row" data-id="' + this.escAttr(area.area_id) + '"' +
                       ' data-name-ar="' + this.escAttr(nameAr) + '"' +
                       ' data-name-en="' + this.escAttr(nameEn) + '"' +
                       ' data-notes-ar="' + this.escAttr(notesAr) + '"' +
                       ' data-notes-en="' + this.escAttr(notesEn) + '"' +
                       ' data-notes-raw="' + this.escAttr(area.delivery_notes || '') + '"' +
                       ' data-price="' + this.escAttr(area.delivery_price) + '"' +
                       ' data-express="' + this.escAttr(area.express_fee) + '"' +
                       ' data-minorder="' + this.escAttr(area.minimum_order) + '"' +
                       ' data-active="' + (parseInt(area.is_active, 10) ? '1' : '0') + '">';

            // Drag handle
            html += '<td class="kdm-col-drag"><span class="kdm-drag-handle dashicons dashicons-menu"></span></td>';

            // Area name (bilingual)
            html += '<td class="kdm-col-area">' +
                    '<span class="kdm-area-name-ar">' + this.escHtml(nameAr) + '</span>' +
                    '<span class="kdm-area-name-en">' + this.escHtml(nameEn) + '</span>' +
                    '</td>';

            // Delivery price
            html += '<td class="kdm-col-price">' + this.fmtPrice(area.delivery_price) + '</td>';

            // Express fee
            if (kdmData.expressEnabled) {
                html += '<td class="kdm-col-express">' + this.fmtPrice(area.express_fee) + '</td>';
            }

            // Delivery notes (bilingual)
            html += '<td class="kdm-col-notes">' +
                    '<span class="kdm-notes-ar">' + this.escHtml(notesAr) + '</span>' +
                    '<span class="kdm-notes-en">' + this.escHtml(notesEn) + '</span>' +
                    '</td>';

            // Min order
            html += '<td class="kdm-col-minorder">' + this.fmtPrice(area.minimum_order) + '</td>';

            // Status toggle
            html += '<td class="kdm-col-status">' +
                    '<label class="kdm-toggle">' +
                    '<input type="checkbox" data-id="' + this.escAttr(area.area_id) + '"' + checked + '>' +
                    '<span class="kdm-toggle-slider"></span>' +
                    '</label></td>';

            // Actions
            html += '<td class="kdm-col-actions">' +
                    '<button type="button" class="button kdm-edit-btn" title="' + this.escAttr(S.editArea) + '">' +
                    '<span class="dashicons dashicons-edit"></span></button> ' +
                    '<button type="button" class="button kdm-delete-btn" title="' + this.escAttr(S.deleteArea) + '">' +
                    '<span class="dashicons dashicons-trash"></span></button>' +
                    '</td>';

            html += '</tr>';
            return html;
        },

        // ====================================================================
        // Edit
        // ====================================================================

        onEditClick: function (e) {
            e.preventDefault();
            var $row = $(e.currentTarget).closest('tr');
            var id   = $row.data('id');

            // Cancel any other editing row
            if (this.editingRowId && this.editingRowId !== id) {
                this.cancelEditRow(this.editingRowId);
            }

            this.editingRowId = id;

            var nameAr   = $row.data('name-ar')   || '';
            var nameEn   = $row.data('name-en')   || '';
            var notesAr  = $row.data('notes-ar')  || '';
            var notesEn  = $row.data('notes-en')  || '';
            var price    = $row.data('price')      || '';
            var express  = $row.data('express')    || '';
            var minorder = $row.data('minorder')   || '';

            var html = '<td class="kdm-col-drag"><span class="kdm-drag-handle dashicons dashicons-menu"></span></td>';

            // Name inputs
            html += '<td class="kdm-col-area">' +
                    '<input type="text" class="kdm-input kdm-input-name-ar" name="name_ar" value="' +
                    this.escAttr(nameAr) + '" placeholder="' + this.escAttr(S.placeholderNameAr) + '" dir="rtl">' +
                    '<input type="text" class="kdm-input kdm-input-name-en" name="name_en" value="' +
                    this.escAttr(nameEn) + '" placeholder="' + this.escAttr(S.placeholderNameEn) + '">' +
                    '</td>';

            // Price
            html += '<td class="kdm-col-price">' +
                    '<input type="number" class="kdm-input kdm-input-price" name="delivery_price" value="' +
                    this.escAttr(price) + '" step="0.001" min="0">' +
                    '<button type="button" class="kdm-push-val-btn" data-field="delivery_price" title="' +
                    this.escAttr(S.pushToAll) + '"><span class="dashicons dashicons-admin-page"></span></button>' +
                    '</td>';

            // Express
            if (kdmData.expressEnabled) {
                html += '<td class="kdm-col-express">' +
                        '<input type="number" class="kdm-input kdm-input-express" name="express_fee" value="' +
                        this.escAttr(express) + '" step="0.001" min="0">' +
                        '<button type="button" class="kdm-push-val-btn" data-field="express_fee" title="' +
                        this.escAttr(S.pushToAll) + '"><span class="dashicons dashicons-admin-page"></span></button>' +
                        '</td>';
            }

            // Notes textareas
            html += '<td class="kdm-col-notes">' +
                    '<textarea class="kdm-input kdm-input-notes-ar" name="notes_ar" placeholder="' +
                    this.escAttr(S.placeholderNotesAr) + '" dir="rtl">' + this.escHtml(notesAr) + '</textarea>' +
                    '<textarea class="kdm-input kdm-input-notes-en" name="notes_en" placeholder="' +
                    this.escAttr(S.placeholderNotesEn) + '">' + this.escHtml(notesEn) + '</textarea>' +
                    '<button type="button" class="kdm-push-val-btn" data-field="delivery_notes" title="' +
                    this.escAttr(S.pushToAll) + '"><span class="dashicons dashicons-admin-page"></span></button>' +
                    '</td>';

            // Min order
            html += '<td class="kdm-col-minorder">' +
                    '<input type="number" class="kdm-input kdm-input-minorder" name="minimum_order" value="' +
                    this.escAttr(minorder) + '" step="0.001" min="0">' +
                    '<button type="button" class="kdm-push-val-btn" data-field="minimum_order" title="' +
                    this.escAttr(S.pushToAll) + '"><span class="dashicons dashicons-admin-page"></span></button>' +
                    '</td>';

            // Status (keep current)
            var checked = parseInt($row.data('active'), 10) ? ' checked' : '';
            html += '<td class="kdm-col-status">' +
                    '<label class="kdm-toggle">' +
                    '<input type="checkbox" data-id="' + this.escAttr(id) + '"' + checked + '>' +
                    '<span class="kdm-toggle-slider"></span>' +
                    '</label></td>';

            // Save / Cancel buttons
            html += '<td class="kdm-col-actions">' +
                    '<button type="button" class="button button-primary kdm-save-btn">' +
                    this.escHtml(S.saveArea) + '</button> ' +
                    '<button type="button" class="button kdm-cancel-btn">' +
                    this.escHtml(S.cancelEdit) + '</button>' +
                    '</td>';

            $row.addClass('kdm-editing').html(html);
        },

        onSaveClick: function (e) {
            e.preventDefault();
            var self = this;
            var $row = $(e.currentTarget).closest('tr');
            var id   = $row.data('id');

            var nameAr = $.trim($row.find('[name="name_ar"]').val());
            var nameEn = $.trim($row.find('[name="name_en"]').val());

            if (!nameAr && !nameEn) {
                this.showNotice(S.nameRequired, 'error');
                return;
            }

            var $btn = $row.find('.kdm-save-btn');
            $btn.prop('disabled', true).text(S.saving);

            $.post(kdmData.ajaxUrl, {
                action:         'kdm_save_area',
                area_id:        id,
                name_en:        nameEn,
                name_ar:        nameAr,
                notes_en:       $.trim($row.find('[name="notes_en"]').val()),
                notes_ar:       $.trim($row.find('[name="notes_ar"]').val()),
                delivery_price: $row.find('[name="delivery_price"]').val(),
                express_fee:    $row.find('[name="express_fee"]').val() || '0',
                minimum_order:  $row.find('[name="minimum_order"]').val(),
                nonce:          kdmData.nonce
            }, function (res) {
                if (res.success && res.data && res.data.area) {
                    var newHtml = self.renderRow(res.data.area);
                    $row.replaceWith(newHtml);
                    self.editingRowId = null;
                    self.showNotice(S.orderSaved, 'success');
                } else {
                    self.showNotice(S.error, 'error');
                    $btn.prop('disabled', false).text(S.saveArea);
                }
            }).fail(function () {
                self.showNotice(S.error, 'error');
                $btn.prop('disabled', false).text(S.saveArea);
            });
        },

        onCancelClick: function (e) {
            e.preventDefault();
            var $row = $(e.currentTarget).closest('tr');
            var id   = $row.data('id');
            this.cancelEditRow(id);
        },

        cancelEditRow: function (id) {
            var $row = $('#kdm-areas-body tr[data-id="' + id + '"]');
            if (!$row.length) return;

            // Reconstruct the area object from data attributes
            var area = {
                area_id:        id,
                area_name:      $row.data('name-ar') || '',
                area_name_decoded: {
                    ar: $row.data('name-ar') || '',
                    en: $row.data('name-en') || ''
                },
                delivery_notes: $row.data('notes-raw') || '',
                delivery_notes_decoded: {
                    ar: $row.data('notes-ar') || '',
                    en: $row.data('notes-en') || ''
                },
                delivery_price: $row.data('price')    || '0',
                express_fee:    $row.data('express')   || '0',
                minimum_order:  $row.data('minorder')  || '0',
                is_active:      $row.data('active')    || '0'
            };

            var newHtml = this.renderRow(area);
            $row.replaceWith(newHtml);
            this.editingRowId = null;
        },

        // ====================================================================
        // Add new area
        // ====================================================================

        onAddClick: function (e) {
            e.preventDefault();

            if (!this.currentCityId) return;

            // Remove any existing new-row
            $('#kdm-areas-body .kdm-new-row').remove();
            // Remove no-areas placeholder
            $('#kdm-areas-body .kdm-no-areas').remove();

            var html = '<tr class="kdm-new-row">';
            html += '<td class="kdm-col-drag"></td>';

            // Name inputs
            html += '<td class="kdm-col-area">' +
                    '<input type="text" class="kdm-input kdm-input-name-ar" name="name_ar" placeholder="' +
                    this.escAttr(S.placeholderNameAr) + '" dir="rtl">' +
                    '<input type="text" class="kdm-input kdm-input-name-en" name="name_en" placeholder="' +
                    this.escAttr(S.placeholderNameEn) + '">' +
                    '</td>';

            // Price
            html += '<td class="kdm-col-price">' +
                    '<input type="number" class="kdm-input kdm-input-price" name="delivery_price" value="0" step="0.001" min="0">' +
                    '</td>';

            // Express
            if (kdmData.expressEnabled) {
                html += '<td class="kdm-col-express">' +
                        '<input type="number" class="kdm-input kdm-input-express" name="express_fee" value="0" step="0.001" min="0">' +
                        '</td>';
            }

            // Notes
            html += '<td class="kdm-col-notes">' +
                    '<textarea class="kdm-input kdm-input-notes-ar" name="notes_ar" placeholder="' +
                    this.escAttr(S.placeholderNotesAr) + '" dir="rtl"></textarea>' +
                    '<textarea class="kdm-input kdm-input-notes-en" name="notes_en" placeholder="' +
                    this.escAttr(S.placeholderNotesEn) + '"></textarea>' +
                    '</td>';

            // Min order
            html += '<td class="kdm-col-minorder">' +
                    '<input type="number" class="kdm-input kdm-input-minorder" name="minimum_order" value="0" step="0.001" min="0">' +
                    '</td>';

            // Status (placeholder)
            html += '<td class="kdm-col-status">—</td>';

            // Save / Cancel
            html += '<td class="kdm-col-actions">' +
                    '<button type="button" class="button button-primary kdm-save-new-btn">' +
                    this.escHtml(S.addBtn) + '</button> ' +
                    '<button type="button" class="button kdm-cancel-new-btn">' +
                    this.escHtml(S.cancelEdit) + '</button>' +
                    '</td>';

            html += '</tr>';

            $('#kdm-areas-body').prepend(html);
            $('#kdm-areas-body .kdm-new-row [name="name_ar"]').focus();
        },

        onSaveNewClick: function (e) {
            e.preventDefault();
            var self = this;
            var $row = $(e.currentTarget).closest('tr');

            var nameAr = $.trim($row.find('[name="name_ar"]').val());
            var nameEn = $.trim($row.find('[name="name_en"]').val());

            if (!nameAr && !nameEn) {
                this.showNotice(S.nameRequired, 'error');
                return;
            }

            var $btn = $row.find('.kdm-save-new-btn');
            $btn.prop('disabled', true).text(S.adding);

            $.post(kdmData.ajaxUrl, {
                action:         'kdm_add_area',
                city_id:        self.currentCityId,
                name_en:        nameEn,
                name_ar:        nameAr,
                notes_en:       $.trim($row.find('[name="notes_en"]').val()),
                notes_ar:       $.trim($row.find('[name="notes_ar"]').val()),
                delivery_price: $row.find('[name="delivery_price"]').val(),
                express_fee:    $row.find('[name="express_fee"]').val() || '0',
                minimum_order:  $row.find('[name="minimum_order"]').val(),
                nonce:          kdmData.nonce
            }, function (res) {
                if (res.success && res.data && res.data.area) {
                    var newHtml = self.renderRow(res.data.area);
                    $row.replaceWith(newHtml);
                    self.showNotice(S.orderSaved, 'success');
                    self.updateCityCount(1);
                    self.initSortable();
                } else {
                    self.showNotice(S.error, 'error');
                    $btn.prop('disabled', false).text(S.addBtn);
                }
            }).fail(function () {
                self.showNotice(S.error, 'error');
                $btn.prop('disabled', false).text(S.addBtn);
            });
        },

        onCancelNewClick: function (e) {
            e.preventDefault();
            $(e.currentTarget).closest('.kdm-new-row').remove();

            // Restore no-areas placeholder if the table body is now empty
            if ($('#kdm-areas-body tr').length === 0) {
                $('#kdm-areas-body').html(
                    '<tr class="kdm-no-areas"><td colspan="' + this.colCount() + '">' +
                    this.escHtml(S.noAreas) + '</td></tr>'
                );
            }
        },

        // ====================================================================
        // Delete
        // ====================================================================

        onDeleteClick: function (e) {
            e.preventDefault();
            var self = this;
            var $row = $(e.currentTarget).closest('tr');
            var id   = $row.data('id');

            if (!confirm(S.confirmDelete)) return;

            $.post(kdmData.ajaxUrl, {
                action:  'kdm_delete_area',
                area_id: id,
                nonce:   kdmData.nonce
            }, function (res) {
                if (res.success) {
                    $row.fadeOut(300, function () {
                        $(this).remove();
                        self.updateCityCount(-1);

                        if ($('#kdm-areas-body tr').length === 0) {
                            $('#kdm-areas-body').html(
                                '<tr class="kdm-no-areas"><td colspan="' + self.colCount() + '">' +
                                self.escHtml(S.noAreas) + '</td></tr>'
                            );
                        }
                    });

                    if (self.editingRowId === id) {
                        self.editingRowId = null;
                    }
                } else {
                    self.showNotice(S.error, 'error');
                }
            }).fail(function () {
                self.showNotice(S.error, 'error');
            });
        },

        // ====================================================================
        // Toggle active
        // ====================================================================

        onToggleClick: function (e) {
            var self = this;
            var $cb  = $(e.currentTarget);
            var id   = $cb.data('id');

            $.post(kdmData.ajaxUrl, {
                action:  'kdm_toggle_area',
                area_id: id,
                nonce:   kdmData.nonce
            }, function (res) {
                if (res.success && res.data) {
                    var active = parseInt(res.data.is_active, 10);
                    $cb.prop('checked', !!active);
                    $cb.closest('tr').attr('data-active', active ? '1' : '0')
                       .data('active', active ? '1' : '0');
                } else {
                    // Revert
                    $cb.prop('checked', !$cb.prop('checked'));
                    self.showNotice(S.error, 'error');
                }
            }).fail(function () {
                $cb.prop('checked', !$cb.prop('checked'));
                self.showNotice(S.error, 'error');
            });
        },

        // ====================================================================
        // Drag-sort
        // ====================================================================

        initSortable: function () {
            var self = this;
            var $body = $('#kdm-areas-body');

            if ($body.data('ui-sortable') || $body.data('sortable')) {
                $body.sortable('destroy');
            }

            $body.sortable({
                handle: '.kdm-drag-handle',
                items:  'tr.kdm-area-row',
                axis:   'y',
                cursor: 'grabbing',
                placeholder: 'kdm-sort-placeholder',
                update: function () {
                    self.saveOrder();
                }
            });
        },

        saveOrder: function () {
            var self  = this;
            var order = [];

            $('#kdm-areas-body tr.kdm-area-row').each(function () {
                order.push($(this).data('id'));
            });

            if (!order.length) return;

            $.post(kdmData.ajaxUrl, {
                action: 'kdm_reorder_areas',
                order:  order,
                nonce:  kdmData.nonce
            }, function (res) {
                if (res.success) {
                    self.showNotice(S.orderSaved, 'success');
                } else {
                    self.showNotice(S.error, 'error');
                }
            }).fail(function () {
                self.showNotice(S.error, 'error');
            });
        },

        // ====================================================================
        // Copy-to-all / Push-to-all
        // ====================================================================

        onCopyColClick: function (e) {
            e.preventDefault();
            var field = $(e.currentTarget).data('field');
            var $firstRow = $('#kdm-areas-body tr.kdm-area-row').first();

            if (!$firstRow.length) return;
            if (!confirm(S.copyConfirm)) return;

            var value;
            if (field === 'delivery_notes') {
                value = $firstRow.data('notes-raw') || '';
            } else {
                value = $firstRow.data(this.fieldToDataAttr(field)) || '0';
            }

            this.doCopyFieldToAll(field, value);
        },

        onPushValClick: function (e) {
            e.preventDefault();
            var field = $(e.currentTarget).data('field');
            var $row  = $(e.currentTarget).closest('tr');

            if (!confirm(S.pushConfirm)) return;

            var value;
            if (field === 'delivery_notes') {
                // Build JSON from current textareas
                var notesAr = $.trim($row.find('[name="notes_ar"]').val());
                var notesEn = $.trim($row.find('[name="notes_en"]').val());
                value = JSON.stringify({ ar: notesAr, en: notesEn });
            } else if (field === 'delivery_price') {
                value = $row.find('[name="delivery_price"]').val() || '0';
            } else if (field === 'express_fee') {
                value = $row.find('[name="express_fee"]').val() || '0';
            } else if (field === 'minimum_order') {
                value = $row.find('[name="minimum_order"]').val() || '0';
            }

            this.doCopyFieldToAll(field, value);
        },

        doCopyFieldToAll: function (fieldName, fieldValue) {
            var self = this;

            $.post(kdmData.ajaxUrl, {
                action:      'kdm_copy_field_to_all',
                city_id:     self.currentCityId,
                field_name:  fieldName,
                field_value: fieldValue,
                nonce:       kdmData.nonce
            }, function (res) {
                if (res.success) {
                    // Reload areas to reflect changes
                    self.loadAreas(self.currentCityId);
                    self.showNotice(S.orderSaved, 'success');
                } else {
                    self.showNotice(S.error, 'error');
                }
            }).fail(function () {
                self.showNotice(S.error, 'error');
            });
        },

        fieldToDataAttr: function (field) {
            var map = {
                'delivery_price': 'price',
                'express_fee':    'express',
                'minimum_order':  'minorder'
            };
            return map[field] || field;
        },

        // ====================================================================
        // UI helpers
        // ====================================================================

        updateCityCount: function (delta) {
            var $item = $('#kdm-city-list .kdm-city-item[data-id="' + this.currentCityId + '"]');
            if (!$item.length) return;
            var $count = $item.find('.kdm-city-count');
            var current = parseInt($count.text(), 10) || 0;
            $count.text(Math.max(0, current + delta));
        },

        showNotice: function (msg, type) {
            type = type || 'info';
            var cls = (type === 'error') ? 'notice-error' : 'notice-success';

            var $notice = $(
                '<div class="notice ' + cls + ' is-dismissible kdm-notice">' +
                '<p>' + this.escHtml(msg) + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">' + this.escHtml(S.close) + '</span>' +
                '</button></div>'
            );

            // Remove existing notices
            $('.kdm-notice').remove();

            // Insert before content panel
            $('#kdm-content-panel').before($notice);

            // Dismiss handler
            $notice.find('.notice-dismiss').on('click', function () {
                $notice.fadeOut(200, function () { $(this).remove(); });
            });

            // Auto-dismiss success after 4 seconds
            if (type !== 'error') {
                setTimeout(function () {
                    $notice.fadeOut(200, function () { $(this).remove(); });
                }, 4000);
            }
        },

        fmtPrice: function (val) {
            var num = parseFloat(val);
            if (isNaN(num)) num = 0;
            return num.toFixed(3) + ' ' + this.escHtml(kdmData.currency);
        },

        escHtml: function (str) {
            if (str === null || str === undefined) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
        },

        escAttr: function (str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    };

    // Boot
    jQuery(document).ready(function () {
        KDM.init();
    });

})(jQuery);
