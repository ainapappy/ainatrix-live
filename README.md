# ainatrix live

**Publiez vos articles WordPress sur vos réseaux sociaux, en un clic ou automatiquement.**

`ainatrix live` est une extension WordPress qui connecte votre site à vos réseaux sociaux : Facebook, LinkedIn, TikTok, Instagram et plus. Rédigez votre article, cliquez sur le bouton de publication — ou laissez les workflows planifiés le faire pour vous, à l'heure que vous avez choisie.

Conçu et maintenu par [Ainatrix](https://www.ainatrix.com) — développeur web freelance spécialisé WordPress, Laravel et Python.

---

## ✨ Pourquoi ainatrix live ?

Publier sur les réseaux sociaux à chaque nouvel article, c'est répétitif et facile à oublier. Les outils existants sont souvent lourds, chers ou limités.

**ainatrix live** fait une seule chose, et la fait bien : votre contenu WordPress part sur vos réseaux, sans friction.

- 🔘 **Bouton de publication intégré** — depuis l'éditeur d'article, publiez manuellement vers les réseaux de votre choix, réseau par réseau ou tous en même temps.
- ⏰ **Workflows planifiés** — programmez la diffusion automatique : dès la publication, à une date précise, ou selon une récurrence. Vos articles partent seuls.
- 🌐 **Multi-réseaux** — Facebook, LinkedIn, TikTok, Instagram… une seule configuration pour toucher toutes vos audiences.
- 🧩 **100 % WordPress natif** — s'installe comme n'importe quelle extension, sans dépendance externe obligatoire.
- 🏗️ **Architecture moderne** — code objet PSR-4, hooks WordPress propres, prêt à être étendu.

## 🚀 Fonctionnalités

| Fonctionnalité          | Description                                                     |
| ----------------------- | --------------------------------------------------------------- |
| Publication manuelle    | Bouton direct dans l'éditeur et sur la liste des articles       |
| Publication automatique | Déclenchement à la publication d'un article ou planifiée        |
| Multi-comptes           | Connectez plusieurs comptes par réseau social                   |
| File d'attente          | Les publications sont mises en file et traitées en arrière-plan |
| Journal des envois      | Suivez le statut de chaque publication (succès, échec, retry)   |
| i18n                    | Entièrement traduisible (text domain `ainatrix`)                |

## 🛠️ Installation

1. Téléchargez ou clonez ce dépôt dans `wp-content/plugins/ainatrix-live` :
   ```bash
   git clone https://github.com/ainapappy/ainatrix-live wp-content/plugins/ainatrix-live
   ```
2. Installez les dépendances PHP :
   ```bash
   composer install --no-dev
   ```
3. Activez l'extension depuis **Extensions** dans votre administration WordPress.
4. Rendez-vous dans les réglages de l'extension pour connecter vos comptes sociaux.

**Prérequis :** WordPress 6.0+, PHP 8.0+, Composer.

## 🗺️ Feuille de route

Le développement est actif. À venir :

- [ ] Connecteurs Facebook, LinkedIn, TikTok et Instagram (API officielles)
- [ ] Éditeur de messages par réseau (aperçu avant envoi)
- [ ] Planification avancée (calendrier éditorial)
- [ ] Statistiques de retentissement par publication
- [ ] Import / export de la configuration

Une idée, un besoin spécifique ? [Contactez-moi](https://www.ainatrix.com) — le plugin évolue aussi selon les retours de ses utilisateurs.

## 🧑‍💻 Sous le capot

```
ainatrix-live/
├── ainatrix-live.php   # Bootstrap du plugin (constantes, autoload, glue)
├── src/                # Classes PSR-4 (namespace AinatrixLive\)
├── inc/                # Couche procédurale résiduelle
└── languages/          # Traductions (.pot, .po, .mo)
```

Le plugin suit un pattern strict de classes de hooks (`src/{Feature}/{Feature}Hooks.php` enregistrées dans `Plugin.php`) — pas de `add_action` procédural éparpillé. C'est ce qui le rend lisible, testable et facile à faire évoluer.

## 🤝 Travaillons ensemble

Ce plugin est développé et maintenu par **Ainatrix**, studio d'un développeur web freelance.

Besoin d'un plugin sur mesure, d'un site WordPress qui vous ressemble, d'une application Laravel ou d'un développement Python ? J'accompagne les indépendants, les TPE et les agences de la conception à la mise en production.

- 🌍 Site & portfolio : **[ainatrix.com](https://www.ainatrix.com)**
- 🛠️ Expertises : **WordPress · Laravel · Python**
- 📬 Contact & devis : **[ainatrix.com](https://www.ainatrix.com)**

> Un plugin qui vous plaît mais qui manque d'une fonctionnalité ? Ouvrez une _issue_ — ou parlons-en directement.

## 📄 Licence

[GPL-3.0](https://www.gnu.org/licenses/gpl-3.0.html) — comme WordPress.
