/**
 * Kuwait Delivery Manager — Admin JavaScript  v1.2.0
 *
 * New in 1.2.0:
 *   - Bilingual area names  (Arabic bold + English muted, two inputs in edit mode)
 *   - Bilingual notes       (Arabic + English textareas in edit mode)
 *   - Notes use <textarea>  (multi-line, auto-resize)
 *   - Copy-to-all button    in each numeric column header (copies first-row value to ALL rows)
 *   - Push-to-all button    per-row in edit mode (copies this row's value to all rows)
 *   - All UI strings        come from kdmData.strings (translatable via PHP i18n)
 *   - Inline editing        covers all fields: name AR/EN, price, express, notes AR/EN, min-order
 */
(function ($) {
    'use strict';

    var KDM = {

        currentGov: '',
        editingId:  null,

        // ====================================================================
        // Boot
        // ====================================================================

        init: function () {
            this.bindEvents();
            var $first = $('.kdm-gov-item.active');
            if ($first.length) {
                this.currentGov = $first.data('key');
                this.loadAreas(this.currentGov);
            }
        },

        // ====================================================================
        // Event binding
        // ====================================================================

        bindEvents: function () {
            $(document).on('click',   '.kdm-gov-item',       this.onGovernorateClick.bind(this));
            $(document).on('keydown', '.kdm-gov-item',        this.onGovernorateKeydown.bind(this));

            // Row CRUD
            $(document).on('click', '.kdm-edit-btn',          this.onEditClick.bind(this));
            $(document).on('click', '.kdm-save-btn',          this.onSaveClick.bind(this));
            $(document).on('click', '.kdm-cancel-btn',        this.onCancelClick.bind(this));
            $(document).on('click', '.kdm-delete-btn',        this.onDeleteClick.bind(this));
            $(document).on('click', '.kdm-toggle',            this.onToggleClick.bind(this));

            // Add new area
            $(document).on('click', '#kdm-add-area-btn',      this.onAddClick.bind(this));
            $(document).on('click', '.kdm-save-new-btn',      this.onSaveNewClick.bind(this));
            $(document).on('click', '.kdm-cancel-new-btn',    this.onCancelNewClick.bind(this));

            // Copy to all
            $(document).on('click', '.kdm-copy-col-btn',      this.onCopyColClick.bind(this));
            $(document).on('click', '.kdm-push-val-btn',      this.onPushValClick.bind(this));

            // Auto-grow textareas
            $(document).on('input', 'textarea.kdm-edit-input', function () {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Remove validation highlight on change
            $(document).on('input', '.kdm-edit-input', function () {
                $(this).removeClass('kdm-input-error');
            });
        },

        // ====================================================================
        // GOVERNORATE SWITCHING
        // ====================================================================

        onGovernorateKeydown: function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(e.currentTarget).trigger('click');
            }
        },

        onGovernorateClick: function (e) {
            var $item  = $(e.currentTarget);
            var govKey = $item.data('key');

            if (govKey === this.currentGov) { return; }

            if (this.editingId !== null && !window.confirm(kdmData.strings.unsavedChanges)) {
                return;
            }

            this.editingId = null;
            $('.kdm-gov-item').removeClass('active').attr('aria-pressed', 'false');
            $item.addClass('active').attr('aria-pressed', 'true');
            this.currentGov = govKey;
            this.loadAreas(govKey);
        },

        loadAreas: function (govKey) {
            var self = this;

            $('#kdm-areas-content').html(
                '<div class="kdm-loading"><span class="kdm-spinner"></span><p>' +
                kdmData.strings.loading + '</p></div>'
            );

            $.ajax({
                url: kdmData.ajaxUrl, type: 'POST',
                data: { action: 'kdm_get_areas', governorate_key: govKey, nonce: kdmData.nonce },
                success: function (r) {
                    if (r.success) {
                        self.renderTable(r.data);
                        var $badge = $('#kdm-count-' + govKey);
                        $badge.text(r.data.areas.length).addClass('visible');
                    } else {
                        self.showNotice('error', r.data.message || kdmData.strings.error);
                        $('#kdm-areas-content').html(
                            '<div class="kdm-empty-state"><p>' +
                            self.escHtml(r.data.message || kdmData.strings.error) + '</p></div>'
                        );
                    }
                },
                error: function () {
                    self.showNotice('error', kdmData.strings.error);
                    $('#kdm-areas-content').html(
                        '<div class="kdm-empty-state"><p>' + kdmData.strings.error + '</p></div>'
                    );
                }
            });
        },

        // ====================================================================
        // TABLE RENDERING
        // ====================================================================

        renderTable: function (data) {
            var areas = data.areas;
            var html  = '';

            // ---- Content header ------------------------------------------
            html += '<div class="kdm-content-header">';
            html += '  <h2 class="kdm-gov-title">';
            html += '    <span class="dashicons dashicons-location" aria-hidden="true"></span>';
            html += '    <span class="kdm-gov-name-ar">' + this.escHtml(data.governorate_name_ar) + '</span>';
            html += '    <span class="kdm-gov-name-en">' + this.escHtml(data.governorate_name_en) + '</span>';
            html += '  </h2>';
            html += '  <button type="button" id="kdm-add-area-btn" class="button button-primary kdm-add-btn">';
            html += '    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' + kdmData.strings.addNewArea;
            html += '  </button>';
            html += '</div>';

            // ---- Empty state (shown above table) -------------------------
            if (areas.length === 0) {
                html += '<div class="kdm-empty-state"><p>' + kdmData.strings.noAreas + '</p></div>';
            }

            // ---- Table ---------------------------------------------------
            html += '<div class="kdm-table-wrap"' + (areas.length === 0 ? ' style="display:none"' : '') + '>';
            html += '<table class="kdm-areas-table wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '  <th class="kdm-col-sort" title="' + kdmData.strings.dragSort + '">';
            html += '    <span class="dashicons dashicons-sort" aria-hidden="true"></span>';
            html += '  </th>';
            html += '  <th class="kdm-col-name">' + kdmData.strings.colArea + '</th>';

            // Price columns with copy-to-all button in header
            html += this.renderColHeader('kdm-col-price', 'delivery_price', kdmData.strings.colPrice);

            // Express column: only when globally enabled
            if ( kdmData.expressEnabled !== false ) {
                html += this.renderColHeader('kdm-col-express', 'express_fee', kdmData.strings.colExpress);
            }

            // Notes column: two copy buttons (AR + EN)
            html += '<th class="kdm-col-notes">' + kdmData.strings.colNotes;
            html += '<button type="button" class="kdm-copy-col-btn" data-field="delivery_notes"';
            html += ' title="' + this.escAttr(kdmData.strings.copyFirstToAll + ' — ' + (kdmData.strings.field_delivery_notes || 'AR')) + '">';
            html += '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span><em>ع</em></button>';
            html += '<button type="button" class="kdm-copy-col-btn" data-field="delivery_notes_en"';
            html += ' title="' + this.escAttr(kdmData.strings.copyFirstToAll + ' — ' + (kdmData.strings.field_delivery_notes_en || 'EN')) + '">';
            html += '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span><em>EN</em></button>';
            html += '</th>';

            html += this.renderColHeader('kdm-col-min', 'minimum_order', kdmData.strings.colMinOrder);

            html += '  <th class="kdm-col-status">'  + kdmData.strings.colStatus  + '</th>';
            html += '  <th class="kdm-col-actions">' + kdmData.strings.colActions + '</th>';
            html += '</tr></thead>';
            html += '<tbody id="kdm-sortable">';

            for (var i = 0; i < areas.length; i++) {
                html += this.renderRow(areas[i]);
            }

            html += '</tbody></table></div>';

            $('#kdm-areas-content').html(html);

            if (areas.length > 0) { this.initSortable(); }
        },

        /** Renders a column header <th> that includes a copy-first-to-all button. */
        renderColHeader: function (cls, field, label) {
            return '<th class="' + cls + '" data-field="' + field + '">' +
                   label +
                   '<button type="button" class="kdm-copy-col-btn" data-field="' + field + '"' +
                   ' title="' + this.escAttr(kdmData.strings.copyFirstToAll) + '">' +
                   '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>' +
                   '</button></th>';
        },

        // ----------------------------------------------------------------
        // Render a single area row (view mode)
        // ----------------------------------------------------------------

        renderRow: function (area) {
            var isEnabled = parseInt(area.is_enabled, 10) === 1;
            var tglClass  = isEnabled ? 'kdm-toggle-on' : 'kdm-toggle-off';
            var tglLabel  = isEnabled ? kdmData.strings.toggleOn : kdmData.strings.toggleOff;

            var r = '<tr class="kdm-area-row" data-id="' + area.id + '" data-gov="' + this.escAttr(area.governorate_key) + '">';

            // Sort handle
            r += '<td class="kdm-col-sort kdm-sort-handle" title="' + kdmData.strings.dragSort + '">';
            r += '  <span class="dashicons dashicons-menu" aria-hidden="true"></span>';
            r += '</td>';

            // ---- Bilingual area name ------------------------------------
            r += '<td class="kdm-col-name">';
            r += '  <div class="kdm-bilingual-view">';
            r += '    <span class="kdm-name-ar">' + this.escHtml(area.area_name_ar) + '</span>';
            if (area.area_name_en) {
                r += '  <span class="kdm-name-en">' + this.escHtml(area.area_name_en) + '</span>';
            }
            r += '  </div>';
            r += '  <input type="text" class="kdm-edit-input kdm-input-ar" name="area_name_ar"';
            r += '         value="' + this.escAttr(area.area_name_ar) + '"';
            r += '         placeholder="' + kdmData.strings.placeholderNameAr + ' *" style="display:none">';
            r += '  <input type="text" class="kdm-edit-input kdm-input-en" name="area_name_en"';
            r += '         value="' + this.escAttr(area.area_name_en || '') + '"';
            r += '         placeholder="' + kdmData.strings.placeholderNameEn + '" style="display:none">';
            r += '</td>';

            // ---- Delivery price ----------------------------------------
            r += this.renderPriceCell('kdm-col-price', 'delivery_price', area.delivery_price);

            // ---- Express fee (conditional on global setting) -----------
            if ( kdmData.expressEnabled !== false ) {
                r += this.renderPriceCell('kdm-col-express', 'express_fee', area.express_fee);
            }

            // ---- Bilingual notes with push-to-all buttons --------------
            var notesAr = area.delivery_notes    || '';
            var notesEn = area.delivery_notes_en || '';
            var notesViewHtml = '';

            if (notesAr) { notesViewHtml += '<span class="kdm-notes-ar">' + this.escHtml(notesAr) + '</span>'; }
            if (notesEn) { notesViewHtml += '<span class="kdm-notes-en">' + this.escHtml(notesEn) + '</span>'; }
            if (!notesViewHtml) { notesViewHtml = '<em class="kdm-empty-val">—</em>'; }

            r += '<td class="kdm-col-notes">';
            r += '  <div class="kdm-bilingual-view">' + notesViewHtml + '</div>';
            // AR notes edit row (hidden until edit mode)
            r += '  <div class="kdm-notes-edit-row" style="display:none">';
            r += '    <textarea class="kdm-edit-input kdm-input-ar" name="delivery_notes"';
            r += '              rows="2" placeholder="' + kdmData.strings.placeholderNotesAr + '">';
            r += this.escHtml(notesAr) + '</textarea>';
            r += '    <button type="button" class="kdm-push-val-btn" data-field="delivery_notes"';
            r += '            title="' + this.escAttr(kdmData.strings.pushToAll) + '">';
            r += '      <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>';
            r += '    </button>';
            r += '  </div>';
            // EN notes edit row
            r += '  <div class="kdm-notes-edit-row" style="display:none">';
            r += '    <textarea class="kdm-edit-input kdm-input-en" name="delivery_notes_en"';
            r += '              rows="2" placeholder="' + kdmData.strings.placeholderNotesEn + '">';
            r += this.escHtml(notesEn) + '</textarea>';
            r += '    <button type="button" class="kdm-push-val-btn" data-field="delivery_notes_en"';
            r += '            title="' + this.escAttr(kdmData.strings.pushToAll) + '">';
            r += '      <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>';
            r += '    </button>';
            r += '  </div>';
            r += '</td>';

            // ---- Minimum order ----------------------------------------
            r += this.renderPriceCell('kdm-col-min', 'minimum_order', area.minimum_order);

            // ---- Status toggle ----------------------------------------
            r += '<td class="kdm-col-status">';
            r += '  <button type="button" class="kdm-toggle ' + tglClass + '" data-id="' + area.id + '"';
            r += '          title="' + this.escAttr(tglLabel) + '" aria-label="' + this.escAttr(tglLabel) + '">';
            r += '    <span class="kdm-toggle-knob"></span>';
            r += '    <span class="kdm-toggle-label">' + tglLabel + '</span>';
            r += '  </button>';
            r += '</td>';

            // ---- Action buttons ---------------------------------------
            r += '<td class="kdm-col-actions">';
            r += '  <button type="button" class="button kdm-edit-btn" data-id="' + area.id + '">';
            r += '    <span class="dashicons dashicons-edit" aria-hidden="true"></span> ' + kdmData.strings.editArea;
            r += '  </button>';
            r += '  <button type="button" class="button button-primary kdm-save-btn" data-id="' + area.id + '" style="display:none">';
            r += '    <span class="dashicons dashicons-saved" aria-hidden="true"></span> ' + kdmData.strings.saveArea;
            r += '  </button>';
            r += '  <button type="button" class="button kdm-cancel-btn" data-id="' + area.id + '" style="display:none">';
            r += '    ' + kdmData.strings.cancelEdit;
            r += '  </button>';
            r += '  <button type="button" class="button kdm-delete-btn" data-id="' + area.id + '"';
            r += '          title="' + kdmData.strings.deleteArea + '">';
            r += '    <span class="dashicons dashicons-trash" aria-hidden="true"></span>';
            r += '  </button>';
            r += '</td>';

            r += '</tr>';
            return r;
        },

        /**
         * Renders a price cell with view-mode span, edit-mode input, and push-to-all button.
         *
         * @param {string} cls        CSS class for the <td>
         * @param {string} fieldName  Input name / data-field attribute
         * @param {*}      value      Current numeric value
         */
        renderPriceCell: function (cls, fieldName, value) {
            var fmtVal = this.fmtPrice(value);
            var r = '<td class="' + cls + '">';
            r += '  <span class="kdm-view-val kdm-price">' + fmtVal + ' ' + kdmData.strings.kwd + '</span>';
            r += '  <div class="kdm-price-edit-wrap" style="display:none">';
            r += '    <input type="number" class="kdm-edit-input" name="' + fieldName + '"';
            r += '           value="' + fmtVal + '" step="0.001" min="0">';
            r += '    <button type="button" class="kdm-push-val-btn" data-field="' + fieldName + '"';
            r += '            title="' + this.escAttr(kdmData.strings.pushToAll) + '">';
            r += '      <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>';
            r += '    </button>';
            r += '  </div>';
            r += '</td>';
            return r;
        },

        // ====================================================================
        // SORTABLE
        // ====================================================================

        initSortable: function () {
            var self = this;
            $('#kdm-sortable').sortable({
                handle:      '.kdm-sort-handle',
                axis:        'y',
                cursor:      'grabbing',
                placeholder: 'kdm-sort-placeholder',
                tolerance:   'pointer',
                update: function () {
                    var order = [];
                    $('#kdm-sortable tr.kdm-area-row').each(function () { order.push($(this).data('id')); });
                    self.saveOrder(order);
                }
            }).disableSelection();
        },

        saveOrder: function (order) {
            var self = this;
            $.ajax({
                url: kdmData.ajaxUrl, type: 'POST',
                data: { action: 'kdm_reorder_areas', order: order, nonce: kdmData.nonce },
                success: function (r) { if (r.success) { self.showNotice('success', kdmData.strings.orderSaved); } }
            });
        },

        // ====================================================================
        // INLINE EDITING
        // ====================================================================

        onEditClick: function (e) {
            var $btn = $(e.currentTarget);
            var $row = $btn.closest('tr');
            var id   = parseInt($btn.data('id'), 10);

            if (this.editingId !== null && this.editingId !== id) {
                this.cancelEdit($('.kdm-area-row[data-id="' + this.editingId + '"]'));
            }
            this.editingId = id;

            // Switch row to edit mode
            $row.find('.kdm-bilingual-view').hide();
            $row.find('.kdm-view-val').hide();
            $row.find('.kdm-edit-input').show();
            $row.find('.kdm-price-edit-wrap').show();
            $row.find('.kdm-notes-edit-row').show();
            $row.find('.kdm-edit-btn').hide();
            $row.find('.kdm-save-btn, .kdm-cancel-btn').show();
            $row.addClass('kdm-row-editing');

            // Focus first AR input
            $row.find('input[name="area_name_ar"]').focus();

            // Auto-size any visible textareas
            $row.find('textarea.kdm-edit-input:visible').each(function () {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        },

        onCancelClick: function (e) {
            this.editingId = null;
            this.cancelEdit($(e.currentTarget).closest('tr'));
        },

        cancelEdit: function ($row) {
            $row.find('.kdm-bilingual-view').show();
            $row.find('.kdm-view-val').show();
            $row.find('.kdm-edit-input').hide();
            $row.find('.kdm-price-edit-wrap').hide();
            $row.find('.kdm-notes-edit-row').hide();
            $row.find('.kdm-edit-btn').show();
            $row.find('.kdm-save-btn, .kdm-cancel-btn').hide();
            $row.removeClass('kdm-row-editing');
            $row.find('.kdm-input-error').removeClass('kdm-input-error');
        },

        onSaveClick: function (e) {
            var $btn = $(e.currentTarget);
            var $row = $btn.closest('tr');
            var id   = parseInt($btn.data('id'), 10);
            var self = this;

            var nameVal = $row.find('input[name="area_name_ar"]').val().trim();
            if (!nameVal) {
                $row.find('input[name="area_name_ar"]').addClass('kdm-input-error').focus();
                self.showNotice('error', kdmData.strings.nameRequired);
                return;
            }

            $btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update-alt"></span> ' + kdmData.strings.saving
            );

            $.ajax({
                url: kdmData.ajaxUrl, type: 'POST',
                data: {
                    action:           'kdm_save_area',
                    id:               id,
                    nonce:            kdmData.nonce,
                    area_name_ar:     nameVal,
                    area_name_en:     $row.find('input[name="area_name_en"]').val(),
                    delivery_price:   $row.find('input[name="delivery_price"]').val(),
                    express_fee:      $row.find('input[name="express_fee"]').val(),
                    delivery_notes:   $row.find('textarea[name="delivery_notes"]').val(),
                    delivery_notes_en:$row.find('textarea[name="delivery_notes_en"]').val(),
                    minimum_order:    $row.find('input[name="minimum_order"]').val()
                },
                success: function (r) {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-saved"></span> ' + kdmData.strings.saveArea
                    );
                    if (r.success) {
                        self.editingId = null;
                        self.updateRowDisplay($row, r.data.area);
                        self.showNotice('success', r.data.message);
                    } else {
                        self.showNotice('error', r.data.message || kdmData.strings.error);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-saved"></span> ' + kdmData.strings.saveArea
                    );
                    self.showNotice('error', kdmData.strings.error);
                }
            });
        },

        /** Refreshes the visible (view-mode) content of a row after a successful save. */
        updateRowDisplay: function ($row, area) {
            // Bilingual name
            var $nameView = $row.find('.kdm-col-name .kdm-bilingual-view');
            $nameView.html(
                '<span class="kdm-name-ar">' + this.escHtml(area.area_name_ar) + '</span>' +
                (area.area_name_en ? '<span class="kdm-name-en">' + this.escHtml(area.area_name_en) + '</span>' : '')
            );

            // Prices
            $row.find('.kdm-col-price .kdm-view-val').html(this.fmtPrice(area.delivery_price) + ' ' + kdmData.strings.kwd);
            if ( kdmData.expressEnabled !== false ) {
                $row.find('.kdm-col-express .kdm-view-val').html(this.fmtPrice(area.express_fee) + ' ' + kdmData.strings.kwd);
            }
            $row.find('.kdm-col-min .kdm-view-val').html(this.fmtPrice(area.minimum_order) + ' ' + kdmData.strings.kwd);

            // Bilingual notes
            var notesAr = area.delivery_notes    || '';
            var notesEn = area.delivery_notes_en || '';
            var notesHtml = '';
            if (notesAr) { notesHtml += '<span class="kdm-notes-ar">' + this.escHtml(notesAr) + '</span>'; }
            if (notesEn) { notesHtml += '<span class="kdm-notes-en">' + this.escHtml(notesEn) + '</span>'; }
            if (!notesHtml) { notesHtml = '<em class="kdm-empty-val">—</em>'; }
            $row.find('.kdm-col-notes .kdm-bilingual-view').html(notesHtml);

            // Refresh input values so re-editing shows the saved data
            $row.find('input[name="area_name_ar"]').val(area.area_name_ar);
            $row.find('input[name="area_name_en"]').val(area.area_name_en || '');
            $row.find('input[name="delivery_price"]').val(this.fmtPrice(area.delivery_price));
            $row.find('input[name="express_fee"]').val(this.fmtPrice(area.express_fee));
            $row.find('textarea[name="delivery_notes"]').val(notesAr);
            $row.find('textarea[name="delivery_notes_en"]').val(notesEn);
            $row.find('input[name="minimum_order"]').val(this.fmtPrice(area.minimum_order));

            this.cancelEdit($row);

            $row.addClass('kdm-row-saved');
            setTimeout(function () { $row.removeClass('kdm-row-saved'); }, 2000);
        },

        // ====================================================================
        // DELETE
        // ====================================================================

        onDeleteClick: function (e) {
            var $btn = $(e.currentTarget);
            var $row = $btn.closest('tr');
            var id   = parseInt($btn.data('id'), 10);
            var self = this;

            if (!window.confirm(kdmData.strings.confirmDelete)) { return; }

            $btn.prop('disabled', true);

            $.ajax({
                url: kdmData.ajaxUrl, type: 'POST',
                data: { action: 'kdm_delete_area', id: id, nonce: kdmData.nonce },
                success: function (r) {
                    if (r.success) {
                        $row.addClass('kdm-row-deleting');
                        setTimeout(function () {
                            $row.remove();
                            if ($('#kdm-sortable tr.kdm-area-row').length === 0) {
                                $('.kdm-table-wrap').hide();
                                if (!$('.kdm-empty-state').length) {
                                    $('.kdm-content-header').after(
                                        '<div class="kdm-empty-state"><p>' + kdmData.strings.noAreas + '</p></div>'
                                    );
                                } else {
                                    $('.kdm-empty-state').show();
                                }
                            }
                            self.decrementBadge(self.currentGov);
                        }, 380);
                        self.showNotice('success', r.data.message);
                        if (self.editingId === id) { self.editingId = null; }
                    } else {
                        $btn.prop('disabled', false);
                        self.showNotice('error', r.data.message || kdmData.strings.error);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    self.showNotice('error', kdmData.strings.error);
                }
            });
        },

        // ====================================================================
        // STATUS TOGGLE
        // ====================================================================

        onToggleClick: function (e) {
            var $btn = $(e.currentTarget);
            var id   = parseInt($btn.data('id'), 10);
            var self = this;

            $btn.prop('disabled', true);

            $.ajax({
                url: kdmData.ajaxUrl, type: 'POST',
                data: { action: 'kdm_toggle_area', id: id, nonce: kdmData.nonce },
                success: function (r) {
                    $btn.prop('disabled', false);
                    if (r.success) {
                        var on    = r.data.is_enabled === 1;
                        var label = on ? kdmData.strings.toggleOn : kdmData.strings.toggleOff;
                        $btn.toggleClass('kdm-toggle-on', on)
                            .toggleClass('kdm-toggle-off', !on)
                            .attr('title', label).attr('aria-label', label)
                            .find('.kdm-toggle-label').text(label);
                    } else {
                        self.showNotice('error', r.data.message || kdmData.strings.error);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    self.showNotice('error', kdmData.strings.error);
                }
            });
        },

        // ====================================================================
        // ADD NEW AREA
        // ====================================================================

        onAddClick: function () {
            $('#kdm-new-area-row').remove();
            $('.kdm-table-wrap').show();
            $('.kdm-empty-state').hide();

            if (!$('#kdm-sortable').length) {
                var tbl = '<div class="kdm-table-wrap"><table class="kdm-areas-table wp-list-table widefat fixed striped">' +
                          '<thead><tr>' +
                          '<th class="kdm-col-sort"></th>' +
                          '<th class="kdm-col-name">'    + kdmData.strings.colArea    + '</th>' +
                          this.renderColHeader('kdm-col-price', 'delivery_price', kdmData.strings.colPrice) +
                          (kdmData.expressEnabled !== false ? this.renderColHeader('kdm-col-express', 'express_fee', kdmData.strings.colExpress) : '') +
                          '<th class="kdm-col-notes">'   + kdmData.strings.colNotes   + '</th>' +
                          this.renderColHeader('kdm-col-min', 'minimum_order', kdmData.strings.colMinOrder) +
                          '<th class="kdm-col-status">'  + kdmData.strings.colStatus  + '</th>' +
                          '<th class="kdm-col-actions">' + kdmData.strings.colActions + '</th>' +
                          '</tr></thead><tbody id="kdm-sortable"></tbody></table></div>';
                $('#kdm-areas-content').append(tbl);
            }

            $('#kdm-sortable').append(this.renderNewRow());
            $('#kdm-new-area-row input[name="area_name_ar"]').focus();
            $('html, body').animate({ scrollTop: $('#kdm-new-area-row').offset().top - 80 }, 250);
        },

        renderNewRow: function () {
            return (
                '<tr id="kdm-new-area-row" class="kdm-area-row kdm-new-row">' +
                '<td class="kdm-col-sort"><span class="dashicons dashicons-menu"></span></td>' +

                '<td class="kdm-col-name">' +
                '  <input type="text" class="kdm-edit-input kdm-input-ar" name="area_name_ar" placeholder="' + kdmData.strings.placeholderNameAr + ' *" required>' +
                '  <input type="text" class="kdm-edit-input kdm-input-en" name="area_name_en" placeholder="' + kdmData.strings.placeholderNameEn + '">' +
                '</td>' +

                '<td class="kdm-col-price">' +
                '  <div class="kdm-price-edit-wrap">' +
                '    <input type="number" class="kdm-edit-input" name="delivery_price" value="0.000" step="0.001" min="0">' +
                '  </div></td>' +

                (kdmData.expressEnabled !== false ?
                    '<td class="kdm-col-express">' +
                    '  <div class="kdm-price-edit-wrap">' +
                    '    <input type="number" class="kdm-edit-input" name="express_fee" value="0.000" step="0.001" min="0">' +
                    '  </div></td>' : '') +

                '<td class="kdm-col-notes">' +
                '  <textarea class="kdm-edit-input kdm-input-ar" name="delivery_notes" rows="2" placeholder="' + kdmData.strings.placeholderNotesAr + '"></textarea>' +
                '  <textarea class="kdm-edit-input kdm-input-en" name="delivery_notes_en" rows="2" placeholder="' + kdmData.strings.placeholderNotesEn + '"></textarea>' +
                '</td>' +

                '<td class="kdm-col-min">' +
                '  <div class="kdm-price-edit-wrap">' +
                '    <input type="number" class="kdm-edit-input" name="minimum_order" value="0.000" step="0.001" min="0">' +
                '  </div></td>' +

                '<td class="kdm-col-status"><span class="kdm-new-status">' + kdmData.strings.autoEnabled + '</span></td>' +

                '<td class="kdm-col-actions">' +
                '  <button type="button" class="button button-primary kdm-save-new-btn">' +
                '    <span class="dashicons dashicons-plus" aria-hidden="true"></span> ' + kdmData.strings.addBtn +
                '  </button>' +
                '  <button type="button" class="button kdm-cancel-new-btn">' + kdmData.strings.cancelEdit + '</button>' +
                '</td></tr>'
            );
        },

        onSaveNewClick: function (e) {
            var $btn = $(e.currentTarget);
            var $row = $('#kdm-new-area-row');
            var self = this;

            var nameVal = $row.find('input[name="area_name_ar"]').val().trim();
            if (!nameVal) {
                $row.find('input[name="area_name_ar"]').addClass('kdm-input-error').focus();
                self.showNotice('error', kdmData.strings.nameRequired);
                return;
            }

            $btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update-alt"></span> ' + kdmData.strings.adding
            );

            $.ajax({
                url: kdmData.ajaxUrl, type: 'POST',
                data: {
                    action:             'kdm_add_area',
                    governorate_key:    self.currentGov,
                    nonce:              kdmData.nonce,
                    area_name_ar:       nameVal,
                    area_name_en:       $row.find('input[name="area_name_en"]').val() || '',
                    delivery_price:     $row.find('input[name="delivery_price"]').val() || '0',
                    express_fee:        $row.find('input[name="express_fee"]').val()    || '0',
                    delivery_notes:     $row.find('textarea[name="delivery_notes"]').val()    || '',
                    delivery_notes_en:  $row.find('textarea[name="delivery_notes_en"]').val() || '',
                    minimum_order:      $row.find('input[name="minimum_order"]').val()  || '0',
                    is_enabled:         1
                },
                success: function (r) {
                    if (r.success) {
                        $row.replaceWith(self.renderRow(r.data.area));
                        var $newRow = $('.kdm-area-row[data-id="' + r.data.area.id + '"]');
                        $newRow.addClass('kdm-row-saved');
                        setTimeout(function () { $newRow.removeClass('kdm-row-saved'); }, 2000);
                        self.showNotice('success', r.data.message);
                        self.incrementBadge(self.currentGov);
                        self.initSortable();
                    } else {
                        $btn.prop('disabled', false).html(
                            '<span class="dashicons dashicons-plus"></span> ' + kdmData.strings.addBtn
                        );
                        self.showNotice('error', r.data.message || kdmData.strings.error);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-plus"></span> ' + kdmData.strings.addBtn
                    );
                    self.showNotice('error', kdmData.strings.error);
                }
            });
        },

        onCancelNewClick: function () { $('#kdm-new-area-row').remove(); },

        // ====================================================================
        // COPY FIRST ROW VALUE → ALL ROWS  (column header button)
        // ====================================================================

        onCopyColClick: function (e) {
            e.preventDefault();
            var field   = $(e.currentTarget).data('field');
            var isText  = (field === 'delivery_notes' || field === 'delivery_notes_en');
            var self    = this;

            var $firstRow = $('#kdm-sortable tr.kdm-area-row:first');
            if (!$firstRow.length) { return; }

            // For both text and numeric: reading the hidden [name] element is reliable
            // (textarea/input always hold current data even in view mode)
            var val, displayVal;
            if (isText) {
                val        = $firstRow.find('[name="' + field + '"]').val() || '';
                displayVal = '"' + (val.length > 30 ? val.substr(0, 30) + '…' : val) + '"';
            } else {
                var $input = $firstRow.find('input[name="' + field + '"]');
                if ($firstRow.hasClass('kdm-row-editing') && $input.length) {
                    val = parseFloat($input.val() || 0);
                } else {
                    val = parseFloat($firstRow.find('.' + this.fieldToCls(field) + ' .kdm-view-val').text()) || 0;
                }
                displayVal = val.toFixed(3);
            }

            var rowCount = $('#kdm-sortable tr.kdm-area-row').length;
            var msg      = kdmData.strings.copyConfirm
                               .replace('{value}', displayVal)
                               .replace('{field}', kdmData.strings['field_' + field] || field)
                               .replace('{count}', rowCount);

            if (!window.confirm(msg)) { return; }
            self.doCopyFieldToAll(field, val);
        },

        // Per-row push button (in edit mode)
        onPushValClick: function (e) {
            e.preventDefault();
            var $btn   = $(e.currentTarget);
            var $row   = $btn.closest('tr');
            var field  = $btn.data('field');
            var isText = (field === 'delivery_notes' || field === 'delivery_notes_en');
            var self   = this;

            var val, displayVal;
            if (isText) {
                val        = $row.find('[name="' + field + '"]').val() || '';
                displayVal = '"' + (val.length > 30 ? val.substr(0, 30) + '…' : val) + '"';
            } else {
                val        = parseFloat($row.find('input[name="' + field + '"]').val() || 0);
                displayVal = val.toFixed(3);
            }

            var count = $('#kdm-sortable tr.kdm-area-row').length;
            var msg   = kdmData.strings.pushConfirm
                            .replace('{value}', displayVal)
                            .replace('{field}', kdmData.strings['field_' + field] || field)
                            .replace('{count}', count);

            if (!window.confirm(msg)) { return; }
            self.doCopyFieldToAll(field, val);
        },

        /**
         * Sends the bulk-update AJAX request and refreshes all row displays.
         * Handles both numeric (price) and text (notes) fields.
         *
         * @param {string}        field  Column name
         * @param {number|string} val    Value to apply
         */
        doCopyFieldToAll: function (field, val) {
            var self   = this;
            var isText = (field === 'delivery_notes' || field === 'delivery_notes_en');

            $.ajax({
                url: kdmData.ajaxUrl, type: 'POST',
                data: {
                    action:          'kdm_copy_field_to_all',
                    governorate_key: self.currentGov,
                    field_name:      field,
                    field_value:     isText ? String(val) : parseFloat(val),
                    nonce:           kdmData.nonce
                },
                success: function (r) {
                    if (r.success) {
                        if (isText) {
                            // Update notes view spans and textarea values
                            var spanCls = (field === 'delivery_notes') ? 'kdm-notes-ar' : 'kdm-notes-en';
                            $('#kdm-sortable tr.kdm-area-row').each(function () {
                                var $r    = $(this);
                                var $view = $r.find('.kdm-col-notes .kdm-bilingual-view');
                                var $span = $view.find('.' + spanCls);
                                if (val) {
                                    if ($span.length) {
                                        $span.text(val);
                                    } else {
                                        // Remove placeholder dash if this is the first note added
                                        $view.find('.kdm-empty-val').remove();
                                        $view.append('<span class="' + spanCls + '">' + self.escHtml(val) + '</span>');
                                    }
                                } else {
                                    $span.remove();
                                    // Restore dash if both notes now empty
                                    if (!$view.find('.kdm-notes-ar, .kdm-notes-en').length) {
                                        $view.html('<em class="kdm-empty-val">—</em>');
                                    }
                                }
                                $r.find('[name="' + field + '"]').val(val);
                                $r.addClass('kdm-row-saved');
                                setTimeout(function () { $r.removeClass('kdm-row-saved'); }, 2000);
                            });
                        } else {
                            var clsMap = {
                                delivery_price: 'kdm-col-price',
                                express_fee:    'kdm-col-express',
                                minimum_order:  'kdm-col-min'
                            };
                            var cls    = clsMap[field];
                            var fmtVal = parseFloat(val).toFixed(3) + ' ' + kdmData.strings.kwd;

                            $('#kdm-sortable tr.kdm-area-row').each(function () {
                                var $r = $(this);
                                $r.find('.' + cls + ' .kdm-view-val').html(fmtVal);
                                $r.find('.' + cls + ' input[name="' + field + '"]').val(parseFloat(val).toFixed(3));
                                $r.addClass('kdm-row-saved');
                                setTimeout(function () { $r.removeClass('kdm-row-saved'); }, 2000);
                            });
                        }
                        self.showNotice('success', r.data.message);
                    } else {
                        self.showNotice('error', r.data.message || kdmData.strings.error);
                    }
                },
                error: function () { self.showNotice('error', kdmData.strings.error); }
            });
        },

        /** Maps a DB field name to its column CSS class. */
        fieldToCls: function (field) {
            return {
                delivery_price:    'kdm-col-price',
                express_fee:       'kdm-col-express',
                minimum_order:     'kdm-col-min',
                delivery_notes:    'kdm-col-notes',
                delivery_notes_en: 'kdm-col-notes'
            }[field] || '';
        },

        // ====================================================================
        // BADGE HELPERS
        // ====================================================================

        incrementBadge: function (k) {
            var $b = $('#kdm-count-' + k);
            $b.text((parseInt($b.text(), 10) || 0) + 1).addClass('visible');
        },

        decrementBadge: function (k) {
            var $b = $('#kdm-count-' + k);
            $b.text(Math.max(0, (parseInt($b.text(), 10) || 1) - 1));
        },

        // ====================================================================
        // NOTICES
        // ====================================================================

        showNotice: function (type, message) {
            var icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            var html =
                '<div class="kdm-notice kdm-notice-' + type + '">' +
                '  <span class="dashicons ' + icon + '" aria-hidden="true"></span>' +
                '  <p>' + this.escHtml(message) + '</p>' +
                '  <button type="button" class="kdm-notice-dismiss" aria-label="' + kdmData.strings.close + '">' +
                '    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>' +
                '  </button>' +
                '</div>';

            var $notices = $('#kdm-notices').html(html);
            var $notice  = $notices.find('.kdm-notice');
            var timer    = setTimeout(function () { $notice.fadeOut(300, function () { $(this).remove(); }); }, 4500);

            $notice.find('.kdm-notice-dismiss').on('click', function () {
                clearTimeout(timer);
                $notice.fadeOut(200, function () { $(this).remove(); });
            });

            $('html, body').animate({ scrollTop: 0 }, 150);
        },

        // ====================================================================
        // UTILITIES
        // ====================================================================

        escHtml: function (s) { return $('<div>').text(String(s)).html(); },
        escAttr: function (s) { return $('<div>').text(String(s)).html().replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },
        fmtPrice: function (v) { return parseFloat(v || 0).toFixed(3); }
    };

    $(document).ready(function () { KDM.init(); });

})(jQuery);
