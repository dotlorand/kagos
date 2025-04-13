// Remove custom popup/close functions and use Bootstrap modals instead

// Function to open Remove Team modal and set its data
function openRemoveTeamModal(teamId, teamName) {
    document.getElementById('teamNameConfirm').innerText = teamName;
    document.getElementById('teamIdInput').value = teamId;
    var removeModal = new bootstrap.Modal(document.getElementById('removeTeamModal'));
    removeModal.show();
}

// Remove old popup() and closePopup() functions

// When clicking the "Csapat törlése" button, attach its event to call openRemoveTeamModal()
// This assumes the button with id "openRemoveTeamModal" is on the page.
document.addEventListener('DOMContentLoaded', function() {
    var removeBtn = document.getElementById('openRemoveTeamModal');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            // Pass team id and name (should be rendered in data attributes or available globally)
            // For example, we assume they are stored as data attributes on the button:
            var teamId = this.getAttribute('data-team-id');
            var teamName = this.getAttribute('data-team-name');
            openRemoveTeamModal(teamId, teamName);
        });
    }
});

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
                chip.className = 'badge rounded-pill bg-primary me-1 mb-1';
                chip.style.cursor = 'pointer';
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
        chip.className = 'badge rounded-pill bg-primary me-1 mb-1';
        chip.style.cursor = 'pointer';
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