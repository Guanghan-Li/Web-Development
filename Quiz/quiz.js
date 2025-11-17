const TOTAL_QUESTIONS = 10;
const daySubjects = {
    0: "General",
    1: "Math",
    2: "Technology",
    3: "Famous People",
    4: "Famous Events",
    5: "History",
    6: "Science"
};


const heroHeader = document.getElementById('heroHeader');
const logoWrap = document.getElementById('logoWrap');
const logoImage = document.getElementById('logoImage');
const logoFallback = document.getElementById('logoFallback');
const subjectLine = document.getElementById('subjectLine');
const currentDateLine = document.getElementById('currentDate');
const startDescription = document.getElementById('startDescription');
const startBtn = document.getElementById('startBtn');
const restartBtn = document.getElementById('restartBtn');
const startScreen = document.getElementById('startScreen');
const quizScreen = document.getElementById('quizScreen');
const endScreen = document.getElementById('endScreen');
const hintBlock = document.getElementById('hintBlock');
const hintBtn = document.getElementById('hintBtn');
const hintText = document.getElementById('hintText');
const quizCard = document.querySelector('.quiz-card');
const questionCounter = document.getElementById('questionCounter');
const questionMeta = document.getElementById('questionMeta');
const questionText = document.getElementById('questionText');
const answerArea = document.getElementById('answerArea');
const submitBtn = document.getElementById('submitBtn');
const nextBtn = document.getElementById('nextBtn');
const stopBtn = document.getElementById('stopBtn');
const feedback = document.getElementById('feedback');
const scoreLine = document.getElementById('scoreLine');
const progressBar = document.getElementById('progressBar');
const progressDotsWrap = document.getElementById('progressDots');
const finalScore = document.getElementById('finalScore');
const finalPercent = document.getElementById('finalPercent');
const finalMessage = document.getElementById('finalMessage');
const currentYear = document.getElementById('currentYear');

const state = {
    questions: [],
    selectedQuestions: [],
    currentIndex: 0,
    answered: 0,
    score: 0,
    maxScore: 0,
    currentAnswer: null,
    results: Array(TOTAL_QUESTIONS).fill(null),
    locked: false,
    started: false,
    finished: false,
    dailySubject: ''
};

let dotElements = [];
let cardFlipTimeout = null;
let isCardAnimating = false;
const FLIP_MIDPOINT_MS = 300;

init();

function loadQuestions() {
    let data = [];
    try {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'questions.json', false);
        xhr.send(null);
        if (xhr.status === 0 || (xhr.status >= 200 && xhr.status < 400)) {
            data = JSON.parse(xhr.responseText);
        }
    } catch (error) {
        console.error('Could not load questions.json', error);
    }

    if (Array.isArray(data) && data.length > 0) {
        state.questions = data.slice();
        prepareGame();
    } else {
        startDescription.textContent = 'Unable to load questions. Make sure questions.json is next to quiz.html.';
        startBtn.disabled = true;
        startBtn.textContent = "Start Today's Quiz";
    }
}

function init() {
    const today = new Date();
    const dayIndex = today.getDay();
    state.dailySubject = daySubjects[dayIndex];
    heroHeader.classList.add(`day-${dayIndex}`);
    subjectLine.textContent = `Subject: ${state.dailySubject}`;
    currentDateLine.textContent = today.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    currentYear.textContent = today.getFullYear();

    if (logoImage) {
        const handleLogoError = () => {
            if (logoWrap) {
                logoWrap.classList.add('missing');
            }
            if (logoFallback) {
                logoFallback.textContent = 'SCU';
            }
        };
        logoImage.addEventListener('error', handleLogoError);
        if (logoImage.complete && logoImage.naturalWidth === 0) {
            handleLogoError();
        }
    }

    handleViewport();
    window.addEventListener('resize', handleViewport);

    buildDots();
    updateScoreDisplay();
    updateProgressBar();

    startBtn.disabled = true;
    startBtn.textContent = 'Loading questions...';

    loadQuestions();

    startBtn.addEventListener('click', () => {
        if (!state.selectedQuestions.length) return;
        startQuiz();
    });

    submitBtn.addEventListener('click', () => {
        if (state.locked || state.currentAnswer === null) return;
        gradeCurrentQuestion();
    });

    nextBtn.addEventListener('click', () => {
        if (!state.locked || isCardAnimating) return;
        if (state.currentIndex >= TOTAL_QUESTIONS - 1) {
            animateCardTransition(() => {
                showEndScreen(false);
            });
            return;
        }
        animateCardTransition(() => {
            state.currentIndex += 1;
            renderQuestion();
        });
    });

    stopBtn.addEventListener('click', () => {
        showEndScreen(true);
    });

    restartBtn.addEventListener('click', () => {
        prepareGame();
        showView('start');
    });

    hintBtn.addEventListener('click', revealHint);
}

