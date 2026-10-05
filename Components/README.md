# Components

Self-contained Oracle Room interface/content objects.

Each component should:
- use an `or-` scoped class namespace;
- carry only the HTML/CSS/JS it genuinely needs;
- remain responsive without relying on Elementor-specific DOM;
- work inside a simple Elementor HTML widget or equivalent WordPress content shell;
- include accessible labels/states for interactive controls;
- reference WordPress-hosted media rather than hard-coded local desktop paths;
- avoid changing global theme structure unless that is explicitly the component's job.

The live page is the assembly surface. Git history is the revision history.
