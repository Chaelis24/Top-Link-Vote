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

window.addEventListener("notify", (event) => {
    clearInterval(fakeProgressInterval);
});

window.addEventListener("notify", (event) => {
    let data = event.detail[0] || event.detail;

    Swal.fire({
        title: data.type === "success" ? "Success!" : "Oops!",
        text: data.message,
        icon: data.type,
        background: "rgba(20, 20, 30, 0.95)",
        color: "#ffffff",
        confirmButtonColor: "#388e3c",
        backdrop: `
                rgba(0,0,123,0.1)
                left top
                no-repeat
            `,
        customClass: {
            popup: "glass-card border-0 shadow-lg",
            title: "text-white fw-bold",
            htmlContainer: "text-white-50",
        },
    });
});
