// === Toggle Card (ouvrir/fermer le traitement) ===
function toggleCard(card) {
    if (card.classList.contains('is-locked')) return;
    card.classList.toggle('is-open');
    var reveal = card.querySelector('.treatment-reveal');
    if (reveal) {
        reveal.setAttribute('aria-hidden', !card.classList.contains('is-open'));
    }
}

// === Modal : ouverture / fermeture ===

function openModal() {
    const modal = document.getElementById('login-modal');
    modal.classList.add('is-visible');
    // On force le focus sur l'input email après une petite pause
    setTimeout(() => {
        document.getElementById('login-email').focus();
    }, 100);
}

function closeModal() {
    document.getElementById('login-modal').classList.remove('is-visible');
}

function closeModalBackdrop(e) {
    if (e.target === e.currentTarget) closeModal();
}

// Fermer avec Echap
document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        closeModal();
    }
});

// La navigation est 100% fonctionnelle côté serveur.
// Le JavaScript fournit uniquement l'ouverture/fermeture des modales et l'accessibilité.

// === Recherche/filtrage côté client (progressive enhancement) ===
function filterCards(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.patho-card').forEach(function(card) {
        if (type === 'all') {
            card.style.display = '';
        } else {
            card.style.display = card.dataset.type === type ? '' : 'none';
        }
    });
}



