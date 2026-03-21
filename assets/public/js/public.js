/**
 * RentAFleet — Public JavaScript
 *
 * @package RentAFleet
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    var RAF = window.RAF || {};
    var api = rafPublic.restUrl;
    var nonce = rafPublic.restNonce;

    /* ═══════════════════════════════════════════════
       Helpers
       ═══════════════════════════════════════════════ */

    function apiGet(endpoint, data) {
        return $.ajax({
            url: api + endpoint,
            method: 'GET',
            data: data || {},
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            }
        });
    }

    function apiPost(endpoint, data) {
        return $.ajax({
            url: api + endpoint,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            }
        });
    }

    function formatPrice(amount) {
        var symbol = rafPublic.currency || '$';
        return symbol + parseFloat(amount).toFixed(2);
    }

    /**
     * Check if the selected pickup date+time is too soon based on the
     * minimum advance booking hours configured in plugin settings.
     *
     * @param {string} pickupDate  Format 'YYYY-MM-DD'.
     * @param {string} pickupTime  Format 'HH:MM' (defaults to '00:00').
     * @return {boolean} True if the pickup is too soon.
     */
    function isTooSoon(pickupDate, pickupTime) {
        var minHours = parseInt(rafPublic.minAdvanceHours || 24, 10);
        if (minHours <= 0) return false;
        if (!pickupDate) return false;

        var now = new Date(rafPublic.serverNow.replace(' ', 'T'));
        var pickupStr = pickupDate + 'T' + (pickupTime || '00:00') + ':00';
        var pickup = new Date(pickupStr);

        if (isNaN(pickup.getTime())) return false;

        var diffHours = (pickup.getTime() - now.getTime()) / (1000 * 60 * 60);
        return diffHours < minHours;
    }

    /* ═══════════════════════════════════════════════
       1. Search Form
       ═══════════════════════════════════════════════ */

    RAF.Search = {
        init: function () {
            var $wrap = $('.raf-search-form-wrap');
            if (!$wrap.length) return;

            $wrap.each(function () {
                var $w = $(this);
                RAF.Search.bind($w);
            });
        },

        bind: function ($wrap) {
            var $form = $wrap.find('.raf-search-form');
            var $results = $wrap.find('.raf-search-results');
            var $loading = $wrap.find('.raf-loading');
            var $grid = $wrap.find('.raf-results-grid');
            var $noResults = $wrap.find('.raf-no-results');
            var $title = $wrap.find('.raf-results-title');

            // Different dropoff toggle
            $wrap.find('#raf-different-dropoff').on('change', function () {
                $wrap.find('.raf-field-dropoff-location').toggle(this.checked);
            });

            // Hide advance-booking error when user changes date/time
            $wrap.find('[name="pickup_date"], [name="pickup_time"]').on('change', function () {
                $wrap.find('.raf-search-error').hide();
            });

            // Form submit
            $form.on('submit', function (e) {
                e.preventDefault();
                var redirect = $form.data('redirect');

                if (redirect) {
                    window.location.href = redirect + '?' + $form.serialize();
                    return;
                }

                var data = {
                    pickup_date: $form.find('[name="pickup_date"]').val(),
                    dropoff_date: $form.find('[name="dropoff_date"]').val(),
                    location_id: $form.find('[name="pickup_location_id"]').val(),
                    category_id: $form.find('[name="category_id"]').val() || 0
                };

                if (!data.pickup_date || !data.dropoff_date) {
                    alert(rafPublic.i18n.select_dates);
                    return;
                }

                // Minimum advance booking check
                var pickupTime = $form.find('[name="pickup_time"]').val() || '00:00';
                var $searchError = $form.find('.raf-search-error');
                if (!$searchError.length) {
                    $searchError = $('<div class="raf-search-error"></div>');
                    $form.find('.raf-search-btn, button[type="submit"]').first().before($searchError);
                }
                if (isTooSoon(data.pickup_date, pickupTime)) {
                    $searchError.text(rafPublic.minAdvanceMsg).show();
                    return;
                }
                $searchError.hide();

                $form.hide();
                $results.hide();
                $loading.show();

                apiGet('search', data)
                    .done(function (res) {
                        var vehicles = res.vehicles || res.data || [];
                        $loading.hide();

                        if (!vehicles.length) {
                            $noResults.show();
                            $results.show();
                            $grid.empty();
                            $title.text('');
                            return;
                        }

                        $noResults.hide();
                        $title.text(vehicles.length + ' bike' + (vehicles.length !== 1 ? 's' : '') + ' found');
                        $grid.empty();

                        $.each(vehicles, function (i, v) {
                            $grid.append(RAF.Search.vehicleCard(v));
                        });

                        $results.show();
                    })
                    .fail(function () {
                        $loading.hide();
                        $form.show();
                        alert(rafPublic.i18n.error || 'An error occurred.');
                    });
            });

            // Modify search
            $wrap.find('.raf-modify-search').on('click', function () {
                $results.hide();
                $form.show();
            });
        },

        /**
         * Bind a change handler on a location select to show/hide a fee notice.
         *
         * @param {jQuery} $wrap  The search form wrapper.
         * @param {string} sel    Selector for the <select>.
         * @param {string} cls    CSS modifier class suffix for the notice element.
         */
        bindLocationFeeNotice: function ($wrap, sel, cls) {
            var $select = $wrap.find(sel);
            if (!$select.length) return;

            // Create the notice element and insert it after the select
            var $notice = $('<div class="raf-location-fee-notice raf-location-fee-notice--' + cls + '"></div>');
            $select.after($notice);

            $select.on('change', function () {
                var $opt = $(this).find('option:selected');
                var isPickup = cls === 'pickup-fee';
                var fee = parseFloat($opt.data(isPickup ? 'pickup-fee' : 'dropoff-fee')) || 0;

                if (fee > 0) {
                    var label = isPickup ? 'Pickup fee' : 'Drop-off fee';
                    $notice.text(label + ': ' + formatPrice(fee)).show();
                } else {
                    $notice.hide().text('');
                }
            });
        },

        vehicleCard: function (v) {
            var imgUrl = v.image_url || v.image || '';
            var img = imgUrl
                ? '<img src="' + imgUrl + '" alt="' + v.name + '" loading="lazy">'
                : '<div class="raf-vehicle-no-image"><span class="dashicons dashicons-motorcycle"></span></div>';

            var price = v.daily_rate > 0
                ? '<div class="raf-vehicle-price"><span class="raf-price-amount">' + formatPrice(v.daily_rate) + '</span><span class="raf-price-period">/day</span></div>'
                : '';

            return '<div class="raf-vehicle-card">' +
                '<div class="raf-vehicle-image">' + img + '</div>' +
                '<div class="raf-vehicle-info">' +
                '<h3 class="raf-vehicle-name">' + v.name + '</h3>' +
                '<div class="raf-vehicle-specs">' + RAF.Search.specs(v) + '</div>' +
                '</div>' +
                '<div class="raf-vehicle-price-action">' + price +
                '<button type="button" class="raf-btn raf-btn-primary raf-book-btn"' +
                ' data-vehicle-id="' + v.id + '"' +
                ' data-vehicle-name="' + (v.name || '').replace(/"/g, '&quot;') + '"' +
                ' data-daily-rate="' + (v.daily_rate || 0) + '"' +
                ' data-image="' + (imgUrl || '').replace(/"/g, '&quot;') + '"' +
                '>Book Now</button>' +
                '</div></div>';
        },

        specs: function (v) {
            var s = '';
            if (v.bike_type) s += '<span class="raf-spec">' + v.bike_type + '</span>';
            if (v.engine_cc) s += '<span class="raf-spec">' + v.engine_cc + 'cc</span>';
            return s;
        },

        /**
         * Build a selectable bike card for Step 2 of booking flow.
         */
        bikeCard: function (v) {
            var imgUrl = v.image_url || v.image || '';
            var img = imgUrl
                ? '<img src="' + imgUrl + '" alt="' + v.name + '" loading="lazy">'
                : '<div class="raf-vehicle-no-image"><span class="dashicons dashicons-motorcycle"></span></div>';

            var price = v.daily_rate > 0
                ? '<div class="raf-bike-card-price">' + formatPrice(v.daily_rate) + '<span>/day</span></div>'
                : '';

            return '<div class="raf-bike-card" data-vehicle-id="' + v.id + '">' +
                '<div class="raf-bike-card-image">' + img + '</div>' +
                '<div class="raf-bike-card-body">' +
                '<h4 class="raf-bike-card-name">' + v.name + '</h4>' +
                '<div class="raf-bike-card-specs">' + RAF.Search.specs(v) + '</div>' +
                price +
                '</div>' +
                '</div>';
        }
    };

    /* ═══════════════════════════════════════════════
       2. Vehicle Filter
       ═══════════════════════════════════════════════ */

    RAF.VehicleFilter = {
        init: function () {
            $('.raf-vehicles-filter').on('click', '.raf-filter-btn', function () {
                var $btn = $(this);
                var cat = $btn.data('category');
                var $wrap = $btn.closest('.raf-vehicles-wrap');

                $btn.siblings().removeClass('active');
                $btn.addClass('active');

                if (cat === 'all') {
                    $wrap.find('.raf-vehicle-card').show();
                } else {
                    $wrap.find('.raf-vehicle-card').each(function () {
                        $(this).toggle($(this).data('category') == cat);
                    });
                }
            });
        }
    };

    /* ═══════════════════════════════════════════════
       3. Booking Form (5-step bike flow)
       ═══════════════════════════════════════════════ */

    RAF.Booking = {
        currentStep: 1,
        totalSteps: 5,
        selectedBike: null,
        coupon: null,

        init: function () {
            var $wrap = $('.raf-booking-wrap');
            if (!$wrap.length) return;

            this.$wrap = $wrap;
            this.$form = $wrap.find('.raf-booking-form');
            this.bindStepNav();
            this.bindBikeSelection();
            this.bindExtras();
            this.bindInsurance();
            this.bindCoupon();
            this.bindConfirm();
            this.showStep(1);
        },

        bindStepNav: function () {
            var self = this;

            this.$wrap.on('click', '.raf-next-step', function (e) {
                e.preventDefault();
                if (self.validateStep(self.currentStep)) {
                    // Step 1 → 2: search available bikes
                    if (self.currentStep === 1) {
                        self.searchBikes();
                        return;
                    }
                    // Step 4 → 5: populate review
                    if (self.currentStep === 4) {
                        self.populateReview();
                    }
                    self.goToStep(self.currentStep + 1);
                }
            });

            this.$wrap.on('click', '.raf-prev-step', function (e) {
                e.preventDefault();
                self.goToStep(self.currentStep - 1);
            });
        },

        goToStep: function (step) {
            if (step < 1 || step > this.totalSteps) return;
            this.currentStep = step;
            this.showStep(step);
            if (step >= 3) {
                this.updatePrice();
            }
        },

        showStep: function (step) {
            this.$wrap.find('.raf-booking-step-content[data-step]').hide();
            this.$wrap.find('.raf-booking-step-content[data-step="' + step + '"]').show();

            // Update step indicators
            this.$wrap.find('.raf-step').each(function () {
                var s = parseInt($(this).data('step'), 10);
                $(this).toggleClass('active', s === step)
                    .toggleClass('completed', s < step);
            });

            // Toggle nav buttons
            this.$wrap.find('.raf-prev-step').toggle(step > 1);
            this.$wrap.find('.raf-next-step').toggle(step < this.totalSteps);
            this.$wrap.find('.raf-confirm-booking').toggle(step === this.totalSteps);
        },

        validateStep: function (step) {
            if (step === 1) {
                var pickup = this.$wrap.find('[name="pickup_date"]').val();
                var dropoff = this.$wrap.find('[name="dropoff_date"]').val();
                var pickupLoc = this.$wrap.find('[name="pickup_location_id"]').val();
                var dropoffLoc = this.$wrap.find('[name="dropoff_location_id"]').val();

                if (!pickup || !dropoff) {
                    alert('Please select pick-up and return dates.');
                    return false;
                }
                if (new Date(dropoff) <= new Date(pickup)) {
                    alert('Return date must be after pick-up date.');
                    return false;
                }
                if (!pickupLoc) {
                    alert('Please select a pick-up location.');
                    return false;
                }
                if (!dropoffLoc) {
                    alert('Please select a return location.');
                    return false;
                }
                return true;
            }

            if (step === 2) {
                if (!this.$wrap.find('[name="vehicle_id"]').val()) {
                    alert('Please select a bike to continue.');
                    return false;
                }
                return true;
            }

            if (step === 4) {
                var valid = true;
                this.$wrap.find('[data-step="4"] [required]').each(function () {
                    if (!$(this).val()) {
                        $(this).focus();
                        valid = false;
                        return false;
                    }
                });
                return valid;
            }

            return true;
        },

        /* ── Step 1 → 2: search available bikes ── */
        searchBikes: function () {
            var self = this;
            var $step2 = this.$wrap.find('[data-step="2"]');
            var $grid = $step2.find('.raf-available-bikes-grid');
            var $noBikes = $step2.find('.raf-no-bikes');
            var $loading = $step2.find('.raf-loading');

            // Move to step 2 and show loading
            self.goToStep(2);
            $grid.empty().hide();
            $noBikes.hide();
            $loading.show();

            var data = {
                pickup_date: self.$wrap.find('[name="pickup_date"]').val(),
                dropoff_date: self.$wrap.find('[name="dropoff_date"]').val(),
                location_id: self.$wrap.find('[name="pickup_location_id"]').val()
            };

            apiGet('search', data)
                .done(function (res) {
                    var bikes = res.vehicles || res.data || [];
                    $loading.hide();

                    if (!bikes.length) {
                        $noBikes.show();
                        return;
                    }

                    $.each(bikes, function (i, v) {
                        $grid.append(RAF.Search.bikeCard(v));
                    });

                    $grid.show();
                })
                .fail(function () {
                    $loading.hide();
                    $noBikes.show();
                });
        },

        /* ── Step 2: bike selection ── */
        bindBikeSelection: function () {
            var self = this;

            this.$wrap.on('click', '.raf-bike-card', function () {
                var $card = $(this);
                var vehicleId = $card.data('vehicle-id');

                // Toggle selection
                self.$wrap.find('.raf-bike-card').removeClass('selected');
                $card.addClass('selected');
                self.$wrap.find('[name="vehicle_id"]').val(vehicleId);

                // Store selected bike data
                self.selectedBike = {
                    id: vehicleId,
                    name: $card.find('.raf-bike-card-name').text(),
                    image: $card.find('img').attr('src') || ''
                };
            });
        },

        bindExtras: function () {
            this.$wrap.on('change', '.raf-extra-checkbox', function () {
                var $item = $(this).closest('.raf-extra-item');
                var $qty = $item.find('.raf-extra-qty');
                if ($qty.length) {
                    $qty.toggle(this.checked);
                }
                RAF.Booking.updatePrice();
            });
        },

        bindInsurance: function () {
            this.$wrap.on('change', '.raf-insurance-checkbox', function () {
                RAF.Booking.updatePrice();
            });
        },

        bindCoupon: function () {
            this.$wrap.on('click', '.raf-apply-coupon', function (e) {
                e.preventDefault();
                var $section = $(this).closest('.raf-coupon-section');
                var code = $section.find('input[name="coupon_code"]').val();
                var $msg = $section.find('.raf-coupon-message');

                if (!code) return;

                apiPost('coupons/validate', { code: code })
                    .done(function (res) {
                        if (res.valid) {
                            $msg.html('<span style="color: #16a34a;">Coupon applied! ' + res.description + '</span>').show();
                            RAF.Booking.coupon = res;
                            RAF.Booking.updatePrice();
                        } else {
                            $msg.html('<span style="color: #dc2626;">' + (res.message || 'Invalid coupon.') + '</span>').show();
                        }
                    })
                    .fail(function () {
                        $msg.html('<span style="color: #dc2626;">Could not validate coupon.</span>').show();
                    });
            });
        },

        updatePrice: function () {
            var $summary = this.$wrap.find('.raf-summary-content');
            if (!$summary.length) return;

            var vehicleId = this.$wrap.find('[name="vehicle_id"]').val();
            var pickupDate = this.$wrap.find('[name="pickup_date"]').val();
            var dropoffDate = this.$wrap.find('[name="dropoff_date"]').val();

            if (!vehicleId || !pickupDate || !dropoffDate) return;

            var data = {
                vehicle_id: vehicleId,
                pickup_date: pickupDate,
                dropoff_date: dropoffDate,
                pickup_location_id: this.$wrap.find('[name="pickup_location_id"]').val() || 0,
                dropoff_location_id: this.$wrap.find('[name="dropoff_location_id"]').val() || 0
            };

            // Collect extras
            var extras = [];
            this.$wrap.find('.raf-extra-checkbox:checked').each(function () {
                var $item = $(this).closest('.raf-extra-item');
                var id = $(this).val();
                var qty = $item.find('.raf-extra-qty select').val() || 1;
                extras.push({ id: parseInt(id, 10), quantity: parseInt(qty, 10) });
            });
            data.extras = extras;

            // Collect insurance
            var insurance = [];
            this.$wrap.find('.raf-insurance-checkbox:checked').each(function () {
                insurance.push(parseInt($(this).val(), 10));
            });
            data.insurance = insurance;

            if (this.coupon) {
                data.coupon_code = this.coupon.code;
            }

            apiPost('price', data)
                .done(function (res) {
                    var html = '<div class="raf-price-breakdown">';
                    if (parseFloat(res.base_price) > 0) {
                        html += '<div><span>Rental (' + res.rental_days + ' day' + (res.rental_days !== 1 ? 's' : '') + ')</span><span class="raf-amount">' + formatPrice(res.base_price) + '</span></div>';
                    }
                    if (parseFloat(res.location_fees) > 0) {
                        var pickupFee  = parseFloat(res.pickup_location_fee || 0);
                        var dropoffFee = parseFloat(res.dropoff_location_fee || 0);
                        var onewayFee  = parseFloat(res.one_way_fee || 0);
                        var pickupName  = res.pickup_location_name || 'Pick-up';
                        var dropoffName = res.dropoff_location_name || 'Drop-off';

                        if (pickupFee > 0) {
                            html += '<div><span>Pick-up: ' + pickupName + '</span><span class="raf-amount">' + formatPrice(pickupFee) + '</span></div>';
                        }
                        if (dropoffFee > 0) {
                            html += '<div><span>Drop-off: ' + dropoffName + '</span><span class="raf-amount">' + formatPrice(dropoffFee) + '</span></div>';
                        }
                        if (onewayFee > 0) {
                            html += '<div><span>One-way fee</span><span class="raf-amount">' + formatPrice(onewayFee) + '</span></div>';
                        }
                        if ((pickupFee > 0 && dropoffFee > 0) || onewayFee > 0) {
                            html += '<div><span>Location fee total</span><span class="raf-amount">' + formatPrice(res.location_fees) + '</span></div>';
                        }
                    }
                    if (parseFloat(res.extras_total) > 0) {
                        html += '<div><span>Extras</span><span class="raf-amount">' + formatPrice(res.extras_total) + '</span></div>';
                    }
                    if (parseFloat(res.insurance_total) > 0) {
                        html += '<div><span>Insurance</span><span class="raf-amount">' + formatPrice(res.insurance_total) + '</span></div>';
                    }
                    if (parseFloat(res.discount_amount) > 0) {
                        html += '<div class="raf-discount-row"><span>Discount</span><span>-' + formatPrice(res.discount_amount) + '</span></div>';
                    }
                    if (parseFloat(res.tax_amount) > 0) {
                        html += '<div><span>Tax</span><span class="raf-amount">' + formatPrice(res.tax_amount) + '</span></div>';
                    }
                    html += '<div class="raf-total-row"><span>Total</span><span>' + formatPrice(res.total) + '</span></div>';
                    if (parseFloat(res.deposit_amount) > 0) {
                        html += '<div class="raf-deposit-row"><span>Deposit Due</span><span>' + formatPrice(res.deposit_amount) + '</span></div>';
                    }
                    html += '</div>';

                    $summary.html(html);
                });
        },

        /* ── Step 4 → 5: populate review section ── */
        populateReview: function () {
            var $f = this.$wrap;
            var bikeName = this.selectedBike ? this.selectedBike.name : 'Selected Bike';

            // Rental details
            var rentalHtml = '<strong>Bike:</strong> ' + bikeName + '<br>' +
                '<strong>Pick-up:</strong> ' + $f.find('[name="pickup_date"]').val() + ' at ' + $f.find('[name="pickup_time"]').val() + '<br>' +
                '<strong>Return:</strong> ' + $f.find('[name="dropoff_date"]').val() + ' at ' + $f.find('[name="dropoff_time"]').val() + '<br>' +
                '<strong>Pick-up Location:</strong> ' + $f.find('[name="pickup_location_id"] option:selected').text() + '<br>' +
                '<strong>Return Location:</strong> ' + $f.find('[name="dropoff_location_id"] option:selected').text();
            $f.find('#raf-review-rental').html(rentalHtml);

            // Customer details
            var customerHtml = '<strong>Name:</strong> ' + $f.find('[name="first_name"]').val() + ' ' + $f.find('[name="last_name"]').val() + '<br>' +
                '<strong>Email:</strong> ' + $f.find('[name="email"]').val() + '<br>' +
                '<strong>Phone:</strong> ' + $f.find('[name="phone"]').val() + '<br>' +
                '<strong>Passport:</strong> ' + $f.find('[name="passport_number"]').val() + '<br>' +
                '<strong>Citizenship:</strong> ' + $f.find('[name="citizenship"]').val();
            $f.find('#raf-review-customer').html(customerHtml);

            // Price is updated via updatePrice()
            this.updatePrice();
        },

        bindConfirm: function () {
            this.$wrap.on('click', '.raf-confirm-booking', function (e) {
                e.preventDefault();
                var $btn = $(this);

                // Validate terms checkbox
                if (!RAF.Booking.$wrap.find('[name="terms"]').is(':checked')) {
                    alert('Please agree to the Terms & Conditions.');
                    return;
                }

                // Minimum advance booking check
                var bPickupDate = RAF.Booking.$wrap.find('[name="pickup_date"]').val();
                var bPickupTime = RAF.Booking.$wrap.find('[name="pickup_time"]').val() || '00:00';
                if (isTooSoon(bPickupDate, bPickupTime)) {
                    alert(rafPublic.minAdvanceMsg);
                    return;
                }

                $btn.prop('disabled', true).text(rafPublic.i18n.processing || 'Processing...');

                var $form = RAF.Booking.$wrap.find('.raf-booking-form');
                var formData = {};

                $form.find('input, select, textarea').each(function () {
                    var name = $(this).attr('name');
                    if (!name) return;
                    if ($(this).is(':checkbox')) {
                        if ($(this).is(':checked')) {
                            formData[name] = $(this).val();
                        }
                    } else {
                        formData[name] = $(this).val();
                    }
                });

                // Extras
                var extras = [];
                RAF.Booking.$wrap.find('.raf-extra-checkbox:checked').each(function () {
                    var $item = $(this).closest('.raf-extra-item');
                    var id = $(this).val();
                    var qty = $item.find('.raf-extra-qty select').val() || 1;
                    extras.push({ id: parseInt(id, 10), quantity: parseInt(qty, 10) });
                });
                formData.extras = extras;

                // Insurance
                var insurance = [];
                RAF.Booking.$wrap.find('.raf-insurance-checkbox:checked').each(function () {
                    insurance.push(parseInt($(this).val(), 10));
                });
                formData.insurance = insurance;

                if (RAF.Booking.coupon) {
                    formData.coupon_code = RAF.Booking.coupon.code;
                }

                apiPost('bookings', formData)
                    .done(function (res) {
                        if (res.booking_number) {
                            var confirmUrl = res.confirmation_url || window.location.href;
                            if (confirmUrl.indexOf('?') > -1) {
                                confirmUrl += '&booking=' + res.booking_number;
                            } else {
                                confirmUrl += '?booking=' + res.booking_number;
                            }
                            window.location.href = confirmUrl;
                        } else {
                            alert(rafPublic.i18n.booking_success || 'Booking confirmed!');
                        }
                    })
                    .fail(function (xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'An error occurred. Please try again.';
                        alert(msg);
                        $btn.prop('disabled', false).text('Confirm Booking');
                    });
            });
        }
    };

    /* ═══════════════════════════════════════════════
       3b. Booking Modal — Quick Book from Search Results
       ═══════════════════════════════════════════════ */

    RAF.BookingModal = {
        $modal: null,
        searchContext: {},
        _bound: false,
        _currentStep: 1,

        init: function () {
            // Always bind open on document — the modal may not exist yet at
            // DOM-ready time if the shortcode is in a tab/accordion.
            this.bindOpen();
            // Bind close and submit only when modal is present.
            if ($('#raf-booking-modal').length) {
                this.$modal = $('#raf-booking-modal');
                this._bound = true;
                this.bindClose();
                this.bindSubmit();
                this.bindContinue();
            }
        },

        bindOpen: function () {
            var self = this;

            // Use event delegation so dynamically injected vehicle cards work.
            // Match both <button> and <a> variants of .raf-book-btn so either
            // shortcode output is handled.
            $(document).on('click', '.raf-book-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Re-look up modal every click — handles cases where the modal
                // wasn't in the DOM when init() ran (caching, page builders).
                var $modal = $('#raf-booking-modal');
                if (!$modal.length) return;
                self.$modal = $modal;

                // Ensure close/submit/continue are wired now that we have the modal.
                if (!self._bound) {
                    self._bound = true;
                    self.bindClose();
                    self.bindSubmit();
                    self.bindContinue();
                }

                var $btn  = $(this);
                // Search form can be anywhere on the page — don't require it
                // to be a strict ancestor of the button.
                var $wrap = $btn.closest('.raf-search-form-wrap');
                var $form = $wrap.length ? $wrap.find('.raf-search-form') : $('.raf-search-form').first();

                // Gather search context from button data attrs + form values
                self.searchContext = {
                    vehicle_id: $btn.data('vehicle-id'),
                    vehicle_name: $btn.data('vehicle-name'),
                    daily_rate: $btn.data('daily-rate'),
                    image: $btn.data('image'),
                    pickup_date: $form.find('[name="pickup_date"]').val() || '',
                    dropoff_date: $form.find('[name="dropoff_date"]').val() || '',
                    pickup_location_id: $form.find('[name="pickup_location_id"]').val() || '',
                    dropoff_location_id: $form.find('[name="dropoff_location_id"]').val() || '',
                    pickup_location_name: $form.find('[name="pickup_location_id"] option:selected').text() || '',
                    dropoff_location_name: $form.find('[name="dropoff_location_id"] option:selected').text() || ''
                };

                // Use pickup as dropoff if not set
                if (!self.searchContext.dropoff_location_id) {
                    self.searchContext.dropoff_location_id = self.searchContext.pickup_location_id;
                    self.searchContext.dropoff_location_name = self.searchContext.pickup_location_name;
                }

                // Populate modal summary
                self.$modal.find('.raf-modal-vehicle-name').text(self.searchContext.vehicle_name);
                self.$modal.find('.raf-modal-vehicle-dates').text(
                    self.searchContext.pickup_date + ' to ' + self.searchContext.dropoff_date
                );
                self.$modal.find('.raf-modal-vehicle-location').text(self.searchContext.pickup_location_name);

                if (self.searchContext.daily_rate > 0) {
                    self.$modal.find('.raf-modal-vehicle-price').text(formatPrice(self.searchContext.daily_rate) + '/day');
                }

                var $img = self.$modal.find('.raf-modal-vehicle-img');
                if (self.searchContext.image) {
                    $img.attr('src', self.searchContext.image).show();
                } else {
                    $img.hide();
                }

                // Reset form safely
                var modalForm = self.$modal.find('.raf-booking-modal-form');
                if (modalForm.length && modalForm[0].reset) {
                    modalForm[0].reset();
                }
                self.$modal.find('.raf-modal-error').hide().text('');
                self.$modal.find('.raf-modal-success').hide();
                self.$modal.find('.raf-modal-form-body').show();
                self.$modal.find('.raf-modal-confirm-btn').prop('disabled', false).text(rafPublic.i18n.book_now || 'Confirm Booking');

                self.open();
            });
        },

        /**
         * Show the modal overlay.
         *
         * Directly sets `display: flex` instead of relying on CSS class
         * specificity to override the inline `display: none`. This is
         * more robust when themes inject conflicting styles.
         */
        open: function () {
            this.$modal.css('display', 'flex').addClass('raf-modal-open');
            $('body').addClass('raf-modal-body-open');

            // Reset to step 1 — price summary loads only when reaching step 2
            this.goToStep(1);
            this.searchContext.selectedExtras = [];
            this.loadExtras();
        },

        /**
         * Navigate to a specific step in the booking modal.
         *
         * @param {number} step Step number (1, 2, or 3).
         */
        goToStep: function (step) {
            this._currentStep = step;
            var $steps = this.$modal.find('.raf-modal-step');
            var $contents = this.$modal.find('.raf-modal-step-content');

            // Update step indicators
            $steps.each(function () {
                var s = parseInt($(this).data('step'), 10);
                $(this).toggleClass('raf-modal-step--active', s === step);
                $(this).toggleClass('raf-modal-step--done', s < step);
                // Replace number with check mark for completed steps
                if (s < step) {
                    $(this).find('.raf-modal-step-num').html('&#10003;');
                } else {
                    $(this).find('.raf-modal-step-num').text(s);
                }
            });

            // Show/hide step contents
            $contents.each(function () {
                var s = parseInt($(this).data('step'), 10);
                $(this).toggle(s === step);
            });

            // Scroll modal to top
            this.$modal.find('.raf-modal-dialog').scrollTop(0);
        },

        /**
         * Fetch available extras from the REST API and render them
         * as selectable add-on cards in step 1.
         */
        loadExtras: function () {
            var self = this;
            var $list = this.$modal.find('.raf-addons-list');
            var $loading = this.$modal.find('.raf-addons-loading');
            var $none = this.$modal.find('.raf-addons-none');

            $list.empty();
            $none.hide();
            $loading.show();

            apiGet('extras')
                .done(function (extras) {
                    $loading.hide();

                    if (!extras || !extras.length) {
                        $none.show();
                        return;
                    }

                    $.each(extras, function (i, ex) {
                        var priceLabel = ex.price_type === 'per_rental'
                            ? formatPrice(ex.price) + '/rental'
                            : formatPrice(ex.price) + '/day';

                        var qtyHtml = '';
                        if (parseInt(ex.max_quantity, 10) > 1) {
                            qtyHtml = '<select class="raf-addon-qty">';
                            for (var q = 1; q <= parseInt(ex.max_quantity, 10); q++) {
                                qtyHtml += '<option value="' + q + '">' + q + '</option>';
                            }
                            qtyHtml += '</select>';
                        }

                        var desc = ex.description ? '<span class="raf-addon-desc">' + $('<span>').text(ex.description).html() + '</span>' : '';

                        var html = '<div class="raf-addon-item">' +
                            '<label class="raf-addon-label">' +
                            '<input type="checkbox" class="raf-addon-checkbox" value="' + ex.id + '"' +
                            ' data-price-type="' + (ex.price_type || 'per_day') + '"' +
                            ' data-price="' + ex.price + '">' +
                            '<div class="raf-addon-info">' +
                            '<span class="raf-addon-name">' + $('<span>').text(ex.name).html() + '</span>' +
                            desc +
                            '</div>' +
                            '<div class="raf-addon-price-wrap">' +
                            '<span class="raf-addon-price">+' + priceLabel + '</span>' +
                            qtyHtml +
                            '</div>' +
                            '</label>' +
                            '</div>';

                        $list.append(html);
                    });

                    // Bind change events to recalculate price when add-ons toggled
                    $list.off('change.rafAddons').on('change.rafAddons', '.raf-addon-checkbox, .raf-addon-qty', function () {
                        self.loadPriceSummary();
                    });
                })
                .fail(function () {
                    $loading.hide();
                    // Silently continue — extras are optional
                });
        },

        /**
         * Read currently checked add-on checkboxes and return an array
         * of {id, quantity} objects suitable for the price/booking APIs.
         *
         * @return {Array} Selected extras.
         */
        getSelectedExtras: function () {
            var extras = [];
            this.$modal.find('.raf-addon-checkbox:checked').each(function () {
                var $cb = $(this);
                var $item = $cb.closest('.raf-addon-item');
                var qty = parseInt($item.find('.raf-addon-qty').val(), 10) || 1;
                extras.push({
                    id: parseInt($cb.val(), 10),
                    quantity: qty
                });
            });
            return extras;
        },

        /**
         * Fetch the full price breakdown from the API and render it
         * inside the modal. Called once each time the modal opens.
         */
        loadPriceSummary: function () {
            var self = this;
            var $block = this.$modal.find('.raf-modal-price-summary');
            var $loading = $block.find('.raf-modal-price-loading');
            var $breakdown = $block.find('.raf-modal-price-breakdown');
            var ctx = this.searchContext;

            // Need at minimum vehicle + dates to request a price
            if (!ctx.vehicle_id || !ctx.pickup_date || !ctx.dropoff_date) {
                $block.hide();
                return;
            }

            // Show loading state
            $breakdown.empty();
            $loading.show();
            $block.show();

            var data = {
                vehicle_id: parseInt(ctx.vehicle_id, 10),
                pickup_date: ctx.pickup_date,
                dropoff_date: ctx.dropoff_date,
                pickup_location_id: parseInt(ctx.pickup_location_id, 10) || 0,
                dropoff_location_id: parseInt(ctx.dropoff_location_id, 10) || 0,
                extras: self.getSelectedExtras(),
                insurance: []
            };

            // Show date range in header
            var $datesEl = $block.find('.raf-modal-price-summary-dates');
            if (ctx.pickup_date && ctx.dropoff_date) {
                $datesEl.text(ctx.pickup_date + ' \u2192 ' + ctx.dropoff_date);
            }

            apiPost('price', data)
                .done(function (res) {
                    $loading.hide();
                    // API returns: base_price, rental_days, daily_rate,
                    // tax_amount, deposit_amount — use exact field names.
                    var days   = parseInt(res.rental_days || 1, 10);
                    var rate   = parseFloat(res.daily_rate || ctx.daily_rate || 0);
                    var rental = parseFloat(res.base_price || 0);

                    function row(icon, label, formula, amount) {
                        return '<tr class="raf-price-row">' +
                            '<td class="raf-price-label">' +
                            '<span class="raf-price-icon">' + icon + '</span>' +
                            '<span class="raf-price-label-text">' + label + '</span>' +
                            (formula ? '<span class="raf-price-formula">' + formula + '</span>' : '') +
                            '</td>' +
                            '<td class="raf-price-amount">' + amount + '</td></tr>';
                    }

                    var html = '<table class="raf-price-table">';

                    var rentalFormula = rate > 0
                        ? formatPrice(rate) + '/day &times; ' + days + ' day' + (days !== 1 ? 's' : '')
                        : days + ' day' + (days !== 1 ? 's' : '');
                    html += row('&#128336;', 'Daily rental', rentalFormula, formatPrice(rental));

                    if (parseFloat(res.location_fees) > 0) {
                        var pickupFee    = parseFloat(res.pickup_location_fee || 0);
                        var dropoffFee   = parseFloat(res.dropoff_location_fee || 0);
                        var onewayFee    = parseFloat(res.one_way_fee || 0);
                        var pickupName   = res.pickup_location_name || ctx.pickup_location_name || 'Pick-up';
                        var dropoffName  = res.dropoff_location_name || ctx.dropoff_location_name || 'Drop-off';

                        var hasMultiple = (pickupFee > 0 && dropoffFee > 0) || onewayFee > 0;
                        if (pickupFee > 0) {
                            html += hasMultiple
                                ? '<tr class="raf-price-row raf-price-row-sub"><td class="raf-price-label"><span class="raf-price-sub-dot">&#8226;</span><span class="raf-price-label-text">Pick-up: ' + pickupName + '</span></td><td class="raf-price-amount">' + formatPrice(pickupFee) + '</td></tr>'
                                : row('&#128205;', 'Pick-up: ' + pickupName, null, formatPrice(pickupFee));
                        }
                        if (dropoffFee > 0) {
                            html += hasMultiple
                                ? '<tr class="raf-price-row raf-price-row-sub"><td class="raf-price-label"><span class="raf-price-sub-dot">&#8226;</span><span class="raf-price-label-text">Drop-off: ' + dropoffName + '</span></td><td class="raf-price-amount">' + formatPrice(dropoffFee) + '</td></tr>'
                                : row('&#128205;', 'Drop-off: ' + dropoffName, null, formatPrice(dropoffFee));
                        }
                        if (onewayFee > 0) {
                            html += '<tr class="raf-price-row raf-price-row-sub"><td class="raf-price-label"><span class="raf-price-sub-dot">&#8226;</span><span class="raf-price-label-text">One-way fee</span></td><td class="raf-price-amount">' + formatPrice(onewayFee) + '</td></tr>';
                        }
                        if (hasMultiple) {
                            html += row('&#128205;', 'Location fee total', null, formatPrice(res.location_fees));
                        }
                    }
                    if (parseFloat(res.extras_total) > 0) {
                        html += row('&#10010;', 'Extras', null, formatPrice(res.extras_total));
                    }
                    if (parseFloat(res.insurance_total) > 0) {
                        html += row('&#128737;', 'Insurance', null, formatPrice(res.insurance_total));
                    }
                    if (parseFloat(res.tax_amount) > 0) {
                        html += row('&#9878;', 'Tax', null, formatPrice(res.tax_amount));
                    }

                    html += '<tr class="raf-price-row raf-price-row-total">' +
                        '<td class="raf-price-label">&#x2714; Total due</td>' +
                        '<td class="raf-price-amount">' + formatPrice(res.total || 0) + '</td></tr>' +
                        '</table>';

                    if (parseFloat(res.deposit_amount) > 0) {
                        html += '<div class="raf-price-deposit-callout">' +
                            '&#128176; Deposit required: <strong>' + formatPrice(res.deposit_amount) + '</strong>' +
                            '</div>';
                    }

                    // Append security deposit notice
                    if (rafPublic.depositNotice) {
                        html += '<div class="raf-deposit-notice">' + rafPublic.depositNotice + '</div>';
                    }

                    $breakdown.html(html);

                })
                .fail(function () {
                    $loading.hide();
                    var rate = parseFloat(ctx.daily_rate) || 0;
                    if (rate <= 0) { $block.hide(); return; }

                    var d1 = new Date(ctx.pickup_date);
                    var d2 = new Date(ctx.dropoff_date);
                    var days = Math.max(1, Math.ceil((d2 - d1) / 86400000));
                    var estimate = rate * days;
                    var formula = formatPrice(rate) + '/day &times; ' + days + ' day' + (days !== 1 ? 's' : '');

                    var html = '<table class="raf-price-table">' +
                        '<tr class="raf-price-row"><td class="raf-price-label">' +
                        '<span class="raf-price-icon">&#128336;</span>' +
                        '<span class="raf-price-label-text">Daily rental</span>' +
                        '<span class="raf-price-formula">' + formula + '</span>' +
                        '</td><td class="raf-price-amount">' + formatPrice(estimate) + '</td></tr>' +
                        '<tr class="raf-price-row raf-price-row-total">' +
                        '<td class="raf-price-label">&#x2714; Estimated total</td>' +
                        '<td class="raf-price-amount">' + formatPrice(estimate) + '</td></tr>' +
                        '</table>';

                    // Append security deposit notice
                    if (rafPublic.depositNotice) {
                        html += '<div class="raf-deposit-notice">' + rafPublic.depositNotice + '</div>';
                    }

                    $breakdown.html(html);
                });
        },

        bindClose: function () {
            var self = this;

            // Close button
            this.$modal.on('click', '.raf-modal-close', function () {
                self.close();
            });

            // Click outside
            this.$modal.on('click', function (e) {
                if ($(e.target).is('.raf-modal-overlay')) {
                    self.close();
                }
            });

            // Escape key
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && self.$modal.hasClass('raf-modal-open')) {
                    self.close();
                }
            });
        },

        close: function () {
            this.$modal.removeClass('raf-modal-open').css('display', 'none');
            $('body').removeClass('raf-modal-body-open');
        },

        /**
         * Bind the "Continue" buttons to advance between steps.
         * Step 1 -> Step 2: stores extras, loads price summary.
         * Step 2 -> Step 3: validates customer form, shows T&C.
         */
        bindContinue: function () {
            var self = this;

            // Step 1 Continue -> Step 2
            this.$modal.on('click', '.raf-modal-continue-btn', function () {
                // Store selected extras in search context
                self.searchContext.selectedExtras = self.getSelectedExtras();

                // Advance to step 2
                self.goToStep(2);

                // Refresh price with final extras selection
                self.loadPriceSummary();
            });

            // Step 2 Continue -> Step 3
            this.$modal.on('click', '.raf-modal-continue-step3-btn', function () {
                var $form = self.$modal.find('.raf-booking-modal-form');
                var $error = self.$modal.find('.raf-modal-error');

                // Client-side validation of customer fields before proceeding
                var fullName = $.trim($form.find('[name="full_name"]').val());
                var passport = $.trim($form.find('[name="passport_number"]').val());
                var phone = $.trim($form.find('[name="phone"]').val());
                var email = $.trim($form.find('[name="email"]').val());

                if (!fullName || !passport || !phone || !email) {
                    $error.text('Please fill in all required fields.').show();
                    return;
                }

                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    $error.text('Please enter a valid email address.').show();
                    return;
                }

                $error.hide();

                // Load T&C content into the terms box
                var $termsBox = self.$modal.find('.raf-terms-box');
                if (rafPublic.termsContent) {
                    $termsBox.html(rafPublic.termsContent);
                } else {
                    $termsBox.html('<p>Please contact us for our full Terms & Conditions.</p>');
                }

                // Reset checkbox and error
                self.$modal.find('#raf-agree-terms').prop('checked', false);
                self.$modal.find('.raf-modal-terms-error').hide().text('');

                // Pre-fill date with today
                var today = new Date().toISOString().split('T')[0];
                self.$modal.find('#raf-agree-date').val(today);

                self.goToStep(3);
            });

            // Allow going back to previous steps by clicking step indicators
            this.$modal.on('click', '.raf-modal-step[data-step="1"]', function () {
                if (self._currentStep > 1) {
                    self.goToStep(1);
                }
            });
            this.$modal.on('click', '.raf-modal-step[data-step="2"]', function () {
                if (self._currentStep > 2) {
                    self.goToStep(2);
                }
            });
        },

        /**
         * Update the compact price total line shown in step 2.
         */
        updateCompactTotal: function () {
            var $compact = this.$modal.find('.raf-modal-price-total-compact');
            if (typeof this._lastPriceTotal === 'undefined') {
                $compact.hide();
                return;
            }

            var html = '<span class="raf-compact-total-label">Total due:</span>' +
                '<span class="raf-compact-total-amount">' + formatPrice(this._lastPriceTotal) + '</span>';
            if (this._lastPriceDeposit > 0) {
                html += '<span class="raf-compact-total-deposit">Deposit: ' + formatPrice(this._lastPriceDeposit) + '</span>';
            }
            $compact.html(html).show();
        },

        bindSubmit: function () {
            var self = this;

            // Prevent native form submit (Step 2 no longer has a submit button)
            this.$modal.on('submit', '.raf-booking-modal-form', function (e) {
                e.preventDefault();
            });

            // Step 3 Confirm Booking button
            this.$modal.on('click', '.raf-modal-confirm-btn', function () {
                var $form = self.$modal.find('.raf-booking-modal-form');
                var $error = self.$modal.find('.raf-modal-terms-error');
                var $btn = self.$modal.find('.raf-modal-confirm-btn');

                // T&C checkbox validation
                if (!self.$modal.find('#raf-agree-terms').is(':checked')) {
                    $error.text(rafPublic.i18n.agree_terms || 'You must agree to the Terms & Conditions to proceed.').show();
                    return;
                }

                // Minimum advance booking check
                if (isTooSoon(self.searchContext.pickup_date, self.searchContext.pickup_time || '00:00')) {
                    $error.text(rafPublic.minAdvanceMsg).show();
                    return;
                }

                $error.hide();
                $btn.prop('disabled', true).text(rafPublic.i18n.processing || 'Processing...');

                // Read customer fields from Step 2 form
                var fullName = $.trim($form.find('[name="full_name"]').val());
                var passport = $.trim($form.find('[name="passport_number"]').val());
                var phone = $.trim($form.find('[name="phone"]').val());
                var email = $.trim($form.find('[name="email"]').val());
                var agreedDate = self.$modal.find('#raf-agree-date').val() || '';

                // Split full name into first + last
                var nameParts = fullName.split(/\s+/);
                var firstName = nameParts[0] || '';
                var lastName = nameParts.slice(1).join(' ') || '';

                var bookingData = {
                    vehicle_id: self.searchContext.vehicle_id,
                    pickup_date: self.searchContext.pickup_date,
                    dropoff_date: self.searchContext.dropoff_date,
                    pickup_location_id: parseInt(self.searchContext.pickup_location_id, 10) || 0,
                    dropoff_location_id: parseInt(self.searchContext.dropoff_location_id, 10) || 0,
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    phone: phone,
                    passport_number: passport,
                    extras: self.searchContext.selectedExtras || [],
                    insurance: [],
                    agreed_date: agreedDate
                };

                apiPost('bookings', bookingData)
                    .done(function (res) {
                        var bookingNumber = res.booking_number || (res.data && res.data.booking_number) || '';

                        // Show success state
                        self.$modal.find('.raf-modal-form-body').hide();
                        self.$modal.find('.raf-modal-success').show();
                        self.$modal.find('.raf-modal-booking-number').text(bookingNumber);
                    })
                    .fail(function (xhr) {
                        var msg = 'An error occurred. Please try again.';
                        if (xhr.responseJSON) {
                            msg = xhr.responseJSON.message || xhr.responseJSON.data || msg;
                        }
                        $error.text(msg).show();
                        $btn.prop('disabled', false).text(rafPublic.i18n.book_now || 'Confirm Booking');
                    });
            });
        }
    };

    /* ═══════════════════════════════════════════════
       4. Vehicle Detail Modal
       ═══════════════════════════════════════════════ */

    RAF.VehicleDetail = {
        init: function () {
            var $modal = $('#raf-vehicle-detail-modal');
            if (!$modal.length) return;

            this.$modal = $modal;
            this.bindOpen();
            this.bindClose();
        },

        bindOpen: function () {
            var self = this;

            $(document).on('click', '.raf-vehicle-card--clickable', function (e) {
                e.preventDefault();
                var data = $(this).data('vehicle');
                if (!data) return;

                // If data was parsed from JSON string, it's already an object
                if (typeof data === 'string') {
                    try { data = JSON.parse(data); } catch (err) { return; }
                }

                self.populate(data);
                self.$modal.css('display', 'flex');
                $('body').addClass('raf-modal-body-open');
            });
        },

        populate: function (v) {
            var $m = this.$modal;

            // Image
            var $img = $m.find('.raf-vd-image');
            if (v.image) {
                $img.attr('src', v.image).attr('alt', v.name).show();
            } else {
                $img.hide();
            }

            // Name
            $m.find('.raf-vd-name').text(v.name);

            // Badges
            var badges = '';
            if (v.type_label) {
                badges += '<span class="raf-spec"><span class="raf-spec-icon">&#127949;</span> ' + $('<span>').text(v.type_label).html() + '</span>';
            }
            if (v.engine_cc) {
                badges += '<span class="raf-spec"><span class="raf-spec-icon">&#9881;</span> ' + v.engine_cc + 'cc</span>';
            }
            $m.find('.raf-vd-badges').html(badges);

            // Description — prefer full description, fall back to short
            var desc = v.description || v.short_desc || '';
            $m.find('.raf-vd-description').html(desc);

            // Specs grid
            var specs = '';
            if (v.year) {
                specs += '<div class="raf-vd-spec-item"><span class="raf-vd-spec-label">Year</span><span class="raf-vd-spec-value">' + v.year + '</span></div>';
            }
            if (v.engine_cc) {
                specs += '<div class="raf-vd-spec-item"><span class="raf-vd-spec-label">Engine</span><span class="raf-vd-spec-value">' + v.engine_cc + 'cc</span></div>';
            }
            if (v.make) {
                specs += '<div class="raf-vd-spec-item"><span class="raf-vd-spec-label">Make</span><span class="raf-vd-spec-value">' + $('<span>').text(v.make).html() + '</span></div>';
            }
            if (v.model) {
                specs += '<div class="raf-vd-spec-item"><span class="raf-vd-spec-label">Model</span><span class="raf-vd-spec-value">' + $('<span>').text(v.model).html() + '</span></div>';
            }
            if (v.color) {
                specs += '<div class="raf-vd-spec-item"><span class="raf-vd-spec-label">Color</span><span class="raf-vd-spec-value">' + $('<span>').text(v.color).html() + '</span></div>';
            }
            if (v.min_age) {
                specs += '<div class="raf-vd-spec-item"><span class="raf-vd-spec-label">Min. Age</span><span class="raf-vd-spec-value">' + v.min_age + '+</span></div>';
            }
            $m.find('.raf-vd-specs').html(specs);

            // Features — look up human-readable labels and emojis
            var featureMap   = (rafPublic.bikeFeatures   || {});
            var emojiMap     = (rafPublic.featureEmojis  || {});
            var featuresHtml = '';
            if (v.features && v.features.length) {
                $.each(v.features, function (i, key) {
                    if (!key) return;
                    var label = featureMap[key] || key;
                    var emoji = emojiMap[key]   ? '<span class="raf-feature-emoji">' + emojiMap[key] + '</span>' : '';
                    featuresHtml += '<span class="raf-feature-tag">' + emoji + $('<span>').text(label).html() + '</span>';
                });
            }
            $m.find('.raf-vd-features').html(featuresHtml);

            // Price
            if (v.daily_rate > 0) {
                $m.find('.raf-vd-price').html(formatPrice(v.daily_rate) + '<span class="raf-vd-price-period">/day</span>');
                $m.find('.raf-vd-price-row').show();
            } else {
                $m.find('.raf-vd-price-row').hide();
            }
        },

        bindClose: function () {
            var self = this;

            // Close button
            this.$modal.on('click', '.raf-vd-close', function () {
                self.close();
            });

            // Click overlay (outside modal box)
            this.$modal.on('click', function (e) {
                if ($(e.target).is('.raf-vd-overlay')) {
                    self.close();
                }
            });

            // Escape key
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && self.$modal.is(':visible')) {
                    self.close();
                }
            });
        },

        close: function () {
            this.$modal.css('display', 'none');
            $('body').removeClass('raf-modal-body-open');
        }
    };

    /* ═══════════════════════════════════════════════
       5. My Bookings — Cancel + Lookup
       ═══════════════════════════════════════════════ */

    RAF.MyBookings = {
        init: function () {
            // Cancel button
            $('.raf-my-bookings-wrap').on('click', '.raf-cancel-booking', function (e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to cancel this booking?')) return;

                var $btn = $(this);
                var bookingId = $btn.data('booking-id');

                $btn.prop('disabled', true);

                apiPost('bookings/' + bookingId + '/cancel', {})
                    .done(function () {
                        $btn.closest('.raf-booking-card').find('.raf-badge')
                            .removeClass('raf-badge-pending raf-badge-confirmed')
                            .addClass('raf-badge-cancelled')
                            .text('Cancelled');
                        $btn.remove();
                    })
                    .fail(function () {
                        alert('Could not cancel booking. Please try again.');
                        $btn.prop('disabled', false);
                    });
            });

            // Email lookup form
            $('.raf-lookup-form').on('submit', function (e) {
                e.preventDefault();
                var $form = $(this);
                var email = $form.find('[name="lookup_email"]').val();

                if (!email) {
                    alert('Please enter your email address.');
                    return;
                }

                $form.find('.raf-btn').prop('disabled', true).text('Looking up...');

                apiGet('bookings/lookup', { email: email })
                    .done(function (res) {
                        var bookings = res.data || res;
                        var $results = $form.closest('.raf-my-bookings-wrap').find('.raf-lookup-results');

                        if (!$results.length) {
                            $results = $('<div class="raf-lookup-results"></div>');
                            $form.after($results);
                        }

                        if (!bookings.length) {
                            $results.html('<div class="raf-notice raf-notice-info">No bookings found for this email address.</div>');
                        } else {
                            var html = '<div class="raf-bookings-list">';
                            $.each(bookings, function (i, b) {
                                html += '<div class="raf-booking-card">' +
                                    '<div class="raf-booking-card-header">' +
                                    '<span class="raf-booking-number">#' + b.booking_number + '</span>' +
                                    '<span class="raf-badge raf-badge-' + b.status + '">' + b.status + '</span>' +
                                    '</div>' +
                                    '<div class="raf-booking-card-body">' +
                                    '<div class="raf-booking-dates">' +
                                    '<strong>' + (b.vehicle_name || 'Bike') + '</strong><br>' +
                                    b.pickup_date + ' — ' + b.dropoff_date +
                                    '</div>' +
                                    '<div class="raf-booking-total">' + formatPrice(b.total_price) + '</div>' +
                                    '</div></div>';
                            });
                            html += '</div>';
                            $results.html(html);
                        }

                        $form.find('.raf-btn').prop('disabled', false).text('Look Up');
                    })
                    .fail(function () {
                        alert('Could not look up bookings. Please try again.');
                        $form.find('.raf-btn').prop('disabled', false).text('Look Up');
                    });
            });
        }
    };

    /* ═══════════════════════════════════════════════
       Initialise on DOM ready
       ═══════════════════════════════════════════════ */

    $(function () {
        RAF.Search.init();
        RAF.VehicleFilter.init();
        RAF.VehicleDetail.init();
        RAF.Booking.init();
        RAF.BookingModal.init();
        RAF.MyBookings.init();
    });

})(jQuery);
