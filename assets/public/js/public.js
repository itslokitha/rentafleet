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
                        $title.text(vehicles.length + ' vehicle' + (vehicles.length !== 1 ? 's' : '') + ' found');
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
                : '<div class="raf-vehicle-no-image"><span class="dashicons dashicons-car"></span></div>';

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
            if (v.transmission) s += '<span class="raf-spec">' + v.transmission + '</span>';
            if (v.fuel_type) s += '<span class="raf-spec">' + v.fuel_type + '</span>';
            if (v.seats) s += '<span class="raf-spec">' + v.seats + ' seats</span>';
            if (v.doors) s += '<span class="raf-spec">' + v.doors + ' doors</span>';
            return s;
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
       3. Booking Form
       ═══════════════════════════════════════════════ */

    RAF.Booking = {
        currentStep: 1,
        totalSteps: 4,

        init: function () {
            var $wrap = $('.raf-booking-wrap');
            if (!$wrap.length) return;

            this.$wrap = $wrap;
            this.$form = $wrap.find('.raf-booking-form');
            this.bindStepNav();
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
            this.updatePrice();
        },

        showStep: function (step) {
            this.$wrap.find('[data-step]').hide();
            this.$wrap.find('[data-step="' + step + '"]').show();

            // Update step indicators
            this.$wrap.find('.raf-step').each(function () {
                var s = parseInt($(this).data('step'), 10);
                $(this).toggleClass('active', s === step)
                    .toggleClass('completed', s < step);
            });
        },

        validateStep: function (step) {
            var $stepEl = this.$wrap.find('[data-step="' + step + '"]');
            var valid = true;

            $stepEl.find('[required]').each(function () {
                if (!$(this).val()) {
                    $(this).focus();
                    valid = false;
                    return false;
                }
            });

            return valid;
        },

        bindExtras: function () {
            this.$wrap.on('change', '.raf-extra-checkbox input', function () {
                RAF.Booking.updatePrice();
            });
            this.$wrap.on('change', '.raf-extra-qty', function () {
                RAF.Booking.updatePrice();
            });
        },

        bindInsurance: function () {
            this.$wrap.on('change', '.raf-insurance-checkbox input', function () {
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
                            $msg.html('<span style="color: #16a34a;">Coupon applied! ' + res.description + '</span>');
                            RAF.Booking.coupon = res;
                            RAF.Booking.updatePrice();
                        } else {
                            $msg.html('<span style="color: #dc2626;">' + (res.message || 'Invalid coupon.') + '</span>');
                        }
                    })
                    .fail(function () {
                        $msg.html('<span style="color: #dc2626;">Could not validate coupon.</span>');
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
            this.$wrap.find('.raf-extra-checkbox input:checked').each(function () {
                var id = $(this).val();
                var qty = $(this).closest('.raf-extra-item').find('.raf-extra-qty').val() || 1;
                extras.push({ id: parseInt(id, 10), qty: parseInt(qty, 10) });
            });
            data.extras = extras;

            // Collect insurance
            var insurance = [];
            this.$wrap.find('.raf-insurance-checkbox input:checked').each(function () {
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
                    html += '</div>';

                    $summary.html(html);
                });
        },

        bindConfirm: function () {
            this.$wrap.on('click', '.raf-confirm-booking', function (e) {
                e.preventDefault();
                var $btn = $(this);
                $btn.prop('disabled', true).text(rafPublic.i18n.processing);

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
                RAF.Booking.$wrap.find('.raf-extra-checkbox input:checked').each(function () {
                    var id = $(this).val();
                    var qty = $(this).closest('.raf-extra-item').find('.raf-extra-qty').val() || 1;
                    extras.push({ id: parseInt(id, 10), qty: parseInt(qty, 10) });
                });
                formData.extras = extras;

                // Insurance
                var insurance = [];
                RAF.Booking.$wrap.find('.raf-insurance-checkbox input:checked').each(function () {
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
                            alert(rafPublic.i18n.booking_success);
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
       4. My Bookings — Cancel
       ═══════════════════════════════════════════════ */

    RAF.MyBookings = {
        init: function () {
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
