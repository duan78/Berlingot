# L'Atelier Berlingot — Site statique

Site vitrine statique pour **L'Atelier Berlingot**, crèche multi-accueil associative située
au 43 boulevard Notre Dame, 13006 Marseille (6ᵉ arr.). Ouverte depuis mai 2009.

## Aperçu

- 100 % statique : HTML + CSS + un peu de JavaScript. **Aucune dépendance**, aucun build.
- Deux pages : `index.html` (présentation, infos pratiques, tarifs, contact)
  et `pedagogie.html` (projet pédagogique détaillé).
- Design « berlingot » : palette douce et joyeuse (corail, menthe, jaune, rose, lilas),
  typographies *Fredoka* et *Nunito*.
- Entièrement responsive (mobile, tablette, ordinateur).
- Accessible : navigation au clavier, `aria`, respect de `prefers-reduced-motion`.
- Formulaire de contact fonctionnel via le client mail (mailto) — aucun serveur requis.

## Contenu

Le contenu a été réaligné sur les documents officiels de la crèche
(projet d'établissement et règlement de fonctionnement, mai 2026) :

| Rubrique | Source |
|---|---|
| Type de structure, ouverture (2009), capacité agréments | Projet d'établissement §1.1 |
| Âge d'accueil (14 mois → 6 ans révolus) | Règlement §1 |
| 3 types d'accueil (régulier / occasionnel / urgence) | Règlement §1.1 |
| 4 piliers pédagogiques (espace ouvert, multi-âge, Montessori, écologie) | Projet §2.1.2 |
| L'équipe (G. Bonvalet, EJE — directrice) | Projet §1.3.1 / Règlement §2 |
| Repas (prestataire circuit court), sommeil, soins | Projet §2.2 |
| Journée type | Projet §2.1.2 |
| Barème CAF, mensualisation, adhésion 50 € | Règlement §5 |
| Familiarisation + période d'essai 1 mois | Projet §2.1.1 / Règlement §1.1 |
| Fermetures annuelles | Règlement §1 |
| Téléphone : **09 53 96 33 48** | Règlement §3 |

## Structure

```
berlingot/
├── index.html          # Page d'accueil (toutes les sections pratiques)
├── pedagogie.html      # Projet pédagogique détaillé
├── css/
│   └── styles.css      # Design complet + responsive
├── js/
│   └── main.js         # Menu mobile, reveal, formulaire, back-to-top
├── assets/
│   └── logo-official.svg # Logo officiel (berlingots) + favicon
└── README.md
```

## Lancer le site en local

Le plus simple : ouvrez `index.html` dans votre navigateur.

Pour un rendu optimal (polices Google Fonts, carte OpenStreetMap), une connexion
internet est utile. Pour démarrer un petit serveur local :

```bash
# Python
python -m http.server 8000

# ou Node
npx serve .
```

Puis ouvrez <http://localhost:8000>.

## Personnalisation rapide

| Que changer ? | Où ? |
|---|---|
| Couleurs, polices | Variables `:root` en haut de `css/styles.css` |
| Coordonnées (téléphone, adresse) | Sections `#contact` dans `index.html` et `pedagogie.html` |
| Email de réception du formulaire | Adresse `mailto:` dans `js/main.js` |
| Horaires / âge / formules | Section `#pratique` dans `index.html` |
| Textes, FAQ, tarifs | Directement dans `index.html` |
| Logo | `assets/logo-official.svg` |

## Mise en ligne

Déposez le dossier chez n'importe quel hébergeur statique :
**Netlify**, **Vercel**, **GitHub Pages**, **Cloudflare Pages**, ou un simple FTP.

> 📧 **Contact** — Email officiel : `contact@latelierberlingot.com`
> (utilisé par le formulaire de contact). Téléphone : 09 53 96 33 48.
