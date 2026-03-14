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

                $form.hide();
                $results.hide();
                $loading.show();

                apiGet('search', data)
                    .done(function (res) {
                        var vehicles = res.data || res;
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

        vehicleCard: function (v) {
            var img = v.image_url
                ? '<img src="' + v.image_url + '" alt="' + v.name + '" loading="lazy">'
                : '<div class="raf-vehicle-no-image"><span class="dashicons dashicons-motorcycle"></span></div>';

            var price = v.daily_rate > 0
                ? '<div class="raf-vehicle-price"><span class="raf-price-amount">' + formatPrice(v.daily_rate) + '</span><span class="raf-price-period">/day</span></div>'
                : '';

            var bookUrl = v.booking_url || '#';

            return '<div class="raf-vehicle-card">' +
                '<div class="raf-vehicle-image">' + img + '</div>' +
                '<div class="raf-vehicle-info">' +
                '<h3 class="raf-vehicle-name">' + v.name + '</h3>' +
                '<div class="raf-vehicle-specs">' + RAF.Search.specs(v) + '</div>' +
                '</div>' +
                '<div class="raf-vehicle-price-action">' + price +
                '<a href="' + bookUrl + '" class="raf-btn raf-btn-primary raf-book-btn">Book Now</a>' +
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
            var img = v.image_url
                ? '<img src="' + v.image_url + '" alt="' + v.name + '" loading="lazy">'
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
                    var bikes = res.data || res;
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
                extras.push({ id: parseInt(id, 10), qty: parseInt(qty, 10) });
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
                    if (res.rental) {
                        html += '<div><span>Rental (' + res.days + ' days)</span><span class="raf-amount">' + formatPrice(res.rental) + '</span></div>';
                    }
                    if (res.extras_total) {
                        html += '<div><span>Extras</span><span class="raf-amount">' + formatPrice(res.extras_total) + '</span></div>';
                    }
                    if (res.insurance_total) {
                        html += '<div><span>Insurance</span><span class="raf-amount">' + formatPrice(res.insurance_total) + '</span></div>';
                    }
                    if (res.discount) {
                        html += '<div class="raf-discount-row"><span>Discount</span><span>-' + formatPrice(res.discount) + '</span></div>';
                    }
                    html += '<div class="raf-total-row"><span>Total</span><span>' + formatPrice(res.total) + '</span></div>';
                    if (res.deposit) {
                        html += '<div class="raf-deposit-row"><span>Deposit Due</span><span>' + formatPrice(res.deposit) + '</span></div>';
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
                    extras.push({ id: parseInt(id, 10), qty: parseInt(qty, 10) });
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
       4. My Bookings — Cancel + Lookup
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
        RAF.Booking.init();
        RAF.MyBookings.init();
    });

})(jQuery);