function prepareGame() {
    state.selectedQuestions = buildDailySet(state.questions, state.dailySubject);
    state.maxScore = state.selectedQuestions.reduce((sum, q) => sum + q.score, 0);
    resetState();
    clearCardAnimation();
    startDescription.innerHTML = `${state.dailySubject} spotlight today.<br><br>10 Questions, picked randomly.`;
    startBtn.disabled = false;
    startBtn.textContent = "Start Today's Quiz";
    updateScoreDisplay();
    updateProgressBar();
    updateDots();
}

function resetState() {
    state.currentIndex = 0;
    state.answered = 0;
    state.score = 0;
    state.currentAnswer = null;
    state.results = Array(TOTAL_QUESTIONS).fill(null);
    state.locked = false;
    state.started = false;
    state.finished = false;
    feedback.textContent = '';
    feedback.className = 'feedback';
    nextBtn.disabled = true;
    submitBtn.disabled = true;
    nextBtn.textContent = 'Next Question';
}

function startQuiz() {
    state.started = true;
    state.finished = false;
    renderQuestion();
    showView('quiz');
}

function renderQuestion() {
    const question = state.selectedQuestions[state.currentIndex];
    if (!question) return;

    state.currentAnswer = null;
    state.locked = false;
    feedback.textContent = '';
    feedback.className = 'feedback';
    submitBtn.disabled = true;
    nextBtn.disabled = true;
    nextBtn.textContent = state.currentIndex === TOTAL_QUESTIONS - 1 ? 'See Results' : 'Next Question';

    questionCounter.textContent = `Question ${state.currentIndex + 1} of ${TOTAL_QUESTIONS}`;
    questionMeta.textContent = `Question Type: ${getTypeLabel(question.type)} - Points: ${question.score}`;
    questionText.textContent = question.question;

    setupHint(question);
    buildAnswerArea(question);
    updateDots();
    updateScoreDisplay();
}

function setupHint(question) {
    if (question.hint) {
        hintBlock.classList.remove('hidden');
        hintText.textContent = '';
        hintText.style.display = 'none';
        hintBtn.disabled = false;
        hintBtn.textContent = 'Show hint';
    } else {
        hintBlock.classList.add('hidden');
    }
}

function revealHint() {
    const question = state.selectedQuestions[state.currentIndex];
    if (!question || !question.hint || hintBtn.disabled) return;
    hintText.textContent = question.hint;
    hintText.style.display = 'block';
    hintBtn.disabled = true;
}

function buildAnswerArea(question) {
    answerArea.innerHTML = '';

    if (question.type === 1 || question.type === 3) {
        const buttons = [];
        question.choices.forEach(choice => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = choice;
            btn.addEventListener('click', () => {
                if (state.locked) return;
                buttons.forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                state.currentAnswer = choice;
                submitBtn.disabled = false;
            });
            buttons.push(btn);
            answerArea.appendChild(btn);
        });
    } else if (question.type === 2) {
        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = 'Type your answer';
        input.autocomplete = 'off';
        input.addEventListener('input', () => {
            state.currentAnswer = input.value.trim();
            submitBtn.disabled = state.currentAnswer.length === 0;
        });
        answerArea.appendChild(input);
    }
}

function gradeCurrentQuestion() {
    const question = state.selectedQuestions[state.currentIndex];
    if (!question) return;

    let isCorrect = false;

    if (question.type === 1 || question.type === 3) {
        isCorrect = state.currentAnswer === question.correctChoice;
    } else if (question.type === 2) {
        const userAnswer = (state.currentAnswer || '').trim().toLowerCase();
        if (Array.isArray(question.correctAnswer)) {
            isCorrect = question.correctAnswer.some(ans => ans.trim().toLowerCase() === userAnswer);
        } else {
            isCorrect = question.correctAnswer.trim().toLowerCase() === userAnswer;
        }
    }

    if (isCorrect) {
        state.score += question.score;
        feedback.textContent = 'Correct!';
        feedback.className = 'feedback correct';
    } else {
        const correctText = question.type === 2 ? formatTextAnswer(question.correctAnswer) : question.correctChoice;
        feedback.textContent = `Incorrect. Correct answer: ${correctText}`;
        feedback.className = 'feedback incorrect';
    }

    lockInputs();
    state.results[state.currentIndex] = isCorrect ? 'correct' : 'incorrect';
    state.answered += 1;
    state.locked = true;
    submitBtn.disabled = true;
    nextBtn.disabled = false;

    updateDots();
    updateScoreDisplay(true);
    updateProgressBar();
}

function lockInputs() {
    const buttons = answerArea.querySelectorAll('button');
    buttons.forEach(btn => btn.disabled = true);
    const input = answerArea.querySelector('input');
    if (input) {
        input.disabled = true;
    }
}

