document.addEventListener('DOMContentLoaded', function () {
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');


if (!menuToggle || !sidebar || !overlay) {
    return;
}

function fermerMenu() {
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
}

menuToggle.addEventListener('click', function () {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('visible');
});

overlay.addEventListener('click', fermerMenu);


});
