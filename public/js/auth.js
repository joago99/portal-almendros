const API = 'http://127.0.0.1:8000';

async function login(e) {
  e.preventDefault();
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const res = await fetch(`${API}/api/auth/login.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  const data = await res.json();
  const msg = document.getElementById('message');
  if (data.ok) {
    window.location.href = '/app.html';
  } else {
    msg.textContent = data.error || 'Error';
  }
}

document.getElementById('loginForm').addEventListener('submit', login);

document.getElementById('forgotPassword').addEventListener('click', async (e) => {
  e.preventDefault();
  const email = prompt('Ingresa tu email');
  if (!email) return;
  const res = await fetch(`${API}/api/auth/reset-request.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
  const data = await res.json();
  alert(data.message || data.error);
});
