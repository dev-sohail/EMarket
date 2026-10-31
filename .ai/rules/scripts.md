---
paths:
  - 'scripts/**/*.php'
---

# Scripts

## Publish CKEditor from an allow-list — never a whole-tree copy
publish-ckeditor.php copies vendor/ckeditor/ckeditor into public/vendor/ckeditor, which is inside the DocumentRoot. Use an explicit top-level allow-list (lang, plugins, skins, adapters, vendor + the four root files); a whole-tree copy publishes samples/, which contains two executable PHP files that then answer requests at /vendor/ckeditor/samples/old/sample_posteddata.php. CKEditor 4 is EOL (June 2023) — plan migration off it. Verify with `find public -name '*.php'`: only public/index.php should ever appear.
