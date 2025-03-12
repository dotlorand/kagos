const select = document.getElementById('politikak-select');
const chipsContainer = document.getElementById('chips-container');
const hiddenInput = document.getElementById('politikak-hidden');

let selectedOptions = [];

function updateHiddenInput() {
    hiddenInput.value = JSON.stringify(selectedOptions);
}

// irja ki a chipeket
window.addEventListener('DOMContentLoaded', function() {
    try {
        const existing = JSON.parse(hiddenInput.value || "[]");
        if (Array.isArray(existing)) {
            existing.forEach(item => {
                selectedOptions.push(item);
                const chip = document.createElement('span');
                chip.className = 'chip';
                chip.textContent = item.text;
                chip.dataset.value = item.value;
                chip.addEventListener('click', function() {
                    chipsContainer.removeChild(chip);
                    selectedOptions = selectedOptions.filter(opt => opt.value !== item.value);
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.text = item.text;
                    select.appendChild(option);
                    updateHiddenInput();
                });
                chipsContainer.appendChild(chip);
                const optionToRemove = select.querySelector(`option[value="${item.value}"]`);
                if (optionToRemove) {
                    optionToRemove.remove();
                }
            });
        }
    } catch (e) {
        console.error('invalid json');
    }
});

// add/remove chip
select.addEventListener('change', function() {
    const value = select.value;
    const text = select.options[select.selectedIndex].text;
    if (value && !selectedOptions.some(opt => opt.value === value)) {
        selectedOptions.push({ value, text });
        const chip = document.createElement('span');
        chip.className = 'chip';
        chip.textContent = text;
        chip.dataset.value = value;
        chip.addEventListener('click', function() {
            chipsContainer.removeChild(chip);
            selectedOptions = selectedOptions.filter(opt => opt.value !== value);
            const option = document.createElement('option');
            option.value = value;
            option.text = text;
            select.appendChild(option);
            updateHiddenInput();
        });
        chipsContainer.appendChild(chip);
        const optionToRemove = select.querySelector(`option[value="${value}"]`);
        if (optionToRemove) {
            optionToRemove.remove();
        }
        updateHiddenInput();
        select.value = "";
    }
});