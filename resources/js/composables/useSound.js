/**
 * useSound — Synthesized game sounds using Web Audio API.
 * No external audio files needed.
 */

let audioCtx = null;

function getContext() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    // Resume if suspended (browser autoplay policy)
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
}

function playTone(frequency, duration, type = 'sine', volume = 0.3) {
    try {
        const ctx = getContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(frequency, ctx.currentTime);

        gain.gain.setValueAtTime(volume, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration);
    } catch {
        // Silently fail if audio is not available
    }
}

/**
 * Your turn! — Ascending two-note chime (friendly alert)
 */
export function playYourTurn() {
    const ctx = getContext();
    const now = ctx.currentTime;

    // Note 1: C5
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(523, now);
    gain1.gain.setValueAtTime(0.35, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.start(now);
    osc1.stop(now + 0.3);

    // Note 2: E5 (after 150ms)
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(659, now + 0.15);
    gain2.gain.setValueAtTime(0, now);
    gain2.gain.setValueAtTime(0.35, now + 0.15);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.start(now + 0.15);
    osc2.stop(now + 0.5);

    // Note 3: G5 (after 300ms)
    const osc3 = ctx.createOscillator();
    const gain3 = ctx.createGain();
    osc3.type = 'sine';
    osc3.frequency.setValueAtTime(784, now + 0.3);
    gain3.gain.setValueAtTime(0, now);
    gain3.gain.setValueAtTime(0.4, now + 0.3);
    gain3.gain.exponentialRampToValueAtTime(0.001, now + 0.7);
    osc3.connect(gain3);
    gain3.connect(ctx.destination);
    osc3.start(now + 0.3);
    osc3.stop(now + 0.7);
}

/**
 * Turn ended — Short descending note
 */
export function playTurnEnd() {
    playTone(440, 0.2, 'sine', 0.2);
    setTimeout(() => playTone(330, 0.25, 'sine', 0.15), 120);
}

/**
 * Nudge — Urgent attention ping (repeated soft pulses)
 */
export function playNudge() {
    playTone(880, 0.1, 'square', 0.15);
    setTimeout(() => playTone(880, 0.1, 'square', 0.15), 200);
}

/**
 * Action happened — Quick click
 */
export function playAction() {
    playTone(600, 0.08, 'triangle', 0.2);
}

/**
 * Negative event (challenge, elimination) — Low thud
 */
export function playNegative() {
    playTone(200, 0.3, 'sawtooth', 0.15);
}

/**
 * Chat message received
 */
export function playChatPing() {
    playTone(1200, 0.08, 'sine', 0.12);
    setTimeout(() => playTone(1500, 0.1, 'sine', 0.1), 80);
}

/**
 * Initialize audio context on first user interaction.
 * Call this once to ensure sounds work later.
 */
export function initAudio() {
    getContext();
}
