<!-- Footer Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ----------------------
// Dark Mode Toggle
// ----------------------
const toggleBtn = document.getElementById('themeToggle');
const body = document.body;

// Load saved theme
if(localStorage.getItem('theme') === 'dark'){
    body.classList.add('dark');
    toggleBtn.innerHTML = '<i class="bi bi-brightness-high-fill"></i> Light Mode';
}

// Toggle dark mode
toggleBtn.addEventListener('click', () => {
    body.classList.toggle('dark');
    const isDark = body.classList.contains('dark');
    toggleBtn.innerHTML = isDark
        ? '<i class="bi bi-brightness-high-fill"></i> Light Mode'
        : '<i class="bi bi-moon-fill"></i> Dark Mode';
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
});

// ----------------------
// Counter Animation
// ----------------------
document.querySelectorAll('.counter').forEach(counter => {
    let countTo = parseInt(counter.getAttribute('data-count'));
    let count = 0;
    let step = Math.ceil(countTo / 100);
    let interval = setInterval(() => {
        count += step;
        if(count >= countTo){ count = countTo; clearInterval(interval); }
        counter.innerText = count;
    }, 20);
});

// ----------------------
// Chart.js Initialization
// ----------------------
// You can define chart data in each dashboard page
// Example: Staff, Admin, Donor dashboards should define variables like:
// genderLabels, genderCounts, ageLabels, ageCounts, etc.

// Global Chart options
const chartOptions = {
    responsive: true,
    plugins: { legend: { display: true } }
};

// Example usage (define variables in each page):
// if(document.getElementById('genderChart')){
//     new Chart(document.getElementById('genderChart'), {
//         type: 'pie',
//         data: { labels: genderLabels, datasets:[{ data: genderCounts, backgroundColor:['#0d6efd','#dc3545','#ffc107'] }] },
//         options: chartOptions
//     });
// }

</script>
</body>
</html>
