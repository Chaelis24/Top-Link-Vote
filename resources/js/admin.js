document.addEventListener("livewire:init", () => {
    Livewire.on("swal", (data) => {
        const options = data[0];
        Swal.fire({
            title: options.title || "Notification",
            text: options.text || "",
            icon: options.icon || "info",
            confirmButtonColor: "#1e3a8a",
            confirmButtonText: options.confirmButtonText || "OK",
        });
    });

    window.addEventListener("open-modal", (e) => {
        const modalElement = document.getElementById(e.detail.id);
        if (modalElement) {
            const m = new bootstrap.Modal(modalElement);
            m.show();
        }
    });

    window.addEventListener("close-modal", (e) => {
        const modalElement = document.getElementById(e.detail.id);
        const m = bootstrap.Modal.getInstance(modalElement);
        if (m) m.hide();
    });

    window.addEventListener("confirm-csv-import", (e) => {
        const rowCount =
            e.detail.count || (e.detail[0] ? e.detail[0].count : 0);

        Swal.fire({
            title: "Import Students?",
            text: `Found ${rowCount} rows. Do you want to proceed?`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#1e3a8a",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Yes, Import it!",
            allowOutsideClick: false,
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: "Importing...",
                    text: "Please wait while we process the records.",
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false,
                    showConfirmButton: false,
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
