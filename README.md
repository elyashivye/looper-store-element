# Looper Dynamic Smart Slider for WooCommerce

תוסף וורדפרס שמאפשר להציג סליידר שונה של Smart Slider 3 בכל עמוד קטגוריית מוצרים של WooCommerce, באמצעות שורטקוד קבוע אחד בתוך Elementor.

## מה התוסף עושה

- מוסיף עמוד הגדרות תחת WooCommerce > Dynamic Smart Slider.
- מאפשר למפות קטגוריית מוצרים (לפי slug / ID / שם) לשורטקוד ספציפי של Smart Slider 3.
- כולל חיפוש/בחירה של קטגוריה מתוך רשימה (עם שם + slug + ID).
- בעת בחירת קטגוריה מוצג גם "דף יעד" עם שם הקטגוריה שזוהתה בפועל.
- כולל חיפוש/בחירה של סליידר Smart Slider מתוך רשימה, עם שם הסליידר ו-ID.
- מסך ההגדרות וההנחיות בממשק הניהול מוצגים בעברית.
- בסוף עמוד התוסף מופיע באנר קרדיט קטן (חצי מוסווה) עם לינק ל־clicknow.space.
- אפשר גם להדביק URL מלא/חלקי של הקטגוריה (עם או בלי `/` בסוף), והתוסף יחלץ אוטומטית את ה־slug.
- גם URLs עם slug בעברית (URL-encoded) נשמרים כמו שצריך.
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
- `https://example.com/product-category/machines-food-freezers/` → `[smartslider3 slider="10"]`
