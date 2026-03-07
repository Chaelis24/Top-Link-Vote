<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Campaign Platforms')] class extends Component {
    // Component State
    public string $search = '';

    /**
     * Handle logout logic for the sidebar.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }

    /**
     * Publish a candidate's platform.
     * In the future, $id will be used to find the Model in the DB.
     */
    public function publishPlatform(int $id)
    {
        // Logic for publishing (e.g., $platform = Platform::find($id); $platform->update(['status' => 'published']);)

        $this->dispatch('notify', message: 'Platform published successfully!', type: 'success');
    }

    /**
     * Save changes to a platform.
     */
    public function saveChanges()
    {
        // Add your validation and save logic here.

        $this->dispatch('notify', message: 'Changes saved successfully.', type: 'info');
    }
}; ?>

<div>
    {{-- Sidebar & UI Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Campaign <span>Platforms</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Review and manage candidate platforms</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-wrap" style="width: 250px;">
                    <i class="bi bi-search"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" class="search-glass"
                        placeholder="Search platforms...">
                </div>
            </div>
        </div>

        {{-- Platform Cards --}}
        <div class="row g-4">
            {{-- Card 1: Juan Dela Cruz --}}
            <div class="col-lg-6 fade-in-up delay-1">
                <div class="glass-card platform-card">
                    <div class="platform-header">
                        <div class="platform-avatar">JD</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">Juan Dela Cruz</h6>
                            <small class="text-white-50">President Candidate • Green Alliance</small>
                            <div class="mt-2">
                                <span class="platform-tag platform-tag-green">Education</span>
                                <span class="platform-tag platform-tag-green">Transparency</span>
                                <span class="platform-tag platform-tag-purple">Innovation</span>
                            </div>
                        </div>
                        <span class="badge badge-status badge-open">Published</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-white-50 text-uppercase"
                            style="font-size: 0.7rem; letter-spacing: 1px;">Vision Statement</small>
                        <p class="mt-1 mb-0" style="font-size: 0.88rem; color: var(--text-secondary);">
                            "A transparent and innovative student government that prioritizes education quality and
                            student welfare above all."
                        </p>
                    </div>

                    <small class="text-white-50 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Key
                        Priorities</small>
                    <ul class="platform-priorities mt-1">
                        <li><i class="bi bi-check-circle-fill"></i>Free academic tutoring services</li>
                        <li><i class="bi bi-check-circle-fill"></i>Transparent budget allocation</li>
                        <li><i class="bi bi-check-circle-fill"></i>Modern campus Wi-Fi upgrade</li>
                        <li><i class="bi bi-check-circle-fill"></i>Mental health awareness programs</li>
                    </ul>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-glow btn-sm flex-fill" data-bs-toggle="modal"
                            data-bs-target="#editPlatformModal"><i class="bi bi-pencil me-1"></i>Edit</button>
                        <button class="btn btn-outline-glow btn-sm flex-fill"
                            style="border-color: var(--purple); color: var(--purple);" data-bs-toggle="modal"
                            data-bs-target="#previewPlatformModal"><i class="bi bi-eye me-1"></i>Preview</button>
                    </div>
                </div>
            </div>

            {{-- Card 3: Antonio Reyes (Draft example with Publish button) --}}
            <div class="col-lg-6 fade-in-up delay-3">
                <div class="glass-card platform-card">
                    <div class="platform-header">
                        <div class="platform-avatar">AR</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">Antonio Reyes</h6>
                            <small class="text-white-50">Vice President Candidate • Green Alliance</small>
                            <div class="mt-2">
                                <span class="platform-tag platform-tag-green">Sports</span>
                                <span class="platform-tag platform-tag-warning">Facilities</span>
                            </div>
                        </div>
                        <span class="badge badge-status"
                            style="background: rgba(253,203,110,0.15); color: var(--warning);">Draft</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-white-50 text-uppercase"
                            style="font-size: 0.7rem; letter-spacing: 1px;">Vision Statement</small>
                        <p class="mt-1 mb-0 fst-italic" style="font-size: 0.88rem; color: rgba(255,255,255,0.4);">
                            No vision statement submitted yet.
                        </p>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-glow btn-sm flex-fill" data-bs-toggle="modal"
                            data-bs-target="#editDraftPlatformModal"><i class="bi bi-pencil me-1"></i>Edit</button>
                        {{-- Updated wire:click for class method --}}
                        <button class="btn btn-glow btn-sm flex-fill" wire:click="publishPlatform(3)"><i
                                class="bi bi-send me-1"></i>Publish</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Modals --}}
    {{-- Edit Platform Modal --}}
    <div class="modal fade modal-glass" id="editPlatformModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-megaphone-fill me-2" style="color: var(--accent);"></i>Edit
                        Platform</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="saveChanges">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Vision Statement</label>
                            <textarea class="form-control-glass w-100" rows="3">A transparent and innovative student government that prioritizes education quality and student welfare above all.</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Platform Tags (comma-separated)</label>
                            <input type="text" class="form-control-glass w-100"
                                value="Education, Transparency, Innovation">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" wire:click="saveChanges" class="btn btn-glow btn-sm"
                        data-bs-dismiss="modal">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>
