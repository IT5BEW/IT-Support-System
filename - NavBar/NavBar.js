const hamburger = document.getElementById('hamburger');
const navLinksDiv = document.getElementById('navLinks');
  hamburger.addEventListener('click', () => {
  navLinksDiv.classList.toggle('show');
});