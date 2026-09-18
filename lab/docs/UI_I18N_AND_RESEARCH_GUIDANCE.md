# UI, i18n, and researcher-guidance contract — v1.4.0

1. The application is light-theme only. There is no dark-mode branch.
2. Researcher-facing pages support FR/EN through the single authority `app/I18n.php`.
3. Adjustable controls have keyboard-focusable information bubbles explaining the concept, what the control changes, and the intended experimental effect/confound.
4. Tooltips never own formulas. The browser asks the backend for derived values.
5. `rho`/counterfactual-share preview comes from `LabService -> ResearchContract -> EquationSet`.
6. `eta` is explicitly Eq. (12), not a generic replacement percentage.
7. “Memory context” is the number of examples materialized from `mu`; it is not the support size of `mu`.
8. Reports display the persisted mathematical trace rather than recomputing admission/gate decisions client-side.
