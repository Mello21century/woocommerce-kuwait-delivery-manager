/**
 * Kuwait Delivery Manager — Checkout JavaScript  v1.3.0
 *
 * Responsibilities:
 *   1. Show/hide the KDM fields when billing_country changes to/from Kuwait (KW)
 *   2. Custom searchable combo dropdown — optgroups per governorate, real-time search
 *   3. Area selection — updates hidden inputs, saves to WC session, triggers totals refresh
 *   4. Delivery type field — shown only when selected area has express surcharge
 *   5. Live price preview panel
 *   6. Session restore — re-populates state after WC updated_checkout DOM replacement
 */
(function ($) {
    'use strict';

    var KDMCheckout = {

        $wrap:     null, // .kdm-combo-wrap (the whole field wrapper)
        $trigger:  null, // .kdm-combo-trigger
        $panel:    null, // .kdm-combo-panel
        $search:   null, // .kdm-combo-search input
        $list:     null, // .kdm-combo-list
        $areaIn:   null, // hidden input: billing_area_id_kdm
        $govIn:    null, // hidden input: billing_governorate_kdm
        $type:     null, // select: billing_delivery_type_kdm
        $country:  null, // select: billing_country
        $price:    null, // #kdm-price-preview div

        isOpen: false,

        // -------------------------------------------------------------------
        // Init — called on document.ready and on WC's updated_checkout
        // -------------------------------------------------------------------

        init: function () {
            this.$wrap    = $('#billing_area_id_kdm_field');
            this.$trigger = $('#kdm-combo-trigger');
            this.$panel   = $('#kdm-combo-panel');
            this.$search  = this.$panel.find('.kdm-combo-search');
            this.$list    = this.$panel.find('.kdm-combo-list');
            this.$areaIn  = $('#billing_area_id_kdm');
            this.$govIn   = $('#billing_governorate_kdm');
            this.$type    = $('#billing_delivery_type_kdm');
            this.$country = $('#billing_country');

            if ( ! this.$wrap.length ) { return; }

            this.injectPricePreview();
            this.bindEvents();

            // Restore delivery-type from session
            if ( kdmCheckout.savedDeliveryType && this.$type.length ) {
                this.$type.val( kdmCheckout.savedDeliveryType );
            }

            // Apply country-based visibility on init
            this.checkCountry( false );

            // Restore express-type visibility if area already selected
            if ( this.$areaIn.val() ) {
                this.syncExpressVisibility( false );
            }
        },

        // -------------------------------------------------------------------
        // Price preview element
        // -------------------------------------------------------------------

        injectPricePreview: function () {
            if ( ! $('#kdm-price-preview').length ) {
                var $after = this.$type.length
                    ? this.$type.closest('.form-row')
                    : this.$wrap;
                $after.after('<div id="kdm-price-preview" style="display:none;"></div>');
            }
            this.$price = $('#kdm-price-preview');
        },

        // -------------------------------------------------------------------
        // Event binding
        // -------------------------------------------------------------------

        bindEvents: function () {
            var self = this;

            // Country change → show/hide KDM fields
            this.$country
                .off('change.kdm')
                .on('change.kdm', function () { self.checkCountry( true ); });

            // Combo trigger click/keyboard
            this.$trigger
                .off('click.kdm keydown.kdm')
                .on('click.kdm', function () { self.togglePanel(); })
                .on('keydown.kdm', function (e) {
                    if ( e.key === 'Enter' || e.key === ' ' ) {
                        e.preventDefault();
                        self.togglePanel();
                    } else if ( e.key === 'Escape' ) {
                        self.closePanel();
                    }
                });

            // Search input filtering
            this.$search
                .off('input.kdm')
                .on('input.kdm', function () { self.filterItems( $(this).val() ); });

            // Prevent the search keydown from bubbling to checkout form
            this.$search
                .off('keydown.kdm')
                .on('keydown.kdm', function (e) {
                    if ( e.key === 'Escape' ) { self.closePanel(); }
                    e.stopPropagation();
                });

            // Item selection
            this.$list
                .off('click.kdm')
                .on('click.kdm', '.kdm-combo-item', function () { self.selectItem( $(this) ); });

            // Delivery type change
            this.$type
                .off('change.kdm')
                .on('change.kdm', function () { self.persistAndRefresh(); });

            // Close panel on outside click
            $(document)
                .off('click.kdm-outside')
                .on('click.kdm-outside', function (e) {
                    if ( ! $(e.target).closest('#billing_area_id_kdm_field').length ) {
                        self.closePanel();
                    }
                });
        },

        // -------------------------------------------------------------------
        // Country show/hide
        // -------------------------------------------------------------------

        checkCountry: function ( clearOnChange ) {
            var country  = this.$country.val();
            var isKuwait = ( country === 'KW' );

            if ( isKuwait ) {
                this.$wrap.show();
                // Restore express-type visibility if an area is already selected
                if ( this.$areaIn.val() ) {
                    this.syncExpressVisibility( false );
                } else if ( this.$type.length ) {
                    this.$type.closest('.form-row').hide();
                }
            } else {
                this.$wrap.hide();
                if ( this.$type.length ) {
                    this.$type.closest('.form-row').hide();
                }
                this.hidePricePreview();
                this.closePanel();

                if ( clearOnChange ) {
                    // Reset selection and clear session
                    this.resetSelection();
                    this.saveSession( 0, '', 'normal' );
                }
            }
        },

        resetSelection: function () {
            this.$areaIn.val('');
            this.$govIn.val('');
            this.$list.find('.kdm-combo-item').removeClass('kdm-selected').attr('aria-selected', 'false');
            this.$trigger.find('.kdm-combo-placeholder')
                .text( kdmCheckout.strings.selectArea )
                .removeClass('has-value');
        },

        // -------------------------------------------------------------------
        // Panel open / close / toggle
        // -------------------------------------------------------------------

        togglePanel: function () {
            this.isOpen ? this.closePanel() : this.openPanel();
        },

        openPanel: function () {
            this.$panel.show();
            this.$trigger
                .addClass('kdm-combo-open')
                .attr('aria-expanded', 'true');
            this.$search.val('').trigger('focus');
            this.filterItems('');
            this.isOpen = true;

            // Scroll selected item into view
            var $sel = this.$list.find('.kdm-selected');
            if ( $sel.length ) {
                var top = $sel.position().top + this.$list.scrollTop() - 60;
                this.$list.scrollTop( Math.max(0, top) );
            }
        },

        closePanel: function () {
            this.$panel.hide();
            this.$trigger
                .removeClass('kdm-combo-open')
                .attr('aria-expanded', 'false');
            this.isOpen = false;
        },

        // -------------------------------------------------------------------
        // Search filtering
        // -------------------------------------------------------------------

        filterItems: function ( term ) {
            var query      = term.toLowerCase().trim();
            var hasResults = false;

            this.$list.find('.kdm-combo-group').each(function () {
                var $group  = $(this);
                var visible = 0;

                $group.find('.kdm-combo-item').each(function () {
                    var name = $(this).data('name') || $(this).text();
                    var show = ! query || name.toLowerCase().indexOf(query) > -1;
                    $(this).toggle( show );
                    if ( show ) { visible++; }
                });

                $group.toggle( visible > 0 );
                if ( visible ) { hasResults = true; }
            });

            this.$panel.find('.kdm-combo-no-results').toggle( ! hasResults );
        },

        // -------------------------------------------------------------------
        // Item selection
        // -------------------------------------------------------------------

        selectItem: function ($item) {
            var areaId  = $item.data('value');
            var govKey  = $item.data('gov');
            var name    = $item.data('name');
            var price   = parseFloat( $item.data('price')   || 0 );
            var express = parseFloat( $item.data('express') || 0 );

            // Update combo UI
            this.$list.find('.kdm-combo-item')
                .removeClass('kdm-selected')
                .attr('aria-selected', 'false');
            $item.addClass('kdm-selected').attr('aria-selected', 'true');

            this.$trigger.find('.kdm-combo-placeholder')
                .text( name )
                .addClass('has-value');

            // Populate hidden inputs
            this.$areaIn.val( areaId );
            this.$govIn.val( govKey );

            this.closePanel();

            // Sync delivery-type visibility and persist
            this.syncExpressVisibility( true );
        },

        // -------------------------------------------------------------------
        // Express delivery type field
        // -------------------------------------------------------------------

        syncExpressVisibility: function ( triggerPersist ) {
            if ( ! this.$type.length ) {
                if ( triggerPersist ) { this.persistAndRefresh(); }
                return;
            }

            var $sel    = this.$list.find('.kdm-selected');
            var express = parseFloat( $sel.data('express') || 0 );
            var $typeRow = this.$type.closest('.form-row');

            if ( express > 0 ) {
                $typeRow.slideDown( 180 );
            } else {
                this.$type.val('normal');
                $typeRow.slideUp( 180 );
            }

            if ( triggerPersist ) { this.persistAndRefresh(); }
        },

        // -------------------------------------------------------------------
        // Price preview
        // -------------------------------------------------------------------

        updatePricePreview: function () {
            var $sel    = this.$list.find('.kdm-selected');
            if ( ! $sel.length || ! $sel.data('value') ) {
                this.hidePricePreview();
                return;
            }

            var name    = $sel.data('name');
            var price   = parseFloat( $sel.data('price')   || 0 );
            var express = parseFloat( $sel.data('express') || 0 );
            var type    = this.$type.val() || 'normal';

            var feeAmount, feeLabel;
            if ( 'express' === type && express > 0 ) {
                feeAmount = price + express;
                feeLabel  = '⚡ ' + kdmCheckout.strings.priceExpress;
            } else {
                feeAmount = price;
                feeLabel  = kdmCheckout.strings.priceNormal;
            }

            this.$price
                .html(
                    '<span class="kdm-preview-label">' + feeLabel + ' — ' + this.escHtml(name) + '</span>' +
                    '<span class="kdm-preview-amount">' + feeAmount.toFixed(3) + ' ' + kdmCheckout.strings.kwd + '</span>'
                )
                .show();
        },

        hidePricePreview: function () {
            if ( this.$price ) { this.$price.hide(); }
        },

        // -------------------------------------------------------------------
        // Session + WC refresh
        // -------------------------------------------------------------------

        persistAndRefresh: function () {
            var areaId = this.$areaIn.val() || 0;
            var govKey = this.$govIn.val()  || '';
            var type   = ( this.$type.val() || 'normal' );

            this.updatePricePreview();
            this.saveSession( areaId, govKey, type, function () {
                $( document.body ).trigger('update_checkout');
            });
        },

        saveSession: function ( areaId, govKey, type, callback ) {
            $.ajax({
                url:  kdmCheckout.ajaxUrl,
                type: 'POST',
                data: {
                    action:          'kdm_set_area_session',
                    area_id:         areaId,
                    governorate_key: govKey,
                    delivery_type:   type,
                    nonce:           kdmCheckout.nonce
                },
                success: function () {
                    if ( typeof callback === 'function' ) { callback(); }
                }
            });
        },

        // -------------------------------------------------------------------
        // Utilities
        // -------------------------------------------------------------------

        escHtml: function (s) {
            return $('<div>').text( String(s) ).html();
        }
    };

    // -----------------------------------------------------------------------
    // Bootstrap
    // -----------------------------------------------------------------------
    $( document ).ready(function () { KDMCheckout.init(); });

    // Re-init after WooCommerce rebuilds the checkout DOM on every totals refresh
    $( document.body ).on('updated_checkout', function () { KDMCheckout.init(); });

})(jQuery);
