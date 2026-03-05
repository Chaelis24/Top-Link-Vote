<?php

use function Livewire\Volt\{state, layout, title, middleware};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

layout('layouts.app');
title('Election Cycle - Admin');

state([
    // Election Settings Toggle States
    'allowVoting' => true,
    'showResults' => false,
    'registrationOpen' => false,
    'emailNotifications' => true,

    // Cycle Data (Static placeholders)
    'progress' => 65,
    'currentCycle' => 'S.Y. 2024-2025 Election',
]);

// Logout function para sa sidebar
$logout = function () {
    Auth::guard('web')->logout();
    Session::invalidate();
    Session::regenerateToken();
    return $this->redirect('/', navigate: true);
};

// Toggle Handlers para sa Settings
$toggleSetting = function ($setting) {
    if (isset($this->{$setting})) {
        $this->{$setting} = !$this->{$setting};
        // Dito papasok ang DB update sa future (e.g., Setting::update(...))
    }
};

?>

<div>
    {{-- Sidebar & Overlay Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        {{-- Topbar --}}
        <div class="topbar" data-aos="fade-down">
            <div>
                <h2>Election <span>Cycle</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Configure election dates, phases, and settings
                </p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#newCycleModal">
                    <i class="bi bi-plus-lg me-1"></i>New Election Cycle
                </button>
            </div>
        </div>

        <div class="row g-4">
            {{-- Current Cycle Status --}}
            <div class="col-lg-8" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card cycle-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-calendar-event-fill me-2"
                                    style="color: var(--accent);"></i>{{ $currentCycle }}</h5>
                            <small class="text-white-50">Student Council General Election</small>
                        </div>
                        <span class="badge badge-status badge-open">
                            <span class="pulse-dot me-1"
                                style="background: var(--success); width: 8px; height: 8px;"></span>
                            Active
                        </span>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-white-50">Election Progress</small>
                            <small class="fw-bold" style="color: var(--accent);">{{ $progress }}%</small>
                        </div>
                        <div class="progress-slim">
                            <div class="bar"
                                style="width: {{ $progress }}%; background: linear-gradient(90deg, var(--accent), var(--purple));">
                            </div>
                        </div>
                    </div>

                    {{-- Timeline --}}
                    <div class="cycle-timeline">
                        <div class="cycle-step completed">
                            <div class="cycle-step-title" style="color: var(--accent);">Filing of Candidacy</div>
                            <div class="cycle-step-date">Jan 15 - Jan 31, 2025</div>
                            <small class="text-white-50">12 candidates filed</small>
                        </div>
                        <div class="cycle-step completed purple">
                            <div class="cycle-step-title" style="color: var(--purple);">Campaign Period</div>
                            <div class="cycle-step-date">Feb 1 - Feb 14, 2025</div>
                            <small class="text-white-50">All platforms submitted</small>
                        </div>
                        <div class="cycle-step active">
                            <div class="cycle-step-title" style="color: var(--accent);">Voting Period</div>
                            <div class="cycle-step-date">Feb 15 - Feb 28, 2025</div>
                            <small style="color: var(--accent);">🔴 Currently Active — 3 days remaining</small>
                        </div>
                        <div class="cycle-step upcoming">
                            <div class="cycle-step-title">Vote Counting & Results</div>
                            <div class="cycle-step-date">Mar 1 - Mar 3, 2025</div>
                            <small class="text-white-50">Automated tally</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Settings Panel --}}
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card cycle-card h-100">
                    <h5 class="fw-bold mb-4"><i class="bi bi-gear-fill me-2" style="color: var(--purple);"></i>Election
                        Settings</h5>

                    <div class="settings-item">
                        <div>
                            <div class="fw-medium" style="font-size: 0.9rem;">Allow Voting</div>
                            <small class="text-white-50">Enable/disable voting portal</small>
                        </div>
                        <button wire:click="toggleSetting('allowVoting')"
                            class="toggle-switch {{ $allowVoting ? 'active' : '' }}"></button>
                    </div>

                    <div class="settings-item">
                        <div>
                            <div class="fw-medium" style="font-size: 0.9rem;">Show Results</div>
                            <small class="text-white-50">Display live results to students</small>
                        </div>
                        <button wire:click="toggleSetting('showResults')"
                            class="toggle-switch {{ $showResults ? 'active' : '' }}"></button>
                    </div>

                    <div class="settings-item">
                        <div>
                            <div class="fw-medium" style="font-size: 0.9rem;">Registration Open</div>
                            <small class="text-white-50">Allow new candidate filing</small>
                        </div>
                        <button wire:click="toggleSetting('registrationOpen')"
                            class="toggle-switch {{ $registrationOpen ? 'active' : '' }}"></button>
                    </div>

                    <div class="settings-item">
                        <div>
                            <div class="fw-medium" style="font-size: 0.9rem;">Email Notifications</div>
                            <small class="text-white-50">Send vote confirmations</small>
                        </div>
                        <button wire:click="toggleSetting('emailNotifications')"
                            class="toggle-switch {{ $emailNotifications ? 'active' : '' }}"></button>
                    </div>

                    <div class="mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,0.05);">
                        <button class="btn btn-outline-glow btn-sm w-100 mb-2" data-bs-toggle="modal"
                            data-bs-target="#editDatesModal">
                            <i class="bi bi-pencil me-1"></i>Edit Dates
                        </button>
                        <button class="btn btn-outline-glow btn-sm w-100 text-danger border-danger"
                            wire:confirm="Are you sure you want to end this election cycle? This cannot be undone.">
                            <i class="bi bi-stop-circle me-1"></i>End Election
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Past Elections --}}
        <div class="glass-card p-4 mt-4" data-aos="fade-up" data-aos-delay="300">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"
                    style="color: var(--text-secondary);"></i>Past Elections</h5>
            <div class="table-responsive">
                <table class="table table-glass mb-0">
                    <thead>
                        <tr>
                            <th>Election</th>
                            <th>Period</th>
                            <th>Candidates</th>
                            <th>Total Votes</th>
                            <th>Turnout</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold text-white">S.Y. 2023-2024</td>
                            <td><small class="text-white-50">Feb 10 - Feb 24, 2024</small></td>
                            <td>10</td>
                            <td>1,102</td>
                            <td><span style="color: var(--accent);">82%</span></td>
                            <td><span class="badge badge-status badge-closed">Completed</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    {{-- New Cycle Modal --}}
    <div class="modal fade modal-glass" id="newCycleModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2" style="color: var(--accent);"></i>New
                        Election Cycle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Election Title</label>
                            <input type="text" class="form-control-glass w-100"
                                placeholder="e.g. S.Y. 2025-2026 Student Council Election">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Voting Start</label>
                                <input type="date" class="form-control-glass w-100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Voting End</label>
                                <input type="date" class="form-control-glass w-100">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Create Cycle</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Dates Modal --}}
    <div class="modal fade modal-glass" id="editDatesModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-range me-2"
                            style="color: var(--accent);"></i>Edit Dates</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white-50 small">Adjust the timeline for the current election cycle.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small">Campaign Period Start</label>
                            <input type="date" class="form-control-glass w-100" value="2025-02-01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white-50 small">Campaign Period End</label>
                            <input type="date" class="form-control-glass w-100" value="2025-02-14">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Update Timeline</button>
                </div>
            </div>
        </div>
    </div>

</div>
