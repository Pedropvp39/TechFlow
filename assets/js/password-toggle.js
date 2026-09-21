/**
 * Toggle de visibilidade de senha
 * Permite mostrar/esconder o texto digitado nos campos de senha
 */

function initPasswordToggle() {
  const toggleButtons = document.querySelectorAll('.toggle-password');
  
  toggleButtons.forEach((button) => {
    button.addEventListener('click', (e) => {
      e.preventDefault();
      
      const targetId = button.dataset.target;
      const input = document.getElementById(targetId);
      
      if (!input) return;
      
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      
      button.textContent = isPassword ? '🙈' : '👁️';
      button.setAttribute('aria-pressed', isPassword);
    });
  });
}

document.addEventListener('DOMContentLoaded', initPasswordToggle);
