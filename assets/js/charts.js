// assets/js/charts.js — replaces Matplotlib TkAgg charts
// Chart.js is loaded via CDN: <script src='https://cdn.jsdelivr.net/npm/chart.js'></script>

// ── Semester GWA Trend Chart ─────────────────────────────────────────────
// PHP passes data like: <script>const semData = <?= json_encode($semData) ?>;</script>
// Then charts.js reads it:

if (typeof semData !== 'undefined') {
  const ctx = document.getElementById('semGwaChart');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: semData.map(d => d.semester),
      datasets: [{
        label: 'Semester GWA',
        data:  semData.map(d => d.gwa),
        borderColor:     '#0D2B6B',
        backgroundColor: 'rgba(13,43,107,0.1)',
        borderWidth: 2,
        pointRadius: 5,
        fill: true,
        tension: 0.3,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: true }, title: {
        display: true, text: 'GWA Performance Trend',
        color: '#0D2B6B', font: { size: 16 }
      }},
      scales: {
        y: { min: 1.0, max: 4.0, reverse: false,
             title: { display: true, text: 'GWA (4.0 = Highest)' } }
      }
    }
  });
}

// ── Risk Distribution Bar Chart ──────────────────────────────────────────
if (typeof riskData !== 'undefined') {
  const ctx2 = document.getElementById('riskChart');
  new Chart(ctx2, {
    type: 'bar',
    data: {
      labels: ['LOW', 'MODERATE', 'HIGH'],
      datasets: [{
        label: 'Number of Students',
        data:  [riskData.low, riskData.moderate, riskData.high],
        backgroundColor: ['#1B7A3E', '#C97A00', '#C62828'],
      }]
    },
    options: { responsive: true }
  });
}
