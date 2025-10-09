# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **Tolery AI CAD** - a Laravel package that provides AI-powered CAD (Computer-Aided Design) generation through a chatbot interface. Users describe parts in natural language, and the AI generates 3D models (OBJ/STEP files) with interactive visualization using Three.js.

## Development Commands

### PHP/Laravel
```bash
# Install dependencies
composer install

# Run tests (Pest)
composer test
vendor/bin/pest

# Run static analysis
composer analyse
vendor/bin/phpstan analyse --memory-limit=2G

# Fix code style (Laravel Pint)
composer format
vendor/bin/pint

# Start development server (Testbench)
composer start
```

### JavaScript/Frontend
```bash
# Install dependencies (using Yarn)
yarn install

# Build assets (Vite)
npm run build
yarn build

# Development mode
npm run dev
yarn dev
```

### Database
```bash
# Publish and run migrations
php artisan vendor:publish --tag="ai-cad-migrations"
php artisan migrate

# Scheduled command for limit renewal (runs daily at 1:00 AM)
php artisan limits:auto-renewal
```

## Architecture

### Core Flow: Chat-to-CAD Generation

1. **User Input** → Livewire component (`Chatbot.php`)
2. **Frontend Request** → JavaScript calls Laravel SSE endpoint (`routes/web.php:ai-cad.stream.generate-cad`)
3. **Server Proxy** → StreamController proxies the request to external API with Bearer token (`StreamController.php`)
4. **SSE Streaming** → AICADClient makes streaming request to external API (`AICADClient.php:26-103`)
5. **Real-time Updates** → Server streams SSE events back to frontend, JavaScript updates UI
6. **Final Response** → 3D model files downloaded and stored locally, URLs saved to ChatMessage
7. **3D Visualization** → Three.js renders model with interactive face selection

### Key Components

**Backend (Laravel)**
- `StreamController`: Proxies SSE streaming from external API to frontend (secure, avoids CORS)
- `AICADClient`: Handles SSE streaming requests to external AI CAD API with Bearer token authentication
- `Chatbot` (Livewire): Main chat interface with real-time streaming
- `ChatMessage`: Stores messages with references to generated CAD files (`ai_cad_path`, `ai_json_edge_path`, `ai_technical_drawing_path`)
- `Chat`: Session management with `session_id` for context preservation

**Routes**
- `POST /ai-cad/stream/generate-cad` (named: `ai-cad.stream.generate-cad`): SSE streaming endpoint, requires authentication

**Frontend (Three.js)**
- `JsonModelViewer3D` (`app.js`): Renders 3D models from JSON format
- Material presets: acier, aluminium, inox
- Interactive face selection for editing specific parts

**Jobs**
- `GetAICADResponse`: Legacy job for non-streaming API calls
- `ProcessChatToCad`: Queued SSE streaming job (timeout: 600s, 3 tries)
- `LimitRenew`: Auto-renews usage limits based on subscription

### Subscription & Limits System

- Uses Laravel Cashier with Stripe integration
- `SubscriptionProduct`: Defines products with usage limits
- `HasLimits` trait: Manages team usage quotas
- `ResetFrequency` enum: Daily/weekly/monthly limit resets
- Auto-renewal via scheduled command at 1:00 AM daily

### Configuration

**Environment Variables Required:**
```env
# AI CAD API
AI_CAD_API_URL=https://tolery-dfm-docker-api.cleverapps.io/api-production
AICAD_API_KEY=your-api-key  # Bearer token for API authentication

# Onshape Integration (optional)
ONSHAPE_SECRET_KEY=
ONSHAPE_ACCESS_KEY=
```

**Config File:** `config/ai-cad.php`
- `chat_user_model`: User model class (default: `App\Models\User`)
- `chat_team_model`: Team model class (default: `App\Models\Team`)

### Models & Database

**Main Models:**
- `Chat`: Conversation session with `session_id`, `team_id`, `user_id`, `material_family`
- `ChatMessage`: Individual messages with role (`user`/`assistant`) and CAD file references
- `Limit`: Usage tracking with `used_amount`, `start_date`, `end_date`
- `SubscriptionProduct`: Stripe products with usage limits

**Storage Pattern:**
- CAD files stored in `storage/ai-chat/{Y-m}/chat-{id}/`
- Format: `getStorageFolder()` in Chat model (line 66-69)

### Frontend Integration

**Required in Consumer App:**
1. Tailwind CSS with source scanning:
   ```css
   @source '../../vendor/tolery/ai-cad/resources/views/**/*.blade.php';
   ```

2. JavaScript import in main JS file:
   ```javascript
   import '../../vendor/tolery/ai-cad/resources/js/app.js'
   ```

3. Vite configuration to build package assets

### Blade Directives

Custom directives registered in `AiCadServiceProvider`:
- `@subscribed`: Check if team has active subscription
- `@hasLimit`: Check if team has usage limits

### API Integration

**External AI CAD API Endpoints:**
- `POST /api/generate-cad-stream`: Streaming CAD generation (SSE)
  - Yields progress events: `{step, status, message, overall_percentage}`
  - Final event: `{final_response: {chat_response, obj_export, json_export, tessellated_export, technical_drawing_export, manufacturing_errors}}`

**Session Management:**
- API maintains context when `session_id` is provided
- First request creates session, subsequent requests use same ID

### Testing

- Framework: Pest PHP
- TestCase uses Orchestra Testbench for package testing
- Architecture tests in `ArchTest.php`
- Test database: SQLite in-memory (`database.default = testing`)

### Important Notes

- **Streaming Architecture**: The package uses SSE (Server-Sent Events) for real-time progress updates during CAD generation
- **Security**: All API calls go through Laravel server proxy to avoid CORS issues and secure the Bearer token. JavaScript never directly accesses the external API.
- **Context Preservation**: `$serverKeepsContext = true` (Chatbot.php:46) means only the last user message is sent to API when session_id exists
- **Rate Limiting**: 10 messages per minute per chat (`$ratePerMinute`, Chatbot.php:39)
- **Lock Mechanism**: 12-second lock prevents duplicate submissions (`$lockSeconds`, Chatbot.php:41)
- **3D Viewer**: Uses Three.js with OrbitControls, supports material presets and face selection
- **File Formats**: Supports OBJ export, JSON tessellated models, and technical drawings
- **File Storage**: CAD files are automatically downloaded from external API and stored locally in Laravel storage for persistence
