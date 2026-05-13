// General utility functions
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any general JavaScript functionality
    console.log('Quiz App initialized');
    
    // Form validation example
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'red';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields.');
            }
        });
    });
    
    // Quiz timer functionality (if on quiz page)
    if (document.getElementById('quizTimer')) {
        let timeLeft = parseInt(document.getElementById('quizTimer').textContent.match(/\d+/g).join(''));
        const timerElement = document.getElementById('quizTimer');
        const quizForm = document.getElementById('quizForm');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `Time Left: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('Time is up! Your quiz will be submitted automatically.');
                quizForm.submit();
            }
            
            timeLeft--;
        }
        
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
    }
});

// Function to handle quiz navigation
function navigateToQuestion(questionNumber) {
    const questionElement = document.getElementById(`q${questionNumber}`);
    if (questionElement) {
        questionElement.scrollIntoView({ behavior: 'smooth' });
    }
}

// Function to handle quiz submission with confirmation
function confirmQuizSubmission() {
    const unanswered = document.querySelectorAll('.question-card input[type="radio"]:not(:checked)').length;
    if (unanswered > 0) {
        return confirm(`You have ${unanswered} unanswered questions. Are you sure you want to submit?`);
    }
    return true;
}

// ============================================
// ===== Register Page Script =====
// ============================================
(function(){
    'use strict';

    // Only run on register page
    if (!document.querySelector('.auth-wrapper')) return;

    // --- Particles ---
    const canvas = document.getElementById('particles-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let particles = [];
        let animId;

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        class Particle {
            constructor() { this.reset(); }
            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 0.3;
                this.speedX = (Math.random() - 0.5) * 0.3;
                this.speedY = (Math.random() - 0.5) * 0.3;
                this.opacity = Math.random() * 0.5 + 0.1;
                this.color = ['rgba(106,17,203,', 'rgba(37,117,252,', 'rgba(0,245,255,'][Math.floor(Math.random() * 3)];
            }
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) this.reset();
            }
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = this.color + this.opacity + ')';
                ctx.fill();
            }
        }

        function initParticles(count) {
            particles = [];
            for (let i = 0; i < count; i++) particles.push(new Particle());
        }

        function drawLines() {
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 140) {
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = `rgba(106,17,203,${0.05 * (1 - dist / 140)})`;
                        ctx.lineWidth = 0.5;
                        ctx.stroke();
                    }
                }
            }
        }

        function animateParticles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => { p.update(); p.draw(); });
            drawLines();
            animId = requestAnimationFrame(animateParticles);
        }

        const count = Math.min(80, Math.floor((window.innerWidth * window.innerHeight) / 15000));
        initParticles(count);
        animateParticles();

        window.addEventListener('resize', () => {
            resizeCanvas();
            initParticles(count);
        });
    }

    // --- Mouse glow ---
    const glow = document.getElementById('mouse-glow');
    if (glow) {
        document.addEventListener('mousemove', e => {
            glow.style.left = e.clientX + 'px';
            glow.style.top = e.clientY + 'px';
        });
    }

    // --- Password strength ---
    const pwInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    if (pwInput) {
        pwInput.addEventListener('input', function() {
            const val = this.value;
            let strength = 0;
            if (val.length >= 6) strength++;
            if (val.length >= 10) strength++;
            if (/[a-z]/.test(val) && /[A-Z]/.test(val)) strength++;
            if (/\d/.test(val)) strength++;
            if (/[^a-zA-Z0-9]/.test(val)) strength++;

            strengthBar.className = 'strength-bar';
            if (val.length === 0) {
                strengthBar.style.width = '0%';
                strengthText.textContent = '';
                return;
            }

            let label = '';
            if (strength <= 1) {
                strengthBar.classList.add('weak');
                label = 'Weak';
            } else if (strength === 2) {
                strengthBar.classList.add('medium');
                label = 'Medium';
            } else if (strength <= 4) {
                strengthBar.classList.add('strong');
                label = 'Strong';
            } else {
                strengthBar.classList.add('very-strong');
                label = 'Very Strong';
            }
            strengthText.textContent = 'Password strength: ' + label;
        });
    }

    // --- Password toggle ---
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            if (!input) return;
            const icon = this.querySelector('i');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    });

    // --- Floating labels "has-value" helper ---
    document.querySelectorAll('.floating-label input').forEach(input => {
        const check = () => input.classList.toggle('has-value', input.value.trim() !== '');
        check();
        input.addEventListener('input', check);
        input.addEventListener('blur', check);
    });

    // --- Real-time validation ---
    const form = document.querySelector('.auth-form');
    const fields = {
        username: { regex: /^[a-zA-Z0-9_]{3,20}$/, msg: '3-20 chars, letters, numbers, underscores' },
        email: { regex: /^[^\s@]+@gmail\.com$/i, msg: 'Please provide a valid @gmail.com address' },
        password: { min: 6, msg: 'At least 6 characters' },
        confirm_password: { match: 'password', msg: 'Passwords must match' }
    };

    function validateField(input) {
        if (input.type === 'checkbox') return;
        const group = input.closest('.form-group');
        if (!group) return;
        const msgEl = group.querySelector('.validation-msg');
        if (!msgEl) return;

        const val = input.value.trim();
        const name = input.name;
        const rule = fields[name];
        let isValid = true;
        let errorMsg = '';

        if (val.length === 0 && input.required) {
            isValid = false;
            errorMsg = 'This field is required';
        } else if (name === 'username' && rule) {
            isValid = rule.regex.test(val);
            if (!isValid) errorMsg = rule.msg;
        } else if (name === 'email' && rule) {
            isValid = rule.regex.test(val);
            if (!isValid) errorMsg = rule.msg;
        } else if (name === 'password') {
            isValid = val.length >= (rule.min || 6);
            if (!isValid) errorMsg = rule.msg;
            const cf = document.getElementById('confirm_password');
            if (cf && cf.value.trim()) validateField(cf);
        } else if (name === 'confirm_password') {
            const pw = document.getElementById('password');
            isValid = val.length > 0 && pw && val === pw.value;
            if (!isValid && val.length > 0) errorMsg = rule.msg;
        }

        group.classList.remove('error', 'success', 'shake');
        msgEl.classList.remove('show', 'error', 'success');

        if (val.length === 0 && !input.required) return;

        if (isValid) {
            group.classList.add('success');
            msgEl.className = 'validation-msg show success';
            msgEl.innerHTML = '<i class="fas fa-check-circle"></i> Looks good!';
        } else {
            group.classList.add('error', 'shake');
            msgEl.className = 'validation-msg show error';
            msgEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + errorMsg;
            setTimeout(() => group.classList.remove('shake'), 400);
        }
    }

    document.querySelectorAll('.auth-form input').forEach(input => {
        if (input.type === 'checkbox') return;
        
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            const group = this.closest('.form-group');
            if (group && (group.classList.contains('error') || group.classList.contains('success'))) {
                validateField(this);
            }
        });
    });

    // --- Button ripple ---
    document.querySelectorAll('[data-ripple]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // --- Animated counter ---
    function animateCounter(el) {
        const target = parseInt(el.dataset.count) || 0;
        const suffix = el.textContent.replace(/[\d]/g, '').trim();
        el.textContent = '0' + suffix;
        let current = 0;
        const step = Math.ceil(target / 40);
        const interval = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(interval);
            }
            el.textContent = current + suffix;
        }, 40);
    }

    const counterObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.stat-number[data-count]').forEach(el => counterObserver.observe(el));

    // --- Scroll to error alert ---
    const alertEl = document.querySelector('.error-alert');
    if (alertEl) {
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // --- Auto-dismiss error alerts after 4.5s ---
    if (alertEl) {
        setTimeout(() => {
            alertEl.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alertEl.style.opacity = '0';
            alertEl.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                if (alertEl.parentNode) alertEl.remove();
            }, 500);
        }, 4500);
    }

    // --- Success overlay OK button ---
    const overlay = document.getElementById('successOverlay');
    const goBtn = document.getElementById('goToLoginBtn');
    if (overlay && goBtn) {
        goBtn.addEventListener('click', function() {
            window.location.href = 'login.php';
        });
    }

})();