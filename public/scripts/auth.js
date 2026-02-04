(function () {
  function setupToggle(passwordId, toggleId) {
    const input = document.getElementById(passwordId);
    const toggle = document.getElementById(toggleId);
    if (!input || !toggle) return;

    toggle.addEventListener('change', () => {
      input.type = toggle.checked ? 'text' : 'password';
    });
  }

  // login
  setupToggle('password', 'showPassword');

  // register
  setupToggle('password', 'showPassword');
  setupToggle('password2', 'showPassword2');
})();
