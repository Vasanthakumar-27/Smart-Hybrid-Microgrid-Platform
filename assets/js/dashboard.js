/**
 * Dashboard JS — Smart Microgrid Platform
 * Handles sidebar toggle, animations, and smooth interactions
 */

document.addEventListener('DOMContentLoaded', function () {

    // ========================================================================
    // Page Load Animation
    // ========================================================================
    document.body.style.opacity = '0';
    setTimeout(function () {
        document.body.style.transition = 'opacity 0.5s ease-out';
        document.body.style.opacity = '1';
    }, 50);

    // ========================================================================
    // Sidebar Toggle (Mobile)
    // ========================================================================
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (window.innerWidth < 768 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

    // ========================================================================
    // Auto-dismiss alerts after 5 seconds with fade-out animation
    // ========================================================================
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(function () {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) bsAlert.close();
            }, 500);
        }, 5000);
    });

    // ========================================================================
    // Smooth number counter animations
    // ========================================================================
    const animateNumbers = function () {
        const statValues = document.querySelectorAll('.stat-value');
        
        statValues.forEach(function (el) {
            const finalValue = el.textContent.trim();
            const numMatch = finalValue.match(/\d+(\.\d+)?/);
            
            if (numMatch) {
                const finalNum = parseFloat(numMatch[0]);
                const prefix = finalValue.substring(0, numMatch.index);
                const suffix = finalValue.substring(numMatch.index + numMatch[0].length);
                
                let currentNum = 0;
                const duration = 1500;
                const start = Date.now();
                
                const animate = function () {
                    const now = Date.now();
                    const progress = Math.min((now - start) / duration, 1);
                    currentNum = finalNum * progress;
                    
                    if (finalNum >= 1000) {
                        el.textContent = prefix + currentNum.toFixed(0) + suffix;
                    } else if (finalNum >= 1) {
                        el.textContent = prefix + currentNum.toFixed(1) + suffix;
                    } else {
                        el.textContent = prefix + currentNum.toFixed(2) + suffix;
                    }
                    
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    }
                };
                
                animate();
            }
        });
    };

    // Run animation on load and when page becomes visible
    animateNumbers();
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) animateNumbers();
    });

    // ========================================================================
    // Add hover elevation to interactive elements
    // ========================================================================
    document.querySelectorAll('.card, .btn, .stat-card, .microgrid-card').forEach(function (el) {
        el.addEventListener('mouseenter', function () {
            this.style.transition = 'transform 0.3s ease, box-shadow 0.3s ease';
        });
    });

    // ========================================================================
    // Scroll reveal animations
    // ========================================================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe cards for reveal animation
    document.querySelectorAll('.stat-card, .dashboard-card, .microgrid-card').forEach(function (el) {
        if (!el.style.opacity) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            observer.observe(el);
        }
    });

    // ========================================================================
    // Tooltip initialization
    // ========================================================================
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // ========================================================================
    // Active link highlighting with smooth scroll
    // ========================================================================
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            const target = document.querySelector(href);
            
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

});

// ============================================================================
// Chart.js Global Defaults (Dark Theme with Enhanced Styling)
// ============================================================================
if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.padding = 15;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';
    Chart.defaults.plugins.tooltip.titleColor = '#f1f5f9';
    Chart.defaults.plugins.tooltip.bodyColor = '#cbd5e1';
    Chart.defaults.plugins.tooltip.borderColor = 'rgba(255, 255, 255, 0.1)';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.displayColors = true;
    Chart.defaults.animation.duration = 800;
}
