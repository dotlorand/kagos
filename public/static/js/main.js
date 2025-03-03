// popups

function popup(popupId) {
    document.getElementById(popupId).style.display = "flex";
}

function closePopup(popupId) {
    document.getElementById(popupId).style.display = "none";
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
    }, 5000);
});