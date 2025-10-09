# Package to use AI CAD in Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tolery/ai-cad.svg?style=flat-square)](https://packagist.org/packages/tolery/ai-cad)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/tolery/ai-cad/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/tolery/ai-cad/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/tolery/ai-cad/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/tolery/ai-cad/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/tolery/ai-cad.svg?style=flat-square)](https://packagist.org/packages/tolery/ai-cad)

Tolery AI CAD is a Laravel package that provides AI-powered CAD (Computer-Aided Design) generation through a chatbot interface. Users describe parts in natural language, and the AI generates 3D models (OBJ/STEP files) with interactive visualization using Three.js.

**Key Features:**
- 🤖 Natural language to CAD conversion
- 📊 Real-time streaming progress with SSE
- 🔒 Secure server-side API proxy (no CORS issues)
- 🎨 Interactive 3D viewer with material presets (steel, aluminum, stainless steel)
- 📦 Multiple export formats (OBJ, STEP, JSON, PDF technical drawings)
- 💾 Automatic file storage and persistence
- 🔐 Bearer token authentication for API security

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/ai-cad.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/ai-cad)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require tolery/ai-cad
```

Your projet need to use Tailwind. Add this line to your main CSS file ( ex: `app.css` ) : 

```css
@source '../../vendor/tolery/ai-cad/resources/views/**/*.blade.php';
```

Your projet need to use vite and to build this package javascript file. Add this line in your main JS file :

```javascript
import '../../vendor/tolery/ai-cad/resources/js/app.js'
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="ai-cad-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="ai-cad-config"
```

Add these environment variables to your `.env` file:

```env
# AI CAD API Configuration
AI_CAD_API_URL=https://tolery-dfm-docker-api.cleverapps.io/api-production
AICAD_API_KEY=your-bearer-token-here

# Onshape Integration (optional)
ONSHAPE_SECRET_KEY=
ONSHAPE_ACCESS_KEY=
```

**Important Security Note:**
All API calls are proxied through your Laravel server. The Bearer token (`AICAD_API_KEY`) is never exposed to the frontend, preventing CORS issues and securing your API credentials.

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="ai-cad-views"
```

## Usage

### Basic Integration

In your Blade view, add the chatbot component:

```blade
<livewire:chatbot :chat="$chat" />
```

Where `$chat` is a `Tolery\AiCad\Models\Chat` instance:

```php
use Tolery\AiCad\Models\Chat;

$chat = Chat::create([
    'team_id' => auth()->user()->team_id,
    'user_id' => auth()->id(),
    'name' => 'My CAD Project',
]);

return view('your-view', compact('chat'));
```

### How It Works

1. **User Input**: User describes a part in natural language (e.g., "Create a steel plate 200x100x3mm with 5mm corner radii")
2. **Server Processing**: The request is sent to your Laravel server at `POST /ai-cad/stream/generate-cad`
3. **API Proxy**: Laravel proxies the request to the external AI CAD API with secure Bearer token authentication
4. **Real-time Progress**: Server streams SSE events back to the frontend showing progress (Analysis → Parameters → Generation → Export)
5. **File Storage**: Generated CAD files (OBJ, STEP, JSON, PDF) are automatically downloaded and stored in Laravel storage
6. **3D Visualization**: The generated model is displayed in an interactive Three.js viewer with material presets

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ultraviolettes](https://github.com/UV)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
