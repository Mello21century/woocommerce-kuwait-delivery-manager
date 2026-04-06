/**
 * KDM CSV Import Wizard
 *
 * Handles the two-step import flow:
 *   Step 1 - Upload a CSV file
 *   Step 2 - Map columns and execute the import
 */

/* global jQuery, kdmImportData */

var KDMImport = {

    importType: 'cities',
    headers: [],
    preview: [],

    /* --------------------------------------------------------
     * Initialisation
     * ------------------------------------------------------ */

    init: function () {
        this.importType = jQuery('input[name="kdm_import_type"]:checked').val() || 'cities';
        this.bindEvents();
        this.onTypeChange();
    },

    bindEvents: function () {
        var self = this;

        jQuery('input[name="kdm_import_type"]').on('change', function () {
            self.importType = jQuery(this).val();
            self.onTypeChange();
        });

        jQuery('#kdm-import-upload-btn').on('click', function (e) {
            e.preventDefault();
            self.onUploadClick();
        });

        jQuery('#kdm-import-back-btn').on('click', function (e) {
            e.preventDefault();
            self.onBackClick();
        });

        jQuery('#kdm-import-execute-btn').on('click', function (e) {
            e.preventDefault();
            self.onExecuteClick();
        });

        jQuery('#kdm-mapping-body').on('change', 'select', function () {
            self.onMappingChange();
        });
    },

    /* --------------------------------------------------------
     * Step 1 helpers
     * ------------------------------------------------------ */

    onTypeChange: function () {
        if (this.importType === 'areas') {
            jQuery('#kdm-import-area-defaults').show();
        } else {
            jQuery('#kdm-import-area-defaults').hide();
        }
    },

    onUploadClick: function () {
        var self = this;
        var fileInput = jQuery('#kdm-csv-file');

        if (!fileInput.val()) {
            this.showNotice(kdmImportData.strings.selectFile, 'error');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'kdm_import_upload');
        formData.append('nonce', kdmImportData.nonce);
        formData.append('csv_file', fileInput[0].files[0]);

        var $btn = jQuery('#kdm-import-upload-btn');
        $btn.prop('disabled', true).text(kdmImportData.strings.uploading);

        jQuery.ajax({
            url: kdmImportData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    self.showStep2(response.data);
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : kdmImportData.strings.error;
                    self.showNotice(msg, 'error');
                }
            },
            error: function () {
                self.showNotice(kdmImportData.strings.error, 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text(kdmImportData.strings.importBtn);
            }
        });
    },

    /* --------------------------------------------------------
     * Step 2 – Build mapping table
     * ------------------------------------------------------ */

    showStep2: function (data) {
        this.headers = data.headers || [];
        this.preview = data.preview || [];

        jQuery('#kdm-import-step1').hide();
        jQuery('#kdm-import-step2').show();
        jQuery('#kdm-import-results').hide();

        var $body = jQuery('#kdm-mapping-body').empty();

        for (var i = 0; i < this.headers.length; i++) {
            $body.append(this.buildMappingRow(i, this.headers[i]));
        }

        jQuery('#kdm-city-resolve-wrap').hide();
    },

    buildMappingRow: function (index, header) {
        var previewValue = '';
        if (this.preview.length > 0 && typeof this.preview[0][index] !== 'undefined') {
            previewValue = this.preview[0][index];
        }

        var options = this.getMappingOptions();
        var optionsHtml = '';
        for (var i = 0; i < options.length; i++) {
            optionsHtml += '<option value="' + this.escHtml(options[i].value) + '">'
                + this.escHtml(options[i].label) + '</option>';
        }

        return '<tr>'
            + '<td>' + this.escHtml(header) + '</td>'
            + '<td><select class="kdm-mapping-select" data-col="' + index + '">'
            + optionsHtml
            + '</select></td>'
            + '<td><code>' + this.escHtml(previewValue) + '</code></td>'
            + '</tr>';
    },

    getMappingOptions: function () {
        var s = kdmImportData.strings;
        var opts = [
            {value: '', label: s.skipColumn},
            {value: 'name_en', label: s.nameEn},
            {value: 'name_ar', label: s.nameAr}
        ];

        if (this.importType === 'areas') {
            opts.push({value: 'delivery_price', label: s.deliveryPrice});
            opts.push({value: 'express_fee', label: s.expressFee});
            opts.push({value: 'delivery_notes', label: s.deliveryNotes});
            opts.push({value: 'minimum_order', label: s.minimumOrder});
            opts.push({value: 'free_minimum_order', label: s.freeMinimumOrder});
            opts.push({value: 'city_ref', label: s.cityRef});
        }

        return opts;
    },

    onMappingChange: function () {
        if (this.importType !== 'areas') {
            jQuery('#kdm-city-resolve-wrap').hide();
            return;
        }

        var hasCityRef = false;
        jQuery('#kdm-mapping-body select').each(function () {
            if (jQuery(this).val() === 'city_ref') {
                hasCityRef = true;
                return false;
            }
        });

        if (hasCityRef) {
            jQuery('#kdm-city-resolve-wrap').show();
        } else {
            jQuery('#kdm-city-resolve-wrap').hide();
        }
    },

    /* --------------------------------------------------------
     * Navigation
     * ------------------------------------------------------ */

    onBackClick: function () {
        jQuery('#kdm-import-step2').hide();
        jQuery('#kdm-import-results').hide();
        jQuery('#kdm-import-step1').show();
    },

    /* --------------------------------------------------------
     * Execute import
     * ------------------------------------------------------ */

    onExecuteClick: function () {
        var self = this;
        var mapping = this.collectMapping();

        var postData = {
            action: 'kdm_import_execute',
            nonce: kdmImportData.nonce,
            country_iso2: jQuery('#kdm-import-country').val(),
            import_type: this.importType,
            column_mapping: JSON.stringify(mapping),
            base_delivery_price: jQuery('#kdm-base-price').val() || '',
            base_express_fee: jQuery('#kdm-base-express').val() || '',
            base_minimum_order: jQuery('#kdm-base-minimum').val() || '',
            base_free_minimum_order: jQuery('#kdm-base-free-minimum').val() || '',
            city_resolve_mode: jQuery('#kdm-city-resolve-mode').val() || ''
        };

        var $btn = jQuery('#kdm-import-execute-btn');
        $btn.prop('disabled', true).text(kdmImportData.strings.importing);

        jQuery.ajax({
            url: kdmImportData.ajaxUrl,
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    self.showResults(response.data);
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : kdmImportData.strings.error;
                    self.showNotice(msg, 'error');
                }
            },
            error: function () {
                self.showNotice(kdmImportData.strings.error, 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text(kdmImportData.strings.importBtn);
            }
        });
    },

    collectMapping: function () {
        var mapping = {};

        jQuery('#kdm-mapping-body select').each(function () {
            var $select = jQuery(this);
            var field = $select.val();
            var colIdx = $select.data('col');

            if (field) {
                mapping[colIdx] = field;
            }
        });

        return mapping;
    },

    /* --------------------------------------------------------
     * Results display
     * ------------------------------------------------------ */

    showResults: function (data) {
        var html = '';

        if (data.message) {
            html += '<p>' + this.escHtml(data.message) + '</p>';
        }

        if (typeof data.success_count !== 'undefined') {
            html += '<p style="color:#46b450;font-weight:600;">'
                + '&#10003; ' + this.escHtml(String(data.success_count)) + ' imported successfully'
                + '</p>';
        }

        if (data.error_count && data.error_count > 0) {
            html += '<p style="color:#dc3232;font-weight:600;">'
                + '&#10007; ' + this.escHtml(String(data.error_count)) + ' errors'
                + '</p>';
        }

        if (data.errors && data.errors.length > 0) {
            html += '<ul style="color:#dc3232;">';
            for (var i = 0; i < data.errors.length; i++) {
                html += '<li>' + this.escHtml(data.errors[i]) + '</li>';
            }
            html += '</ul>';
        }

        jQuery('#kdm-import-results-content').html(html);
        jQuery('#kdm-import-results').show();
    },

    /* --------------------------------------------------------
     * Utilities
     * ------------------------------------------------------ */

    showNotice: function (msg, type) {
        var cssClass = (type === 'error')
            ? 'notice notice-error'
            : 'notice notice-success';

        var $notice = jQuery('<div class="' + cssClass + ' is-dismissible"><p>'
            + this.escHtml(msg)
            + '</p><button type="button" class="notice-dismiss"></button></div>');

        $notice.find('.notice-dismiss').on('click', function () {
            $notice.fadeOut(200, function () {
                $notice.remove();
            });
        });

        jQuery('.wrap').find('> .notice').remove();
        jQuery('.wrap h1').first().after($notice);
    },

    escHtml: function (str) {
        if (typeof str !== 'string') {
            return '';
        }
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

jQuery(document).ready(function () {
    KDMImport.init();
});
