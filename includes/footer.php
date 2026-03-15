    </div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<!-- Session Timeout Warning Modal -->
<div class="modal" id="sessionTimeoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Session Timeout Warning</h5>
            </div>
            <div class="modal-body">
                <p>Your session will expire in <strong><span id="timeoutCountdown">0:00</span></strong>.</p>
                <p class="small text-muted">Click "Stay Logged In" to continue working, or you will be logged out automatically.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="extendSessionBtn">
                    <i class="bi bi-arrow-clockwise"></i> Stay Logged In
                </button>
                <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/dashboard.js"></script>
<script>
/**
 * Session Timeout Management
 */
(function() {
    const TIMEOUT_CHECK_INTERVAL = 10000; // Check every 10 seconds
    const WARNING_THRESHOLD = 300000; // Show warning 5 minutes before timeout
    let sessionTimeoutModal = null;
    let countdownInterval = null;

    /**
     * Extend session via AJAX
     */
    function extendSession() {
        fetch('<?= BASE_URL ?>api/session.php?action=extend', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                console.log('Session extended');
                if (sessionTimeoutModal) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('sessionTimeoutModal'));
                    if (modal) modal.hide();
                }
                startTimeoutCheck();
            }
        })
        .catch(e => console.error('Session extend failed:', e));
    }

    /**
     * Check session timeout status
     */
    function checkSessionTimeout() {
        fetch('<?= BASE_URL ?>api/session.php?action=check-timeout', {
            headers: {
                'X-CSRF-Token': getCsrfToken()
            }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.valid) {
                // Session invalid - redirect to login
                window.location.href = '<?= BASE_URL ?>index.php?session_expired=1&reason=' + encodeURIComponent(data.reason);
                return;
            }

            if (data.show_warning && data.seconds_remaining > 0) {
                showTimeoutWarning(data.seconds_remaining);
            }
        })
        .catch(e => console.error('Timeout check failed:', e));
    }

    /**
     * Show timeout warning modal with countdown
     */
    function showTimeoutWarning(secondsRemaining) {
        const modal = document.getElementById('sessionTimeoutModal');
        if (!modal) return;

        sessionTimeoutModal = new bootstrap.Modal(modal);
        sessionTimeoutModal.show();

        // Start countdown
        clearInterval(countdownInterval);
        let remaining = secondsRemaining;

        countdownInterval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                // Session will expire
                return;
            }

            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            document.getElementById('timeoutCountdown').textContent = 
                minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        }, 1000);
    }

    /**
     * Get CSRF token from meta tag or cookie
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        
        const match = document.cookie.match(/csrf_token=([^;]+)/);
        return match ? match[1] : '';
    }

    /**
     * Start timeout checking
     */
    function startTimeoutCheck() {
        // Initial check
        checkSessionTimeout();
        
        // Check periodically
        setInterval(checkSessionTimeout, TIMEOUT_CHECK_INTERVAL);
    }

    /**
     * Track user activity to extend idle timeout
     */
    function trackUserActivity() {
        // Only send if warning isn't currently showing
        if (!sessionTimeoutModal) {
            extendSession();
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        startTimeoutCheck();

        // Extend session button
        const extendBtn = document.getElementById('extendSessionBtn');
        if (extendBtn) {
            extendBtn.addEventListener('click', extendSession);
        }

        // Track user activity (click, keyboard)
        let activityTimeout;
        function resetActivityTimer() {
            clearTimeout(activityTimeout);
            activityTimeout = setTimeout(trackUserActivity, 60000); // Send extend every 60 seconds of activity
        }

        document.addEventListener('click', resetActivityTimer);
        document.addEventListener('keypress', resetActivityTimer);
        window.addEventListener('focus', resetActivityTimer);

        // Start the timer
        resetActivityTimer();
    });
})();
</script>
</body>
</html>
