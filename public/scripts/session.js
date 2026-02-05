(function () {
  const root = document.getElementById('sessionRoot');
  if (!root) return;

  const sessionId = root.dataset.sessionId || '';
  const gameType = root.dataset.gameType || '';
  const inviteLink = root.dataset.inviteLink || '';
  const currentUserId = parseInt(root.dataset.currentUserId || '0', 10);

  // Elements: game panel
  const resultCircle = document.getElementById('resultCircle');
  const actionBtn = document.getElementById('actionBtn');
  const hint = document.getElementById('hint');
  const copyBtn = document.getElementById('copyLink');

  // Elements: dice ui
  const diceSettings = document.getElementById('diceSettings');
  const dicePreset = document.getElementById('dicePreset');
  const diceCustom = document.getElementById('diceCustom');
  const dicePresetLabel = document.getElementById('dicePresetLabel');

  // Elements: participants
  const participantsList = document.getElementById('participantsList');
  const participantsCount = document.getElementById('participantsCount');

  if (!sessionId) return;

  // Label przycisku w zależności od gry
  if (actionBtn) {
    if (gameType === 'coin_flip') actionBtn.textContent = 'Flip coin';
    if (gameType === 'roll_dice') actionBtn.textContent = 'Roll dice';
  }

  // Copy invite link
  copyBtn?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(inviteLink);
      if (hint) hint.textContent = 'Invite link copied!';
    } catch (e) {
      if (hint) hint.textContent = 'Could not copy link (browser blocked).';
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

  // -------------------------
  // Dice UI
  // -------------------------
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
      if (hint) hint.textContent = 'Custom sides must be between 2 and 1000. Using D6.';
      return 6;
    }
    return n;
  }

  // -------------------------
  // Game action
  // -------------------------
  async function doAction() {
    if (hint) hint.textContent = '';

    if (gameType === 'coin_flip') {
      const { ok, data } = await postJson('/api/coin-flip/flip', { session_id: sessionId });
      if (!ok) {
        if (hint) hint.textContent = data.error || 'Error';
        return;
      }
      if (resultCircle) resultCircle.textContent = data.result;
      return;
    }

    if (gameType === 'roll_dice') {
      const sides = getDiceSides();
      const { ok, data } = await postJson('/api/dice/roll', { session_id: sessionId, sides });
      if (!ok) {
        if (hint) hint.textContent = data.error || 'Error';
        return;
      }
      if (resultCircle) resultCircle.textContent = data.result;
      return;
    }
  }

  actionBtn?.addEventListener('click', doAction);

  // -------------------------
  // Poll latest event
  // -------------------------
  let lastEventId = null;

  async function pollLatest() {
    try {
      const res = await fetch('/api/session/latest?session_id=' + encodeURIComponent(sessionId));
      const json = await res.json().catch(() => ({}));
      const ev = json.event;
      if (!ev) return;

      if (lastEventId === ev.id) return;
      lastEventId = ev.id;

      let payload = ev.payload;
      if (typeof payload === 'string') {
        try { payload = JSON.parse(payload); } catch (e) {}
      }

      if (payload && payload.result !== undefined) {
        if (resultCircle) resultCircle.textContent = payload.result;
      }
    } catch (e) {
      // cisza
    }
  }

  // -------------------------
  // Participants
  // -------------------------
  let lastParticipantsHash = '';

  function renderParticipants(list) {
    if (!participantsList) return;

    participantsList.innerHTML = '';

    list.forEach((p) => {
      const li = document.createElement('li');
      li.style.display = 'flex';
      li.style.alignItems = 'center';
      li.style.justifyContent = 'space-between';
      li.style.gap = '12px';
      li.style.padding = '10px 12px';
      li.style.border = '2px solid #eef2ff';
      li.style.borderRadius = '14px';
      li.style.background = 'rgba(255,255,255,0.7)';

      const left = document.createElement('div');
      left.style.display = 'flex';
      left.style.alignItems = 'center';
      left.style.gap = '10px';

      const avatar = document.createElement('div');
      avatar.style.width = '34px';
      avatar.style.height = '34px';
      avatar.style.borderRadius = '12px';
      avatar.style.background = '#e9edff';
      avatar.style.display = 'grid';
      avatar.style.placeItems = 'center';
      avatar.style.fontWeight = '900';

      const nick = (p.nickname || '?').toString();
      avatar.textContent = nick.slice(0, 1).toUpperCase();

      if (p.avatar_url) {
        avatar.textContent = '';
        avatar.style.backgroundImage = `url("${p.avatar_url}")`;
        avatar.style.backgroundSize = 'cover';
        avatar.style.backgroundPosition = 'center';
      }

      const name = document.createElement('div');
      name.style.fontWeight = '900';
      name.textContent = nick || 'Unknown';

      left.appendChild(avatar);
      left.appendChild(name);

      const right = document.createElement('div');
      right.className = 'hint';
      right.style.whiteSpace = 'nowrap';
      if (parseInt(p.id, 10) === currentUserId) {
        right.textContent = 'You';
      } else {
        right.textContent = '';
      }

      li.appendChild(left);
      li.appendChild(right);
      participantsList.appendChild(li);
    });

    if (participantsCount) {
      participantsCount.textContent = `${list.length} participant${list.length === 1 ? '' : 's'}`;
    }
  }

  async function pollParticipants() {
    if (!participantsList && !participantsCount) return;

    try {
      const res = await fetch('/api/session/participants?session_id=' + encodeURIComponent(sessionId));
      const json = await res.json().catch(() => ({}));
      const list = Array.isArray(json.participants) ? json.participants : [];

      const hash = JSON.stringify(list.map((x) => [x.id, x.nickname, x.avatar_url]));
      if (hash === lastParticipantsHash) return;
      lastParticipantsHash = hash;

      renderParticipants(list);
    } catch (e) {
      // cisza
    }
  }

  // -------------------------
  // Init
  // -------------------------
  initDiceUI();

  // pierwsze pobranie
  pollLatest();
  pollParticipants();

  // jeden timer na wszystko (czyściej)
  setInterval(() => {
    pollLatest();
    pollParticipants();
  }, 2000);
})();
