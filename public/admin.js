(() => {
  function togglePrivacyUrlRow() {
    const select = document.getElementById('asfw_privacy_page');
    const input = document.getElementById('asfw_privacy_url');

    if (!select || !input) {
      return;
    }

    const row = input.closest('tr');
    if (!row) {
      return;
    }

    row.style.display = select.value === 'custom' ? '' : 'none';
  }

  document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('asfw_privacy_page');
    if (!select) {
      return;
    }

    select.addEventListener('change', togglePrivacyUrlRow);
    togglePrivacyUrlRow();
  });
})();
