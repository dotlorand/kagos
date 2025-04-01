// popups

function popup(popupId) {
    document.getElementById(popupId).style.display = "flex";
    document.getElementById("popup-container").style.display = "flex";
}

function closePopup() {
    const container = document.getElementById("popup-container");
    Array.from(container.children).forEach(child => {
        if (!child.classList.contains("popup-bg")) {
            child.style.display = "none";
        }
    });
    container.style.display = "none";
}

function removePopup(teamId, teamName) {
    var popupElem = document.getElementById('remove-team');
    popupElem.querySelector('input[name="team_id"]').value = teamId;
    popupElem.querySelector('#team-name-confirm').innerText = teamName;
    popup('remove-team');
}

// toast

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.toast').forEach(function(toast) {
            toast.remove();
        });
    }, 3000);
});

// init

const select = document.getElementById('politikak-select');
const chipsContainer = document.getElementById('chips-container');
const hiddenInput = document.getElementById('politikak-hidden');

let selectedOptions = [];

function updateHiddenInput() {
    hiddenInput.value = JSON.stringify(selectedOptions);
    const form = document.querySelector("form.init-form");
    if (form) {
        form.dispatchEvent(new Event("input"));
    }
}

// politika chipek
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
                    // Use the chip's own dataset value
                    const chipValue = this.dataset.value;
                    selectedOptions = selectedOptions.filter(opt => opt.value !== chipValue);
                    const option = document.createElement('option');
                    option.value = chipValue;
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
            const chipValue = this.dataset.value;
            selectedOptions = selectedOptions.filter(opt => opt.value !== chipValue);
            const option = document.createElement('option');
            option.value = chipValue;
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


// detect changes

document.addEventListener("DOMContentLoaded", function() {
    // get form and save button
    const form = document.querySelector("form.init-form");
    if (!form) return;
    const saveButton = form.querySelector("input[type='submit']");
    
    // get init
    const initialData = {};
    Array.from(form.elements).forEach(el => {
        if (el.name) {
            initialData[el.name] = el.value;
        }
    });
    
    let unsavedChanges = false;
    
    function checkForChanges() {
        unsavedChanges = false;
        Array.from(form.elements).forEach(el => {
            if (el.name && initialData[el.name] !== el.value) {
                unsavedChanges = true;
            }
        });

        if (unsavedChanges) {
            saveButton.classList.add("highlight");
        } else {
            saveButton.classList.remove("highlight");
        }
    }
    
    // fieldeket nez folyamatosan
    form.addEventListener("input", checkForChanges);
    form.addEventListener("change", checkForChanges);
});