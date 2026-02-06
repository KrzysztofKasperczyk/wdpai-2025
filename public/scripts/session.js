(function () {
  const root = document.getElementById('sessionRoot');
  if (!root) return;

  const sessionId = root.dataset.sessionId || '';
  const gameType = root.dataset.gameType || '';
  const inviteLink = root.dataset.inviteLink || '';
  const currentUserId = parseInt(root.dataset.currentUserId || '0', 10);
  const isHost = (root.dataset.isHost || '0') === '1';

  // Elements: game panel
  const resultCircle = document.getElementById('resultCircle');
  const actionBtn = document.getElementById('actionBtn');
  const hint = document.getElementById('hint');
  const copyBtn = document.getElementById('copyLink');

  // Elements: participants
  const participantsList = document.getElementById('participantsList');
  const participantsCount = document.getElementById('participantsCount');

  if (!sessionId) return;

  // Coin-flip only
  if (gameType !== 'coin_flip') {
    if (hint) hint.textContent = 'Unsupported game type.';
    if (actionBtn) actionBtn.disabled = true;
    return;
  }

  // Button label
  if (actionBtn) actionBtn.textContent = 'Flip coin';

  // Observer UI
  if (!isHost && actionBtn) {
    actionBtn.disabled = true;
    actionBtn.classList.add('disabled');
    if (hint) hint.textContent = 'You are an observer in this session.';
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

  // Presence: heartbeat + leave
  async function ping() {
    try {
      await fetch('/api/session/ping', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId }),
      });
    } catch (e) {
      // cisza
    }
  }

  function leaveNow() {
    try {
      const payload = JSON.stringify({ session_id: sessionId });
      const blob = new Blob([payload], { type: 'application/json' });

      if (navigator.sendBeacon) {
        navigator.sendBeacon('/api/session/leave', blob);
      } else {
        fetch('/api/session/leave', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: payload,
          keepalive: true,
        }).catch(() => {});
      }
    } catch (e) {
      // cisza
    }
  }

  window.addEventListener('beforeunload', leaveNow);

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      leaveNow();
    } else {
      ping();
      pollParticipants(true);
    }
  });

  // Game action (host-only)
  async function doAction() {
    if (!isHost) {
      if (hint) hint.textContent = 'Only the host can control the game.';
      return;
    }

    if (hint) hint.textContent = '';

    const { ok, data } = await postJson('/api/coin-flip/flip', { session_id: sessionId });
    if (!ok) {
      if (hint) hint.textContent = data.error || 'Error';
      return;
    }

    if (resultCircle) resultCircle.textContent = data.result;

    // po flippie wymuś odświeżenie listy (eliminacje/winner/nowe wybory)
    pollParticipants(true);
  }

  actionBtn?.addEventListener('click', doAction);

  // Poll latest event
  let lastEventId = null;

  async function pollLatest() {
    try {
      const res = await fetch('/api/session/latest?session_id=' + encodeURIComponent(sessionId));
      const json = await res.json().catch(() => ({}));
      const ev = json.event;
      if (!ev) return;

      if (lastEventId === ev.id) return;
      lastEventId = ev.id;

      // nowy event -> odśwież participants
      pollParticipants(true);

      let payload = ev.payload;
      if (typeof payload === 'string') {
        try { payload = JSON.parse(payload); } catch (e) {}
      }

      if (payload && payload.result !== undefined) {
        if (resultCircle) resultCircle.textContent = payload.result;
      }

      if (payload && payload.winner_id) {
        const wid = parseInt(payload.winner_id, 10);
        // spróbuj znaleźć zwycięzcę po nicku z cache participants
        const winner = (lastParticipants || []).find(p => parseInt(p.id, 10) === wid);
        const winnerName = winner?.nickname ? winner.nickname : `user #${wid}`;

        if (hint) hint.textContent = `Winner decided! ${winnerName}`;

        // dopnij refresh (żeby UI pokazało zielonego)
        pollParticipants(true);
      }
    } catch (e) {
      // cisza
    }
  }

  // Participants
  let lastParticipantsHash = '';
  let lastParticipants = [];

  function renderParticipants(list) {
    if (!participantsList) return;

    participantsList.innerHTML = '';

    if (!Array.isArray(list) || list.length === 0) {
      const li = document.createElement('li');
      li.className = 'hint';
      li.textContent = 'No active participants';
      participantsList.appendChild(li);
      if (participantsCount) participantsCount.textContent = '0 participants';
      return;
    }

    const isHostFlag = (v) => v === true || v === 1 || v === '1' || v === 't' || v === 'true';

    list.forEach((p) => {
      const uid = parseInt(p.id, 10);
      const nick = (p.nickname || '?').toString();
      const status = (p.status || 'active').toString(); // active/eliminated/winner
      const choice = (p.coin_choice || '').toString();  // heads/tails/''

      const li = document.createElement('li');
      li.style.display = 'flex';
      li.style.alignItems = 'center';
      li.style.justifyContent = 'space-between';
      li.style.gap = '12px';
      li.style.padding = '10px 12px';
      li.style.border = '2px solid #eef2ff';
      li.style.borderRadius = '14px';
      li.style.background = 'rgba(255,255,255,0.7)';

      // LEFT
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
      avatar.textContent = nick.slice(0, 1).toUpperCase();

      if (p.avatar_url) {
        avatar.textContent = '';
        avatar.style.backgroundImage = `url("${p.avatar_url}")`;
        avatar.style.backgroundSize = 'cover';
        avatar.style.backgroundPosition = 'center';
      }

      const nameWrap = document.createElement('div');
      nameWrap.style.display = 'flex';
      nameWrap.style.flexDirection = 'column';
      nameWrap.style.gap = '2px';

      const name = document.createElement('div');
      name.style.fontWeight = '900';
      name.textContent = nick || 'Unknown';

      if (status === 'eliminated') name.style.color = '#dc2626';
      if (status === 'winner') name.style.color = '#16a34a';

      const sub = document.createElement('div');
      sub.className = 'hint';
      sub.style.lineHeight = '1.1';

      const tags = [];
      if (uid === currentUserId) tags.push('You');
      if (isHostFlag(p.is_host)) tags.push('Host');
      if (status === 'eliminated') tags.push('Eliminated');
      if (status === 'winner') tags.push('Winner');
      sub.textContent = tags.join(' • ');

      nameWrap.appendChild(name);
      if (tags.length > 0) nameWrap.appendChild(sub);

      left.appendChild(avatar);
      left.appendChild(nameWrap);

      // RIGHT badge
      const right = document.createElement('div');
      right.style.display = 'flex';
      right.style.alignItems = 'center';

      const badge = document.createElement('span');
      badge.style.display = 'inline-flex';
      badge.style.alignItems = 'center';
      badge.style.justifyContent = 'center';
      badge.style.padding = '6px 10px';
      badge.style.borderRadius = '999px';
      badge.style.fontWeight = '900';
      badge.style.fontSize = '12px';
      badge.style.border = '2px solid #eef2ff';
      badge.style.background = 'rgba(255,255,255,0.85)';

      if (choice === 'heads') {
        badge.textContent = 'HEADS';
      } else if (choice === 'tails') {
        badge.textContent = 'TAILS';
      } else {
        badge.textContent = '—';
        badge.style.opacity = '0.6';
      }

      right.appendChild(badge);

      li.appendChild(left);
      li.appendChild(right);
      participantsList.appendChild(li);
    });

    if (participantsCount) {
      participantsCount.textContent = `${list.length} participant${list.length === 1 ? '' : 's'}`;
    }
  }

  async function pollParticipants(force = false) {
    if (!participantsList && !participantsCount) return;

    try {
      const res = await fetch('/api/session/participants?session_id=' + encodeURIComponent(sessionId));
      const json = await res.json().catch(() => ({}));
      const list = Array.isArray(json.participants) ? json.participants : [];

      const hash = JSON.stringify(
        list.map((x) => [x.id, x.nickname, x.avatar_url, x.coin_choice, x.status, x.is_host])
      );

      if (!force && hash === lastParticipantsHash) return;
      lastParticipantsHash = hash;
      lastParticipants = list;

      renderParticipants(list);
    } catch (e) {
      // cisza
    }
  }

  // Init
  ping();
  pollLatest();
  pollParticipants(true);

  setInterval(ping, 5000);

  setInterval(() => {
    pollLatest();
    pollParticipants(false);
  }, 2000);
})();
