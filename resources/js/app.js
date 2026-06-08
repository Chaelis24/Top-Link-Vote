import ApexCharts from "apexcharts";
import "./bootstrap";
window.ApexCharts = ApexCharts;

document.addEventListener("livewire:navigating", () => {
    const backdrops = document.querySelectorAll(".modal-backdrop");
    backdrops.forEach((backdrop) => backdrop.remove());

    document.body.classList.remove("modal-open");
    document.body.style.overflow = "";
    document.body.style.paddingRight = "";
});

document.addEventListener("livewire:navigated", () => {
    if (!document.querySelector(".modal.show")) {
        document.body.classList.remove("modal-open");
        const remainingBackdrops = document.querySelectorAll(".modal-backdrop");
        remainingBackdrops.forEach((b) => b.remove());
    }
});
