# Asset Publishing

Le package ToleryCAD est maintenant autonome et inclut tous ses assets (images, SVG, etc.).

## Publication des assets

Pour publier les assets du package dans votre application Laravel, exécutez :

```bash
php artisan vendor:publish --tag=ai-cad-assets
```

Cela copiera tous les assets depuis `vendor/tolery/ai-cad/resources/assets/` vers `public/vendor/ai-cad/`.

## Structure des assets

```
vendor/tolery/ai-cad/
└── resources/
    └── assets/
        └── images/
            ├── bot-icon.svg
            ├── cad.svg
            ├── chat.svg
            └── pdf.svg
```

Après publication :

```
public/
└── vendor/
    └── ai-cad/
        └── images/
            ├── bot-icon.svg
            ├── cad.svg
            ├── chat.svg
            └── pdf.svg
```

## Assets inclus

- `bot-icon.svg` - Icône du bot ToleryCAD utilisée dans les messages du chat
- `cad.svg` - Icône CAD avec dégradé violet→indigo pour le bloc CAD-to-CAD (import de fichiers CAO)
- `chat.svg` - Icône de bulle de chat avec dégradé pour le bloc Text-to-CAD (description textuelle)
- `pdf.svg` - Icône de document PDF avec dégradé pour le bloc PDF-to-CAD (import de plans techniques)

## Utilisation dans les vues

Les assets sont automatiquement référencés via :

```blade
{{ asset('vendor/ai-cad/images/bot-icon.svg') }}
{{ asset('vendor/ai-cad/images/cad.svg') }}
{{ asset('vendor/ai-cad/images/chat.svg') }}
{{ asset('vendor/ai-cad/images/pdf.svg') }}
```

## Publication automatique

Les assets sont automatiquement publiés lors de l'installation du package si vous utilisez la découverte automatique de packages Laravel.

Si ce n'est pas le cas, ajoutez ceci dans votre `composer.json` :

```json
"extra": {
    "laravel": {
        "dont-discover": []
    }
}
```

Puis exécutez :

```bash
composer update
php artisan vendor:publish --tag=ai-cad-assets
```
