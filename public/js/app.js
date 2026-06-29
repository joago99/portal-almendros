const API = 'http://127.0.0.1:8000';

async function loadApp() {
  const res = await fetch(`${API}/api/auth/session.php`);
  const data = await res.json();
  if (!data.loggedIn) {
    window.location.href = '/';
    return;
  }
  const user = data.user;
  if (user.force_password_change) {
    document.getElementById('passwordModal').style.display = 'flex';
  }
  loadDashboard();
}

async function loadDashboard() {
  const res = await fetch(`${API}/api/app/dashboard.php`);
  const html = await res.text();
  document.getElementById('mainContent').innerHTML = html;
}

document.getElementById('changePasswordBtn').addEventListener('click', async (e) => {
  e.preventDefault();
  document.getElementById('changePasswordModal').style.display = 'flex';
});
document.getElementById('closeModal').addEventListener('click', () => {
  document.getElementById('changePasswordModal').style.display = 'none';
});
document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const body = {
    current: document.getElementById('currentPassword').value,
    new: document.getElementById('newPassword').value
  };
  const res = await fetch(`${API}/api/auth/change-password.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  const data = await res.json();
  const msg = document.getElementById('passwordMessage');
  if (data.ok) {
    msg.textContent = 'Contraseña actualizada';
    document.getElementById('changePasswordModal').style.display = 'none';
  } else {
    msg.textContent = data.error;
  }
});
document.getElementById('logoutBtn').addEventListener('click', async () => {
  await fetch(`${API}/api/auth/logout.php`);
  window.location.href = '/';
});

loadApp();
