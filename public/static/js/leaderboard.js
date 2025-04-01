// shadow on stuck thead
document.addEventListener('DOMContentLoaded', function() {
    const thead = document.querySelector('table thead');
    if (!thead) return;

    window.addEventListener('scroll', function() {
        // check pos based on viewport
        const rect = thead.getBoundingClientRect();
        if (rect.top <= 0) {
            thead.classList.add('stuck');
        } else {
            thead.classList.remove('stuck');
        }
    });
});