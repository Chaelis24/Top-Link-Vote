/**
 * Application entry point.
 *
 * Imports ApexCharts for real-time tally charts and
 * sets up Livewire navigation event listeners that
 * clean up stale modal backdrops on page transitions.
 */
import ApexCharts from "apexcharts";
import "./bootstrap";
window.ApexCharts = ApexCharts;

// Clean up Bootstrap modal artifacts before Livewire navigation starts
document.addEventListener("livewire:navigating", () => {
    const backdrops = document.querySelectorAll(".modal-backdrop");
    backdrops.forEach((backdrop) => backdrop.remove());

    document.body.classList.remove("modal-open");
    document.body.style.overflow = "";
    document.body.style.paddingRight = "";
});

// Final cleanup after navigation completes
document.addEventListener("livewire:navigated", () => {
    if (!document.querySelector(".modal.show")) {
        document.body.classList.remove("modal-open");
        const remainingBackdrops = document.querySelectorAll(".modal-backdrop");
        remainingBackdrops.forEach((b) => b.remove());
    }
});
