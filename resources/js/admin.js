/**
 * Admin-wide Livewire event handlers.
 *
 * Listens for custom events dispatched from Volt components:
 * - swal: generic SweetAlert2 notifications
 * - open-modal / close-modal: Bootstrap modal controls
 * - confirm-csv-import: CSV import confirmation flow
 */
document.addEventListener("livewire:init", () => {
    // Generic SweetAlert2 notification handler
    Livewire.on("swal", (data) => {
        const options = data[0];
        const isMobile = window.innerWidth < 480;
        Swal.fire({
            title: options.title || "Notification",
            text: options.text || "",
            icon: options.icon || "info",
            width: isMobile ? "90%" : options.width || "400px",
            confirmButtonColor: "#1e3a8a",
            confirmButtonText: options.confirmButtonText || "OK",
            padding: options.padding || "1rem",
            customClass: {
                title: isMobile ? "fs-6" : "fs-5",
                htmlContainer: isMobile ? "fs-6" : "fs-6",
                confirmButtonText: "btn btn-sm px-4",
            },
        }).then((result) => {
            if (result.isConfirmed) {
                if (data.redirect) {
                    Livewire.navigate(data.redirect);
                }
            }
        });
    });

    // Open a Bootstrap modal by ID
    window.addEventListener("open-modal", (e) => {
        const modalElement = document.getElementById(e.detail.id);
        if (modalElement) {
            const m = new bootstrap.Modal(modalElement);
            m.show();
        }
    });

    // Close a Bootstrap modal by ID and clean up backdrops
    window.addEventListener("close-modal", (event) => {
        let modal = document.getElementById(event.detail.id);
        let modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }

        document
            .querySelectorAll(".modal-backdrop")
            .forEach((el) => el.remove());
        document.body.classList.remove("modal-open");
        document.body.style.overflow = "";
        document.body.style.paddingRight = "";
    });

    // CSV import confirmation dialog
    window.addEventListener("confirm-csv-import", (e) => {
        const rowCount =
            e.detail.count || (e.detail[0] ? e.detail[0].count : 0);
        const isMobile = window.innerWidth < 480;
        Swal.fire({
            title: "Import Students?",
            text: `Found ${rowCount} rows. Do you want to proceed?`,
            icon: "question",
            width: isMobile ? "90%" : "400px",
            showCancelButton: true,
            confirmButtonColor: "#1e3a8a",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Yes, Import it!",
            allowOutsideClick: false,
            padding: "1rem",
            customClass: {
                title: isMobile ? "fs-6" : "fs-5",
                htmlContainer: isMobile ? "fs-6" : "fs-6",
                confirmButtonText: "btn btn-sm px-4",
            },
        }).then((result) => {
            if (result.isConfirmed) {
                const isMobile = window.innerWidth < 480;
                Swal.fire({
                    title: "Importing...",
                    text: "Please wait while we process the records.",
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    width: isMobile ? "90%" : "400px",
                    padding: "1rem",
                    customClass: {
                        title: isMobile ? "fs-6" : "fs-5",
                        htmlContainer: isMobile ? "fs-6" : "fs-6",
                        confirmButtonText: "btn btn-sm px-4",
                    },
                });

                const lwElement = document.querySelector("[wire\\:id]");
                if (lwElement) {
                    const component = Livewire.find(
                        lwElement.getAttribute("wire:id"),
                    );
                    if (component) {
                        component.processImport();
                    } else {
                        Swal.fire(
                            "Error",
                            "Livewire component not found.",
                            "error",
                        );
                    }
                } else {
                    console.error("No Livewire component found on this page.");
                }
            }
        });
    });
});
