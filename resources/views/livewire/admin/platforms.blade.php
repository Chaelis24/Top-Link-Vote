<?php

use function Livewire\Volt\{state, layout, title, middleware};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

layout('layouts.app');
title('Campaign Platforms - Admin');

state([
    'search' => '',
    // Dito mo i-ma-map ang data mula sa DB sa susunod
]);

// Logout function para sa sidebar
$logout = function () {
    Auth::guard('web')->logout();
    Session::invalidate();
    Session::regenerateToken();
    return $this->redirect('/', navigate: true);
};

// Function para sa publishing ng platform
$publishPlatform = function ($id) {
    // Logic para sa pag-publish (DB update)
    $this->dispatch('notify', message: 'Platform published successfully!', type: 'success');
};

?>

<div>
    {{-- Sidebar & UI Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar" data-aos="fade-down">
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
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
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

            {{-- Card 2: Maria Santos --}}
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card platform-card">
                    <div class="platform-header">
                        <div class="platform-avatar"
                            style="background: linear-gradient(135deg, var(--purple), var(--purple-glow));">MS</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">Maria Santos</h6>
                            <small class="text-white-50">President Candidate • Purple Wave</small>
                            <div class="mt-2">
                                <span class="platform-tag platform-tag-purple">Student Rights</span>
                                <span class="platform-tag platform-tag-purple">Community</span>
                                <span class="platform-tag platform-tag-green">Sustainability</span>
                            </div>
                        </div>
                        <span class="badge badge-status badge-open">Published</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-white-50 text-uppercase"
                            style="font-size: 0.7rem; letter-spacing: 1px;">Vision Statement</small>
                        <p class="mt-1 mb-0" style="font-size: 0.88rem; color: var(--text-secondary);">
                            "Empowering every student's voice through inclusive governance, sustainable initiatives, and
                            stronger community bonds."
                        </p>
                    </div>

                    <small class="text-white-50 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Key
                        Priorities</small>
                    <ul class="platform-priorities mt-1">
                        <li><i class="bi bi-check-circle-fill" style="color: var(--purple);"></i>Student rights advocacy
                            center</li>
                        <li><i class="bi bi-check-circle-fill" style="color: var(--purple);"></i>Campus sustainability
                            program</li>
                    </ul>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-glow btn-sm flex-fill"><i
                                class="bi bi-pencil me-1"></i>Edit</button>
                        <button class="btn btn-outline-glow btn-sm flex-fill"
                            style="border-color: var(--purple); color: var(--purple);" data-bs-toggle="modal"
                            data-bs-target="#previewPlatformModal"><i class="bi bi-eye me-1"></i>Preview</button>
                    </div>
                </div>
            </div>

            {{-- Card 3: Antonio Reyes (Draft) --}}
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="300">
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
                        <button class="btn btn-glow btn-sm flex-fill" wire:click="publishPlatform(3)"><i
                                class="bi bi-send me-1"></i>Publish</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Edit Platform Modal --}}
    <div class="modal fade modal-glass" id="editPlatformModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-megaphone-fill me-2"
                            style="color: var(--accent);"></i>Edit Platform</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Vision Statement</label>
                            <textarea class="form-control-glass w-100" rows="3">A transparent and innovative student government that prioritizes education quality and student welfare above all.</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Platform Tags (comma-separated)</label>
                            <input type="text" class="form-control-glass w-100"
                                value="Education, Transparency, Innovation">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Key Priorities (one per line)</label>
                            <textarea class="form-control-glass w-100" rows="5">Free academic tutoring services
Transparent budget allocation
Modern campus Wi-Fi upgrade
Mental health awareness programs</textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Preview Platform Modal --}}
    <div class="modal fade modal-glass" id="previewPlatformModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="platform-avatar"
                            style="width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, var(--purple), var(--purple-glow)); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 700; color: #fff;">
                            MS</div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Maria Santos — Platform Preview</h5>
                            <small class="text-white-50">President Candidate • Purple Wave</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="platform-tag platform-tag-purple">Student Rights</span>
                        <span class="platform-tag platform-tag-purple">Community</span>
                        <span class="platform-tag platform-tag-green">Sustainability</span>
                    </div>

                    <div
                        style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 16px; margin-bottom: 16px; border-left: 4px solid var(--purple);">
                        <h6 class="fw-bold mb-2" style="font-size: 0.85rem; color: var(--purple);"><i
                                class="bi bi-quote me-1"></i>Vision Statement</h6>
                        <p class="mb-0 fst-italic text-white-50" style="font-size: 0.88rem; line-height: 1.6;">
                            "Empowering every student's voice through inclusive governance, sustainable initiatives, and
                            stronger community bonds."</p>
                    </div>

                    <h6 class="fw-bold mb-3" style="font-size: 0.9rem;"><i class="bi bi-check-circle-fill me-2"
                            style="color: var(--purple);"></i>Key Priorities</h6>

                    <div
                        style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 14px; margin-bottom: 10px;">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-check-circle-fill" style="color: var(--purple); margin-top: 2px;"></i>
                            <div>
                                <div class="fw-semibold" style="font-size: 0.9rem;">Student Rights Advocacy Center
                                </div>
                                <small class="text-white-50">Establish a dedicated office for student rights support
                                    and legal guidance.</small>
                            </div>
                        </div>
                    </div>

                    <div class="glass p-3 mt-3" style="border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-white-50">Status</small>
                                <div><span class="badge badge-status badge-open">Published</span></div>
                            </div>
                            <div class="text-end">
                                <small class="text-white-50">Last Updated</small>
                                <div class="fw-medium" style="font-size: 0.85rem;">Feb 10, 2025</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        style="border-color: var(--purple); color: var(--purple);" data-bs-toggle="modal"
                        data-bs-target="#editPlatformModal">Edit Platform</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Draft Platform Modal --}}
    <div class="modal fade modal-glass" id="editDraftPlatformModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"
                            style="color: var(--warning);"></i>Edit Draft — Antonio Reyes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Vision Statement</label>
                            <textarea class="form-control-glass w-100" rows="3" placeholder="Enter vision statement..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Platform Tags</label>
                            <input type="text" class="form-control-glass w-100" value="Sports, Facilities">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm" wire:click="publishPlatform(3)">Save &
                        Publish</button>
                </div>
            </div>
        </div>
    </div>

</div>
