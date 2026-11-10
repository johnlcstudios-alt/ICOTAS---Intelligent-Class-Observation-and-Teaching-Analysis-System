/* Shared Chart.js renderers. Charts are progressively enhanced: without
   JavaScript the surrounding tables still communicate the data. */
(function () {
    'use strict';

    function read(id) {
        const el = document.getElementById(id);
        if (!el) { return null; }
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }

    function make(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas || !window.Chart) { return null; }
        return new Chart(canvas, config);
    }

    window.ICOTASCharts = {
        bar: function (id, labels, values, label, max) {
            return make(id, {
                type: 'bar',
                data: { labels: labels, datasets: [{ label: label || 'Average', data: values, backgroundColor: 'rgba(112,28,43,.78)', borderRadius: 5, maxBarThickness: 38 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: max || undefined, grid: { color: '#eef0f3' } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
            });
        },
        line: function (id, labels, values, label, max) {
            return make(id, {
                type: 'line',
                data: { labels: labels, datasets: [{ label: label || 'Average', data: values, borderColor: '#701c2b', backgroundColor: 'rgba(112,28,43,.08)', fill: true, tension: .3, pointRadius: 3, pointBackgroundColor: '#c9a227', borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false, min: 1, max: max || 5, grid: { color: '#eef0f3' } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
            });
        },
        doughnut: function (id, labels, values) {
            return make(id, {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: values, backgroundColor: ['#2e7d6b', '#98a2b3', '#b42318', '#c9a227', '#4f6d9a'], borderWidth: 2, borderColor: '#fff' }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } }
            });
        },
        initFromJson: function () {
            document.querySelectorAll('[data-chart]').forEach(function (canvas) {
                const config = read(canvas.id + '_data');
                if (!config) { return; }
                const type = canvas.getAttribute('data-chart');
                const fn = window.ICOTASCharts[type];
                if (fn) { fn(canvas.id, config.labels || [], config.values || [], config.label || '', config.max || 0); }
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (window.Chart) { window.ICOTASCharts.initFromJson(); }
    });
})();
