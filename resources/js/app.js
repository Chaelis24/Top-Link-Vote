import "./bootstrap";

window.toggleSidebar = function () {
    const sidebar = document.querySelector(".sidebar");
    const overlay = document.querySelector(".sidebar-overlay");
    const toggleBtn = document.querySelector(".sidebar-toggle");

    if (sidebar && overlay) {
        sidebar.classList.toggle("show");
        overlay.classList.toggle("show");
    }
};
document.addEventListener("click", (e) => {
    if (e.target.classList.contains("sidebar-overlay")) {
        const sidebar = document.querySelector(".sidebar");
        const overlay = document.querySelector(".sidebar-overlay");

        if (sidebar && overlay) {
            sidebar.classList.remove("show");
            overlay.classList.remove("show");
        }
    }
});
