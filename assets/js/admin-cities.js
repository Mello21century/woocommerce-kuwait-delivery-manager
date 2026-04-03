/**
 * Kuwait Delivery Manager — Cities Admin JavaScript
 *
 * Manages cities for the selected country:
 *   - Load / render cities table via AJAX
 *   - Inline edit (bilingual names: Arabic + English)
 *   - Add new city (inline row at top)
 *   - Delete city (with cascade warning)
 *   - Toggle is_active
 *   - Drag-sort with jQuery UI Sortable
 */
/* global jQuery, kdmCitiesData */

var KDMCities = {

    editingId: null,
    adding:    false,

    /* ------------------------------------------------------------------ */
    /*  Boot                                                               */
    /* ------------------------------------------------------------------ */

    init: function () {
        this.s = kdmCitiesData.strings;
        this.bindEvents();
        this.loadCities();
    },

    /* ------------------------------------------------------------------ */
    /*  Event binding — delegation on #kdm-cities-content                  */
    /* ------------------------------------------------------------------ */

    bindEvents: function () {
        var self  = this;
        var $wrap = jQuery('#kdm-cities-content');

        $wrap.on('click', '.kdm-edit-btn',       function (e) { self.onEditClick(e); });
        $wrap.on('click', '.kdm-save-btn',        function (e) { self.onSaveClick(e); });
        $wrap.on('click', '.kdm-cancel-btn',      function (e) { self.onCancelClick(e); });
        $wrap.on('click', '.kdm-delete-btn',      function (e) { self.onDeleteClick(e); });
        $wrap.on('click', '.kdm-toggle input',    function (e) { self.onToggleClick(e); });
        $wrap.on('click', '#kdm-add-city-btn',    function (e) { self.onAddClick(e); });
        $wrap.on('click', '.kdm-save-new-btn',    function (e) { self.onSaveNewClick(e); });
        $wrap.on('click', '.kdm-cancel-new-btn',  function (e) { self.onCancelNewClick(e); });

        // Enter = submit, Escape = cancel inside inline inputs
        $wrap.on('keydown', '.kdm-inline-input', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                var $row = jQuery(this).closest('tr');
                if ($row.hasClass('kdm-new-row')) {
                    $row.find('.kdm-save-new-btn').trigger('click');
                } else {
                    $row.find('.kdm-save-btn').trigger('click');
                }
            }
            if (e.which === 27) {
                e.preventDefault();
                var $r = jQuery(this).closest('tr');
                if ($r.hasClass('kdm-new-row')) {
                    $r.find('.kdm-cancel-new-btn').trigger('click');
                } else {
                    $r.find('.kdm-cancel-btn').trigger('click');
                }
            }
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Load cities via AJAX                                               */
    /* ------------------------------------------------------------------ */

    loadCities: function () {
        var self  = this;
        var $wrap = jQuery('#kdm-cities-content');

        $wrap.html('<p class="kdm-loading">' + self.escHtml(self.s.loading) + '</p>');

        jQuery.post(kdmCitiesData.ajaxUrl, {
            action:       'kdm_get_cities',
            country_iso2: kdmCitiesData.selectedCountry,
            nonce:        kdmCitiesData.nonce
        })
        .done(function (res) {
            if (res.success && res.data && res.data.cities) {
                self.renderTable(res.data.cities);
            } else {
                $wrap.html('<p class="kdm-error">' + self.escHtml(self.s.error) + '</p>');
            }
        })
        .fail(function () {
            $wrap.html('<p class="kdm-error">' + self.escHtml(self.s.error) + '</p>');
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Render full table                                                   */
    /* ------------------------------------------------------------------ */

    renderTable: function (cities) {
        var self  = this;
        var s     = self.s;
        var $wrap = jQuery('#kdm-cities-content');
        var html  = '';

        // Toolbar with "Add" button
        html += '<div class="kdm-cities-toolbar">';
        html += '<button type="button" id="kdm-add-city-btn" class="button button-primary">';
        html += self.escHtml(s.addBtn);
        html += '</button>';
        html += '</div>';

        if (!cities.length) {
            html += '<p class="kdm-no-items">' + self.escHtml(s.noCities) + '</p>';
            $wrap.html(html);
            return;
        }

        html += '<table class="wp-list-table widefat fixed striped kdm-cities-table">';
        html += '<thead><tr>';
        html += '<th class="kdm-col-drag" title="' + self.escAttr(s.dragSort) + '">&nbsp;</th>';
        html += '<th class="kdm-col-city">'    + self.escHtml(s.colCity)    + '</th>';
        html += '<th class="kdm-col-areas">'   + self.escHtml(s.colAreas)   + '</th>';
        html += '<th class="kdm-col-status">'  + self.escHtml(s.colStatus)  + '</th>';
        html += '<th class="kdm-col-actions">' + self.escHtml(s.colActions) + '</th>';
        html += '</tr></thead>';
        html += '<tbody id="kdm-cities-tbody">';

        for (var i = 0; i < cities.length; i++) {
            html += self.renderRow(cities[i]);
        }

        html += '</tbody></table>';
        $wrap.html(html);

        self.initSortable();
    },

    /* ------------------------------------------------------------------ */
    /*  Render a single table row                                          */
    /* ------------------------------------------------------------------ */

    renderRow: function (city) {
        var self   = this;
        var s      = self.s;
        var nameAr = (city.city_name_decoded && city.city_name_decoded.ar) || '';
        var nameEn = (city.city_name_decoded && city.city_name_decoded.en) || '';
        var active = parseInt(city.is_active, 10);
        var areas  = parseInt(city.area_count, 10) || 0;

        var html = '';
        html += '<tr data-city-id="' + self.escAttr(city.city_id) + '"';
        html += ' data-name-ar="'    + self.escAttr(nameAr)       + '"';
        html += ' data-name-en="'    + self.escAttr(nameEn)       + '">';

        // Drag handle
        html += '<td class="kdm-col-drag">';
        html += '<span class="kdm-drag-handle" title="' + self.escAttr(s.dragSort) + '">&#x2261;</span>';
        html += '</td>';

        // City name (bilingual display)
        html += '<td class="kdm-col-city">';
        html += '<span class="kdm-city-display">';
        html += '<strong class="kdm-name-ar">' + self.escHtml(nameAr) + '</strong>';
        if (nameEn) {
            html += ' <span class="kdm-name-en">' + self.escHtml(nameEn) + '</span>';
        }
        html += '</span>';
        html += '</td>';

        // Area count (read-only)
        html += '<td class="kdm-col-areas">' + areas + '</td>';

        // Status toggle
        html += '<td class="kdm-col-status">';
        html += '<label class="kdm-toggle">';
        html += '<input type="checkbox"' + (active ? ' checked' : '') + '>';
        html += '<span class="kdm-toggle-slider"></span>';
        html += '</label>';
        html += '</td>';

        // Actions
        html += '<td class="kdm-col-actions">';
        html += '<button type="button" class="button button-small kdm-edit-btn" title="' + self.escAttr(s.editCity) + '">';
        html += self.escHtml(s.editCity) + '</button> ';
        html += '<button type="button" class="button button-small button-link-delete kdm-delete-btn" title="' + self.escAttr(s.deleteCity) + '">';
        html += self.escHtml(s.deleteCity) + '</button>';
        html += '</td>';

        html += '</tr>';
        return html;
    },

    /* ------------------------------------------------------------------ */
    /*  Inline edit — enter edit mode                                      */
    /* ------------------------------------------------------------------ */

    onEditClick: function (e) {
        e.preventDefault();
        var self   = this;
        var s      = self.s;
        var $row   = jQuery(e.currentTarget).closest('tr');
        var cityId = $row.data('city-id');

        // Cancel any other editing row first
        if (self.editingId && self.editingId !== cityId) {
            self._restoreRow();
        }

        self.editingId = cityId;

        var nameAr = $row.data('name-ar') || '';
        var nameEn = $row.data('name-en') || '';

        // Replace city name cell with inputs
        $row.find('.kdm-col-city').html(
            '<input type="text" class="kdm-inline-input kdm-input-ar" ' +
            'value="' + self.escAttr(nameAr) + '" ' +
            'placeholder="' + self.escAttr(s.placeholderNameAr) + '" dir="rtl"> ' +
            '<input type="text" class="kdm-inline-input kdm-input-en" ' +
            'value="' + self.escAttr(nameEn) + '" ' +
            'placeholder="' + self.escAttr(s.placeholderNameEn) + '">'
        );

        // Replace actions cell with save / cancel
        $row.find('.kdm-col-actions').html(
            '<button type="button" class="button button-small button-primary kdm-save-btn">' +
            self.escHtml(s.saveCity) + '</button> ' +
            '<button type="button" class="button button-small kdm-cancel-btn">' +
            self.escHtml(s.cancelEdit) + '</button>'
        );

        $row.find('.kdm-input-ar').focus();
    },

    /* ------------------------------------------------------------------ */
    /*  Inline edit — save                                                 */
    /* ------------------------------------------------------------------ */

    onSaveClick: function (e) {
        e.preventDefault();
        var self   = this;
        var s      = self.s;
        var $btn   = jQuery(e.currentTarget);
        var $row   = $btn.closest('tr');
        var cityId = $row.data('city-id');

        var nameAr = jQuery.trim($row.find('.kdm-input-ar').val());
        var nameEn = jQuery.trim($row.find('.kdm-input-en').val());

        if (!nameAr && !nameEn) {
            self.showNotice(s.nameRequired, 'error');
            return;
        }

        $btn.prop('disabled', true).text(s.saving);

        jQuery.post(kdmCitiesData.ajaxUrl, {
            action:  'kdm_save_city',
            city_id: cityId,
            name_en: nameEn,
            name_ar: nameAr,
            nonce:   kdmCitiesData.nonce
        })
        .done(function (res) {
            if (res.success && res.data && res.data.city) {
                var $newRow = jQuery(self.renderRow(res.data.city));
                $row.replaceWith($newRow);
                self.editingId = null;
                self.showNotice(s.saveCity, 'success');
            } else {
                self.showNotice(s.error, 'error');
                $btn.prop('disabled', false).text(s.saveCity);
            }
        })
        .fail(function () {
            self.showNotice(s.error, 'error');
            $btn.prop('disabled', false).text(s.saveCity);
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Inline edit — cancel                                               */
    /* ------------------------------------------------------------------ */

    onCancelClick: function (e) {
        e.preventDefault();
        this._restoreRow();
    },

    /**
     * Restore the currently-editing row back to display mode.
     */
    _restoreRow: function () {
        if (!this.editingId) {
            return;
        }
        var self = this;
        var s    = self.s;
        var $row = jQuery('tr[data-city-id="' + self.editingId + '"]');

        if (!$row.length) {
            self.editingId = null;
            return;
        }

        var nameAr = $row.data('name-ar') || '';
        var nameEn = $row.data('name-en') || '';

        // Restore city name display
        var displayHtml = '<span class="kdm-city-display">';
        displayHtml += '<strong class="kdm-name-ar">' + self.escHtml(nameAr) + '</strong>';
        if (nameEn) {
            displayHtml += ' <span class="kdm-name-en">' + self.escHtml(nameEn) + '</span>';
        }
        displayHtml += '</span>';
        $row.find('.kdm-col-city').html(displayHtml);

        // Restore action buttons
        $row.find('.kdm-col-actions').html(
            '<button type="button" class="button button-small kdm-edit-btn" title="' + self.escAttr(s.editCity) + '">' +
            self.escHtml(s.editCity) + '</button> ' +
            '<button type="button" class="button button-small button-link-delete kdm-delete-btn" title="' + self.escAttr(s.deleteCity) + '">' +
            self.escHtml(s.deleteCity) + '</button>'
        );

        self.editingId = null;
    },

    /* ------------------------------------------------------------------ */
    /*  Add new city — show inline row                                     */
    /* ------------------------------------------------------------------ */

    onAddClick: function (e) {
        e.preventDefault();
        var self = this;
        var s    = self.s;

        if (self.adding) {
            return;
        }

        // Cancel any active inline edit first
        if (self.editingId) {
            self._restoreRow();
        }

        self.adding = true;

        var $tbody = jQuery('#kdm-cities-tbody');

        // If table does not exist yet (empty state), create it
        if (!$tbody.length) {
            var $wrap    = jQuery('#kdm-cities-content');
            var tableHtml = '';
            tableHtml += '<table class="wp-list-table widefat fixed striped kdm-cities-table">';
            tableHtml += '<thead><tr>';
            tableHtml += '<th class="kdm-col-drag">&nbsp;</th>';
            tableHtml += '<th class="kdm-col-city">'    + self.escHtml(s.colCity)    + '</th>';
            tableHtml += '<th class="kdm-col-areas">'   + self.escHtml(s.colAreas)   + '</th>';
            tableHtml += '<th class="kdm-col-status">'  + self.escHtml(s.colStatus)  + '</th>';
            tableHtml += '<th class="kdm-col-actions">' + self.escHtml(s.colActions) + '</th>';
            tableHtml += '</tr></thead>';
            tableHtml += '<tbody id="kdm-cities-tbody"></tbody></table>';
            $wrap.find('.kdm-no-items').remove();
            $wrap.append(tableHtml);
            $tbody = jQuery('#kdm-cities-tbody');
        }

        var rowHtml = '';
        rowHtml += '<tr class="kdm-new-row">';
        rowHtml += '<td class="kdm-col-drag">&nbsp;</td>';
        rowHtml += '<td class="kdm-col-city">';
        rowHtml += '<input type="text" class="kdm-inline-input kdm-input-ar" ' +
                   'placeholder="' + self.escAttr(s.placeholderNameAr) + '" dir="rtl"> ';
        rowHtml += '<input type="text" class="kdm-inline-input kdm-input-en" ' +
                   'placeholder="' + self.escAttr(s.placeholderNameEn) + '">';
        rowHtml += '</td>';
        rowHtml += '<td class="kdm-col-areas">0</td>';
        rowHtml += '<td class="kdm-col-status">&mdash;</td>';
        rowHtml += '<td class="kdm-col-actions">';
        rowHtml += '<button type="button" class="button button-small button-primary kdm-save-new-btn">' +
                   self.escHtml(s.addNewCity) + '</button> ';
        rowHtml += '<button type="button" class="button button-small kdm-cancel-new-btn">' +
                   self.escHtml(s.cancelEdit) + '</button>';
        rowHtml += '</td>';
        rowHtml += '</tr>';

        $tbody.prepend(rowHtml);
        $tbody.find('.kdm-new-row .kdm-input-ar').focus();
    },

    /* ------------------------------------------------------------------ */
    /*  Add new city — save                                                */
    /* ------------------------------------------------------------------ */

    onSaveNewClick: function (e) {
        e.preventDefault();
        var self = this;
        var s    = self.s;
        var $btn = jQuery(e.currentTarget);
        var $row = $btn.closest('tr');

        var nameAr = jQuery.trim($row.find('.kdm-input-ar').val());
        var nameEn = jQuery.trim($row.find('.kdm-input-en').val());

        if (!nameAr && !nameEn) {
            self.showNotice(s.nameRequired, 'error');
            return;
        }

        $btn.prop('disabled', true).text(s.adding);

        jQuery.post(kdmCitiesData.ajaxUrl, {
            action:       'kdm_add_city',
            country_iso2: kdmCitiesData.selectedCountry,
            name_en:      nameEn,
            name_ar:      nameAr,
            nonce:        kdmCitiesData.nonce
        })
        .done(function (res) {
            if (res.success && res.data && res.data.city) {
                var $newRow = jQuery(self.renderRow(res.data.city));
                $row.replaceWith($newRow);
                self.adding = false;
                self.showNotice(s.addNewCity, 'success');
                self.initSortable();
            } else {
                self.showNotice(s.error, 'error');
                $btn.prop('disabled', false).text(s.addNewCity);
            }
        })
        .fail(function () {
            self.showNotice(s.error, 'error');
            $btn.prop('disabled', false).text(s.addNewCity);
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Add new city — cancel                                              */
    /* ------------------------------------------------------------------ */

    onCancelNewClick: function (e) {
        e.preventDefault();
        jQuery(e.currentTarget).closest('.kdm-new-row').remove();
        this.adding = false;
    },

    /* ------------------------------------------------------------------ */
    /*  Delete city                                                        */
    /* ------------------------------------------------------------------ */

    onDeleteClick: function (e) {
        e.preventDefault();
        var self   = this;
        var s      = self.s;
        var $btn   = jQuery(e.currentTarget);
        var $row   = $btn.closest('tr');
        var cityId = $row.data('city-id');

        if (!confirm(s.confirmDelete)) {
            return;
        }

        $btn.prop('disabled', true);

        jQuery.post(kdmCitiesData.ajaxUrl, {
            action:  'kdm_delete_city',
            city_id: cityId,
            nonce:   kdmCitiesData.nonce
        })
        .done(function (res) {
            if (res.success) {
                $row.fadeOut(300, function () {
                    jQuery(this).remove();
                    // If no rows remain, show the empty-state message
                    if (!jQuery('#kdm-cities-tbody tr').length) {
                        jQuery('.kdm-cities-table').remove();
                        jQuery('#kdm-cities-content').append(
                            '<p class="kdm-no-items">' + self.escHtml(s.noCities) + '</p>'
                        );
                    }
                });
            } else {
                self.showNotice(s.error, 'error');
                $btn.prop('disabled', false);
            }
        })
        .fail(function () {
            self.showNotice(s.error, 'error');
            $btn.prop('disabled', false);
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Toggle active status                                               */
    /* ------------------------------------------------------------------ */

    onToggleClick: function (e) {
        var self   = this;
        var $input = jQuery(e.currentTarget);
        var $row   = $input.closest('tr');
        var cityId = $row.data('city-id');

        // Prevent the default browser toggle; we set it after AJAX confirms
        e.preventDefault();
        $input.prop('disabled', true);

        jQuery.post(kdmCitiesData.ajaxUrl, {
            action:  'kdm_toggle_city',
            city_id: cityId,
            nonce:   kdmCitiesData.nonce
        })
        .done(function (res) {
            if (res.success && typeof res.data.is_active !== 'undefined') {
                var active = parseInt(res.data.is_active, 10);
                $input.prop('checked', !!active);
            } else {
                self.showNotice(self.s.error, 'error');
            }
            $input.prop('disabled', false);
        })
        .fail(function () {
            self.showNotice(self.s.error, 'error');
            $input.prop('disabled', false);
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Drag-sort via jQuery UI Sortable                                   */
    /* ------------------------------------------------------------------ */

    initSortable: function () {
        var self   = this;
        var $tbody = jQuery('#kdm-cities-tbody');

        if (!$tbody.length || !jQuery.fn.sortable) {
            return;
        }

        // Destroy a previous instance if present
        if ($tbody.sortable('instance')) {
            $tbody.sortable('destroy');
        }

        $tbody.sortable({
            handle:      '.kdm-drag-handle',
            axis:        'y',
            cursor:      'grabbing',
            placeholder: 'kdm-sortable-placeholder',
            helper: function (evt, tr) {
                // Preserve cell widths while dragging
                var $originals = tr.children();
                var $helper    = tr.clone();
                $helper.children().each(function (i) {
                    jQuery(this).width($originals.eq(i).width());
                });
                return $helper;
            },
            update: function () {
                self.saveOrder();
            }
        });
    },

    saveOrder: function () {
        var self  = this;
        var order = [];

        jQuery('#kdm-cities-tbody tr').each(function () {
            var id = jQuery(this).data('city-id');
            if (id) {
                order.push(id);
            }
        });

        if (!order.length) {
            return;
        }

        jQuery.post(kdmCitiesData.ajaxUrl, {
            action: 'kdm_reorder_cities',
            order:  order,
            nonce:  kdmCitiesData.nonce
        })
        .done(function (res) {
            if (res.success) {
                self.showNotice(self.s.orderSaved, 'success');
            } else {
                self.showNotice(self.s.error, 'error');
            }
        })
        .fail(function () {
            self.showNotice(self.s.error, 'error');
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Admin notice helper                                                */
    /* ------------------------------------------------------------------ */

    showNotice: function (msg, type) {
        type = type || 'success';
        var cls = (type === 'error') ? 'notice-error' : 'notice-success';

        var $notice = jQuery(
            '<div class="notice ' + cls + ' is-dismissible kdm-notice">' +
            '<p>' + this.escHtml(msg) + '</p>' +
            '<button type="button" class="notice-dismiss">' +
            '<span class="screen-reader-text">Dismiss</span></button>' +
            '</div>'
        );

        // Remove any existing KDM notices
        jQuery('.kdm-notice').remove();

        // Insert after the page heading
        var $heading = jQuery('.wrap h1').first();
        if ($heading.length) {
            $heading.after($notice);
        } else {
            jQuery('#kdm-cities-content').before($notice);
        }

        // Auto-dismiss after 4 seconds
        setTimeout(function () {
            $notice.fadeOut(300, function () {
                jQuery(this).remove();
            });
        }, 4000);

        // Manual dismiss
        $notice.on('click', '.notice-dismiss', function () {
            $notice.fadeOut(200, function () {
                jQuery(this).remove();
            });
        });
    },

    /* ------------------------------------------------------------------ */
    /*  Utility — escape HTML entities                                     */
    /* ------------------------------------------------------------------ */

    escHtml: function (str) {
        if (typeof str !== 'string') {
            return '';
        }
        return str
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    },

    /* ------------------------------------------------------------------ */
    /*  Utility — escape for HTML attributes                               */
    /* ------------------------------------------------------------------ */

    escAttr: function (str) {
        if (typeof str !== 'string') {
            return '';
        }
        return str
            .replace(/&/g,  '&amp;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;');
    }
};

jQuery(document).ready(function () {
    KDMCities.init();
});
