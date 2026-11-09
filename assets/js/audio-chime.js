/**
 * JAZUVA - Web Audio API Notification Chime
 * Synthesizes a pleasant bell/chime sound without needing external MP3 files
 */
class NotificationChime {
  constructor() {
    this.audioCtx = null;
  }

  init() {
    if (!this.audioCtx) {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (AudioContext) {
        this.audioCtx = new AudioContext();
      }
    }
  }

  play() {
    try {
      this.init();
      if (!this.audioCtx) return;

      if (this.audioCtx.state === 'suspended') {
        this.audioCtx.resume();
      }

      const now = this.audioCtx.currentTime;

      // Note 1 (E5)
      const osc1 = this.audioCtx.createOscillator();
      const gain1 = this.audioCtx.createGain();
      osc1.type = 'sine';
      osc1.frequency.setValueAtTime(659.25, now);
      gain1.gain.setValueAtTime(0.3, now);
      gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
      osc1.connect(gain1);
      gain1.connect(this.audioCtx.destination);
      osc1.start(now);
      osc1.stop(now + 0.6);

      // Note 2 (G#5)
      const osc2 = this.audioCtx.createOscillator();
      const gain2 = this.audioCtx.createGain();
      osc2.type = 'sine';
      osc2.frequency.setValueAtTime(830.61, now + 0.15);
      gain2.gain.setValueAtTime(0.3, now + 0.15);
      gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
      osc2.connect(gain2);
      gain2.connect(this.audioCtx.destination);
      osc2.start(now + 0.15);
      osc2.stop(now + 0.8);

      // Note 3 (B5)
      const osc3 = this.audioCtx.createOscillator();
      const gain3 = this.audioCtx.createGain();
      osc3.type = 'sine';
      osc3.frequency.setValueAtTime(987.77, now + 0.3);
      gain3.gain.setValueAtTime(0.35, now + 0.3);
      gain3.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
      osc3.connect(gain3);
      gain3.connect(this.audioCtx.destination);
      osc3.start(now + 0.3);
      osc3.stop(now + 1.2);

    } catch (e) {
      console.warn("Audio chime playback error:", e);
    }
  }
}

window.jazuvaChime = new NotificationChime();