function showEndScreen(stoppedEarly) {
    if (!state.selectedQuestions.length) return;
    state.locked = true;
    state.finished = true;
    state.started = false;
    clearCardAnimation();
    updateDots();
    updateScoreDisplay(true);

    const percent = state.maxScore ? ((state.score / state.maxScore) * 100) : 0;
    const percentRounded = Math.round(percent);
    finalScore.textContent = `You scored ${state.score} out of ${state.maxScore} points.`;
    finalPercent.textContent = `Percentage: ${percentRounded}%`;
    finalMessage.textContent = getFinalMessage(percent, stoppedEarly);

    showView('end');
}

function getFinalMessage(percent, stoppedEarly) {
    if (percent <= 50) {
        return 'Nice try, you can do better! Try again!';
    }
    if (percent <= 80) {
        return 'Good Job, wanna try and improve your score?';
    }
    return 'Great job, you are super smart.';
}

function animateCardTransition(onMidpoint) {
    if (!quizCard) {
        onMidpoint();
        return;
    }

    if (cardFlipTimeout) {
        clearTimeout(cardFlipTimeout);
        cardFlipTimeout = null;
    }

    quizCard.classList.remove('flip-animating');
    void quizCard.offsetWidth;

    quizCard.classList.add('flip-animating');
    isCardAnimating = true;

    cardFlipTimeout = setTimeout(() => {
        onMidpoint();
    }, FLIP_MIDPOINT_MS);

    const handleAnimationEnd = () => {
        quizCard.classList.remove('flip-animating');
        isCardAnimating = false;
        quizCard.removeEventListener('animationend', handleAnimationEnd);
    };

    quizCard.addEventListener('animationend', handleAnimationEnd, { once: true });
}

function clearCardAnimation() {
    if (cardFlipTimeout) {
        clearTimeout(cardFlipTimeout);
        cardFlipTimeout = null;
    }
    if (quizCard) {
        quizCard.classList.remove('flip-animating');
    }
    isCardAnimating = false;
}

function showView(view) {
    startScreen.classList.toggle('hidden', view !== 'start');
    quizScreen.classList.toggle('hidden', view !== 'quiz');
    endScreen.classList.toggle('hidden', view !== 'end');
}

function getTypeLabel(type) {
    if (type === 1) return 'Multiple Choice';
    if (type === 2) return 'Text Input';
    return 'True / False';
}

function buildDailySet(allQuestions, subject) {
    const subjectQuestions = allQuestions.filter(q => q.subject === subject);
    const otherQuestions = allQuestions.filter(q => q.subject !== subject);

    const selected = shuffleArray([...subjectQuestions]).slice(0, TOTAL_QUESTIONS);
    const usedQuestions = selected.map(q => q.question);

    if (selected.length < TOTAL_QUESTIONS) {
        const extras = shuffleArray([...otherQuestions]);
        for (let i = 0; i < extras.length; i += 1) {
            const q = extras[i];
            if (usedQuestions.indexOf(q.question) !== -1) continue;
            selected.push(q);
            usedQuestions.push(q.question);
            if (selected.length === TOTAL_QUESTIONS) {
                break;
            }
        }
    }

    return selected.slice(0, TOTAL_QUESTIONS);
}

function shuffleArray(arr) {
    for (let i = arr.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

function updateDots() {
    dotElements.forEach((dot, index) => {
        dot.classList.remove('current', 'correct', 'incorrect');
        if (state.results[index] === 'correct') {
            dot.classList.add('correct');
        } else if (state.results[index] === 'incorrect') {
            dot.classList.add('incorrect');
        } else if (!state.finished && state.started && index === state.currentIndex) {
            dot.classList.add('current');
        }
    });
}

function buildDots() {
    progressDotsWrap.innerHTML = '';
    dotElements = [];
    for (let i = 0; i < TOTAL_QUESTIONS; i += 1) {
        const dot = document.createElement('div');
        dot.className = 'dot';
        progressDotsWrap.appendChild(dot);
        dotElements.push(dot);
    }
}

function updateScoreDisplay(pop = false) {
    const percent = state.maxScore ? ((state.score / state.maxScore) * 100) : 0;
    let questionNumber = 0;
    if (state.finished) {
        questionNumber = state.answered;
    } else if (state.started) {
        questionNumber = Math.min(state.currentIndex + 1, TOTAL_QUESTIONS);
    }
    const percentDisplay = Math.round(percent);
    scoreLine.textContent = `Score: ${state.score} / ${state.maxScore} (${percentDisplay}%) - Question ${questionNumber} / ${TOTAL_QUESTIONS}`;
    if (pop) {
        scoreLine.classList.remove('pop');
        void scoreLine.offsetWidth;
        scoreLine.classList.add('pop');
    }
}

function updateProgressBar() {
    const fraction = state.answered / TOTAL_QUESTIONS;
    progressBar.style.width = `${Math.min(fraction, 1) * 100}%`;
}

function formatTextAnswer(answer) {
    if (Array.isArray(answer)) {
        return answer.join(' / ');
    }
    return answer;
}
//So that the mobile scroll works.
function handleViewport() {
    const isSmall = window.innerHeight < 640 || window.innerWidth < 768;
    document.body.classList.toggle('mobile-scroll', isSmall);
}
