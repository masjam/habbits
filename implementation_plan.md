# Build REST API for Mobile Application

This plan outlines the steps to build a REST API backend for the proposed Android/iOS mobile application without disrupting the existing Web/PWA functionality.

## Proposed Changes

We will introduce Laravel Sanctum for API token-based authentication and create dedicated API routes and controllers that return JSON responses.

### 1. Installation & Configuration
- Run `php artisan install:api` to scaffold API routes (`routes/api.php`) and install Laravel Sanctum.

### 2. Models
#### [MODIFY] `app/Models/User.php`
- Add the `HasApiTokens` trait to allow the user model to generate and manage Sanctum API tokens.

### 3. API Controllers
#### [NEW] `app/Http/Controllers/Api/AuthController.php`
- Create an API-specific Auth controller with `login` and `logout` methods.
- The `login` method will validate credentials and return a bearer token (`$user->createToken('mobile-app')->plainTextToken`) as JSON.

#### [NEW] `app/Http/Controllers/Api/HabitController.php`
- (Optional/Next Step) Create endpoints to fetch and submit habit logs, returning JSON data.

### 4. Routes
#### [MODIFY] `routes/api.php` (Created by install:api)
- Define the `POST /login` route.
- Define a protected route group using the `auth:sanctum` middleware.
- Add `POST /logout` inside the protected group.
- Add routes for fetching user data and habits.

## Verification Plan

### Manual Verification
1. I will test the `POST /api/login` endpoint using `curl` to ensure it returns a valid token.
2. I will test the `GET /api/user` endpoint using the generated token to ensure the middleware works.
3. Verify that the existing web login and dashboard are completely unaffected.
