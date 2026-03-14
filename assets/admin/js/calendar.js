/**
 * RentAFleet — Calendar JavaScript
 *
 * Renders a monthly calendar grid with booking events.
 *
 * @package RentAFleet
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    if (typeof rafAdmin === 'undefined') return;

    var Calendar = {
        year: new Date().getFullYear(),
        month: new Date().getMonth(), // 0-indexed
        vehicleId: 0,
        events: [],

        DAYS: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        MONTHS: [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ],

        init: function () {
            var $wrapper = $('.raf-calendar-wrapper');
            if (!$wrapper.length) return;

            this.$grid = $('#raf-calendar-grid');
            this.$title = $('#raf-cal-title');
            this.$modal = $('#raf-calendar-modal');

            this.bindControls();
            this.load();
        },

        bindControls: function () {
            var self = this;

            $('#raf-cal-prev').on('click', function () {
                self.month--;
                if (self.month < 0) {
                    self.month = 11;
                    self.year--;
                }
                self.load();
            });

            $('#raf-cal-next').on('click', function () {
                self.month++;
                if (self.month > 11) {
                    self.month = 0;
                    self.year++;
                }
                self.load();
            });

            $('#raf-cal-today').on('click', function () {
                var now = new Date();
                self.year = now.getFullYear();
                self.month = now.getMonth();
                self.load();
            });

            $('#raf-calendar-vehicle').on('change', function () {
                self.vehicleId = parseInt($(this).val(), 10) || 0;
                self.load();
            });

            // Modal close
            this.$modal.on('click', '.raf-modal-close', function () {
                self.$modal.hide();
            });
            this.$modal.on('click', function (e) {
                if ($(e.target).is('.raf-modal')) {
                    self.$modal.hide();
                }
            });
        },

        load: function () {
            var self = this;
            var startDate = this.year + '-' + this.pad(this.month + 1) + '-01';
            var lastDay = new Date(this.year, this.month + 1, 0).getDate();
            var endDate = this.year + '-' + this.pad(this.month + 1) + '-' + this.pad(lastDay);

            this.$title.text(this.MONTHS[this.month] + ' ' + this.year);
            this.$grid.addClass('loading');

            $.post(rafAdmin.ajaxUrl, {
                action: 'raf_get_calendar_data',
                nonce: rafAdmin.nonce,
                start: startDate,
                end: endDate,
                vehicle_id: this.vehicleId
            }, function (res) {
                self.events = res.success ? res.data : [];
                self.render();
                self.$grid.removeClass('loading');
            }).fail(function () {
                self.events = [];
                self.render();
                self.$grid.removeClass('loading');
            });
        },

        render: function () {
            var html = '';

            // Header row
            html += '<div class="raf-cal-header">';
            for (var d = 0; d < 7; d++) {
                html += '<div class="raf-cal-header-cell">' + this.DAYS[d] + '</div>';
            }
            html += '</div>';

            // Build days grid
            var firstDayOfMonth = new Date(this.year, this.month, 1).getDay();
            var daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
            var daysInPrevMonth = new Date(this.year, this.month, 0).getDate();
            var today = new Date();
            var todayStr = today.getFullYear() + '-' + this.pad(today.getMonth() + 1) + '-' + this.pad(today.getDate());

            html += '<div class="raf-cal-body">';

            // Previous month's trailing days
            for (var p = firstDayOfMonth - 1; p >= 0; p--) {
                var prevDay = daysInPrevMonth - p;
                html += '<div class="raf-cal-cell other-month"><span class="raf-cal-day-number">' + prevDay + '</span></div>';
            }

            // Current month days
            for (var day = 1; day <= daysInMonth; day++) {
                var dateStr = this.year + '-' + this.pad(this.month + 1) + '-' + this.pad(day);
                var isToday = dateStr === todayStr;
                var cellClass = 'raf-cal-cell' + (isToday ? ' today' : '');
                var dayEvents = this.getEventsForDate(dateStr);

                html += '<div class="' + cellClass + '">';
                html += '<span class="raf-cal-day-number">' + day + '</span>';

                // Show up to 3 events, then "+N more"
                var maxShow = 3;
                for (var e = 0; e < Math.min(dayEvents.length, maxShow); e++) {
                    var evt = dayEvents[e];
                    html += '<span class="raf-cal-event" style="background:' + evt.color + '" data-event-id="' + evt.id + '">' +
                        this.escHtml(evt.title) + '</span>';
                }
                if (dayEvents.length > maxShow) {
                    html += '<span class="raf-cal-more">+' + (dayEvents.length - maxShow) + ' more</span>';
                }

                html += '</div>';
            }

            // Next month's leading days to fill out the grid
            var totalCells = firstDayOfMonth + daysInMonth;
            var remainder = totalCells % 7;
            if (remainder > 0) {
                for (var n = 1; n <= 7 - remainder; n++) {
                    html += '<div class="raf-cal-cell other-month"><span class="raf-cal-day-number">' + n + '</span></div>';
                }
            }

            html += '</div>';
            this.$grid.html(html);

            // Bind event clicks
            var self = this;
            this.$grid.find('.raf-cal-event').on('click', function () {
                var eventId = $(this).data('event-id');
                self.showEventDetail(eventId);
            });
        },

        getEventsForDate: function (dateStr) {
            var results = [];
            var checkDate = new Date(dateStr + 'T00:00:00');

            for (var i = 0; i < this.events.length; i++) {
                var evt = this.events[i];
                var start = new Date(evt.start + 'T00:00:00');
                var end = new Date(evt.end + 'T00:00:00');
                // end is exclusive (day after last day)
                if (checkDate >= start && checkDate < end) {
                    results.push(evt);
                }
            }
            return results;
        },

        showEventDetail: function (eventId) {
            var evt = null;
            for (var i = 0; i < this.events.length; i++) {
                if (this.events[i].id == eventId) {
                    evt = this.events[i];
                    break;
                }
            }
            if (!evt) return;

            var html = '<h3>' + this.escHtml(evt.title) + '</h3>';

            html += '<div class="raf-modal-detail"><span class="label">Status</span>' +
                '<span class="value"><span class="raf-modal-status" style="background:' + evt.color + '">' + evt.status + '</span></span></div>';

            if (evt.booking_number) {
                html += '<div class="raf-modal-detail"><span class="label">Booking #</span><span class="value">' + this.escHtml(evt.booking_number) + '</span></div>';
            }
            if (evt.vehicle) {
                html += '<div class="raf-modal-detail"><span class="label">Bike</span><span class="value">' + this.escHtml(evt.vehicle) + '</span></div>';
            }
            if (evt.customer) {
                html += '<div class="raf-modal-detail"><span class="label">Customer</span><span class="value">' + this.escHtml(evt.customer) + '</span></div>';
            }
            if (evt.pickup_date) {
                html += '<div class="raf-modal-detail"><span class="label">Pick-up</span><span class="value">' + this.escHtml(evt.pickup_date) + '</span></div>';
            }
            if (evt.dropoff_date) {
                html += '<div class="raf-modal-detail"><span class="label">Return</span><span class="value">' + this.escHtml(evt.dropoff_date) + '</span></div>';
            }
            if (evt.total_price) {
                html += '<div class="raf-modal-detail"><span class="label">Total</span><span class="value">' + this.escHtml(evt.total_price) + '</span></div>';
            }

            if (evt.booking_number && !evt.blocked) {
                html += '<div class="raf-modal-actions">' +
                    '<a href="' + rafAdmin.adminUrl + '?page=raf-bookings&action=view&id=' + evt.id + '" class="button button-primary">View Booking</a>' +
                    '<a href="' + rafAdmin.adminUrl + '?page=raf-bookings&action=edit&id=' + evt.id + '" class="button">Edit</a>' +
                    '</div>';
            }

            $('#raf-modal-body').html(html);
            this.$modal.show();
        },

        pad: function (n) {
            return n < 10 ? '0' + n : '' + n;
        },

        escHtml: function (str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };

    $(function () {
        Calendar.init();
    });

})(jQuery);
