# Changelog

All notable changes to `ai-cad` will be documented in this file.

## v0.8.6 - 2026-01-04

### What's New

#### Face Context for Edit Panel Regeneration

When modifying hole parameters (diameter, depth) from the config panel, the regeneration request now includes a `[FACE_CONTEXT: ...]` string with full face identification:

- Face ID
- Position (centroid coordinates)
- Bounding box dimensions
- Area
- Feature type (Perçage, Taraudage, etc.)
- Specific metrics (diameter, depth, dimensions)

This enables the chatbot to properly identify which specific feature is being modified, displaying the same "pastille" (chip) as manual face selection.

#### Changes

- New `buildFaceContext()` method in config panel
- Updated `saveEdits()` to include face context in message
- Consistent format with `FaceSelectionManager`

#### Full Changelog

https://github.com/Tolery-Dev/tolery-ai-cad-package/compare/v0.8.5...v0.8.6

## 0.6.1 - 2025-11-06

### What's Changed

* Screenshot coté frontend by @croustibat in https://github.com/Tolery-Dev/tolery-ai-cad-package/pull/31
* Pricing Flexible by @croustibat in https://github.com/Tolery-Dev/tolery-ai-cad-package/pull/32
* Improve UI by @croustibat in https://github.com/Tolery-Dev/tolery-ai-cad-package/pull/33

**Full Changelog**: https://github.com/Tolery-Dev/tolery-ai-cad-package/compare/0.6.0...0.6.1
