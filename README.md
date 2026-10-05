# The Oracle Room Site

Public-site design and component source for **The Oracle Room | Psychic Readings by Kathleen Cameron**.

## Working model

- **WordPress / Namaha** provides the site shell, global theme structure, page routing and native navigation.
- **Elementor** is used sparingly as an assembly surface when it is useful.
- **Bespoke HTML/CSS/JS objects** provide the designed marketing/editorial sections.
- **GitHub is the canonical source** for approved component code and implementation notes.
- **WordPress is the live assembly/testing surface.**
- **Kathleen Studio Kore** is a separate application and repository. Do not mix Studio code into this repo.

## Build rule

Work object by object. Each component should be independently understandable, responsive, accessible and scoped so it does not leak styles into unrelated WordPress/theme UI.

Keep only the current approved/production-candidate component in its normal location. Git history is the version record.

## Structure

```text
Components/
  Header/
  Hero/
  Reading-Pathways/
  Editorial-Statement/
  In-Person/
  About-Teaser/
  Journal-Teaser/
  CTA/
  Footer/
Pages/
  Home/
  Readings/
  Book-Online/
  In-Person/
  About-Kathleen/
  Journal/
CSS/
JavaScript/
Media-Notes/
Archive/
```

## Theme

- Theme: Namaha (Out the Box Themes)
- Builder: Elementor, used lightly
- Site: https://oracle.kathleencameron.info/
- Visual direction: dark editorial occult, intimate/private-salon feeling, mystical without generic purple-galaxy tarot styling.
