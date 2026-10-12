(function () {
    'use strict';
    var dateNode = document.querySelector('[data-sidebar-date]');
    var timeNode = document.querySelector('[data-sidebar-time]');
    if (!dateNode || !timeNode) return;

    var months = ['', 'حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'];
    var persianDigits = '۰۱۲۳۴۵۶۷۸۹';
    function toPersianDigits(value) {
        return String(value).replace(/[0-9]/g, function (digit) {
            return persianDigits[Number(digit)];
        });
    }
    function updateClock() {
        var now = new Date();
        try {
            var parts = new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
                year: 'numeric', month: 'numeric', day: 'numeric'
            }).formatToParts(now);
            var values = {};
            parts.forEach(function (part) {
                if (part.type !== 'literal') values[part.type] = part.value;
            });
            var monthNumber = Number(values.month);
            var monthName = months[monthNumber] || values.month;
            dateNode.textContent = toPersianDigits(values.day) + ' ' + monthName + ' ' + toPersianDigits(values.year);
        } catch (error) {
            dateNode.textContent = new Intl.DateTimeFormat('fa-AF', {
                year: 'numeric', month: 'long', day: 'numeric', calendar: 'persian'
            }).format(now);
        }
        timeNode.textContent = new Intl.DateTimeFormat('fa-AF', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
        }).format(now);
    }
    updateClock();
    window.setInterval(updateClock, 1000);
}());