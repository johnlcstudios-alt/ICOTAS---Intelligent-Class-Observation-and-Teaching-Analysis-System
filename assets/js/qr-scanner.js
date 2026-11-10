/**
 * On-device QR scanner for the student evaluation portal.
 *
 * Decoding happens entirely in the browser with jsQR. The camera stream is
 * never uploaded. Only same-origin evaluation links are opened; any other
 * payload is rejected and the manual session-code form remains available.
 */
(function () {
    'use strict';

    var video = document.getElementById('qrVideo');
    var canvas = document.getElementById('qrCanvas');
    var startButton = document.getElementById('qrStart');
    var stopButton = document.getElementById('qrStop');
    var status = document.getElementById('qrStatus');
    var codeInput = document.getElementById('sessionCode');

    if (!video || !canvas || !startButton || !status) {
        return;
    }

    var stream = null;
    var frameRequest = null;
    var scanning = false;
    var detectionLocked = false;
    var lastMessage = '';

    function setStatus(message, tone) {
        lastMessage = message;
        status.textContent = message;
        status.className = 'small mb-0 ' + (tone === 'error' ? 'text-danger' : (tone === 'success' ? 'text-success' : 'text-muted'));
    }

    function setRunning(running) {
        scanning = running;
        startButton.classList.toggle('d-none', running);
        if (stopButton) {
            stopButton.classList.toggle('d-none', !running);
        }
        video.classList.toggle('is-live', running);
    }

    function stopCamera() {
        if (frameRequest !== null) {
            window.cancelAnimationFrame(frameRequest);
            frameRequest = null;
        }
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
        try {
            video.pause();
        } catch (error) { /* no-op */ }
        video.srcObject = null;
        setRunning(false);
    }

    function manualFallback() {
        if (codeInput) {
            codeInput.focus();
            codeInput.select();
        }
    }

    function openEvaluation(payload) {
        var text = String(payload || '').trim();
        if (text === '') {
            return false;
        }

        // A QR code may contain an absolute same-origin evaluation link.
        if (/^https?:\/\//i.test(text)) {
            var parsed;
            try {
                parsed = new URL(text);
            } catch (error) {
                return false;
            }
            if (parsed.origin !== window.location.origin || parsed.pathname.indexOf('/evaluate/') === -1) {
                return false;
            }
            setStatus('QR detected. Opening the evaluation…', 'success');
            window.location.assign(parsed.href);
            return true;
        }

        // Otherwise accept a plain session code and let the student confirm.
        if (/^[A-Z0-9-]{4,20}$/i.test(text) && codeInput) {
            codeInput.value = text.toUpperCase();
            setStatus('QR detected. Session code filled in — tap "Open evaluation" to continue.', 'success');
            codeInput.focus();
            return true;
        }

        return false;
    }

    function scanFrame() {
        if (!scanning || !stream) {
            return;
        }
        if (video.readyState === video.HAVE_ENOUGH_DATA && typeof window.jsQR === 'function') {
            var width = video.videoWidth;
            var height = video.videoHeight;
            if (width > 0 && height > 0) {
                // Cap the decode canvas so older phones stay responsive.
                var scale = Math.min(1, 720 / Math.max(width, height));
                canvas.width = Math.round(width * scale);
                canvas.height = Math.round(height * scale);
                var context = canvas.getContext('2d', { willReadFrequently: true });
                if (context) {
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    var image = context.getImageData(0, 0, canvas.width, canvas.height);
                    var result = window.jsQR(image.data, canvas.width, canvas.height, {
                        inversionAttempts: 'dontInvert'
                    });
                    if (result && result.data) {
                        if (detectionLocked) {
                            return;
                        }
                        detectionLocked = true;
                        stopCamera();
                        var handled = openEvaluation(result.data);
                        if (!handled) {
                            setStatus('This QR code is not an ICOTAS evaluation link. Ask your instructor for the correct code, or enter it manually.', 'error');
                            detectionLocked = false;
                            manualFallback();
                        }
                        return;
                    }
                }
            }
        }
        frameRequest = window.requestAnimationFrame(scanFrame);
    }

    function cameraErrorMessage(error) {
        if (!window.isSecureContext) {
            return 'Camera access is blocked because this page is not served over HTTPS. Use the session code instead.';
        }
        switch (error && error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                return 'Camera permission was denied. Allow camera access in your browser settings, or enter the session code manually.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'No camera was found on this device. Enter the session code manually.';
            case 'NotReadableError':
            case 'TrackStartError':
                return 'The camera is already in use by another application. Close it, then try again, or enter the session code manually.';
            case 'OverconstrainedError':
                return 'This device does not support the requested camera settings. Try again, or enter the session code manually.';
            default:
                return 'The camera could not be started. Enter the session code manually.';
        }
    }

    function startCamera() {
        if (scanning) {
            return;
        }
        detectionLocked = false;
        if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            setStatus('Camera access requires HTTPS or localhost. Use the session code instead.', 'error');
            manualFallback();
            return;
        }
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            setStatus('This browser does not support camera access. Use the session code instead.', 'error');
            manualFallback();
            return;
        }
        if (typeof window.jsQR !== 'function') {
            setStatus('The QR decoder did not load. Check your connection and refresh, or use the session code.', 'error');
            manualFallback();
            return;
        }

        setStatus('Requesting camera permission…');
        navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        }).then(function (cameraStream) {
            stream = cameraStream;
            video.srcObject = cameraStream;
            video.setAttribute('playsinline', '');
            video.muted = true;
            return video.play();
        }).then(function () {
            setRunning(true);
            setStatus('Scanning… Hold the QR code steady inside the frame.', 'success');
            frameRequest = window.requestAnimationFrame(scanFrame);
        }).catch(function (error) {
            stopCamera();
            setStatus(cameraErrorMessage(error), 'error');
            manualFallback();
        });
    }

    startButton.addEventListener('click', startCamera);
    if (stopButton) {
        stopButton.addEventListener('click', function () {
            stopCamera();
            setStatus('Camera stopped. You can start it again or enter the session code manually.');
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden && scanning) {
            stopCamera();
            setStatus('Camera paused while this tab is hidden. Start the camera again when you return.');
        }
    });

    window.addEventListener('pagehide', stopCamera);

    if (lastMessage === '') {
        setStatus('Camera is off. Your video never leaves this device; QR decoding happens in the browser.');
    }
}());
