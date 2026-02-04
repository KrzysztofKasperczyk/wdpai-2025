(function () {
  const root = document.getElementById('sessionRoot');
  if (!root) return;

  const sessionId = root.dataset.sessionId;
  const gameType = root.dataset.gameType;
  const inviteLink = root.dataset.inviteLink;

  const resultCircle = document.getElementById('resultCircle');
  const actionBtn = document.getElementById('actionBtn');
  const hint = document.getElementById('hint');
  const copyBtn = document.getElementById('copyLink');

  const diceSettings = document.getElementById('diceSettings');
  const dicePreset = document.getElementById('dicePreset');
  const diceCustom = document.getElementById('diceCustom');
  const dicePresetLabel = document.getElementById('dicePresetLabel');

  // label przycisku
  if (gameType === 'coin_flip') actionBtn.textContent = 'Flip coin';
  if (gameType === 'roll_dice') actionBtn.textContent = 'Roll dice';
  if (gameType === 'spin_wheel') actionBtn.textContent = 'Spin wheel';

  copyBtn?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(inviteLink);
      hint.textContent = 'Invite link copied!';
    } catch (e) {
      hint.textContent = 'Could not copy link (browser blocked).';
    }
  });

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, data };
  }

  function initDiceUI() {
    if (gameType !== 'roll_dice') return;
    if (!diceSettings || !dicePreset || !diceCustom || !dicePresetLabel) return;

    diceSettings.style.display = 'block';

    function sync() {
      const val = dicePreset.value;
      if (val === 'custom') {
        diceCustom.style.display = 'inline-block';
        dicePresetLabel.textContent = 'Custom';
      } else {
        diceCustom.style.display = 'none';
        dicePresetLabel.textContent = 'D' + val;
      }
    }

    dicePreset.addEventListener('change', sync);
    diceCustom.addEventListener('input', () => {
      dicePresetLabel.textContent = 'Custom';
    });

    sync();
  }

  function getDiceSides() {
    if (!dicePreset || !diceCustom) return 6;

    if (dicePreset.value !== 'custom') {
      return parseInt(dicePreset.value, 10);
    }

    const n = parseInt(diceCustom.value, 10);
    if (!Number.isFinite(n) || n < 2 || n > 1000) {
      hint.textContent = 'Custom sides must be between 2 and 1000. Using D6.';
      return 6;
    }
    return n;
  }

  async function doAction() {
    hint.textContent = '';

    if (gameType === 'coin_flip') {
      const { ok, data } = await postJson('/api/coin-flip/flip', { session_id: sessionId });
      if (!ok) return (hint.textContent = data.error || 'Error');
      resultCircle.textContent = data.result;
      return;
    }

    if (gameType === 'roll_dice') {
      const sides = getDiceSides();
      const { ok, data } = await postJson('/api/dice/roll', { session_id: sessionId, sides });
      if (!ok) return (hint.textContent = data.error || 'Error');
      resultCircle.textContent = data.result;
      return;
    }

    if (gameType === 'spin_wheel') {
      const options = ['A', 'B', 'C', 'D']; // na razie stałe
      const { ok, data } = await postJson('/api/wheel/spin', { session_id: sessionId, options });
      if (!ok) return (hint.textContent = data.error || 'Error');
      resultCircle.textContent = data.result;
      return;
    }
  }

  actionBtn.addEventListener('click', doAction);

  // polling latest
  let lastEventId = null;

  async function pollLatest() {
    try {
      const res = await fetch('/api/session/latest?session_id=' + encodeURIComponent(sessionId));
      const json = await res.json();
      const ev = json.event;
      if (!ev) return;

      if (lastEventId === ev.id) return;
      lastEventId = ev.id;

      let payload = ev.payload;
      if (typeof payload === 'string') {
        try { payload = JSON.parse(payload); } catch (e) {}
      }

      if (payload && payload.result !== undefined) {
        resultCircle.textContent = payload.result;
      }
    } catch (e) {
      // cisza
    }
  }

  initDiceUI();
  pollLatest();
  setInterval(pollLatest, 2000);
})();
