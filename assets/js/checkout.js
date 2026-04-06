/**
 * Kuwait Delivery Manager — Checkout JavaScript  v1.3.0
 *
 * Responsibilities:
 *   1. Show/hide KDM fields based on billing_country (dynamic — any country with cities)
 *   2. Custom searchable combo dropdown — optgroups per city, real-time search
 *   3. Area selection — updates hidden inputs, saves to WC session, triggers totals refresh
 *   4. Delivery type field — shown only when selected area has express surcharge
 *   5. Live price preview panel
 *   6. Session restore — re-populates state after WC updated_checkout DOM replacement
 */
(function ($) {
    'use strict';

    var KDMCheckout = {

        $wrap:     null,
        $trigger:  null,
        $panel:    null,
        $search:   null,
        $list:     null,
        $areaIn:   null,
        $cityIn:   null,
        $type:     null,
        $country:  null,
        $price:    null,

        isOpen: false,

        // -------------------------------------------------------------------
        // Init
        // -------------------------------------------------------------------

        init: function () {
            this.$wrap    = $('#billing_area_id_kdm_field');
            this.$trigger = $('#kdm-combo-trigger');
            this.$panel   = $('#kdm-combo-panel');
            this.$search  = this.$panel.find('.kdm-combo-search');
            this.$list    = this.$panel.find('.kdm-combo-list');
            this.$areaIn  = $('#billing_area_id_kdm');
            this.$cityIn  = $('#billing_city_id_kdm');
            this.$type    = $('#billing_delivery_type_kdm');
            this.$country = $('#billing_country');

            if ( ! this.$wrap.length ) { return; }

            this.injectPricePreview();
            this.bindEvents();

            if ( kdmCheckout.savedDeliveryType && this.$type.length ) {
                this.$type.val( kdmCheckout.savedDeliveryType );
            }

            this.checkCountry( false );
            this.applyItemStates();

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

            this.$country
                .off('change.kdm')
                .on('change.kdm', function () { self.checkCountry( true ); });

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

            this.$search
                .off('input.kdm')
                .on('input.kdm', function () { self.filterItems( $(this).val() ); });

            this.$search
                .off('keydown.kdm')
                .on('keydown.kdm', function (e) {
                    if ( e.key === 'Escape' ) { self.closePanel(); }
                    e.stopPropagation();
                });

            this.$list
                .off('click.kdm')
                .on('click.kdm', '.kdm-combo-item', function () {
                    if ( $(this).hasClass('kdm-disabled') ) { return; }
                    self.selectItem( $(this) );
                });

            this.$type
                .off('change.kdm')
                .on('change.kdm', function () { self.persistAndRefresh(); });

            $(document)
                .off('click.kdm-outside')
                .on('click.kdm-outside', function (e) {
                    if ( ! $(e.target).closest('#billing_area_id_kdm_field').length ) {
                        self.closePanel();
                    }
                });
        },

        // -------------------------------------------------------------------
        // Country show/hide — dynamic check against countriesWithCities
        // -------------------------------------------------------------------

        checkCountry: function ( clearOnChange ) {
            var country    = this.$country.val();
            var hasDelivery = (
                kdmCheckout.countriesWithCities &&
                kdmCheckout.countriesWithCities.indexOf( country ) !== -1
            );

            if ( hasDelivery ) {
                this.$wrap.show();
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
                    this.resetSelection();
                    this.saveSession( 0, 0, 'normal' );
                }
            }
        },

        resetSelection: function () {
            this.$areaIn.val('');
            this.$cityIn.val('');
            this.$list.find('.kdm-combo-item').removeClass('kdm-selected').attr('aria-selected', 'false');
            this.$trigger.find('.kdm-combo-placeholder')
                .text( kdmCheckout.strings.selectArea )
                .removeClass('has-value');
        },

        // -------------------------------------------------------------------
        // Apply disabled / free-delivery states to all combo items
        // -------------------------------------------------------------------

        applyItemStates: function () {
            var cartSubtotal = kdmCheckout.cartSubtotal || 0;
            var S            = kdmCheckout.strings;
            var currency     = S.currency || '';

            this.$list.find('.kdm-combo-item').each(function () {
                var $item     = $(this);
                var minOrder  = parseFloat( $item.data('minimum')     || 0 );
                var freeMin   = parseFloat( $item.data('freeminimum') || 0 );
                var basePrice = parseFloat( $item.data('price')       || 0 );

                var $priceEl = $item.find('.kdm-item-price');

                // Reset any previously applied states.
                $item.removeClass('kdm-disabled').removeAttr('aria-disabled');
                $priceEl.find('.kdm-badge-free, .kdm-free-hint, .kdm-min-order-note').remove();
                $priceEl.find('.kdm-price-text').each(function () {
                    $(this).replaceWith( $(this).text() );
                });

                // Disabled: cart is below minimum_order threshold.
                if ( minOrder > 0 && cartSubtotal < minOrder ) {
                    $item.addClass('kdm-disabled').attr('aria-disabled', 'true');
                    var note = S.minOrderNote
                        ? S.minOrderNote.replace( '%s', minOrder.toFixed(3) + ' ' + currency )
                        : '(' + minOrder.toFixed(3) + ' ' + currency + ')';
                    $priceEl.append( '<span class="kdm-min-order-note">' + note + '</span>' );
                    return; // No free-delivery badge on disabled items.
                }

                // Free delivery badge or hint.
                if ( freeMin > 0 ) {
                    if ( cartSubtotal >= freeMin ) {
                        $priceEl.html( '<span class="kdm-badge-free">' + ( S.freeDelivery || 'Free' ) + '</span>' );
                    } else {
                        var hint = S.freeOver
                            ? S.freeOver.replace( '%s', freeMin.toFixed(3) + ' ' + currency )
                            : 'Free on orders over ' + freeMin.toFixed(3) + ' ' + currency;
                        $priceEl.append( '<span class="kdm-free-hint">' + hint + '</span>' );
                    }
                }
            });
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
            var cityId  = $item.data('city');
            var name    = $item.data('name');

            this.$list.find('.kdm-combo-item')
                .removeClass('kdm-selected')
                .attr('aria-selected', 'false');
            $item.addClass('kdm-selected').attr('aria-selected', 'true');

            this.$trigger.find('.kdm-combo-placeholder')
                .text( name )
                .addClass('has-value');

            this.$areaIn.val( areaId );
            this.$cityIn.val( cityId );

            this.closePanel();
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
            var $sel = this.$list.find('.kdm-selected');
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
                feeLabel  = kdmCheckout.strings.priceExpress;
            } else {
                feeAmount = price;
                feeLabel  = kdmCheckout.strings.priceNormal;
            }

            this.$price
                .html(
                    '<span class="kdm-preview-label">' + feeLabel + ' — ' + this.escHtml(name) + '</span>' +
                    '<span class="kdm-preview-amount">' + feeAmount.toFixed(3) + ' ' + this.escHtml(kdmCheckout.strings.currency) + '</span>'
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
            var cityId = this.$cityIn.val() || 0;
            var type   = ( this.$type.val() || 'normal' );

            this.updatePricePreview();
            this.saveSession( areaId, cityId, type, function () {
                $( document.body ).trigger('update_checkout');
            });
        },

        saveSession: function ( areaId, cityId, type, callback ) {
            $.ajax({
                url:  kdmCheckout.ajaxUrl,
                type: 'POST',
                data: {
                    action:        'kdm_set_area_session',
                    area_id:       areaId,
                    city_id:       cityId,
                    delivery_type: type,
                    nonce:         kdmCheckout.nonce
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
    $( document.body ).on('updated_checkout', function () { KDMCheckout.init(); });

})(jQuery);
