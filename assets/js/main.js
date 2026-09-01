//  Dark mode toggle 
const themeToggle = document.getElementById('themeToggle');
const themeIcon   = document.getElementById('themeIcon');

function applyThemeIcon(theme) 
{
  if (!themeIcon) return;
  themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}

// Sync icon with the theme already applied by the inline head script
applyThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');

if (themeToggle) 
{
  themeToggle.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    applyThemeIcon(next);
  });
}

//  Mobile sidebar toggle 

const sidebar  = document.querySelector('.sidebar');
const menuBtn  = document.getElementById('menuToggle');

if (menuBtn && sidebar) 
{
  menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
}

//  Auto-dismiss flash alerts 
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(el => {
    const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
    bsAlert.close();
  });
}, 4000);

//  Mark active nav link 
const currentPath = window.location.pathname;
document.querySelectorAll('.sidebar a.nav-link').forEach(link => {
  if (link.getAttribute('href') && currentPath.endsWith(link.getAttribute('href').split('/').pop())) {
    link.classList.add('active');
  }
});

//  AJAX helper 
async function apiCall(url, data = {}) 
{
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  const res = await fetch(url, { method: 'POST', body: fd });
  return res.json();
}

//  Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});

//  Notification fetch 
async function loadNotifications() 
{
  const badge = document.querySelector('.notif-bell .dot');
  if (!badge) return;
  const data = await apiCall('/university_portal/api/notifications.php', { action: 'count' });
  if (data.count > 0) 
  {
    badge.textContent = data.count;
    badge.style.display = 'flex';
  } 
  
  else 
  {
    badge.style.display = 'none';
  }
}
loadNotifications();
