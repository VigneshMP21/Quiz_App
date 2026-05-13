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