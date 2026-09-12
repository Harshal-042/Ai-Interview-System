/**
 * Candidate Continuous Interview Handler
 * Pure Vanilla JavaScript - No external frameworks
 */

(function () {
    const config = window.INTERVIEW_CONFIG || {};
    const questions = config.questions || [];
    const sessionId = config.sessionId;

    let mediaStream = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let currentQuestionIndex = 0;
    let timerInterval = null;
    let elapsedSeconds = 0;
    let speechRecognition = null;
    let fullTranscript = '';

    // DOM Elements
    const setupPhase = document.getElementById('setupPhase');
    const interviewPhase = document.getElementById('interviewPhase');
    const processingPhase = document.getElementById('processingPhase');

    const setupPreview = document.getElementById('setupPreview');
    const cameraPlaceholder = document.getElementById('cameraPlaceholder');
    const enableMediaBtn = document.getElementById('enableMediaBtn');
    const startInterviewBtn = document.getElementById('startInterviewBtn');
    const deviceStatus = document.getElementById('deviceStatus');

    const liveVideo = document.getElementById('liveVideo');
    const timerDisplay = document.getElementById('timerDisplay');
    const questionProgressBadge = document.getElementById('questionProgressBadge');
    const questionNumberLabel = document.getElementById('questionNumberLabel');
    const currentQuestionText = document.getElementById('currentQuestionText');
    const replayVoiceBtn = document.getElementById('replayVoiceBtn');
    const ttsStatus = document.getElementById('ttsStatus');
    const liveTranscript = document.getElementById('liveTranscript');
    const nextQuestionBtn = document.getElementById('nextQuestionBtn');

    const progressBar = document.getElementById('progressBar');
    const processingStatusText = document.getElementById('processingStatusText');

    // 1. Enable Camera & Microphone
    enableMediaBtn.addEventListener('click', async () => {
        try {
            deviceStatus.textContent = 'Requesting camera & microphone access...';
            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: true
            });

            setupPreview.srcObject = mediaStream;
            cameraPlaceholder.style.display = 'none';
            setupPreview.style.display = 'block';

            deviceStatus.innerHTML = '<span style="color: var(--success); font-weight: 600;">&#10003; Camera & Microphone Connected</span>';
            enableMediaBtn.disabled = true;
            startInterviewBtn.disabled = false;
        } catch (err) {
            console.error('Media error:', err);
            deviceStatus.innerHTML = `<span style="color: var(--danger);">Permission error: ${err.message || 'Unable to access media devices'}</span>`;
        }
    });

    // 2. Real local Piper TTS / Voice Question Delivery
    let currentQuestionAudio = null;
    async function speakQuestion(text) {
        try {
            if (currentQuestionAudio) { currentQuestionAudio.pause(); currentQuestionAudio = null; }
            ttsStatus.textContent = 'Loading local Piper voice...';
            const response = await fetch('../api/tts.php?text=' + encodeURIComponent(text));
            if (!response.ok) {
                const info = await response.json().catch(() => ({}));
                throw new Error(info.error || 'Piper TTS is unavailable');
            }
            const blob = await response.blob();
            currentQuestionAudio = new Audio(URL.createObjectURL(blob));
            currentQuestionAudio.onended = () => { ttsStatus.textContent = ''; };
            await currentQuestionAudio.play();
        } catch (error) {
            console.error('Piper TTS error:', error);
            ttsStatus.textContent = 'Question voice unavailable: ' + error.message;
        }
    }

    replayVoiceBtn.addEventListener('click', () => {
        if (questions[currentQuestionIndex]) {
            speakQuestion(questions[currentQuestionIndex].question);
        }
    });

    // 4. Start Continuous Interview
    startInterviewBtn.addEventListener('click', () => {
        if (!mediaStream) return;

        // Notify server interview is In Progress
        try {
            const startForm = new URLSearchParams();
            startForm.append('session_id', sessionId);
            fetch('../api/start.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: startForm.toString()
            }).catch(e => console.warn('Could not update start status:', e));
        } catch (e) {}

        // Switch UI phase
        setupPhase.style.display = 'none';
        interviewPhase.style.display = 'block';
        liveVideo.srcObject = mediaStream;

        // Initialize MediaRecorder for ONE continuous recording
        recordedChunks = [];
        let mimeType = 'video/webm;codecs=vp8,opus';
        if (!MediaRecorder.isTypeSupported(mimeType)) {
            mimeType = MediaRecorder.isTypeSupported('video/mp4') ? 'video/mp4' : '';
        }

        const options = mimeType ? { mimeType } : {};
        mediaRecorder = new MediaRecorder(mediaStream, options);

        mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };

        mediaRecorder.start(1000); // Collect in 1s slices

        // Start elapsed timer
        elapsedSeconds = 0;
        timerInterval = setInterval(() => {
            elapsedSeconds++;
            const mins = String(Math.floor(elapsedSeconds / 60)).padStart(2, '0');
            const secs = String(elapsedSeconds % 60).padStart(2, '0');
            timerDisplay.textContent = `${mins}:${secs}`;
        }, 1000);

        // Display and speak Question 1
        loadQuestion(0);
    });

    // 5. Load Question
    function loadQuestion(index) {
        currentQuestionIndex = index;
        const q = questions[index];
        if (!q) return;

        questionNumberLabel.textContent = `Question ${index + 1}`;
        currentQuestionText.textContent = q.question;
        questionProgressBadge.textContent = `Question ${index + 1} of ${questions.length}`;

        if (index === questions.length - 1) {
            nextQuestionBtn.textContent = 'Finish & Submit Interview';
            nextQuestionBtn.classList.remove('btn-primary');
            nextQuestionBtn.classList.add('btn-danger');
        } else {
            nextQuestionBtn.textContent = 'Next Question \u2192';
            nextQuestionBtn.classList.remove('btn-danger');
            nextQuestionBtn.classList.add('btn-primary');
        }

        // Voice question delivery via Piper TTS
        speakQuestion(q.question);
    }

    // 6. Next Question / Finish Interview
    nextQuestionBtn.addEventListener('click', () => {
        if (currentQuestionIndex < questions.length - 1) {
            // Move to next question - RECORDING CONTINUES UNINTERRUPTED!
            loadQuestion(currentQuestionIndex + 1);
        } else {
            // Finish Interview - Stop continuous recording and submit
            finishInterview();
        }
    });

    // 7. Finish Interview & Upload
    async function finishInterview() {
        clearInterval(timerInterval);
        if (currentQuestionAudio) { currentQuestionAudio.pause(); }

        // Switch to processing phase
        interviewPhase.style.display = 'none';
        processingPhase.style.display = 'block';
        progressBar.style.width = '30%';
        processingStatusText.textContent = 'Finalizing continuous video recording...';

        mediaRecorder.onstop = async () => {
            // Release media devices
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
            }

            const videoBlob = new Blob(recordedChunks, { type: recordedChunks[0]?.type || 'video/webm' });

            progressBar.style.width = '55%';
            processingStatusText.textContent = 'Uploading single continuous video recording...';

            const formData = new FormData();
            formData.append('session_id', sessionId);
            formData.append('video', videoBlob, `interview_${sessionId}.webm`);

            try {
                // Upload recording
                try {
                    const uploadRes = await fetch('../api/upload.php', {
                        method: 'POST',
                        body: formData
                    });
                    const uploadData = await uploadRes.json();
                    if (!uploadData.success) {
                        console.warn('Video upload notice:', uploadData.error);
                    }
                } catch (uploadErr) {
                    console.warn('Video upload warning:', uploadErr);
                }

                progressBar.style.width = '75%';
                processingStatusText.textContent = 'Executing FFmpeg, Whisper, and Ollama AI evaluation...';

                // Process AI evaluation & video analysis (Updates status to Completed)
                const processForm = new URLSearchParams();
                processForm.append('session_id', sessionId);

                const processRes = await fetch('../api/process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: processForm.toString()
                });
                const processData = await processRes.json();
                if (!processRes.ok || !processData.success) { throw new Error(processData.error || 'Interview processing failed'); }

                progressBar.style.width = '100%';
                processingStatusText.textContent = 'Submission complete! Redirecting...';

                setTimeout(() => {
                    window.location.href = 'finish.php';
                }, 1000);

            } catch (err) {
                console.error('Submission error:', err);
                processingStatusText.innerHTML = `<span style="color: var(--danger);">Submission failed: ${err.message}. Retrying or redirecting...</span>`;
                setTimeout(() => {
                    window.location.href = 'finish.php';
                }, 2500);
            }
        };

        mediaRecorder.stop();
    }
})();
