# Looper Dynamic Smart Slider for WooCommerce

תוסף וורדפרס שמאפשר להציג סליידר שונה של Smart Slider 3 בכל עמוד קטגוריית מוצרים של WooCommerce, באמצעות שורטקוד קבוע אחד בתוך Elementor.

## מה התוסף עושה

- מוסיף עמוד הגדרות תחת WooCommerce > Dynamic Smart Slider.
- מאפשר למפות קטגוריית מוצרים (לפי slug / ID / שם) לשורטקוד ספציפי של Smart Slider 3.
- מוסיף שורטקוד קבוע: `[ldss_dynamic_slider]`.
- כששורטקוד זה נטען בעמוד קטגוריה, התוסף מזהה את הקטגוריה הנוכחית ומריץ את השורטקוד המתאים.
- אפשר להגדיר גם Default Shortcode למקרה שאין התאמה.

## התקנה מהירה

1. העלה את קובץ התוסף לתיקיית `wp-content/plugins/`.
2. הפעל את התוסף דרך לוח הבקרה של וורדפרס.
3. עבור ל־WooCommerce > Dynamic Smart Slider והגדר מיפויים.
4. ב־Elementor, בעמוד תבנית הקטגוריה, שים ווידג'ט Shortcode עם:
   - `[ldss_dynamic_slider]`

## דוגמה למיפוי

- `shoes` → `[smartslider3 slider="7"]`
- `bags` → `[smartslider3 slider="8"]`
- `15` → `[smartslider3 slider="9"]` (לפי Term ID)

