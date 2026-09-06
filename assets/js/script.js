/**
 * MediQueue - Interactive Frontend & Real-time AJAX Utilities
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Sidebar Toggle on Mobile
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    let backdrop = document.querySelector('.sidebar-backdrop');

    if (!backdrop && sidebar) {
        backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('show');
        });

        if (backdrop) {
            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
            });
        }
    }

    // 2. Auto-dismiss Bootstrap Alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 6000);
    });

    // 3. Demo Login Autofill Switcher
    const demoButtons = document.querySelectorAll('.btn-demo-login');
    demoButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const role = btn.getAttribute('data-role');
            const emailInput = document.getElementById('loginEmail');
            const passwordInput = document.getElementById('loginPassword');
            const roleSelect = document.getElementById('loginRole');

            if (role === 'admin') {
                if (emailInput) emailInput.value = 'admin@mediqueue.com';
                if (passwordInput) passwordInput.value = 'admin123';
                if (roleSelect) roleSelect.value = 'admin';
            } else if (role === 'doctor') {
                if (emailInput) emailInput.value = 'doctor@mediqueue.com';
                if (passwordInput) passwordInput.value = 'doctor123';
                if (roleSelect) roleSelect.value = 'doctor';
            } else if (role === 'patient') {
                if (emailInput) emailInput.value = 'patient@mediqueue.com';
                if (passwordInput) passwordInput.value = 'patient123';
                if (roleSelect) roleSelect.value = 'patient';
            }
        });
    });

    // 4. Live Virtual Queue Real-time Polling for Patient
    const liveQueueBox = document.getElementById('patientLiveQueue');
    if (liveQueueBox) {
        const queueId = liveQueueBox.getAttribute('data-queue-id');
        const baseUrl = liveQueueBox.getAttribute('data-base-url') || '';
        let lastStatus = liveQueueBox.getAttribute('data-status');

        const pollQueueStatus = () => {
            fetch(`${baseUrl}/ajax/queue_status.php?queue_id=${queueId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const q = data.queue;

                        // Check if doctor called the patient
                        if (lastStatus !== 'called' && q.status === 'called') {
                            playCallNotificationChime();
                            showCalledModal(q);
                        }
                        lastStatus = q.status;

                        // Update DOM elements
                        const posEl = document.getElementById('queuePositionDisplay');
                        const aheadEl = document.getElementById('patientsAheadDisplay');
                        const waitEl = document.getElementById('estimatedWaitDisplay');
                        const badgeEl = document.getElementById('queueStatusBadgeDisplay');
                        const bannerEl = document.getElementById('queueStatusBanner');

                        if (posEl) posEl.textContent = '#' + q.queue_position;
                        if (aheadEl) aheadEl.textContent = q.patients_ahead;
                        if (waitEl) waitEl.textContent = q.estimated_waiting_time + ' Mins';
                        if (badgeEl) badgeEl.innerHTML = q.badge_html;

                        if (bannerEl) {
                            if (q.status === 'called') {
                                bannerEl.className = 'alert alert-primary alert-dismissible fade show d-flex align-items-center mb-4';
                                bannerEl.innerHTML = `
                                    <i class="fa-solid fa-bullhorn fa-2x me-3 text-primary"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1 fw-bold">Doctor is Calling You!</h5>
                                        <p class="mb-0">Dr. <strong>${q.doctor_name}</strong> is ready for your consultation in <strong>${q.room_number || 'Consultation Room'}</strong>. Please proceed now.</p>
                                    </div>
                                `;
                                bannerEl.classList.remove('d-none');
                            } else if (q.status === 'in_consultation') {
                                bannerEl.className = 'alert alert-info alert-dismissible fade show d-flex align-items-center mb-4';
                                bannerEl.innerHTML = `
                                    <i class="fa-solid fa-stethoscope fa-2x me-3 text-info"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1 fw-bold">In Consultation</h5>
                                        <p class="mb-0">Your consultation is currently in progress with Dr. ${q.doctor_name}.</p>
                                    </div>
                                `;
                                bannerEl.classList.remove('d-none');
                            } else if (q.status === 'completed') {
                                bannerEl.className = 'alert alert-success alert-dismissible fade show d-flex align-items-center mb-4';
                                bannerEl.innerHTML = `
                                    <i class="fa-solid fa-circle-check fa-2x me-3 text-success"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1 fw-bold">Consultation Completed</h5>
                                        <p class="mb-0">Thank you for visiting MediQueue. Your consultation has ended.</p>
                                    </div>
                                `;
                                bannerEl.classList.remove('d-none');
                            }
                        }
                    }
                })
                .catch(err => console.error('Queue poll error:', err));
        };

        // Poll every 4 seconds
        setInterval(pollQueueStatus, 4000);
    }

    // 5. Dynamic Slot Loading in Appointment Booking
    const doctorSelect = document.getElementById('bookingDoctor');
    const dateInput = document.getElementById('bookingDate');
    const slotsContainer = document.getElementById('availableSlotsContainer');
    const baseUrlInput = document.getElementById('appBaseUrl');

    function loadAvailableSlots() {
        if (!doctorSelect || !dateInput || !slotsContainer) return;

        const doctorId = doctorSelect.value;
        const selectedDate = dateInput.value;
        const baseUrl = baseUrlInput ? baseUrlInput.value : '';

        if (!doctorId || !selectedDate) {
            slotsContainer.innerHTML = '<p class="text-muted small my-2"><i class="fa-solid fa-circle-info me-1"></i> Please select both a doctor and a date to view available slots.</p>';
            return;
        }

        slotsContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div> Loading available slots...</div>';

        fetch(`${baseUrl}/ajax/get_slots.php?doctor_id=${doctorId}&date=${selectedDate}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.slots && data.slots.length > 0) {
                    let html = '<div class="slot-grid">';
                    data.slots.forEach(slot => {
                        const disabled = slot.is_booked ? 'disabled' : '';
                        const title = slot.is_booked ? 'Already Booked' : 'Available';
                        const disabledClass = slot.is_booked ? 'disabled' : '';

                        html += `
                            <div>
                                <input type="radio" name="appointment_time" value="${slot.time}" id="slot_${slot.time.replace(':', '_')}" class="slot-radio" ${disabled} required>
                                <label for="slot_${slot.time.replace(':', '_')}" class="slot-label ${disabledClass}" title="${title}">
                                    ${slot.formatted}
                                </label>
                            </div>
                        `;
                    });
                    html += '</div>';
                    slotsContainer.innerHTML = html;
                } else {
                    slotsContainer.innerHTML = `<div class="alert alert-warning py-2 px-3 small"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${data.message || 'No available slots for this date.'}</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                slotsContainer.innerHTML = '<div class="alert alert-danger py-2 px-3 small">Failed to load time slots. Please try again.</div>';
            });
    }

    if (doctorSelect && dateInput) {
        doctorSelect.addEventListener('change', loadAvailableSlots);
        dateInput.addEventListener('change', loadAvailableSlots);
    }
});

// Synthesize pleasant chime using Web Audio API
function playCallNotificationChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc1.frequency.setValueAtTime(880, ctx.currentTime + 0.15); // A5

        gain1.gain.setValueAtTime(0.3, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);

        osc1.connect(gain1);
        gain1.connect(ctx.destination);

        osc1.start();
        osc1.stop(ctx.currentTime + 0.8);
    } catch (e) {
        console.log('Audio autoplay prevented:', e);
    }
}

// Modal for called notification
function showCalledModal(queue) {
    const modalHtml = `
        <div class="modal fade" id="calledAlertModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-bullhorn me-2"></i> It's Your Turn!</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <div class="step-circle mb-3 bg-primary text-white" style="width: 70px; height: 70px; font-size: 2rem;">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Queue #${queue.queue_number}</h4>
                        <p class="text-muted mb-3">Your appointment with <strong>${queue.doctor_name}</strong> is now ready.</p>
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <span class="text-muted d-block small">Proceed to</span>
                            <span class="fs-5 fw-bold text-primary">${queue.room_number || 'Room 101'}</span>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-primary px-4 fw-semibold" data-bs-dismiss="modal">I'm On My Way</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing if any
    const existing = document.getElementById('calledAlertModal');
    if (existing) existing.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalElem = document.getElementById('calledAlertModal');
    const bsModal = new bootstrap.Modal(modalElem);
    bsModal.show();
}
